<?php
// scripts/check_schedules.php (Versão Final, Completa e Robusta)

// Garante que o script só é executado a partir da linha de comandos
if (php_sapi_name() !== 'cli') {
    die("Acesso negado.");
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/StatusStore.php';
use App\Database;
use SpotMaster\Api\StatusStore;

// Define o fuso horário e os caminhos dos ficheiros de controlo
date_default_timezone_set('Europe/Lisbon');
$lockFilePath = __DIR__ . '/schedule.lock';
$processedScheduleFilePath = __DIR__ . '/last_processed_schedule.json';
$lookbackSeconds = 5 * 60;
$heartbeatFile = __DIR__ . '/../public/robot_heartbeat.log'; // Caminho na pasta public

// Lock real: o teste por data do ficheiro tinha uma corrida e também podia
// descartar uma execução válida quando o Agendador arrancava alguns segundos cedo.
$lockHandle = fopen($lockFilePath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "AVISO: Ja existe uma verificacao em curso.\n";
    exit;
}

// Lógica de Pulsação (Heartbeat) para o Dashboard saber que o robô está vivo
file_put_contents($heartbeatFile, time());

$now = new DateTime();
echo "---------------------------------------------------\n";
echo "Verificacao iniciada em: " . $now->format('Y-m-d H:i:s') . "\n";

try {
    $pdo = Database::getInstance();
    $statusStore = new StatusStore(__DIR__ . '/../public/status.json');
    $currentDayOfWeek = (int)$now->format('N');

        // 1. Vai buscar os agendamentos gerais e apenas a configuração de fecho vigente
        $closingTitles = "'Fecho - 15 minutos', 'Fecho - 10 minutos', 'Fecho - 5 minutos', 'Fecho - Parque Fechado'";
        $sql = "SELECT s.id, s.announcement_id, s.play_at
                        FROM schedules s
                        JOIN announcements a ON a.id = s.announcement_id
                        WHERE s.day_of_week = ? AND s.is_active = 1
                            AND (
                                    a.title NOT IN ($closingTitles)
                                    OR s.effective_from <=> (
                                            SELECT MAX(s2.effective_from)
                                            FROM schedules s2
                                            JOIN announcements a2 ON a2.id = s2.announcement_id
                                            WHERE a2.title IN ($closingTitles)
                                                AND s2.is_active = 1
                                                AND s2.effective_from <= ?
                                    )
                            )";
    $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentDayOfWeek, $now->format('Y-m-d')]);
    $todaysSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$todaysSchedules) {
        echo "Nenhum agendamento ativo para hoje.\n";
        exit;
    }

    $scheduleFound = null;
    $scheduledTimeFound = null;
    $nowTimestamp = $now->getTimestamp();

    $processedData = [];
    if (file_exists($processedScheduleFilePath)) {
        $processedData = json_decode((string) file_get_contents($processedScheduleFilePath), true);
        $processedData = is_array($processedData) ? $processedData : [];
    }
    // Compatibilidade com o formato antigo, que guardava apenas uma ocorrência.
    $processedOccurrences = $processedData['occurrences'] ?? [];
    if (isset($processedData['occurrence_key'])) {
        $processedOccurrences[$processedData['occurrence_key']] = $processedData['processed_at'] ?? $now->format(DateTime::ATOM);
    }

    // 2. Itera sobre os agendamentos em PHP para encontrar uma correspondência no minuto atual
    //    Isto é mais robusto contra problemas de fuso horário da base de dados.
    foreach ($todaysSchedules as $schedule) {
        $scheduledTime = new DateTime($now->format('Y-m-d') . ' ' . $schedule['play_at']);
        $scheduledTimestamp = $scheduledTime->getTimestamp();
        
        $occurrenceKey = $schedule['id'] . '@' . $scheduledTime->format('Y-m-d H:i');

        // Aceita atrasos do Agendador até cinco minutos e escolhe a ocorrência
        // ainda não processada mais antiga. Assim, um arranque tardio não perde o anúncio.
        if ($nowTimestamp >= $scheduledTimestamp
            && ($nowTimestamp - $scheduledTimestamp) <= $lookbackSeconds
            && !isset($processedOccurrences[$occurrenceKey])
            && ($scheduledTimeFound === null || $scheduledTime < $scheduledTimeFound)) {
            $scheduleFound = $schedule;
            $scheduledTimeFound = $scheduledTime;
        }
    }

    if ($scheduleFound) {
        echo "AGENDAMENTO ENCONTRADO (ID: " . $scheduleFound['id'] . ")!\n";

        $scheduledTime = $scheduledTimeFound;
        $scheduleOccurrenceKey = $scheduleFound['id'] . '@' . $scheduledTime->format('Y-m-d H:i');

        $announcementId = $scheduleFound['announcement_id'];
        
        // Vai buscar todos os detalhes do anúncio
        $stmtAnn = $pdo->prepare("SELECT title, file_path, duration_seconds FROM announcements WHERE id = ?");
        $stmtAnn->execute([$announcementId]);
        $announcement = $stmtAnn->fetch(PDO::FETCH_ASSOC);

        if ($announcement) {
            // Envia a ordem completa para o ficheiro de status
            $status = [
                'status' => 'play',
                'url' => '/uploads/' . $announcement['file_path'],
                'title' => $announcement['title'],
                'duration' => (int)$announcement['duration_seconds'],
                'initial_state' => 'pending',
                'pause_on_play' => true,
                'play_id' => 'sched-' . $scheduleFound['id'] . '-' . $scheduledTime->format('YmdHi'), // Identificador único desta ocorrência (usado pelo JS para deduplicar)
                'ts' => time()
            ];
            $statusStore->write($status);
            echo "Ficheiro status.json atualizado com estado inicial.\n";

            // Regista a atividade na base de dados
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (announcement_title, play_type) VALUES (?, 'Automático')");
            $logStmt->execute([$announcement['title']]);
            echo "Atividade registada na base de dados.\n";

            $processedOccurrences[$scheduleOccurrenceKey] = $now->format(DateTime::ATOM);
            // Mantém apenas ocorrências recentes para o ficheiro não crescer indefinidamente.
            $retentionLimit = (clone $now)->modify('-8 days')->getTimestamp();
            $processedOccurrences = array_filter(
                $processedOccurrences,
                static fn ($processedAt) => (strtotime((string) $processedAt) ?: 0) >= $retentionLimit
            );
            file_put_contents($processedScheduleFilePath, json_encode([
                'occurrences' => $processedOccurrences,
                'last_schedule_id' => (int) $scheduleFound['id'],
                'last_announcement_id' => (int) $announcementId,
                'updated_at' => $now->format(DateTime::ATOM)
            ], JSON_UNESCAPED_SLASHES), LOCK_EX);
            echo "Marcador de execucao do minuto atualizado.\n";
            
            echo "Processo concluido com sucesso.\n";
            exit; // Sai depois de processar o primeiro anúncio do minuto
        } else {
            echo "ERRO: Anuncio com ID $announcementId nao encontrado.\n";
        }
    } else {
        echo "Nenhum agendamento para o minuto atual.\n";
    }

} catch (Exception $e) {
    echo "ERRO CRITICO: " . $e->getMessage() . "\n";
    error_log("Erro no Cron Job do Spot Master: " . $e->getMessage());
}

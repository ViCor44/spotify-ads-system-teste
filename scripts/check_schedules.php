<?php
// scripts/check_schedules.php (Versão Final, Completa e Robusta)

// Garante que o script só é executado a partir da linha de comandos
if (php_sapi_name() !== 'cli') {
    die("Acesso negado.");
}

require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;
use App\SpotifyClient;

// Define o fuso horário e os caminhos dos ficheiros de controlo
date_default_timezone_set('Europe/Lisbon');
$lockFilePath = __DIR__ . '/schedule.lock';
$heartbeatFile = __DIR__ . '/../public/robot_heartbeat.log'; // Caminho na pasta public

// Lógica de Bloqueio (Lock File) para impedir execuções sobrepostas
if (file_exists($lockFilePath)) {
    // Se o ficheiro de lock foi criado há menos de 58 segundos, sai imediatamente.
    if (time() - filemtime($lockFilePath) < 58) {
        echo "AVISO: Processo executado recentemente. Abortando para evitar duplicacao.\n";
        exit;
    }
}
// Cria/atualiza o ficheiro de lock imediatamente para "reservar" este minuto
touch($lockFilePath);

// Lógica de Pulsação (Heartbeat) para o Dashboard saber que o robô está vivo
file_put_contents($heartbeatFile, time());

$now = new DateTime();
echo "---------------------------------------------------\n";
echo "Verificacao iniciada em: " . $now->format('Y-m-d H:i:s') . "\n";

try {
    $pdo = Database::getInstance();
    $currentDayOfWeek = (int)$now->format('N');

    // 1. Vai buscar TODOS os agendamentos ativos para o dia de hoje
    $sql = "SELECT id, announcement_id, play_at FROM schedules WHERE day_of_week = ? AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentDayOfWeek]);
    $todaysSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$todaysSchedules) {
        echo "Nenhum agendamento ativo para hoje.\n";
        exit;
    }

    $scheduleFound = null;
    $nowTimestamp = $now->getTimestamp();

    // 2. Itera sobre os agendamentos em PHP para encontrar uma correspondência no minuto atual
    //    Isto é mais robusto contra problemas de fuso horário da base de dados.
    foreach ($todaysSchedules as $schedule) {
        $scheduledTime = new DateTime($now->format('Y-m-d') . ' ' . $schedule['play_at']);
        $scheduledTimestamp = $scheduledTime->getTimestamp();
        
        // Verifica se a hora agendada está no minuto atual (desde o segundo 0 até ao 59)
        if ($nowTimestamp >= $scheduledTimestamp && ($nowTimestamp - $scheduledTimestamp) < 60) {
            $scheduleFound = $schedule;
            break; // Encontrámos o agendamento para este minuto
        }
    }

    if ($scheduleFound) {
        echo "AGENDAMENTO ENCONTRADO (ID: " . $scheduleFound['id'] . ")!\n";

        $announcementId = $scheduleFound['announcement_id'];
        
        // Vai buscar todos os detalhes do anúncio
        $stmtAnn = $pdo->prepare("SELECT title, file_path, duration_seconds FROM announcements WHERE id = ?");
        $stmtAnn->execute([$announcementId]);
        $announcement = $stmtAnn->fetch(PDO::FETCH_ASSOC);

        if ($announcement) {
            $spotifyClient = new SpotifyClient();

            // VERIFICA O ESTADO DO SPOTIFY ANTES DE FAZER QUALQUER COISA
            $state = $spotifyClient->getPlaybackState();
            $initialState = ($state && $state->is_playing) ? 'playing' : 'paused';
            echo "Estado inicial do Spotify: $initialState\n";

            $spotifyClient->pausePlayback();
            echo "Spotify pausado (ou já estava em pausa).\n";

            // Envia a ordem completa para o ficheiro de status
            $status = [
                'status' => 'play',
                'url' => '/uploads/' . $announcement['file_path'],
                'title' => $announcement['title'],
                'duration' => (int)$announcement['duration_seconds'],
                'initial_state' => $initialState // A nossa "memória de estado"
            ];
            file_put_contents(__DIR__ . '/../public/status.json', json_encode($status));
            echo "Ficheiro status.json atualizado com estado inicial.\n";

            // Regista a atividade na base de dados
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (announcement_title, play_type) VALUES (?, 'Automático')");
            $logStmt->execute([$announcement['title']]);
            echo "Atividade registada na base de dados.\n";
            
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

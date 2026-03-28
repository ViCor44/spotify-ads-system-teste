<?php
// api/create_schedule.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se os campos do formulário de criação de múltiplos horários foram enviados
    if (isset($_POST['announcement_id']) && isset($_POST['play_at'])) {
        
        $announcementId = $_POST['announcement_id'];
        // Se nenhum dia for selecionado, $_POST['days'] pode não existir. Usamos um array vazio como fallback.
        $daysOfWeek = $_POST['days'] ?? [];
        $timesString = $_POST['play_at'];

        // Guarda os dados submetidos na sessão para repopular o formulário em caso de erro
        $_SESSION['form_data'] = $_POST;

        // Limpa e processa a string de horas
        $times = array_map('trim', explode(',', $timesString));
        $times = array_filter($times); // Remove entradas vazias

        try {
            $pdo = Database::getInstance();
            $daysOfWeekMap = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
            
            // --- VALIDAÇÃO DE SOBREPOSIÇÃO ---
            $stmtDuration = $pdo->prepare("SELECT duration_seconds FROM announcements WHERE id = ?");
            $stmtDuration->execute([$announcementId]);
            $adDuration = (int)$stmtDuration->fetchColumn();

            foreach ($daysOfWeek as $day) {
                foreach ($times as $time) {
                    $newStartTime = strtotime($time);
                    $newEndTime = $newStartTime + $adDuration;

                    // A consulta aqui é mais simples: verifica TODOS os agendamentos existentes.
                    $sqlCheck = "SELECT s.play_at, a.duration_seconds, a.title
                                 FROM schedules s
                                 JOIN announcements a ON s.announcement_id = a.id
                                 WHERE s.day_of_week = ?";
                    $stmtCheck = $pdo->prepare($sqlCheck);
                    $stmtCheck->execute([$day]);
                    $existingSchedules = $stmtCheck->fetchAll();

                    foreach ($existingSchedules as $existing) {
                        $existingStartTime = strtotime($existing['play_at']);
                        $existingEndTime = $existingStartTime + (int)$existing['duration_seconds'];
                        if ($newStartTime < $existingEndTime && $existingStartTime < $newEndTime) {
                            $dayName = $daysOfWeekMap[$day];
                            $_SESSION['flash_error'] = "Erro de sobreposição: O horário de $dayName às $time conflita com o anúncio \"" . htmlspecialchars($existing['title']) . "\".";
                            header('Location: ../public/index.php?page=manage_schedules&action=create');
                            exit();
                        }
                    }
                }
            }
            // --- FIM DA VALIDAÇÃO ---

            // Se a validação passar, insere os novos horários
            if (!empty($daysOfWeek) && !empty($times)) {
                $sqlInsert = "INSERT INTO schedules (announcement_id, day_of_week, play_at) VALUES (?, ?, ?)";
                $stmtInsert = $pdo->prepare($sqlInsert);

                foreach ($daysOfWeek as $day) {
                    foreach ($times as $time) {
                        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                            $stmtInsert->execute([$announcementId, $day, $time]);
                        }
                    }
                }
            }
            
            // Limpa os dados da sessão em caso de sucesso
            unset($_SESSION['form_data']);
            unset($_SESSION['flash_error']);

            header('Location: ../public/index.php?page=manage_schedules&action=list&status=created');
            exit();

        } catch (Exception $e) {
            // Guarda o erro na sessão e redireciona de volta
            $_SESSION['flash_error'] = "Erro ao criar o agendamento: " . $e->getMessage();
            header('Location: ../public/index.php?page=manage_schedules&action=create');
            exit();
        }
    }
}

// Se o script for acedido de forma incorreta, redireciona para o dashboard
header('Location: ../public/index.php?page=dashboard');
exit();

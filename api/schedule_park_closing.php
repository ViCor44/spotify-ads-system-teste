<?php
// api/schedule_park_closing.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['days'], $_POST['closing_time'])) {
        $days = $_POST['days'];
        $closingTime = $_POST['closing_time'];

        try {
            $pdo = Database::getInstance();
            $pdo->beginTransaction();

            // 1. Encontrar os IDs dos anúncios de fecho
            $announcementTitles = [
                'Fecho - 15 minutos' => -15,
                'Fecho - 10 minutos' => -10,
                'Fecho - 5 minutos' => -5,
                'Fecho - Parque Fechado' => 0
            ];
            $announcements = [];
            foreach ($announcementTitles as $title => $offset) {
                $stmt = $pdo->prepare("SELECT id, duration_seconds FROM announcements WHERE title = ?");
                $stmt->execute([$title]);
                if (!$ann = $stmt->fetch()) {
                    throw new Exception("O anúncio obrigatório \"$title\" não foi encontrado. Por favor, carregue-o na página 'Gerir Anúncios'.");
                }
                $announcements[$title] = ['id' => $ann['id'], 'duration' => $ann['duration_seconds'], 'offset' => $offset];
            }

            // 2. Apagar TODOS os agendamentos de fecho antigos para os dias selecionados
            $announcementIdsToDelete = array_column($announcements, 'id');
            $placeholders = implode(',', array_fill(0, count($announcementIdsToDelete), '?'));
            $stmtDelete = $pdo->prepare("DELETE FROM schedules WHERE announcement_id IN ($placeholders) AND day_of_week IN (" . implode(',', $days) . ")");
            $stmtDelete->execute($announcementIdsToDelete);

            // 3. Criar os novos agendamentos
            foreach ($days as $day) {
                $closingDateTime = new DateTime($closingTime);
                
                foreach ($announcements as $title => $details) {
                    $adDateTime = clone $closingDateTime;
                    if ($details['offset'] !== 0) {
                        $adDateTime->modify("{$details['offset']} minutes");
                    }
                    $playAt = $adDateTime->format('H:i:s');
                    
                    // Validação de sobreposição (reutiliza a nossa lógica robusta)
                    // ... (Opcional, mas recomendado. Se quiser adicionar, a lógica pode ser copiada de create_schedule.php)

                    $sqlInsert = "INSERT INTO schedules (announcement_id, day_of_week, play_at) VALUES (?, ?, ?)";
                    $stmtInsert = $pdo->prepare($sqlInsert);
                    $stmtInsert->execute([$details['id'], $day, $playAt]);
                }
            }
            
            $pdo->commit();
            header('Location: ../public/index.php?page=manage_schedules&action=list&status=closing_scheduled');
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = "Erro ao agendar o fecho: " . $e->getMessage();
            header('Location: ../public/index.php?page=manage_schedules&action=closing');
            exit();
        }
    }
}
<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/StatusStore.php';

use SpotMaster\Api\StatusStore;
use App\Database;
use App\ScheduleValidity;

if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=UTF-8');

$store = new StatusStore();
$statusFromStore = $store->read(); // Assume que retorna um array ou null
$status = array_merge([
    'robotStatusOk' => false,
    'closingScheduleExists' => false,
    'placeholderWarning' => false,
    'nextClosing' => null
], is_array($statusFromStore) ? $statusFromStore : []);

try {
    $pdo = Database::getInstance();
    
    // 1. Verifica a pulsação do Robô
    $heartbeatFile = __DIR__ . '/../public/robot_heartbeat.log';
    if (file_exists($heartbeatFile) && is_readable($heartbeatFile) && (time() - filemtime($heartbeatFile)) < 180) {
        $status['robotStatusOk'] = true;
    }

    // 2. Lógica para encontrar o PRÓXIMO fecho
    $sqlClosing = "SELECT s.day_of_week, s.play_at, s.effective_from, a.title
                   FROM schedules s
                   JOIN announcements a ON s.announcement_id = a.id
                   WHERE a.title = 'Fecho - Parque Fechado' AND s.is_active = 1";
    $allClosingSchedules = $pdo->query($sqlClosing)->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($allClosingSchedules)) {
        $status['closingScheduleExists'] = true;
        date_default_timezone_set('Europe/Lisbon');
        $now = new DateTime();
        $daysOfWeekMap = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
        $next = ScheduleValidity::findNext($allClosingSchedules, ScheduleValidity::fetchClosingTransitions($pdo), $now);

        if ($next) {
            $schedule = $next['schedule'];
            $potentialDate = $next['date'];
            $dayName = $daysOfWeekMap[(int)$schedule['day_of_week']];
            if ($potentialDate->format('W') !== $now->format('W')) {
                $dayName = "Próxima " . $dayName;
            }
            $status['nextClosing'] = [
                'day' => $dayName,
                'time' => $potentialDate->format('H:i')
            ];
        }
    }
    
    // 3. Verifica se existem áudios provisórios nos anúncios de fecho
    $closingTitles = "'Fecho - 15 minutos', 'Fecho - 10 minutos', 'Fecho - 5 minutos', 'Fecho - Parque Fechado'";
    $sqlPlaceholders = "SELECT COUNT(*) FROM announcements WHERE title IN ($closingTitles) AND file_path LIKE 'silent_%'";
    if ((int)$pdo->query($sqlPlaceholders)->fetchColumn() > 0) {
        $status['placeholderWarning'] = true;
    }

} catch (Exception $e) { 
    error_log("Erro em get_system_status: " . $e->getMessage());
    $status['error'] = $e->getMessage(); // Adiciona o erro ao status
}

echo json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
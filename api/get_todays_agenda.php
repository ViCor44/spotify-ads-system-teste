<?php
// api/get_todays_agenda.php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $pdo = Database::getInstance();
    date_default_timezone_set('Europe/Lisbon');
    
    $currentDay = (int)date('N');
    $currentTime = date('H:i:s');
    
    $sqlAgenda = "SELECT a.title, s.play_at 
                  FROM schedules s
                  JOIN announcements a ON s.announcement_id = a.id
                  WHERE s.day_of_week = ? AND s.play_at >= ? AND s.is_active = 1
                  ORDER BY s.play_at ASC";
    $stmtAgenda = $pdo->prepare($sqlAgenda);
    $stmtAgenda->execute([$currentDay, $currentTime]);
    $todaysAgenda = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);

    // Formata os dados para o JS
    foreach ($todaysAgenda as &$item) {
        $item['time'] = date("H:i", strtotime($item['play_at']));
    }

    echo json_encode($todaysAgenda);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar a agenda.']);
}

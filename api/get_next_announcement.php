<?php
// api/get_next_announcement.php

header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\ScheduleValidity;

try {
    $pdo = Database::getInstance();
    $nextAnnouncement = null;

    $sqlNext = "SELECT s.day_of_week, s.play_at, s.effective_from, a.title
                FROM schedules s
                JOIN announcements a ON s.announcement_id = a.id
                WHERE s.is_active = 1";
    $stmtNext = $pdo->query($sqlNext);
    $allSchedules = $stmtNext->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($allSchedules)) {
        date_default_timezone_set('Europe/Lisbon');
        $now = new DateTime();
        $daysOfWeekMap = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
        $next = ScheduleValidity::findNext($allSchedules, ScheduleValidity::fetchClosingTransitions($pdo), $now);

        if ($next) {
            $schedule = $next['schedule'];
            $potentialDate = $next['date'];
            $dayName = $daysOfWeekMap[(int)$schedule['day_of_week']];
            if ($potentialDate->format('W') !== $now->format('W')) {
                $dayName = "Próxima " . $dayName;
            }
            $nextAnnouncement = [
                'title' => $schedule['title'],
                'day' => $dayName,
                'time' => $potentialDate->format('H:i'),
                'timestamp' => $potentialDate->getTimestamp()
            ];
        }
    }
    
    echo json_encode($nextAnnouncement);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
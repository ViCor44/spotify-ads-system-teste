<?php
// api/get_next_announcement.php

header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

try {
    $pdo = Database::getInstance();
    $nextAnnouncement = null;

    $sqlNext = "SELECT s.day_of_week, s.play_at, a.title 
                FROM schedules s
                JOIN announcements a ON s.announcement_id = a.id
                WHERE s.is_active = 1";
    $stmtNext = $pdo->query($sqlNext);
    $allSchedules = $stmtNext->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($allSchedules)) {
        date_default_timezone_set('Europe/Lisbon');
        $now = new DateTime();
        $nextTimestamp = PHP_INT_MAX;
        $daysOfWeekMap = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];

        foreach ($allSchedules as $schedule) {
            $scheduleDay = (int)$schedule['day_of_week'];
            $potentialDate = new DateTime('today ' . $schedule['play_at']);
            $currentDayNum = (int)$potentialDate->format('N');
            $dayDiff = $scheduleDay - $currentDayNum;
            
            if ($dayDiff < 0) { $dayDiff += 7; }
            if ($dayDiff > 0) { $potentialDate->modify("+$dayDiff days"); }
            if ($potentialDate < $now) { $potentialDate->modify('+7 days'); }
            
            $potentialTimestamp = $potentialDate->getTimestamp();

            if ($potentialTimestamp < $nextTimestamp) {
                $nextTimestamp = $potentialTimestamp;
                $dayName = $daysOfWeekMap[$scheduleDay];
                if (date('W', $nextTimestamp) != date('W', $now->getTimestamp())) {
                    $dayName = "Próxima " . $dayName;
                }
                $nextAnnouncement = [
                    'title' => $schedule['title'],
                    'day' => $dayName,
                    'time' => date("H:i", $nextTimestamp),
                    'timestamp' => $nextTimestamp
                ];
            }
        }
    }
    
    echo json_encode($nextAnnouncement);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
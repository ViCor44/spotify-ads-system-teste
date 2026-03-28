<?php
// api/get_activity_log.php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $pdo = Database::getInstance();
    // Carrega os últimos 5 registos de atividade, os mais recentes primeiro
    $stmtLogs = $pdo->query("SELECT announcement_title, play_type, played_at FROM activity_logs ORDER BY played_at DESC LIMIT 5");
    $activityLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Formata os dados para serem facilmente usados pelo JavaScript
    foreach ($activityLogs as &$log) {
        $log['time'] = date("H:i", strtotime($log['played_at']));
        $log['type_class'] = strtolower($log['play_type']);
    }

    echo json_encode($activityLogs);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar o log de atividade.']);
}

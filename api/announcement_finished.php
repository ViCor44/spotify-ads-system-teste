<?php
// api/announcement_finished.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\SpotifyClient;

$initialState = $_GET['initial_state'] ?? 'paused';
$finishedTitle = $_GET['title'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($finishedTitle === 'Fecho - Parque Fechado') {
        echo json_encode(['success' => true, 'resumed' => false, 'reason' => 'park_closed']);
        exit();
    }

    if ($initialState !== 'playing') {
        echo json_encode(['success' => true, 'resumed' => false, 'reason' => 'initially_paused']);
        exit();
    }

    $lastException = null;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            (new SpotifyClient())->resumePlayback();
            echo json_encode(['success' => true, 'resumed' => true, 'attempt' => $attempt]);
            exit();
        } catch (Exception $exception) {
            $lastException = $exception;
            error_log("Tentativa $attempt de retomar Spotify falhou: " . $exception->getMessage());
            if ($attempt < 3) {
                sleep(1);
            }
        }
    }

    throw $lastException ?? new RuntimeException('Não foi possível retomar o Spotify.');

} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro em announcement_finished: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
<?php
// api/start_spotify_pause.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/StatusStore.php';

use App\SpotifyClient;
use SpotMaster\Api\StatusStore;

$playId = (string) ($_GET['play_id'] ?? '');

try {
    $pauseLock = fopen(sys_get_temp_dir() . '/spot-master-spotify-pause.lock', 'c');
    if ($pauseLock === false || !flock($pauseLock, LOCK_EX)) {
        throw new RuntimeException('Não foi possível bloquear a operação de pausa do Spotify.');
    }

    $statusStore = new StatusStore();
    $status = $statusStore->read();
    if ($playId === '' || ($status['play_id'] ?? '') !== $playId || ($status['status'] ?? '') !== 'play') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A ordem de reprodução já não está ativa.']);
        exit;
    }

    if (($status['spotify_paused'] ?? false) === true && in_array($status['initial_state'] ?? '', ['playing', 'paused'], true)) {
        echo json_encode(['success' => true, 'initial_state' => $status['initial_state']]);
        exit;
    }

    $spotifyClient = new SpotifyClient();
    $state = $spotifyClient->getPlaybackState();
    $initialState = ($state && $state->is_playing) ? 'playing' : 'paused';
    $spotifyClient->pausePlayback();

    $latestStatus = $statusStore->read();
    if (($latestStatus['play_id'] ?? '') === $playId && ($latestStatus['status'] ?? '') === 'play') {
        $latestStatus['initial_state'] = $initialState;
        $latestStatus['spotify_paused'] = true;
        $statusStore->write($latestStatus);
    }

    echo json_encode(['success' => true, 'initial_state' => $initialState]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

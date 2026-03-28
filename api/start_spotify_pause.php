<?php
// api/start_spotify_pause.php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
use App\SpotifyClient;

try {
    $spotifyClient = new SpotifyClient();
    
    // Obtém o estado ANTES de pausar
    $state = $spotifyClient->getPlaybackState();
    $initialState = ($state && $state->is_playing) ? 'playing' : 'paused';

    // Pausa a reprodução
    $spotifyClient->pausePlayback();

    // Devolve o estado inicial para o JavaScript se lembrar
    echo json_encode(['success' => true, 'initial_state' => $initialState]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

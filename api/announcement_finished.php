<?php
// api/announcement_finished.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;
use App\SpotifyClient;

// Obtém os dados que o JavaScript enviou
$initialState = $_GET['initial_state'] ?? 'paused';
$finishedTitle = $_GET['title'] ?? '';

try {
    // A REGRA ESPECIAL: Se o título for 'Fecho - Parque Fechado', não fazemos nada.
    if ($finishedTitle === 'Fecho - Parque Fechado') {
        
        echo "";
        http_response_code(200);
        exit();

    } else {
        // Para todos os outros anúncios, segue a lógica normal de memória de estado
        $spotifyClient = new SpotifyClient();
        if ($initialState === 'playing') {
            $spotifyClient->resumePlayback();
        }
    }
    
    http_response_code(200);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro em announcement_finished: " . $e->getMessage());
}
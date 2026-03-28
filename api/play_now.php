<?php
// api/play_now.php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/StatusStore.php';

use App\Database;
use App\SpotifyClient;
use SpotMaster\Api\StatusStore;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close(); // evita bloquear outras requests
}

// Aceita GET ou POST id
$announcementId = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) $announcementId = (int) $_GET['id'];
if (isset($_POST['id']) && is_numeric($_POST['id'])) $announcementId = (int) $_POST['id'];

if ($announcementId === null) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'ID do anúncio inválido.']);
    exit;
}

try {
    // 1) Buscar anúncio
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT title, file_path, duration_seconds FROM announcements WHERE id = ?');
    $stmt->execute([$announcementId]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ann) {
        throw new Exception('Anúncio não encontrado.');
    }

    $title    = (string) $ann['title'];
    $filePath = (string) $ann['file_path'];
    $duration = (int)    $ann['duration_seconds'];
    $publicUrl = '/uploads/' . ltrim($filePath, '/');

    // 2) Estado inicial do Spotify (best-effort)
     $spotifyClient = new SpotifyClient();

            // VERIFICA O ESTADO DO SPOTIFY ANTES DE FAZER QUALQUER COISA
            $state = $spotifyClient->getPlaybackState();
            $initialState = ($state && $state->is_playing) ? 'playing' : 'paused';
            echo "Estado inicial do Spotify: $initialState\n";

            $spotifyClient->pausePlayback();

    // 3) Payload p/ status.json
    $payload = [
        'status'        => 'play',
        'title'         => $title,
        'url'           => $publicUrl,
        'has_gong'      => false,
        'duration'      => $duration,
        'initial_state' => $initialState,
        'ts'            => time(),
    ];

    // 4) Escrita robusta (usa a tua StatusStore com flock/LOCK_EX)
    $store = new StatusStore();
    $store->write($payload);

    // 5) Log
    $pdo->prepare('INSERT INTO activity_logs (announcement_title, play_type) VALUES (?, "Manual")')
        ->execute([$title]);

    // 6) Resposta: JSON para AJAX; redirect só como fallback
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true, 'status' => $payload]);
        // opcional: concluir resposta e deixar tarefas pendentes acabarem
        if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
        exit;
    }

    // fallback para chamadas não-AJAX
    header('Location: ../public/index.php?page=play_announcements&status=triggered');
    exit;

} catch (\Throwable $e) {
    error_log('[play_now] ' . $e->getMessage());
    // Se foi AJAX, devolve JSON de erro
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
    // fallback antigo
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Erro em play_now: ' . $e->getMessage();
}

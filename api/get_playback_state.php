<?php
// api/get_playback_state.php

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\SpotifyClient;

// Verifica se existe um token para evitar erros
$pdo = Database::getInstance();
$token_exists = $pdo->query("SELECT access_token FROM spotify_tokens WHERE id = 1")->fetchColumn();

if (!$token_exists) {
    // Se não há token, devolve um objeto JSON vazio
    echo json_encode(null);
    exit();
}

try {
    $spotifyClient = new SpotifyClient();
    $playbackState = $spotifyClient->getPlaybackState();
    
    // Devolve o estado da reprodução como uma string JSON
    echo json_encode($playbackState);

} catch (Exception $e) {
    // Em caso de erro, devolve um objeto de erro em JSON
    http_response_code(500); // Erro de Servidor
    echo json_encode(['error' => $e->getMessage()]);
}
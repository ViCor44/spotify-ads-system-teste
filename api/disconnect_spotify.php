<?php
// api/disconnect_spotify.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $pdo = Database::getInstance();

    // Apaga todos os registos da tabela de tokens, desligando a aplicação
    $pdo->exec("TRUNCATE TABLE spotify_tokens");

    // Redireciona de volta para o dashboard, que agora mostrará o estado "Não ligado"
    header('Location: ../public/index.php?page=dashboard&status=disconnected');
    exit();

} catch (Exception $e) {
    die("Ocorreu um erro ao tentar desligar do Spotify: " . $e->getMessage());
}

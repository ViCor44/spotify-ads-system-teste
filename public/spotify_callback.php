<?php
// public/spotify_callback.php

// Incluímos tudo o que é necessário
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Database;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

session_start();

// Verificação de segurança: O 'state' que recebemos do Spotify tem de ser igual ao que guardámos na sessão.
if (!isset($_GET['state']) || !isset($_SESSION['spotify_auth_state']) || $_GET['state'] !== $_SESSION['spotify_auth_state']) {
    die('State mismatch. Possível ataque CSRF.');
}

// Limpamos o state da sessão, já não é necessário.
unset($_SESSION['spotify_auth_state']);

// Verificamos se recebemos um código de autorização
if (isset($_GET['code'])) {
    $authCode = $_GET['code'];
    
    // Agora, trocamos o código de autorização por um access token
    $client = new Client();
    $tokenUrl = 'https://accounts.spotify.com/api/token';

    try {
        // O pedido para a API do Spotify tem de ser do tipo POST
        $response = $client->post($tokenUrl, [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'redirect_uri' => SPOTIFY_REDIRECT_URI,
            ],
            'headers' => [
                // A autorização tem de ser 'Basic' e o valor é o client_id:client_secret codificado em Base64
                'Authorization' => 'Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
            ],
        ]);

        $body = $response->getBody();
        $data = json_decode($body);

        // Extraímos os tokens e a data de expiração
        $accessToken = $data->access_token;
        $refreshToken = $data->refresh_token;
        $expiresIn = $data->expires_in; // Duração em segundos
        $expiresAt = time() + $expiresIn; // Timestamp de quando o token expira

        // Guardamos os tokens na base de dados
        $pdo = Database::getInstance();
        
        // Usamos "ON DUPLICATE KEY UPDATE" para inserir na primeira vez e atualizar nas seguintes.
        // Isto é útil se o utilizador re-autorizar a aplicação.
        $sql = "INSERT INTO spotify_tokens (id, access_token, refresh_token, expires_at) VALUES (1, ?, ?, FROM_UNIXTIME(?))
                ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expires_at = VALUES(expires_at)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$accessToken, $refreshToken, $expiresAt]);

        // Tudo correu bem, redirecionamos para o painel principal
        header('Location: index.php');
        exit();

    } catch (GuzzleException $e) {
        die('Erro ao obter o token: ' . $e->getMessage());
    }

} else if (isset($_GET['error'])) {
    die('Ocorreu um erro na autorização do Spotify: ' . htmlspecialchars($_GET['error']));
} else {
    die('Pedido inválido. Nenhum código de autorização recebido.');
}
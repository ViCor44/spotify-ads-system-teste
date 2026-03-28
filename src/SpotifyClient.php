<?php
// src/SpotifyClient.php (Versão para PHP 8+)

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class SpotifyClient
{
    private const API_BASE_URL = 'https://api.spotify.com/';

    private Client $httpClient;
    private \PDO $pdo;

    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $expiresAt = null;

    public function __construct()
    {
        // A CORREÇÃO ESTÁ AQUI: Adicionamos 'verify' => false para resolver problemas de SSL no XAMPP.
        $this->httpClient = new Client(['base_uri' => self::API_BASE_URL, 'verify' => false]);
        $this->pdo = Database::getInstance();
        $this->loadTokensFromDB();
    }

    private function loadTokensFromDB(): void
    {
        $stmt = $this->pdo->query("SELECT access_token, refresh_token, expires_at FROM spotify_tokens WHERE id = 1");
        $tokens = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($tokens) {
            $this->accessToken = $tokens['access_token'];
            $this->refreshToken = $tokens['refresh_token'];
            $this->expiresAt = strtotime($tokens['expires_at']);
        }
    }

    private function isTokenExpired(): bool
    {
        if ($this->expiresAt === null) return true;
        return (time() + 60) >= $this->expiresAt;
    }

    private function refreshToken(): bool
    {
        if ($this->refreshToken === null) return false;
        require_once __DIR__ . '/../config/database.php';
        try {
            $response = $this->httpClient->post('https://accounts.spotify.com/api/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                ],
                'headers' => ['Authorization' => 'Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET)],
            ]);
            $data = json_decode($response->getBody()->getContents());
            $this->accessToken = $data->access_token;
            $this->expiresAt = time() + $data->expires_in;
            if (isset($data->refresh_token)) $this->refreshToken = $data->refresh_token;
            $this->saveTokensToDB();
            return true;
        } catch (GuzzleException $e) {
            error_log('Spotify Refresh Token Error: ' . $e->getMessage());
            return false;
        }
    }

    private function saveTokensToDB(): void
    {
        $sql = "UPDATE spotify_tokens SET access_token = ?, refresh_token = ?, expires_at = FROM_UNIXTIME(?) WHERE id = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->accessToken, $this->refreshToken, $this->expiresAt]);
    }

    private function makeRequest(string $method, string $endpoint, array $options = []): ?\stdClass
    {
        if ($this->accessToken === null || $this->isTokenExpired()) {
            if (!$this->refreshToken()) {
                throw new \Exception('Não foi possível renovar o token do Spotify.');
            }
        }
        try {
            $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
            $response = $this->httpClient->request($method, $endpoint, $options);
            if ($response->getStatusCode() === 204) return null;
            return json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($responseBody);
            $errorMessage = $errorData->error->message ?? 'Erro desconhecido da API';
            $statusCode = $e->getResponse()->getStatusCode();
            throw new \Exception("Erro da API do Spotify ($statusCode): $errorMessage");
        } catch (GuzzleException $e) {
            throw new \Exception('Erro de comunicação com o Spotify: ' . $e->getMessage());
        }
    }

    public function getPlaybackState(): ?\stdClass
    {
        return $this->makeRequest('GET', 'v1/me/player');
    }

    public function pausePlayback(): ?\stdClass
    {
        $state = $this->getPlaybackState();
        if ($state && isset($state->is_playing) && $state->is_playing) {
            return $this->makeRequest('PUT', 'v1/me/player/pause');
        }
        return null;
    }

    public function resumePlayback(): ?\stdClass
    {
        $state = $this->getPlaybackState();
        if (!$state || (isset($state->is_playing) && !$state->is_playing)) {
            return $this->makeRequest('PUT', 'v1/me/player/play');
        }
        return null;
    }
}

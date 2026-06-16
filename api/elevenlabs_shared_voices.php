<?php
// api/elevenlabs_shared_voices.php
// Proxy para a Voice Library da ElevenLabs:
//   GET  ?lang=pt[&gender=female&accent=portugal&q=texto&page=0]  -> procura vozes públicas
//   POST action=add  voice_id=...  public_owner_id=...  [new_name=...]  -> adiciona à conta

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client as HttpClient;

if (!function_exists('elevenlabs_http')) {
    function elevenlabs_http(): HttpClient {
        static $http = null;
        if ($http === null) {
            $http = new HttpClient([
                'base_uri'    => 'https://api.elevenlabs.io/',
                'http_errors' => false,
                'timeout'     => 30,
            ]);
        }
        return $http;
    }

    /** Mapeia o idioma do UI ('pt','en','es','fr') para a chave da API ElevenLabs. */
    function elevenlabs_lang_for(string $uiLang): string {
        $map = ['pt' => 'pt', 'en' => 'en', 'es' => 'es', 'fr' => 'fr'];
        return $map[$uiLang] ?? $uiLang;
    }
}

if (PHP_SAPI === 'cli') exit(0);

header('Content-Type: application/json; charset=utf-8');

try {
    if (!defined('ELEVENLABS_API_KEY') || ELEVENLABS_API_KEY === '' || ELEVENLABS_API_KEY === 'SUBSTITUIR_PELA_API_KEY_DA_ELEVENLABS') {
        throw new Exception('ELEVENLABS_API_KEY não configurada em config/database.php.');
    }

    // ------------------- POST: adicionar voz -------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $voiceId       = trim((string) ($_POST['voice_id'] ?? ''));
        $publicOwnerId = trim((string) ($_POST['public_owner_id'] ?? ''));
        $newName       = trim((string) ($_POST['new_name'] ?? ''));

        if ($voiceId === '' || $publicOwnerId === '') {
            throw new Exception('voice_id e public_owner_id são obrigatórios.');
        }
        if ($newName === '') $newName = 'Voz importada';

        $resp = elevenlabs_http()->post(
            'v1/voices/add/' . rawurlencode($publicOwnerId) . '/' . rawurlencode($voiceId),
            [
                'headers' => [
                    'xi-api-key'   => ELEVENLABS_API_KEY,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [ 'new_name' => $newName ],
            ]
        );

        $status = $resp->getStatusCode();
        $body   = (string) $resp->getBody();
        $data   = json_decode($body, true);

        if ($status >= 200 && $status < 300) {
            // Invalida a cache local de vozes para que a nova apareça
            $cacheFile = __DIR__ . '/../storage/elevenlabs_voices.json';
            if (is_file($cacheFile)) @unlink($cacheFile);

            echo json_encode([
                'ok'       => true,
                'voice_id' => is_array($data) ? ($data['voice_id'] ?? $voiceId) : $voiceId,
                'name'     => $newName,
            ]);
            exit;
        }

        $msg = $body;
        if (is_array($data)) {
            if (isset($data['detail']['message'])) $msg = $data['detail']['message'];
            elseif (isset($data['detail']))       $msg = is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
            elseif (isset($data['message']))      $msg = $data['message'];
        }
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => 'ElevenLabs (HTTP ' . $status . '): ' . substr($msg, 0, 500)]);
        exit;
    }

    // ------------------- GET: procurar vozes públicas -------------------
    $uiLang = strtolower(trim((string) ($_GET['lang'] ?? '')));
    if ($uiLang === '') {
        throw new Exception('Parâmetro "lang" em falta.');
    }

    $query = [
        'page_size' => 30,
        'language'  => elevenlabs_lang_for($uiLang),
        'page'      => max(0, (int) ($_GET['page'] ?? 0)),
    ];

    foreach (['gender', 'age', 'accent', 'use_cases', 'category', 'descriptives'] as $k) {
        if (!empty($_GET[$k])) $query[$k] = trim((string) $_GET[$k]);
    }
    if (!empty($_GET['q'])) {
        $query['search'] = trim((string) $_GET['q']);
    }

    $resp = elevenlabs_http()->get('v1/shared-voices', [
        'headers' => [
            'xi-api-key' => ELEVENLABS_API_KEY,
            'Accept'     => 'application/json',
        ],
        'query' => $query,
    ]);

    $status = $resp->getStatusCode();
    $body   = (string) $resp->getBody();
    $data   = json_decode($body, true);

    if ($status !== 200) {
        $msg = $body;
        if (is_array($data)) {
            if (isset($data['detail']['message'])) $msg = $data['detail']['message'];
            elseif (isset($data['detail']))       $msg = is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
            elseif (isset($data['message']))      $msg = $data['message'];
        }
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => 'ElevenLabs (HTTP ' . $status . '): ' . substr($msg, 0, 500)]);
        exit;
    }

    $voices = [];
    if (is_array($data) && !empty($data['voices'])) {
        foreach ($data['voices'] as $v) {
            $voices[] = [
                'voice_id'        => (string) ($v['voice_id'] ?? ''),
                'public_owner_id' => (string) ($v['public_owner_id'] ?? ''),
                'name'            => (string) ($v['name'] ?? ''),
                'gender'          => (string) ($v['gender'] ?? ''),
                'age'             => (string) ($v['age'] ?? ''),
                'accent'          => (string) ($v['accent'] ?? ''),
                'language'        => (string) ($v['language'] ?? ''),
                'descriptive'     => (string) ($v['descriptive'] ?? ''),
                'use_case'        => (string) ($v['use_case'] ?? ''),
                'preview_url'     => (string) ($v['preview_url'] ?? ''),
                'free_users_allowed' => (bool) ($v['free_users_allowed'] ?? false),
            ];
        }
    }

    echo json_encode([
        'ok'      => true,
        'lang'    => $uiLang,
        'page'    => $query['page'],
        'count'   => count($voices),
        'has_more'=> (bool) ($data['has_more'] ?? false),
        'voices'  => $voices,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

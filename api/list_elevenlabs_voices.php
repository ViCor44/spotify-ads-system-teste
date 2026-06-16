<?php
// api/list_elevenlabs_voices.php
// Lista as vozes disponíveis na conta ElevenLabs (com cache em ficheiro).
// Uso:
//   GET  -> devolve JSON (com cache de 24h)
//   GET ?refresh=1 -> força refresh do cache
//   include direto em PHP -> usa a função get_elevenlabs_voices(...)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client as HttpClient;

if (!function_exists('get_elevenlabs_voices')) {
    /**
     * Devolve a lista de vozes ElevenLabs (com cache em ficheiro).
     *
     * @param bool $forceRefresh  Se true ignora o cache.
     * @param int  $ttlSeconds    Tempo de vida do cache (default 24h).
     * @return array              Array de vozes: [['voice_id','name','category','labels','preview_url'], ...]
     */
    function get_elevenlabs_voices(bool $forceRefresh = false, int $ttlSeconds = 86400): array {
        $cacheDir  = __DIR__ . '/../storage';
        $cacheFile = $cacheDir . '/elevenlabs_voices.json';

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        // Usa cache se existir e for válido
        if (!$forceRefresh && is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttlSeconds)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        if (!defined('ELEVENLABS_API_KEY') || ELEVENLABS_API_KEY === '' || ELEVENLABS_API_KEY === 'SUBSTITUIR_PELA_API_KEY_DA_ELEVENLABS') {
            throw new Exception('ELEVENLABS_API_KEY não configurada em config/database.php.');
        }

        $http = new HttpClient([
            'base_uri'    => 'https://api.elevenlabs.io/',
            'http_errors' => false,
            'timeout'     => 30,
        ]);

        $resp = $http->get('v1/voices', [
            'headers' => [
                'xi-api-key' => ELEVENLABS_API_KEY,
                'Accept'     => 'application/json',
            ],
        ]);

        $status = $resp->getStatusCode();
        $body   = (string) $resp->getBody();

        if ($status !== 200) {
            // Se há cache antigo, devolve-o como fallback
            if (is_file($cacheFile)) {
                $cached = json_decode((string) file_get_contents($cacheFile), true);
                if (is_array($cached) && !empty($cached)) {
                    return $cached;
                }
            }
            throw new Exception("ElevenLabs /v1/voices falhou (HTTP $status): " . substr($body, 0, 300));
        }

        $decoded = json_decode($body, true);
        $voices  = [];

        if (is_array($decoded) && !empty($decoded['voices']) && is_array($decoded['voices'])) {
            foreach ($decoded['voices'] as $v) {
                if (empty($v['voice_id']) || empty($v['name'])) continue;
                $voices[] = [
                    'voice_id'    => (string) $v['voice_id'],
                    'name'        => (string) $v['name'],
                    'category'    => (string) ($v['category'] ?? ''),
                    'labels'      => is_array($v['labels'] ?? null) ? $v['labels'] : [],
                    'preview_url' => (string) ($v['preview_url'] ?? ''),
                ];
            }
        }

        // Ordena por categoria (premade/cloned/...) e depois por nome
        usort($voices, function ($a, $b) {
            return [$a['category'], strtolower($a['name'])]
                <=> [$b['category'], strtolower($b['name'])];
        });

        if (!empty($voices)) {
            @file_put_contents($cacheFile, json_encode($voices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $voices;
    }
}

// Se for chamado diretamente via HTTP, devolve JSON
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $force = !empty($_GET['refresh']);
        $voices = get_elevenlabs_voices($force);
        echo json_encode([
            'ok'      => true,
            'count'   => count($voices),
            'default' => defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '',
            'voices'  => $voices,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

<?php
// api/tts_settings.php
// Lê/escreve preferências do TTS (atualmente: voz predefinida pelo utilizador).

if (!function_exists('tts_settings_path')) {
    function tts_settings_path(): string {
        $dir = __DIR__ . '/../storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/tts_settings.json';
    }

    /** Devolve as preferências guardadas (array). */
    function tts_settings_read(): array {
        $path = tts_settings_path();
        if (!is_file($path)) return [];
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /** Escreve as preferências (merge). */
    function tts_settings_write(array $patch): bool {
        $path     = tts_settings_path();
        $current  = tts_settings_read();
        $merged   = array_merge($current, $patch);
        $merged['updated_at'] = date('c');
        return (bool) file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /** Voz predefinida do utilizador; fallback para a constante em config/database.php. */
    function tts_get_default_voice_id(): string {
        $s = tts_settings_read();
        if (!empty($s['default_voice_id'])) return (string) $s['default_voice_id'];
        return defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '';
    }
}

// --- Endpoint HTTP (POST set | GET get) ---
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/list_elevenlabs_voices.php';

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $voiceId = trim((string) ($_POST['voice_id'] ?? ''));
            if ($voiceId === '') {
                throw new Exception('voice_id em falta.');
            }

            // Validação contra a lista da ElevenLabs (com fallback para formato).
            $valid = false;
            try {
                $voices = get_elevenlabs_voices();
                $valid  = in_array($voiceId, array_column($voices, 'voice_id'), true);
            } catch (Throwable $e) {
                $valid = (bool) preg_match('/^[A-Za-z0-9]{16,}$/', $voiceId);
            }
            if (!$valid) {
                throw new Exception('voice_id inválido.');
            }

            tts_settings_write(['default_voice_id' => $voiceId]);
            echo json_encode(['ok' => true, 'default_voice_id' => $voiceId]);
            exit;
        }

        // GET
        echo json_encode([
            'ok'               => true,
            'default_voice_id' => tts_get_default_voice_id(),
            'settings'         => tts_settings_read(),
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

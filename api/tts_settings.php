<?php
// api/tts_settings.php
// Lê/escreve preferências do TTS (voz predefinida global + por idioma).

if (!function_exists('tts_settings_path')) {

    /** Idiomas suportados no UI do anúncio. */
    function tts_supported_langs(): array {
        return ['pt', 'en', 'es', 'fr', 'de', 'it', 'nl', 'pl'];
    }

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

    /** Escreve as preferências (merge profundo simples). */
    function tts_settings_write(array $patch): bool {
        $path    = tts_settings_path();
        $current = tts_settings_read();

        // merge "by_lang" sem perder linguas antigas
        if (isset($patch['default_voice_by_lang']) && is_array($patch['default_voice_by_lang'])) {
            $existing = is_array($current['default_voice_by_lang'] ?? null)
                ? $current['default_voice_by_lang'] : [];
            $patch['default_voice_by_lang'] = array_merge($existing, $patch['default_voice_by_lang']);
        }

        $merged = array_merge($current, $patch);
        $merged['updated_at'] = date('c');
        return (bool) file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /** Voz predefinida global; fallback para a constante em config/database.php. */
    function tts_get_default_voice_id(): string {
        $s = tts_settings_read();
        if (!empty($s['default_voice_id'])) return (string) $s['default_voice_id'];
        return defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '';
    }

    /**
     * Voz predefinida para um idioma específico.
     * Cascata: by_lang[$lang] -> default_voice_id -> ELEVENLABS_VOICE_ID.
     */
    function tts_get_default_voice_id_for_lang(string $lang): string {
        $s = tts_settings_read();
        if (!empty($s['default_voice_by_lang'][$lang])) {
            return (string) $s['default_voice_by_lang'][$lang];
        }
        return tts_get_default_voice_id();
    }

    /** Mapa lang => voice_id (apenas para os idiomas suportados). */
    function tts_get_all_default_voices_by_lang(): array {
        $out = [];
        foreach (tts_supported_langs() as $lang) {
            $out[$lang] = tts_get_default_voice_id_for_lang($lang);
        }
        return $out;
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
            $lang    = strtolower(trim((string) ($_POST['lang'] ?? '')));

            if ($voiceId === '') {
                throw new Exception('voice_id em falta.');
            }
            if ($lang !== '' && !in_array($lang, tts_supported_langs(), true)) {
                throw new Exception('lang inválida.');
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

            if ($lang === '') {
                tts_settings_write(['default_voice_id' => $voiceId]);
                echo json_encode(['ok' => true, 'scope' => 'global', 'default_voice_id' => $voiceId]);
            } else {
                tts_settings_write(['default_voice_by_lang' => [$lang => $voiceId]]);
                echo json_encode([
                    'ok'       => true,
                    'scope'    => 'lang',
                    'lang'     => $lang,
                    'voice_id' => $voiceId,
                ]);
            }
            exit;
        }

        echo json_encode([
            'ok'                    => true,
            'default_voice_id'      => tts_get_default_voice_id(),
            'default_voice_by_lang' => tts_get_all_default_voices_by_lang(),
            'settings'              => tts_settings_read(),
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

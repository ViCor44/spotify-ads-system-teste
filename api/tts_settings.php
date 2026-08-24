<?php
// api/tts_settings.php
// Lê/escreve preferências do TTS (voz predefinida global + por idioma).

if (!function_exists('tts_settings_path')) {

    /** Idiomas suportados no UI do anúncio. */
    function tts_supported_langs(): array {
        return ['pt', 'en', 'es', 'fr'];
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

    /** IDs das vozes marcadas como preferidas. */
    function tts_get_favorite_voice_ids(): array {
        $s = tts_settings_read();
        $ids = is_array($s['favorite_voice_ids'] ?? null) ? $s['favorite_voice_ids'] : [];
        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    /** Adiciona/remove uma voz da lista de preferidas. */
    function tts_set_favorite_voice(string $voiceId, bool $favorite): bool {
        $ids = tts_get_favorite_voice_ids();
        if ($favorite && !in_array($voiceId, $ids, true)) {
            $ids[] = $voiceId;
        } elseif (!$favorite) {
            $ids = array_values(array_filter($ids, fn($id) => $id !== $voiceId));
        }
        return tts_settings_write(['favorite_voice_ids' => $ids]);
    }

    /** Devolve voice_settings, incluindo a velocidade suportada pela ElevenLabs. */
    function tts_get_voice_settings(): array {
        $s = tts_settings_read();
        $defaults = [
            'stability'         => 0.50,
            'similarity_boost'  => 0.75,
            'style'             => 0.00,
            'use_speaker_boost' => true,
            'speed'             => 0.85,
        ];
        $stored = isset($s['voice_settings']) && is_array($s['voice_settings'])
            ? $s['voice_settings']
            : [];
        $settings = array_merge($defaults, $stored);
        $settings['speed'] = max(0.7, min(1.2, (float) $settings['speed']));
        return $settings;
    }

    /** Escreve voice_settings e guarda no storage. */
    function tts_set_voice_settings(float $stability = 0.50, float $similarity_boost = 0.75, float $style = 0.00, bool $use_speaker_boost = true, float $speed = 0.85): bool {
        $settings = [
            'stability'         => max(0.0, min(1.0, (float) $stability)),
            'similarity_boost'  => max(0.0, min(1.0, (float) $similarity_boost)),
            'style'             => max(0.0, min(1.0, (float) $style)),
            'use_speaker_boost' => (bool) $use_speaker_boost,
            'speed'             => max(0.7, min(1.2, (float) $speed)),
        ];
        return tts_settings_write(['voice_settings' => $settings]);
    }

    /** Lista de model_ids suportados no UI. */
    function tts_supported_models(): array {
        return [
            'eleven_multilingual_v2' => 'Multilingual v2 (recomendado, estável)',
            'eleven_v3'              => 'v3 (alpha — mais expressivo)',
            'eleven_turbo_v2_5'      => 'Turbo v2.5 (rápido)',
            'eleven_flash_v2_5'      => 'Flash v2.5 (muito rápido, menor qualidade)',
        ];
    }

    /** Devolve o model_id ativo: settings -> constante ELEVENLABS_MODEL_ID -> multilingual_v2. */
    function tts_get_model_id(): string {
        $s = tts_settings_read();
        if (!empty($s['model_id'])) return (string) $s['model_id'];
        return defined('ELEVENLABS_MODEL_ID') ? ELEVENLABS_MODEL_ID : 'eleven_multilingual_v2';
    }

    /** Guarda o model_id (após validação contra tts_supported_models()). */
    function tts_set_model_id(string $modelId): bool {
        if (!array_key_exists($modelId, tts_supported_models())) {
            throw new Exception('model_id inválido.');
        }
        return tts_settings_write(['model_id' => $modelId]);
    }

    // ------- Vozes adicionadas manualmente por voice_id -------

    /** Devolve a lista de vozes adicionadas por ID (formato compatível com get_elevenlabs_voices). */
    function tts_get_custom_voices(): array {
        $s = tts_settings_read();
        $list = $s['custom_voices'] ?? [];
        if (!is_array($list)) return [];
        $out = [];
        foreach ($list as $v) {
            if (empty($v['voice_id'])) continue;
            $out[] = [
                'voice_id'    => (string) $v['voice_id'],
                'name'        => (string) ($v['name'] ?? 'Voz personalizada'),
                'category'    => 'custom',
                'labels'      => is_array($v['labels'] ?? null) ? $v['labels'] : ['description' => 'adicionada por ID'],
                'preview_url' => (string) ($v['preview_url'] ?? ''),
            ];
        }
        return $out;
    }

    /** Adiciona uma voz manual (guarda voice_id + nome). Devolve true se guardou; false se já existia. */
    function tts_add_custom_voice(string $voiceId, string $name = '', string $lang = ''): bool {
        $voiceId = trim($voiceId);
        if (!preg_match('/^[A-Za-z0-9_-]{10,}$/', $voiceId)) {
            throw new Exception('voice_id inválido.');
        }
        if ($name === '') $name = 'Voz ' . substr($voiceId, 0, 6);

        $s = tts_settings_read();
        $list = is_array($s['custom_voices'] ?? null) ? $s['custom_voices'] : [];

        foreach ($list as $v) {
            if (($v['voice_id'] ?? '') === $voiceId) {
                return false; // duplicada
            }
        }

        $labels = ['description' => 'adicionada por ID'];
        if ($lang !== '') $labels['language'] = $lang;

        $list[] = [
            'voice_id' => $voiceId,
            'name'     => $name,
            'lang'     => $lang,
            'labels'   => $labels,
            'added_at' => date('c'),
        ];
        tts_settings_write(['custom_voices' => $list]);
        return true;
    }

    /** Remove uma voz manual pelo voice_id. */
    function tts_remove_custom_voice(string $voiceId): bool {
        $s = tts_settings_read();
        $list = is_array($s['custom_voices'] ?? null) ? $s['custom_voices'] : [];
        $new = array_values(array_filter($list, fn($v) => ($v['voice_id'] ?? '') !== $voiceId));
        if (count($new) === count($list)) return false;
        // Reescreve o array completo (não usa merge para permitir remoção real)
        $current = tts_settings_read();
        $current['custom_voices'] = $new;
        $current['updated_at']    = date('c');
        return (bool) file_put_contents(
            tts_settings_path(),
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

// --- Endpoint HTTP (POST set | GET get) ---
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/list_elevenlabs_voices.php';

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action  = trim((string) ($_POST['action'] ?? ''));
            $voiceId = trim((string) ($_POST['voice_id'] ?? ''));
            $lang    = strtolower(trim((string) ($_POST['lang'] ?? '')));

            // --- Ação: escolher modelo (v2 / v3 / turbo / flash) ---
            if ($action === 'set_model') {
                $modelId = trim((string) ($_POST['model_id'] ?? ''));
                tts_set_model_id($modelId);
                echo json_encode([
                    'ok'       => true,
                    'type'     => 'model',
                    'model_id' => tts_get_model_id(),
                ]);
                exit;
            }

            if ($action === 'set_favorite_voice') {
                if ($voiceId === '') throw new Exception('voice_id em falta.');
                $favorite = filter_var($_POST['favorite'] ?? false, FILTER_VALIDATE_BOOLEAN);
                tts_set_favorite_voice($voiceId, $favorite);
                echo json_encode([
                    'ok' => true,
                    'type' => 'favorite_voice',
                    'voice_id' => $voiceId,
                    'favorite' => $favorite,
                    'favorite_voice_ids' => tts_get_favorite_voice_ids(),
                ]);
                exit;
            }

            // --- Ação: adicionar voz manual por ID ---
            if ($action === 'add_custom_voice') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $vlng = strtolower(trim((string) ($_POST['voice_lang'] ?? '')));
                $added = tts_add_custom_voice($voiceId, $name, $vlng);
                // Invalida cache das vozes (o merge é feito em get_elevenlabs_voices)
                $cacheFile = __DIR__ . '/../storage/elevenlabs_voices.json';
                if (is_file($cacheFile)) @unlink($cacheFile);
                echo json_encode([
                    'ok'       => true,
                    'type'     => 'custom_voice',
                    'added'    => $added,
                    'voice_id' => $voiceId,
                    'name'     => $name !== '' ? $name : ('Voz ' . substr($voiceId, 0, 6)),
                ]);
                exit;
            }

            // --- Ação: remover voz manual por ID ---
            if ($action === 'remove_custom_voice') {
                $removed = tts_remove_custom_voice($voiceId);
                $cacheFile = __DIR__ . '/../storage/elevenlabs_voices.json';
                if (is_file($cacheFile)) @unlink($cacheFile);
                echo json_encode([
                    'ok'       => true,
                    'type'     => 'custom_voice',
                    'removed'  => $removed,
                    'voice_id' => $voiceId,
                ]);
                exit;
            }

            // Guardar os ajustes de voz enviados pela página TTS.
            if (isset($_POST['stability'], $_POST['similarity_boost'], $_POST['style'])) {
                $stability         = (float) ($_POST['stability'] ?? 0.50);
                $similarity_boost  = (float) ($_POST['similarity_boost'] ?? 0.75);
                $style             = (float) ($_POST['style'] ?? 0.00);
                $use_speaker_boost = (bool) ($_POST['use_speaker_boost'] ?? true);
                $speed             = (float) ($_POST['speed'] ?? 0.85);

                if (tts_set_voice_settings($stability, $similarity_boost, $style, $use_speaker_boost, $speed)) {
                    echo json_encode([
                        'ok'                => true,
                        'type'              => 'voice_settings',
                        'voice_settings'    => tts_get_voice_settings(),
                    ]);
                } else {
                    throw new Exception('Erro ao guardar voice_settings.');
                }
                exit;
            }


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
            'model_id'              => tts_get_model_id(),
            'supported_models'      => tts_supported_models(),
            'custom_voices'         => tts_get_custom_voices(),
            'favorite_voice_ids'    => tts_get_favorite_voice_ids(),
            'settings'              => tts_settings_read(),
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

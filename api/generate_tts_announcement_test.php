<?php
// api/generate_tts_announcement_test.php
// Versão ElevenLabs (substitui o Google Cloud TTS).
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Database;
use App\SpotifyClient;
use GuzzleHttp\Client as HttpClient;
use Google\Cloud\Translate\V2\TranslateClient;

// =================== Helpers ===================

/** Converte número 0..99 para extenso PT (mantido para soletrar matrículas). */
function numeroParaExtensoPT($n) {
    if ($n < 0 || $n > 99) return (string)$n;
    $unidades  = ["zero","um","dois","três","quatro","cinco","seis","sete","oito","nove"];
    $especiais = ["dez","onze","doze","treze","catorze","quinze","dezasseis","dezassete","dezoito","dezanove"];
    $dezenas   = ["","","vinte","trinta","quarenta","cinquenta","sessenta","setenta","oitenta","noventa"];
    if ($n < 10) return $unidades[$n];
    if ($n < 20) return $especiais[$n - 10];
    $dezena  = (int) floor($n / 10);
    $unidade = $n % 10;
    return $dezenas[$dezena] . ($unidade > 0 ? " e " . $unidades[$unidade] : "");
}

/** Carrega as credenciais Google APENAS para o Translate (já não é usado para TTS). */
function ensureGoogleTranslateCreds(): void {
    static $done = false;
    if ($done) return;
    $saPath = __DIR__ . '/../config/google-tts-sa.json';
    if (!file_exists($saPath)) {
        throw new Exception("Credenciais Google Translate não encontradas: " . $saPath);
    }
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $saPath);
    $done = true;
}

/** Tradução via Google Cloud Translate v2. Devolve o original em caso de falha. */
function gtranslate_text(string $text, string $target, ?string $source = null): string {
    if ($text === '' || $target === '') return $text;
    if (!class_exists(\Google\Cloud\Translate\V2\TranslateClient::class)) {
        @file_put_contents(__DIR__.'/../public/translate_debug.json',
            json_encode(['error'=>'TranslateClient class not found'], JSON_PRETTY_PRINT));
        return $text;
    }

    try {
        ensureGoogleTranslateCreds();
    } catch (\Throwable $e) {
        @file_put_contents(__DIR__.'/../public/translate_debug.json',
            json_encode(['error'=>'creds: '.$e->getMessage()], JSON_PRETTY_PRINT));
        return $text;
    }

    static $client = null;
    if ($client === null) $client = new TranslateClient();

    $opts = ['target' => $target, 'format' => 'text'];
    if (!empty($source)) $opts['source'] = $source;

    try {
        $res = $client->translate($text, $opts);
        @file_put_contents(
            __DIR__.'/../public/translate_debug.json',
            json_encode(['request' => ['text'=>$text,'target'=>$target,'source'=>$source], 'response'=>$res],
                JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        );
        return isset($res['text'])
            ? html_entity_decode($res['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $text;
    } catch (\Throwable $e) {
        @file_put_contents(
            __DIR__.'/../public/translate_debug.json',
            json_encode(['request'=>['text'=>$text,'target'=>$target,'source'=>$source], 'error'=>$e->getMessage()],
                JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        );
        return $text;
    }
}

/** Silêncio PCM 16-bit mono (segundos). */
function pcmSilence(float $seconds, int $sampleRate = 22050, int $channels = 1, int $bits = 16): string {
    $bytesPerSample = (int) ($bits / 8);
    $numFrames = (int) round($seconds * $sampleRate);
    return str_repeat("\x00", $numFrames * $channels * $bytesPerSample);
}

/**
 * Aplica ganho a PCM 16-bit com um limitador suave.
 *
 * Limitar o multiplicador pelo maior pico do ficheiro anulava praticamente
 * todo o ganho quando a ElevenLabs devolvia apenas uma amostra perto de 0 dB.
 * A curva abaixo amplifica as restantes amostras e comprime progressivamente
 * apenas as que se aproximam do limite digital.
 */
function pcmApplyGain16(string $pcm, float $gain): string {
    if ($gain <= 0 || abs($gain - 1.0) < 0.001 || $pcm === '') return $pcm;

    $length = strlen($pcm) - (strlen($pcm) % 2);
    $limit = 32767.0;
    $knee = $limit * 0.85;
    $headroom = $limit - $knee;

    for ($offset = 0; $offset < $length; $offset += 2) {
        $sample = ord($pcm[$offset]) | (ord($pcm[$offset + 1]) << 8);
        if ($sample >= 0x8000) $sample -= 0x10000;

        $amplified = $sample * $gain;
        $magnitude = abs($amplified);
        if ($magnitude > $knee) {
            $magnitude = $knee + $headroom * (1 - exp(-($magnitude - $knee) / $headroom));
        }
        $sample = (int) round(($amplified < 0 ? -1 : 1) * $magnitude);
        $sample = max(-32768, min(32767, $sample));
        if ($sample < 0) $sample += 0x10000;

        $pcm[$offset]     = chr($sample & 0xff);
        $pcm[$offset + 1] = chr(($sample >> 8) & 0xff);
    }

    return $pcm;
}

/** Constrói um WAV (RIFF/PCM) a partir de PCM bruto + parâmetros. */
function wavBuildFromPcm(string $pcm, int $channels, int $sampleRate, int $bits): string {
    $byteRate   = (int)($sampleRate * $channels * ($bits / 8));
    $blockAlign = (int)($channels * ($bits / 8));
    $dataSize   = strlen($pcm);
    $riffSize   = 36 + $dataSize;

    $header  = 'RIFF' . pack('V', $riffSize) . 'WAVE';
    $header .= 'fmt ' . pack('V', 16);
    $header .= pack('v', 1);           // PCM
    $header .= pack('v', $channels);
    $header .= pack('V', $sampleRate);
    $header .= pack('V', $byteRate);
    $header .= pack('v', $blockAlign);
    $header .= pack('v', $bits);
    $header .= 'data' . pack('V', $dataSize);

    return $header . $pcm;
}

/**
 * Sintetiza voz via ElevenLabs em PCM bruto (16-bit signed little-endian, mono).
 * Devolve apenas os bytes PCM (sem cabeçalho WAV) para serem concatenados.
 */
function elevenlabs_synthesize_pcm(string $text, int $sampleRate, ?string $voiceId = null, ?string $modelId = null, ?array $voiceSettings = null): string {
    if (!defined('ELEVENLABS_API_KEY') || ELEVENLABS_API_KEY === '' || ELEVENLABS_API_KEY === 'SUBSTITUIR_PELA_API_KEY_DA_ELEVENLABS') {
        throw new Exception("ELEVENLABS_API_KEY não configurada em config/database.php.");
    }

    $voiceId = $voiceId ?: (defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '21m00Tcm4TlvDq8ikWAM');
    if (!$modelId) {
        require_once __DIR__ . '/tts_settings.php';
        $modelId = tts_get_model_id();
    }

    // ElevenLabs aceita pcm_16000, pcm_22050, pcm_24000, pcm_44100.
    $allowed = [16000, 22050, 24000, 44100];
    if (!in_array($sampleRate, $allowed, true)) {
        $sampleRate = 22050;
    }
    $outputFormat = 'pcm_' . $sampleRate;
    // Se não for fornecido voice_settings, carrega do storage
    if ($voiceSettings === null) {
        require_once __DIR__ . '/tts_settings.php';
        $voiceSettings = tts_get_voice_settings();
    }


    static $http = null;
    if ($http === null) {
        $http = new HttpClient([
            'base_uri'    => 'https://api.elevenlabs.io/',
            'http_errors' => false,
            'timeout'     => 60,
        ]);
    }

    $resp = $http->post('v1/text-to-speech/' . rawurlencode($voiceId), [
        'headers' => [
            'xi-api-key'   => ELEVENLABS_API_KEY,
            'Accept'       => 'audio/basic',
            'Content-Type' => 'application/json',
        ],
        'query' => [ 'output_format' => $outputFormat ],
        'json' => [
            'text'           => $text,
            'model_id'       => $modelId,
            'voice_settings' => $voiceSettings,
        ],
    ]);

    $status = $resp->getStatusCode();
    $body   = (string) $resp->getBody();

    if ($status !== 200) {
        $errMsg  = $body;
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['detail']['message'])) $errMsg = $decoded['detail']['message'];
            elseif (isset($decoded['detail']))       $errMsg = is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']);
            elseif (isset($decoded['message']))      $errMsg = $decoded['message'];
        }
        throw new Exception("ElevenLabs falhou (HTTP $status): " . substr($errMsg, 0, 500));
    }

    if ($body === '') {
        throw new Exception("ElevenLabs devolveu áudio vazio.");
    }

    // Algumas respostas de erro vêm com Content-Type application/json mesmo com HTTP 200.
    $ct = strtolower($resp->getHeaderLine('Content-Type'));
    if (strpos($ct, 'json') !== false) {
        $decoded = json_decode($body, true);
        $msg = is_array($decoded) ? json_encode($decoded) : $body;
        throw new Exception("ElevenLabs devolveu JSON em vez de áudio: " . substr($msg, 0, 500));
    }

    return $body;
}

// =================== Controller ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['languages'])) {
    $_SESSION['last_tts_data'] = $_POST;

    $announcementType  = $_POST['announcement_type'] ?? 'plate';

    // Lê e normaliza idiomas escolhidos
    $selectedLanguages = $_POST['languages'] ?? [];
    if (!is_array($selectedLanguages)) $selectedLanguages = [$selectedLanguages];
    $supported = ['pt','en','es','fr'];
    $selectedLanguages = array_values(array_intersect($selectedLanguages, $supported));
    if (empty($selectedLanguages)) $selectedLanguages = ['pt'];

    // Campos do personalizado
    $customText = trim($_POST['custom_text'] ?? '');
    $playGong   = !empty($_POST['custom_gong']);

    // Vozes escolhidas pelo utilizador (uma por idioma).
    // Fallback: voz predefinida guardada para o idioma -> voz global -> ELEVENLABS_VOICE_ID.
    require_once __DIR__ . '/tts_settings.php';
    require_once __DIR__ . '/list_elevenlabs_voices.php';

    $voiceByLangPost = $_POST['voice_id_by_lang'] ?? [];
    if (!is_array($voiceByLangPost)) $voiceByLangPost = [];

    // Carrega lista atual para validar (com fallback graceful)
    $validVoiceIds = [];
    try {
        $availableVoices = get_elevenlabs_voices();
        $validVoiceIds   = array_column($availableVoices, 'voice_id');
    } catch (Throwable $e) {
        $validVoiceIds = []; // sem validação rígida
    }

    $voiceByLang = [];
    foreach ($supported as $lng) {
        $candidate = isset($voiceByLangPost[$lng]) ? trim((string) $voiceByLangPost[$lng]) : '';
        // Se a voz veio do form e é válida, usa-a; senão usa a preferência guardada para o idioma.
        if ($candidate !== '' && (empty($validVoiceIds) ? preg_match('/^[A-Za-z0-9]{16,}$/', $candidate) : in_array($candidate, $validVoiceIds, true))) {
            $voiceByLang[$lng] = $candidate;
        } else {
            $voiceByLang[$lng] = tts_get_default_voice_id_for_lang($lng);
        }
    }

    // Config de línguas (sem voz: a ElevenLabs usa a mesma voz multilingue)
    $langConfig = [
        'pt' => [
            'plate_text'       => "Atenção ao proprietário do veículo %s %s, com a matrícula %s. Repito, matrícula %s. Por favor, dirija-se à receção. Obrigado",
            'plate_text_color' => "Atenção ao proprietário do veículo %s %s de cor %s, com a matrícula %s. Repito, matrícula %s. Por favor, dirija-se à receção. Obrigado",
            'child_text'  => "Atenção, solicitamos a presença dos pais ou responsáveis da criança, %s, repito, %s, junto à receção. Obrigado",
            'person_text' => "Atenção, solicitamos a presença de %s, repito, %s, junto à receção. Obrigado",
            'phoneticMap' => [ 'A'=>'Á','B'=>'Bê','C'=>'Cê','D'=>'Dê','E'=>'É','F'=>'Efe','G'=>'Gê','H'=>'Agá','I'=>'I','J'=>'Jota','K'=>'Cápa','L'=>'Ele','M'=>'Eme','N'=>'Ene','O'=>'Ó','P'=>'Pê','Q'=>'Quê','R'=>'Erre','S'=>'Esse','T'=>'Tê','U'=>'U','V'=>'Vê','W'=>'Dáblio','X'=>'Xis','Y'=>'Ipsílon','Z'=>'Zê' ],
            'numberFormatter' => 'numeroParaExtensoPT'
        ],
        'en' => [
            'plate_text'       => "Attention to the owner of the %s %s, with license plate %s. I repeat, %s. Please proceed to the reception. Thank you",
            'plate_text_color' => "Attention to the owner of the %s %s, %s in color, with license plate %s. I repeat, %s. Please proceed to the reception. Thank you",
            'child_text'  => "Attention, we request the presence of the parents or guardians of the child %s. I repeat, %s. at the reception. Thank you",
            'person_text' => "Attention, we request the presence of %s. I repeat, %s. at the reception. Thank you",
        ],
        'es' => [
            'plate_text'       => "Atención al propietario del vehículo %s %s, con matrícula %s. Repito, %s. Por favor, diríjase a recepción. Gracias",
            'plate_text_color' => "Atención al propietario del vehículo %s %s de color %s, con matrícula %s. Repito, %s. Por favor, diríjase a recepción. Gracias",
            'child_text'  => "Atención, solicitamos la presencia de los padres o responsables del niño %s. Repito, %s. en la recepción. Gracias",
            'person_text' => "Atención, solicitamos la presencia de %s. Repito, %s. en la recepción. Gracias",
        ],
        'fr' => [
            'plate_text'       => "Attention au propriétaire du véhicule %s %s, avec la plaque d'immatriculation %s. Je répète, %s. Veuillez vous présenter à la réception. Merci",
            'plate_text_color' => "Attention au propriétaire du véhicule %s %s de couleur %s, avec la plaque d'immatriculation %s. Je répète, %s. Veuillez vous présenter à la réception. Merci",
            'child_text'  => "Attention, nous demandons la présence des parents ou tuteurs de l'enfant %s. Je répète, %s. à la réception. Merci",
            'person_text' => "Attention, nous demandons la présence de %s. Je répète, %s. à la réception. Merci",
        ],
    ];

    // Alvos para Translate (ISO-639-1)
    $translateTarget = ['pt'=>'pt','en'=>'en','es'=>'es','fr'=>'fr'];

    // Construção dos textos por idioma
    $segments = []; // cada item: ['lang', 'text']
    $textToLog = '';

    try {
        foreach ($selectedLanguages as $lang) {
            $cfg = $langConfig[$lang] ?? null;
            if (!$cfg) continue;

            $textToSpeech = '';

            if ($announcementType === 'plate' && !empty($_POST['license_plate'])) {
                $make  = trim($_POST['vehicle_make'] ?? '');
                $model = trim($_POST['vehicle_model'] ?? '');
                $color = trim($_POST['vehicle_color'] ?? '');
                $plate = strtoupper(trim($_POST['license_plate']));
                $textToLog = "Anúncio Matrícula: $make $model"
                    . ($color !== '' ? " ($color)" : '')
                    . " $plate";

                $plateCleaned = str_replace([' ', '-'], ' ', $plate);
                $spelledParts = [];
                preg_match_all('/([A-Z]+|[0-9]+)/', $plateCleaned, $matches);
                $parts = $matches[0] ?? [];
                foreach ($parts as $part) {
                    if (is_numeric($part) && isset($cfg['numberFormatter'])) {
                        $spelledParts[] = call_user_func($cfg['numberFormatter'], (int)$part);
                    } else {
                        foreach (str_split($part) as $ch) {
                            $spelledParts[] = $cfg['phoneticMap'][$ch] ?? $ch;
                        }
                    }
                }
                $plateSpelled = implode(', ', $spelledParts);

                if ($color !== '' && !empty($cfg['plate_text_color'])) {
                    // Traduz a cor (input em PT) para o idioma alvo.
                    $target      = $translateTarget[$lang] ?? $lang;
                    $colorLocal  = ($target === 'pt') ? $color : gtranslate_text($color, $target, 'pt');
                    $textToSpeech = sprintf($cfg['plate_text_color'], $make, $model, $colorLocal, $plateSpelled, $plateSpelled);
                } else {
                    $textToSpeech = sprintf($cfg['plate_text'], $make, $model, $plateSpelled, $plateSpelled);
                }

            } elseif ($announcementType === 'child' && !empty($_POST['child_name'])) {
                $childName = trim($_POST['child_name']);
                $textToLog = "Anúncio Criança: $childName";
                $textToSpeech = sprintf($cfg['child_text'], $childName, $childName);

            } elseif ($announcementType === 'person' && !empty($_POST['person_name'])) {
                $personName = trim($_POST['person_name']);
                $textToLog = "Anúncio Chamada: $personName";
                $textToSpeech = sprintf($cfg['person_text'], $personName, $personName);

            } elseif ($announcementType === 'custom' && $customText !== '') {
                $textToLog = "Anúncio Personalizado";
                $target = $translateTarget[$lang] ?? 'pt';
                // Traduz sempre (mesmo pt->pt devolve igual; útil p/ debug)
                $textToSpeech = gtranslate_text($customText, $target, null);
            }

            if ($textToSpeech !== '') {
                $segments[] = [
                    'lang'     => $lang,
                    'text'     => $textToSpeech,
                    'voice_id' => $voiceByLang[$lang] ?? '',
                ];
            }
        }

        if (empty($segments)) {
            die("Ocorreu um erro ao gerar o anúncio: dados insuficientes.");
        }

        // =================== Cache ===================
        require_once __DIR__ . '/tts_settings.php';
        $modelId = tts_get_model_id();
        if (isset($_POST['voice_settings']) && is_array($_POST['voice_settings'])) {
            $postedVoiceSettings = $_POST['voice_settings'];
            tts_set_voice_settings(
                (float) ($postedVoiceSettings['stability'] ?? 50) / 100,
                (float) ($postedVoiceSettings['similarity_boost'] ?? 75) / 100,
                (float) ($postedVoiceSettings['style'] ?? 0) / 100,
                isset($postedVoiceSettings['use_speaker_boost']),
                (float) ($postedVoiceSettings['speed'] ?? 85) / 100
            );
        }
        $voiceSettings = tts_get_voice_settings();
        // A ElevenLabs ignora "speed" para o modelo v3 (só se aplica a v2/turbo/flash).
        if (strpos($modelId, 'eleven_v3') === 0) {
            unset($voiceSettings['speed']);
        }

        // Parâmetros de áudio uniformes (para concatenar PCM sem clicks)
        $SAMPLE_RATE = 22050;
        $CHANNELS    = 1;
        $BITS        = 16;
        $SILENCE_SEC = 0.40; // pausa entre idiomas
        $ttsGainPercent = max(100, min(200, (int)($_POST['tts_gain'] ?? 120)));
        $TTS_GAIN       = $ttsGainPercent / 100;

        $cacheKeyBase = [
            'provider'         => 'elevenlabs',
            'format'           => 'pcm_wav',
            'model'            => $modelId,
            'announcementType' => $announcementType,
            'segments'         => array_map(function($s){
                return ['lang' => $s['lang'], 'text' => $s['text'], 'voice_id' => $s['voice_id']];
            }, $segments),
            'voice_settings'   => $voiceSettings,
            'audio' => [
                'sr'      => $SAMPLE_RATE,
                'ch'      => $CHANNELS,
                'bits'    => $BITS,
                'silence' => $SILENCE_SEC,
                'gain'    => $TTS_GAIN,
            ],
        ];
        $cacheKey = hash('sha256', json_encode($cacheKeyBase, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $ttsDir = __DIR__ . '/../public/uploads/tts/';
        if (!is_dir($ttsDir)) {
            if (!mkdir($ttsDir, 0775, true) && !is_dir($ttsDir)) {
                die("Ocorreu um erro ao gerar o anúncio: não foi possível criar a pasta de saída.");
            }
        }
        $fileName = "tts_multilang_{$cacheKey}.wav";
        $filePath = $ttsDir . $fileName;

        // =================== Geração ===================
        $allLangsUsed = array_map(fn($s) => $s['lang'], $segments);

        if (!file_exists($filePath)) {
            $pcmParts = [];
            $lastIdx  = count($segments) - 1;

            $fallbackVoice = defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '';
            foreach ($segments as $i => $s) {
                $segVoice = !empty($s['voice_id']) ? $s['voice_id'] : $fallbackVoice;
                $pcmParts[] = elevenlabs_synthesize_pcm($s['text'], $SAMPLE_RATE, $segVoice, $modelId, $voiceSettings);
                if ($i !== $lastIdx && $SILENCE_SEC > 0) {
                    $pcmParts[] = pcmSilence($SILENCE_SEC, $SAMPLE_RATE, $CHANNELS, $BITS);
                }
            }

            $pcmAll   = pcmApplyGain16(implode('', $pcmParts), $TTS_GAIN);
            $wavFinal = wavBuildFromPcm($pcmAll, $CHANNELS, $SAMPLE_RATE, $BITS);

            if (file_put_contents($filePath, $wavFinal) === false) {
                throw new Exception("Falha a escrever o ficheiro de áudio.");
            }
        }

        // Duração (getID3)
        $getID3 = new \getID3();
        $fileInfo = $getID3->analyze($filePath);
        $durationSeconds = isset($fileInfo['playtime_seconds']) ? (int) round($fileInfo['playtime_seconds']) : 0;

        // Spotify (opcional)
        $spotifyClient = new SpotifyClient();
        $state = $spotifyClient->getPlaybackState();
        $initialState = ($state && !empty($state->is_playing)) ? 'playing' : 'paused';
        $spotifyClient->pausePlayback();

        $title = $textToLog . (empty($allLangsUsed) ? '' : ' (' . implode(', ', $allLangsUsed) . ')');
        $status = [
            'status'         => 'play',
            'type'           => 'single',
            'url'            => '/uploads/tts/' . $fileName,
            'title'          => $title,
            'duration'       => $durationSeconds,
            'initial_state'  => $initialState,
            'has_gong'       => $playGong, // gong é tocado pelo player se marcado
        ];
        file_put_contents(__DIR__ . '/../public/status.json',
            json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Log
        $pdo = Database::getInstance();
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (announcement_title, play_type) VALUES (?, 'Manual')");
        $logStmt->execute([$title]);

        header('Location: ../public/index.php?page=tts_announcement&status=success');
        exit();

    } catch (Exception $e) {
        die("Ocorreu um erro ao gerar o anúncio: " . $e->getMessage());
    }

} else {
    header('Location: ../public/index.php?page=tts_announcement');
    exit();
}

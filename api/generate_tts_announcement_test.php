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

/**
 * Sintetiza voz via ElevenLabs (formato MP3).
 * Devolve o conteúdo binário do MP3.
 */
function elevenlabs_synthesize(string $text, ?string $voiceId = null, ?string $modelId = null): string {
    if (!defined('ELEVENLABS_API_KEY') || ELEVENLABS_API_KEY === '' || ELEVENLABS_API_KEY === 'SUBSTITUIR_PELA_API_KEY_DA_ELEVENLABS') {
        throw new Exception("ELEVENLABS_API_KEY não configurada em config/database.php.");
    }

    $voiceId = $voiceId ?: (defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '21m00Tcm4TlvDq8ikWAM');
    $modelId = $modelId ?: (defined('ELEVENLABS_MODEL_ID') ? ELEVENLABS_MODEL_ID : 'eleven_multilingual_v2');

    static $http = null;
    if ($http === null) {
        $http = new HttpClient([
            'base_uri'    => 'https://api.elevenlabs.io/',
            'http_errors' => false,
            'timeout'     => 60,
        ]);
    }

    $payload = [
        'text'           => $text,
        'model_id'       => $modelId,
        'voice_settings' => [
            'stability'        => 0.45,
            'similarity_boost' => 0.85,
            'style'            => 0.20,
            'use_speaker_boost'=> true,
        ],
    ];

    $resp = $http->post('v1/text-to-speech/' . rawurlencode($voiceId), [
        'headers' => [
            'xi-api-key'   => ELEVENLABS_API_KEY,
            'Accept'       => 'audio/mpeg',
            'Content-Type' => 'application/json',
        ],
        'query' => [
            'output_format' => 'mp3_44100_128',
        ],
        'json' => $payload,
    ]);

    $status = $resp->getStatusCode();
    $body   = (string) $resp->getBody();

    if ($status !== 200) {
        // Tenta extrair mensagem de erro JSON
        $errMsg = $body;
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['detail']['message'])) $errMsg = $decoded['detail']['message'];
            elseif (isset($decoded['detail']))       $errMsg = is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']);
            elseif (isset($decoded['message']))      $errMsg = $decoded['message'];
        }
        throw new Exception("ElevenLabs falhou (HTTP $status): " . substr($errMsg, 0, 500));
    }

    if ($body === '' || strpos($resp->getHeaderLine('Content-Type'), 'audio') === false) {
        throw new Exception("ElevenLabs devolveu resposta inválida (Content-Type: " . $resp->getHeaderLine('Content-Type') . ").");
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

    // Config de línguas (sem voz: a ElevenLabs usa a mesma voz multilingue)
    $langConfig = [
        'pt' => [
            'plate_text'  => "Atenção ao proprietário do veículo %s %s, com a matrícula %s. Repito, matrícula %s. Por favor, dirija-se à receção. Obrigado",
            'child_text'  => "Atenção, solicitamos a presença dos pais ou responsáveis da criança, %s, repito, %s, junto à receção. Obrigado",
            'person_text' => "Atenção, solicitamos a presença de %s, repito, %s, junto à receção. Obrigado",
            'phoneticMap' => [ 'A'=>'Á','B'=>'Bê','C'=>'Cê','D'=>'Dê','E'=>'É','F'=>'Efe','G'=>'Gê','H'=>'Agá','I'=>'I','J'=>'Jota','K'=>'Cápa','L'=>'Ele','M'=>'Eme','N'=>'Ene','O'=>'Ó','P'=>'Pê','Q'=>'Quê','R'=>'Erre','S'=>'Esse','T'=>'Tê','U'=>'U','V'=>'Vê','W'=>'Dáblio','X'=>'Xis','Y'=>'Ipsílon','Z'=>'Zê' ],
            'numberFormatter' => 'numeroParaExtensoPT'
        ],
        'en' => [
            'plate_text'  => "Attention to the owner of the %s %s, with license plate %s. I repeat, %s. Please proceed to the reception. Thank you",
            'child_text'  => "Attention, we request the presence of the parents or guardians of the child %s. I repeat, %s. at the reception. Thank you",
            'person_text' => "Attention, we request the presence of %s. I repeat, %s. at the reception. Thank you",
        ],
        'es' => [
            'plate_text'  => "Atención al propietario del vehículo %s %s, con matrícula %s. Repito, %s. Por favor, diríjase a recepción. Gracias",
            'child_text'  => "Atención, solicitamos la presencia de los padres o responsables del niño %s. Repito, %s. en la recepción. Gracias",
            'person_text' => "Atención, solicitamos la presencia de %s. Repito, %s. en la recepción. Gracias",
        ],
        'fr' => [
            'plate_text'  => "Attention au propriétaire du véhicule %s %s, avec la plaque d'immatriculation %s. Je répète, %s. Veuillez vous présenter à la réception. Merci",
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
                $plate = strtoupper(trim($_POST['license_plate']));
                $textToLog = "Anúncio Matrícula: $make $model $plate";

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
                $textToSpeech = sprintf($cfg['plate_text'], $make, $model, $plateSpelled, $plateSpelled);

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
                    'lang' => $lang,
                    'text' => $textToSpeech,
                ];
            }
        }

        if (empty($segments)) {
            die("Ocorreu um erro ao gerar o anúncio: dados insuficientes.");
        }

        // =================== Cache ===================
        $voiceId = defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '';
        $modelId = defined('ELEVENLABS_MODEL_ID') ? ELEVENLABS_MODEL_ID : '';

        $cacheKeyBase = [
            'provider'         => 'elevenlabs',
            'voice'            => $voiceId,
            'model'            => $modelId,
            'announcementType' => $announcementType,
            'segments'         => array_map(function($s){
                return ['lang' => $s['lang'], 'text' => $s['text']];
            }, $segments),
        ];
        $cacheKey = hash('sha256', json_encode($cacheKeyBase, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $ttsDir = __DIR__ . '/../public/uploads/tts/';
        if (!is_dir($ttsDir)) {
            if (!mkdir($ttsDir, 0775, true) && !is_dir($ttsDir)) {
                die("Ocorreu um erro ao gerar o anúncio: não foi possível criar a pasta de saída.");
            }
        }
        $fileName = "tts_multilang_{$cacheKey}.mp3";
        $filePath = $ttsDir . $fileName;

        // =================== Geração ===================
        $allLangsUsed = array_map(fn($s) => $s['lang'], $segments);

        if (!file_exists($filePath)) {
            $mp3Parts = [];

            foreach ($segments as $s) {
                $mp3Parts[] = elevenlabs_synthesize($s['text'], $voiceId, $modelId);
            }

            $mp3Final = implode('', $mp3Parts);

            if (file_put_contents($filePath, $mp3Final) === false) {
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

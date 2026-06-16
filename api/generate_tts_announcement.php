<?php
// api/generate_tts_announcement.php
// Versão ElevenLabs (substitui o uso do translate.google.com como TTS).
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Database;
use App\SpotifyClient;
use GuzzleHttp\Client as HttpClient;
use getID3;

// --- FUNÇÃO AUXILIAR PARA CONVERTER NÚMEROS EM TEXTO (PORTUGUÊS) ---
function numeroParaExtensoPT($n) {
    if ($n < 0 || $n > 99) return (string)$n;
    $unidades = ["zero", "um", "dois", "três", "quatro", "cinco", "seis", "sete", "oito", "nove"];
    $especiais = ["dez", "onze", "doze", "treze", "catorze", "quinze", "dezasseis", "dezassete", "dezoito", "dezanove"];
    $dezenas = ["", "", "vinte", "trinta", "quarenta", "cinquenta", "sessenta", "setenta", "oitenta", "noventa"];
    if ($n < 10) return $unidades[$n];
    if ($n < 20) return $especiais[$n - 10];
    $dezena = floor($n / 10);
    $unidade = $n % 10;
    return $dezenas[$dezena] . ($unidade > 0 ? " e " . $unidades[$unidade] : "");
}

/** Constrói um WAV (RIFF/PCM) a partir de PCM bruto. */
function wavBuildFromPcm(string $pcm, int $channels, int $sampleRate, int $bits): string {
    $byteRate   = (int)($sampleRate * $channels * ($bits / 8));
    $blockAlign = (int)($channels * ($bits / 8));
    $dataSize   = strlen($pcm);
    $riffSize   = 36 + $dataSize;

    $header  = 'RIFF' . pack('V', $riffSize) . 'WAVE';
    $header .= 'fmt ' . pack('V', 16);
    $header .= pack('v', 1);
    $header .= pack('v', $channels);
    $header .= pack('V', $sampleRate);
    $header .= pack('V', $byteRate);
    $header .= pack('v', $blockAlign);
    $header .= pack('v', $bits);
    $header .= 'data' . pack('V', $dataSize);

    return $header . $pcm;
}

/** Sintetiza voz via ElevenLabs em PCM bruto (16-bit signed LE, mono). */
function elevenlabs_synthesize_basic(string $text, int $sampleRate = 22050): string {
    if (!defined('ELEVENLABS_API_KEY') || ELEVENLABS_API_KEY === '' || ELEVENLABS_API_KEY === 'SUBSTITUIR_PELA_API_KEY_DA_ELEVENLABS') {
        throw new Exception("ELEVENLABS_API_KEY não configurada em config/database.php.");
    }
    $voiceId = defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '21m00Tcm4TlvDq8ikWAM';
    $modelId = defined('ELEVENLABS_MODEL_ID') ? ELEVENLABS_MODEL_ID : 'eleven_multilingual_v2';

    $allowed = [16000, 22050, 24000, 44100];
    if (!in_array($sampleRate, $allowed, true)) $sampleRate = 22050;

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
        'query' => [ 'output_format' => 'pcm_' . $sampleRate ],
        'json' => [
            'text'           => $text,
            'model_id'       => $modelId,
            'voice_settings' => [
                'stability'         => 0.45,
                'similarity_boost'  => 0.85,
                'style'             => 0.20,
                'use_speaker_boost' => true,
            ],
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
    $ct = strtolower($resp->getHeaderLine('Content-Type'));
    if (strpos($ct, 'json') !== false) {
        throw new Exception("ElevenLabs devolveu JSON em vez de áudio: " . substr($body, 0, 500));
    }
    return $body;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['languages'])) {
    
    $_SESSION['last_tts_data'] = $_POST; // Guarda os dados para repopular o formulário

    $announcementType = $_POST['announcement_type'] ?? 'plate';
    $selectedLanguages = $_POST['languages'];
    $textToLog = '';
    
    // --- MAPA DE CONFIGURAÇÃO MULTILINGUE COMPLETO ---
    // Nota: com a ElevenLabs (eleven_multilingual_v2) usamos a mesma voz para todos
    // os idiomas — basta enviar o texto na língua certa.
    $langConfig = [
        'pt' => [
            'plate_text' => "Atenção ao proprietário do veículo %s %s, com a matrícula %s,. Repito, %s. Por favor, dirija-se à receção. Obrigado",
            'child_text' => "Atenção, solicitamos a presença dos pais ou responsáveis da criança %s na receção. Obrigado",
            'person_text' => "Atenção, solicitamos a presença de %s na receção. Obrigado",
            'phoneticMap' => [ 'A'=>'Á','B'=>'Bê','C'=>'Cê','D'=>'Dê','E'=>'É','F'=>'Efe','G'=>'Gê','H'=>'Agá','I'=>'I','J'=>'Jota','K'=>'Cápa','L'=>'Ele','M'=>'Eme','N'=>'Ene','O'=>'Ó','P'=>'Pê','Q'=>'Quê','R'=>'Erre','S'=>'Esse','T'=>'Tê','U'=>'U','V'=>'Vê','W'=>'Dáblio','X'=>'Xis','Y'=>'Ipsílon','Z'=>'Zê' ],
            'numberFormatter' => 'numeroParaExtensoPT'
        ],
        'en' => [
            'plate_text' => "Attention to the owner of the %s %s, with license plate %s,. I repeat, %s. Please proceed to the reception. Thank you",
            'child_text' => "Attention, we request the presence of the parents or guardians of the child %s at the reception. Thank you",
            'person_text' => "Attention, we request the presence of %s at the reception. Thank you"
        ],
        'es' => [
            'plate_text' => "Atención al propietario del vehículo %s %s, con matrícula %s,. Repito, %s. Por favor, diríjase a recepción. Gracias",
            'child_text' => "Atención, solicitamos la presencia de los padres o responsables del niño %s en la recepción. Gracias",
            'person_text' => "Atención, solicitamos la presencia de %s en la recepción. Gracias"
        ],
        'fr' => [
            'plate_text' => "Attention au propriétaire du véhicule %s %s, avec la plaque d'immatriculation %s,. Je répète, %s. Veuillez vous présenter à la réception. Merci",
            'child_text' => "Attention, nous demandons la présence des parents ou tuteurs de l'enfant %s à la réception. Merci",
            'person_text' => "Attention, nous demandons la présence de %s à la réception. Merci"
        ]
    ];

    $SAMPLE_RATE = 22050;
    $CHANNELS    = 1;
    $BITS        = 16;
    $pcmAll = '';

    try {
        foreach ($selectedLanguages as $lang) {
            if (!isset($langConfig[$lang])) continue;
            $config = $langConfig[$lang];
            $textToSpeech = '';

            if ($announcementType === 'plate' && !empty($_POST['license_plate'])) {
                $make = trim($_POST['vehicle_make'] ?? '');
                $model = trim($_POST['vehicle_model'] ?? '');
                $plate = strtoupper(trim($_POST['license_plate']));
                $textToLog = "Anúncio Matrícula: $make $model $plate";

                $plateCleaned = str_replace([' ', '-'], ' ', $plate);
                $spelledParts = [];
                preg_match_all('/([A-Z]+|[0-9]+)/', $plateCleaned, $matches);
                $parts = $matches[0] ?? [];
                foreach ($parts as $part) {
                    if (is_numeric($part) && isset($config['numberFormatter'])) {
                        $spelledParts[] = call_user_func($config['numberFormatter'], (int)$part);
                    } else {
                        $chars = str_split($part);
                        foreach ($chars as $char) { $spelledParts[] = $config['phoneticMap'][$char] ?? $char; }
                    }
                }
                $plateSpelled = implode(', ', $spelledParts);
                $textToSpeech = sprintf($config['plate_text'], $make, $model, $plateSpelled, $plateSpelled);

            } elseif ($announcementType === 'child' && !empty($_POST['child_name'])) {
                $childName = trim($_POST['child_name']);
                $textToLog = "Anúncio Criança: $childName";
                $textToSpeech = sprintf($config['child_text'], $childName);
            
            } elseif ($announcementType === 'person' && !empty($_POST['person_name'])) {
                $personName = trim($_POST['person_name']);
                $textToLog = "Anúncio Chamada: $personName";
                $textToSpeech = sprintf($config['person_text'], $personName);
            }

            if (!empty($textToSpeech)) {
                if ($pcmAll !== '') {
                    // 0.4s de silêncio entre idiomas
                    $pcmAll .= str_repeat("\x00", (int) round(0.4 * $SAMPLE_RATE) * $CHANNELS * ($BITS / 8));
                }
                $pcmAll .= elevenlabs_synthesize_basic($textToSpeech, $SAMPLE_RATE);
            }
        }

        if ($pcmAll === '') { throw new Exception("Não foi possível gerar o áudio. Verifique se preencheu os campos."); }

        $ttsAudioContent = wavBuildFromPcm($pcmAll, $CHANNELS, $SAMPLE_RATE, $BITS);

        $ttsDir = __DIR__ . '/../public/uploads/tts/';
        if (!is_dir($ttsDir)) { mkdir($ttsDir, 0775, true); }
        $fileName = 'tts_multilang_' . uniqid() . '.wav';
        $filePath = $ttsDir . $fileName;
        file_put_contents($filePath, $ttsAudioContent);

        $getID3 = new getID3;
        $fileInfo = $getID3->analyze($filePath);
        $durationSeconds = isset($fileInfo['playtime_seconds']) ? (int)round($fileInfo['playtime_seconds']) : 30;

        $spotifyClient = new SpotifyClient();
        $state = $spotifyClient->getPlaybackState();
        $initialState = ($state && $state->is_playing) ? 'playing' : 'paused';
        //$spotifyClient->pausePlayback();

        $status = [
            'status' => 'play', 'url' => '/uploads/tts/' . $fileName,
            'title' => $textToLog, 'duration' => $durationSeconds,
            'initial_state' => $initialState,
            'has_gong' => true
        ];
        file_put_contents(__DIR__ . '/../public/status.json', json_encode($status));

        $pdo = Database::getInstance();
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (announcement_title, play_type) VALUES (?, 'Manual')");
        $logStmt->execute([$textToLog]);

        header('Location: ../public/index.php?page=tts_announcement&status=success');
        exit();

    } catch (Exception $e) {
        die("Ocorreu um erro ao gerar o anúncio: " . $e->getMessage());
    }
} else {
    header('Location: ../public/index.php?page=tts_announcement');
    exit();
}

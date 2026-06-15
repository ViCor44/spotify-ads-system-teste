<?php
// api/generate_tts_announcement.php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Database;
use App\SpotifyClient;

use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;

use Google\Cloud\Translate\V2\TranslateClient; // <— Translate

// =================== Helpers ===================
function numeroParaExtensoPT($n) {
    if ($n < 0 || $n > 99) return (string)$n;
    $unidades = ["zero","um","dois","três","quatro","cinco","seis","sete","oito","nove"];
    $especiais = ["dez","onze","doze","treze","catorze","quinze","dezasseis","dezassete","dezoito","dezanove"];
    $dezenas = ["","","vinte","trinta","quarenta","cinquenta","sessenta","setenta","oitenta","noventa"];
    if ($n < 10) return $unidades[$n];
    if ($n < 20) return $especiais[$n - 10];
    $dezena = (int) floor($n / 10);
    $unidade = $n % 10;
    return $dezenas[$dezena] . ($unidade > 0 ? " e " . $unidades[$unidade] : "");
}

/** Silêncio PCM (tamanho em segundos) */
function pcmSilence(float $seconds, int $sampleRate=22050, int $channels=1, int $bits=16): string {
    $bytesPerSample = (int) ($bits / 8);
    $numFrames = (int) round($seconds * $sampleRate);
    return str_repeat("\x00", $numFrames * $channels * $bytesPerSample);
}

/**
 * Extrai PCM (chunk "data") e metadados básicos de um WAV (RIFF) em binário.
 * Retorna ['pcm' => (string), 'channels'=>int, 'sampleRate'=>int, 'bits'=>int]
 */
function wavExtractPcm(string $wavBinary): array {
    if (strlen($wavBinary) < 44 || substr($wavBinary,0,4)!=='RIFF' || substr($wavBinary,8,4)!=='WAVE') {
        throw new Exception("Conteúdo WAV inválido.");
    }
    $offset = 12; // após "RIFFxxxxWAVE"
    $channels = 1; $sampleRate = 22050; $bits = 16;
    $pcm = null;

    while ($offset + 8 <= strlen($wavBinary)) {
        $chunkId  = substr($wavBinary, $offset, 4);
        $chunkLen = unpack('V', substr($wavBinary, $offset+4, 4))[1];
        $offset  += 8;

        if ($chunkId === 'fmt ') {
            $fmt = substr($wavBinary, $offset, $chunkLen);
            $audioFormat   = unpack('v', substr($fmt, 0, 2))[1];
            $channels      = unpack('v', substr($fmt, 2, 2))[1];
            $sampleRate    = unpack('V', substr($fmt, 4, 4))[1];
            $bits          = unpack('v', substr($fmt, 14, 2))[1];
            if ($audioFormat !== 1) {
                throw new Exception("WAV não-PCM: formato {$audioFormat}.");
            }
        } elseif ($chunkId === 'data') {
            $pcm = substr($wavBinary, $offset, $chunkLen);
        }

        $offset += $chunkLen + ($chunkLen % 2);
    }

    if ($pcm === null) throw new Exception("Chunk 'data' não encontrado no WAV.");
    return ['pcm'=>$pcm,'channels'=>$channels,'sampleRate'=>$sampleRate,'bits'=>$bits];
}

/** Constrói um WAV (RIFF) a partir de PCM + parâmetros. */
function wavBuildFromPcm(string $pcm, int $channels, int $sampleRate, int $bits): string {
    $byteRate   = (int)($sampleRate * $channels * ($bits/8));
    $blockAlign = (int)($channels * ($bits/8));
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

/** Carrega credenciais Google uma única vez (TTS e Translate) */
function ensureGoogleCreds(): void {
    static $done = false;
    if ($done) return;
    $saPath = __DIR__ . '/../config/google-tts-sa.json';
    if (!file_exists($saPath)) throw new Exception("Credenciais Google TTS/Translate não encontradas: " . $saPath);
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $saPath);
    $done = true;
}

/** Tradução via Google Cloud Translate v2. Devolve o original se falhar. (DEBUG em /public/translate_debug.json) */
function gtranslate_text(string $text, string $target, ?string $source = null): string {
    if ($text === '' || $target === '') return $text;
    if (!class_exists(\Google\Cloud\Translate\V2\TranslateClient::class)) {
        // Lib não instalada — registo mínimo
        @file_put_contents(__DIR__.'/../public/translate_debug.json', json_encode(['error'=>'TranslateClient class not found'], JSON_PRETTY_PRINT));
        return $text;
    }
    ensureGoogleCreds();

    static $client = null;
    if ($client === null) $client = new TranslateClient();

    $opts = ['target' => $target, 'format' => 'text'];
    if (!empty($source)) $opts['source'] = $source;

    try {
        $res = $client->translate($text, $opts);

        // DEBUG: request/response
        @file_put_contents(
            __DIR__.'/../public/translate_debug.json',
            json_encode(['request' => ['text'=>$text,'target'=>$target,'source'=>$source], 'response'=>$res], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        );

        return isset($res['text'])
            ? html_entity_decode($res['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $text;
    } catch (\Throwable $e) {
        @file_put_contents(
            __DIR__.'/../public/translate_debug.json',
            json_encode(['request'=>['text'=>$text,'target'=>$target,'source'=>$source], 'error'=>$e->getMessage()], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        );
        return $text;
    }
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
    $customGong = !empty($_POST['custom_gong']);
    $playGong   = !empty($_POST['custom_gong']);

    // Config de línguas e vozes (Google TTS) + rate/pitch por idioma
    $langConfig = [
        'pt' => [
            'code'  => 'pt-PT',
            'voice' => 'pt-PT-Wavenet-F',
            'rate'  => 1.00,
            'pitch' => -5.0,
            'plate_text'  => "Atenção ao proprietário do veículo %s %s, com a matrícula %s. Repito, matrícula %s. Por favor, dirija-se à receção. Obrigado",
            'child_text'  => "Atenção, solicitamos a presença dos pais ou responsáveis da criança, %s, repito, %s, junto à receção. Obrigado",
            'person_text' => "Atenção, solicitamos a presença de %s, repito, %s, junto à receção. Obrigado",
            'phoneticMap' => [ 'A'=>'Á','B'=>'Bê','C'=>'Cê','D'=>'Dê','E'=>'É','F'=>'Efe','G'=>'Gê','H'=>'Agá','I'=>'I','J'=>'Jota','K'=>'Cápa','L'=>'Ele','M'=>'Eme','N'=>'Ene','O'=>'Ó','P'=>'Pê','Q'=>'Quê','R'=>'Erre','S'=>'Esse','T'=>'Tê','U'=>'U','V'=>'Vê','W'=>'Dáblio','X'=>'Xis','Y'=>'Ipsílon','Z'=>'Zê' ],
            'numberFormatter' => 'numeroParaExtensoPT'
        ],
        'en' => [
            'code'  => 'en-GB',
            'voice' => 'en-GB-Wavenet-A',
            'rate'  => 1.00,
            'pitch' => 0.0,
            'plate_text'  => "Attention to the owner of the %s %s, with license plate %s. I repeat, %s. Please proceed to the reception. Thank you",
            'child_text'  => "Attention, we request the presence of the parents or guardians of the child %s. I repeat, %s. at the reception. Thank you",
            'person_text' => "Attention, we request the presence of %s. I repeat, %s. at the reception. Thank you"
        ],
        'es' => [
            'code'  => 'es-ES',
            'voice' => 'es-ES-Wavenet-F',
            'rate'  => 0.90,
            'pitch' => 0.0,
            'plate_text'  => "Atención al propietario del vehículo %s %s, con matrícula %s. Repito, %s. Por favor, diríjase a recepción. Gracias",
            'child_text'  => "Atención, solicitamos la presencia de los padres o responsables del niño %s. Repito, %s. en la recepción. Gracias",
            'person_text' => "Atención, solicitamos la presencia de %s. Repito, %s. en la recepción. Gracias"
        ],
        'fr' => [
            'code'  => 'fr-FR',
            'voice' => 'fr-FR-Wavenet-F',
            'rate'  => 0.98,
            'pitch' => -0.5,
            'plate_text'  => "Attention au propriétaire du véhicule %s %s, avec la plaque d'immatriculation %s. Je répète, %s. Veuillez vous présenter à la réception. Merci",
            'child_text'  => "Attention, nous demandons la présence des parents ou tuteurs de l'enfant %s. Je répète, %s. à la réception. Merci",
            'person_text' => "Attention, nous demandons la présence de %s. Je répète, %s. à la réception. Merci"
        ],
    ];

    // Alvos para Translate (ISO-639-1)
    $translateTarget = ['pt'=>'pt','en'=>'en','es'=>'es','fr'=>'fr'];

    // Parâmetros de áudio uniformes (para concatenar)
    $SAMPLE_RATE = 22050;
    $CHANNELS    = 1;
    $BITS        = 16;
    $SILENCE_SEC = 0.40; // pausa entre idiomas

    // Construção dos textos por idioma (também serve para cache)
    $segments = []; // cada item: ['lang','code','voice','rate','pitch','text']
    $textToLog = '';

    try {
        ensureGoogleCreds();

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
                $translated = gtranslate_text($customText, $target, null);
                $textToSpeech = $translated;
            }

            if ($textToSpeech !== '') {
                $segments[] = [
                    'lang'  => $lang,
                    'code'  => $cfg['code'],
                    'voice' => $cfg['voice'] ?? null,
                    'rate'  => $cfg['rate'] ?? 0.95,
                    'pitch' => $cfg['pitch'] ?? -1.0,
                    'text'  => $textToSpeech,
                ];
            }
        }

        if (empty($segments)) {
            die("Ocorreu um erro ao gerar o anúncio: dados insuficientes.");
        }

        // =================== Cache ===================
        $cacheKeyBase = [
            'announcementType' => $announcementType,
            'customGong' => $playGong,
            'segments' => array_map(function($s){
                return [
                    'code'  => $s['code'],
                    'voice' => $s['voice'],
                    'rate'  => $s['rate'],
                    'pitch' => $s['pitch'],
                    'text'  => $s['text'],
                ];
            }, $segments),
            'audio' => [
                'sr' => $SAMPLE_RATE,
                'ch' => $CHANNELS,
                'bits' => $BITS,
                'silence' => $SILENCE_SEC,
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
            $ttsClient = new TextToSpeechClient();

            $pcmParts = [];

            // Gongo no início (se marcado e existir)
            $gongPathWav = __DIR__ . '/../public/assets/gong.wav';
            if ($playGong && file_exists($gongPathWav)) {
                $gongMeta = wavExtractPcm(file_get_contents($gongPathWav));
                // Não vamos forçar resample: assume 22.05k/mono/16bit para o gong.wav
                if ($gongMeta['sampleRate'] != $SAMPLE_RATE || $gongMeta['channels'] != $CHANNELS || $gongMeta['bits'] != $BITS) {
                    // Em produção: idealmente ter o gong.wav já no mesmo formato
                    // Aqui apenas anexamos; se formato diferir pode tocar acelerado/lento.
                }
                $pcmParts[] = $gongMeta['pcm'];
                $pcmParts[] = pcmSilence(0.30, $SAMPLE_RATE, $CHANNELS, $BITS);
            }

            $lastIdx = count($segments) - 1;
            foreach ($segments as $i => $s) {
                $input = (new SynthesisInput())->setText($s['text']); // texto simples; se quiseres SSML, troca para setSsml()
                $voice = (new VoiceSelectionParams())->setLanguageCode($s['code'] ?? 'en-GB');
                if (!empty($s['voice'])) $voice->setName($s['voice']);
                $audioConfig = (new AudioConfig())
                    ->setAudioEncoding(AudioEncoding::LINEAR16)
                    ->setSampleRateHertz($SAMPLE_RATE)
                    ->setSpeakingRate($s['rate'])
                    ->setPitch($s['pitch']);
                $resp = $ttsClient->synthesizeSpeech($input, $voice, $audioConfig);

                $wavBinary = $resp->getAudioContent();
                $meta = wavExtractPcm($wavBinary);

                // Verificação leve de formato (em build real, poderias normalizar/resample)
                if ($meta['channels'] != $CHANNELS || $meta['sampleRate'] != $SAMPLE_RATE || $meta['bits'] != $BITS) {
                    throw new Exception("Parâmetros de áudio não uniformes na língua {$s['lang']}.");
                }

                $pcmParts[] = $meta['pcm'];

                if ($i !== $lastIdx && $SILENCE_SEC > 0) {
                    $pcmParts[] = pcmSilence($SILENCE_SEC, $SAMPLE_RATE, $CHANNELS, $BITS);
                }
            }

            if (method_exists($ttsClient, 'close')) $ttsClient->close();

            $pcmAll = implode('', $pcmParts);
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
            'has_gong'       => $playGong, // <— só toca gong quando marcado
        ];
        file_put_contents(__DIR__ . '/../public/status.json', json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

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

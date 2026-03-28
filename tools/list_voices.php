<?php
// tools/list_voices.php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender;

// === CONFIG ===
$serviceAccountPath = __DIR__ . '/../config/google-tts-sa.json';
if (file_exists($serviceAccountPath)) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $serviceAccountPath);
}

// --- Helpers ---
function genderToStr(int $g): string {
    switch ($g) {
        case SsmlVoiceGender::MALE: return 'MALE';
        case SsmlVoiceGender::FEMALE: return 'FEMALE';
        case SsmlVoiceGender::NEUTRAL: return 'NEUTRAL';
        default: return 'UNSPECIFIED';
    }
}
function nameHint(string $name): string {
    foreach (['Neural2','Wavenet','Standard','Studio','Polyglot'] as $t) {
        if (stripos($name, $t) !== false) return $t;
    }
    return '';
}
function isCli(): bool { return PHP_SAPI === 'cli'; }
function toArray($maybeRepeated) : array {
    if (is_array($maybeRepeated)) return $maybeRepeated;
    if ($maybeRepeated instanceof Traversable) return iterator_to_array($maybeRepeated);
    return (array)$maybeRepeated;
}

// --- CLI args / Query params ---
$lang = null; $outCsv = false; $outJson = false;

if (isCli()) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--lang=') === 0)   { $lang = substr($arg, 7); }
        if ($arg === '--csv')  { $outCsv  = true; }
        if ($arg === '--json') { $outJson = true; }
    }
} else {
    $lang   = isset($_GET['lang']) ? trim((string)$_GET['lang']) : null;
    $format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : '';
    $outCsv  = ($format === 'csv');
    $outJson = ($format === 'json');
}

try {
    $client = new TextToSpeechClient();

    $resp = $lang
        ? $client->listVoices(['languageCode' => $lang])
        : $client->listVoices();

    $voices = $resp->getVoices();
    $rows = [];

    foreach ($voices as $v) {
        // getLanguageCodes() -> RepeatedField (converter para array)
        $langs = toArray($v->getLanguageCodes());
        $rows[] = [
            'languages'  => implode(',', $langs),
            'name'       => $v->getName(),
            'gender'     => genderToStr($v->getSsmlGender()),
            'sampleRate' => (int)$v->getNaturalSampleRateHertz(),
            'hint'       => nameHint($v->getName()),
        ];
    }

    usort($rows, function ($a, $b) {
        $la = explode(',', $a['languages'])[0] ?? '';
        $lb = explode(',', $b['languages'])[0] ?? '';
        return [$la, $a['name']] <=> [$lb, $b['name']];
    });

    if ($outJson) {
        if (!isCli()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'filter_language' => $lang,
            'count' => count($rows),
            'voices' => $rows
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($outCsv) {
        if (!isCli()) header('Content-Type: text/csv; charset=utf-8');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['languages','name','gender','sampleRate','hint']);
        foreach ($rows as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }

    if (isCli()) {
        // Tabela simples no terminal (lida bem com lista vazia)
        $w1 = max(9, ...array_map(fn($r)=>strlen($r['languages']), $rows ?: [['languages'=>'']]));
        $w2 = max(4, ...array_map(fn($r)=>strlen($r['name']),      $rows ?: [['name'=>'']]));
        $w3 = 8; $w4 = 10; $w5 = 8;

        printf("Voices%s\n", $lang ? " (lang={$lang})" : "");
        printf("%-{$w1}s  %-{$w2}s  %-{$w3}s  %-{$w4}s  %-{$w5}s\n", 'languages','name','gender','sampleRate','hint');
        printf("%'-{$w1}s  %'-{$w2}s  %'-{$w3}s  %'-{$w4}s  %'-{$w5}s\n", '', '', '', '', '');
        foreach ($rows as $r) {
            printf(
                "%-{$w1}s  %-{$w2}s  %-{$w3}s  %-{$w4}d  %-{$w5}s\n",
                $r['languages'], $r['name'], $r['gender'], (int)$r['sampleRate'], $r['hint']
            );
        }
        exit;
    }

    // HTML
    ?>
    <!doctype html>
    <html lang="pt">
    <head>
      <meta charset="utf-8">
      <title>Google TTS — Lista de Vozes<?= $lang ? " ({$lang})" : "" ?></title>
      <style>
        body { font-family: system-ui, Arial, sans-serif; padding: 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 14px; }
        th { background: #f5f5f5; text-align: left; }
        tr:nth-child(even){ background: #fafafa; }
        .controls { margin-bottom: 12px; }
        input, select, button { padding: 6px 10px; }
      </style>
    </head>
    <body>
      <h1>Google TTS — Lista de Vozes <?= $lang ? "({$lang})" : "" ?></h1>
      <div class="controls">
        <form method="get">
          <label>Idioma (ex. pt-PT, en-GB): </label>
          <input type="text" name="lang" value="<?= htmlspecialchars((string)$lang) ?>" />
          <button type="submit">Filtrar</button>
          <a href="?">Limpar</a>
          &nbsp;|&nbsp;
          <a href="?<?= $lang ? 'lang='.urlencode($lang).'&' : '' ?>format=csv">CSV</a>
          &nbsp;|&nbsp;
          <a href="?<?= $lang ? 'lang='.urlencode($lang).'&' : '' ?>format=json">JSON</a>
        </form>
      </div>
      <p><strong>Total:</strong> <?= count($rows) ?></p>
      <table>
        <thead>
          <tr>
            <th>languages</th>
            <th>name</th>
            <th>gender</th>
            <th>sampleRate</th>
            <th>hint</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['languages']) ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= htmlspecialchars($r['gender']) ?></td>
            <td><?= (int)$r['sampleRate'] ?></td>
            <td><?= htmlspecialchars($r['hint']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </body>
    </html>
    <?php

} catch (Throwable $e) {
    if (isCli()) {
        fwrite(STDERR, "Erro: " . $e->getMessage() . PHP_EOL);
        exit(1);
    } else {
        http_response_code(500);
        echo "<pre>Erro: " . htmlspecialchars($e->getMessage()) . "</pre>";
    }
}

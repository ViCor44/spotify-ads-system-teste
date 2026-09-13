<?php

if (PHP_SAPI !== 'cli') {
    exit("Acesso negado.\n");
}

$root = dirname(__DIR__);
$checkOnly = in_array('--check', $argv, true);
$errors = [];

function result(string $label, bool $ok, string $detail = ''): void
{
    echo ($ok ? '[OK]   ' : '[ERRO] ') . $label;
    if ($detail !== '') {
        echo ': ' . $detail;
    }
    echo PHP_EOL;
}

result('PHP 8.0 ou superior', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $errors[] = 'A versão do PHP é demasiado antiga.';
}

foreach (['curl', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo_mysql'] as $extension) {
    $loaded = extension_loaded($extension);
    result("Extensão PHP $extension", $loaded);
    if (!$loaded) {
        $errors[] = "Falta a extensão PHP $extension.";
    }
}

$requiredFiles = [
    'Configuração' => $root . '/config/database.php',
    'Dependências Composer' => $root . '/vendor/autoload.php',
    'Esquema da base de dados' => $root . '/database/schema.sql',
];
foreach ($requiredFiles as $label => $path) {
    $exists = is_file($path);
    result($label, $exists, $exists ? '' : $path);
    if (!$exists) {
        $errors[] = "$label em falta.";
    }
}

if ($errors) {
    exit(1);
}

require_once $root . '/config/database.php';

foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'SPOTIFY_CLIENT_ID', 'SPOTIFY_CLIENT_SECRET', 'SPOTIFY_REDIRECT_URI', 'ELEVENLABS_API_KEY'] as $constant) {
    $configured = defined($constant) && ($constant === 'DB_PASS' || (string) constant($constant) !== '')
        && !str_contains((string) constant($constant), 'CHANGE_ME');
    result("Configuração $constant", $configured);
    if (!$configured) {
        $errors[] = "$constant não está configurado.";
    }
}

if ($errors) {
    exit(1);
}

try {
    $databaseName = (string) DB_NAME;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
        throw new RuntimeException('DB_NAME só pode conter letras, números e underscore.');
    }

    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    result('Ligação ao MySQL', true, DB_HOST);

    if (!$checkOnly) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$databaseName`");
        $schema = (string) file_get_contents($root . '/database/schema.sql');
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema, -1, PREG_SPLIT_NO_EMPTY) as $statement) {
            $pdo->exec($statement);
        }
        result('Estrutura da base de dados', true, 'criada ou já existente');
    } else {
        $pdo->exec("USE `$databaseName`");
    }

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['announcements', 'schedules', 'activity_logs', 'spotify_tokens'] as $table) {
        $exists = in_array($table, $tables, true);
        result("Tabela $table", $exists);
        if (!$exists) {
            $errors[] = "Tabela $table em falta.";
        }
    }
} catch (Throwable $exception) {
    result('Base de dados', false, $exception->getMessage());
    $errors[] = 'Não foi possível preparar a base de dados.';
}

foreach (['public/uploads', 'public/uploads/tts', 'storage', 'storage/tmp_tts'] as $directory) {
    $path = $root . '/' . $directory;
    if (!$checkOnly && !is_dir($path)) {
        mkdir($path, 0775, true);
    }
    $writable = is_dir($path) && is_writable($path);
    result("Pasta gravável $directory", $writable);
    if (!$writable) {
        $errors[] = "A pasta $directory não está disponível para escrita.";
    }
}

if (!$checkOnly && !$errors) {
    require_once $root . '/vendor/autoload.php';
    define('SPOT_MASTER_INIT', true);
    require $root . '/init.php';
    result('Dados iniciais', true);
}

echo PHP_EOL . ($errors ? 'Diagnóstico concluído com erros.' : 'Sistema pronto.') . PHP_EOL;
exit($errors ? 1 : 0);
<?php
require __DIR__ . '/vendor/autoload.php';

use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;

echo "Autoload carregado\n";

echo "FQN: " . TextToSpeechClient::class . "\n";
echo class_exists(TextToSpeechClient::class) ? "Classe encontrada ✅\n" : "Classe NÃO encontrada ❌\n";

// Teste rápido de autenticação (ajusta o caminho):
$sa = __DIR__ . '/config/google-tts-sa.json';
if (!file_exists($sa)) {
    echo "Credenciais não encontradas em $sa (salta o teste de API)\n";
    exit;
}
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $sa);
try {
    $client = new TextToSpeechClient();
    echo "Cliente TTS criado ✅\n";
    if (method_exists($client, 'close')) $client->close();
} catch (Throwable $e) {
    echo "Falha ao criar cliente TTS: " . $e->getMessage() . "\n";
}

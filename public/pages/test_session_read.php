<?php
// public/pages/test_session_read.php
// A sessão já foi iniciada no index.php, por isso não precisamos de session_start() aqui.

echo "<h1>Teste de Leitura de Sessão</h1>";

// Vamos ver o que está dentro da variável $_SESSION
echo "<pre style='background-color: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
echo "Conteúdo completo de \$_SESSION:<br>";
var_dump($_SESSION);
echo "</pre>";

$message = $_SESSION['test_message'] ?? null;

echo "<h2>Resultado do Teste:</h2>";

if ($message) {
    echo "<p style='font-size: 1.2em; font-weight: bold; color: green;'>" . htmlspecialchars($message) . "</p>";
} else {
    echo "<p style='font-size: 1.2em; font-weight: bold; color: red;'>ERRO: A MENSAGEM DE TESTE NÃO FOI ENCONTRADA NA SESSÃO.</p>";
}

// Limpa a variável de teste da sessão para não interferir com testes futuros
unset($_SESSION['test_message']);
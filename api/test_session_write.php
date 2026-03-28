<?php
// api/test_session_write.php
session_start();

$_SESSION['test_message'] = "A sessão está a funcionar! Hora do teste: " . date('H:i:s');

// Redireciona para a nossa página de leitura de teste
header('Location: ../public/index.php?page=test_read');
exit();
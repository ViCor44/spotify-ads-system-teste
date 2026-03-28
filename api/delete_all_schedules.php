<?php
// api/delete_all_schedules.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::getInstance();

        // TRUNCATE TABLE é mais eficiente que DELETE para apagar todas as linhas de uma tabela.
        // Ele também reinicia o contador de auto-incremento.
        $pdo->exec("TRUNCATE TABLE schedules");

        header('Location: ../public/index.php?page=manage_schedules&action=list&status=all_deleted');
        exit();

    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Erro ao apagar todos os agendamentos: " . $e->getMessage();
        header('Location: ../public/index.php?page=manage_schedules&action=list');
        exit();
    }
} else {
    // Se o script for acedido de forma incorreta, redireciona para o dashboard
    header('Location: ../public/index.php?page=dashboard');
    exit();
}

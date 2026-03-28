<?php
// api/toggle_schedule.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $scheduleId = (int)$_GET['id'];
    try {
        $pdo = Database::getInstance();
        // A magia está no `is_active = NOT is_active`, que inverte o valor booleano (0 para 1, 1 para 0)
        $sql = "UPDATE schedules SET is_active = NOT is_active WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$scheduleId]);

        header('Location: ../public/index.php?page=manage_schedules&action=list&status=toggled');
        exit();

    } catch (Exception $e) {
        die("Erro ao alterar o estado do agendamento: " . $e->getMessage());
    }
}
header('Location: ../public/index.php?page=dashboard');
exit();
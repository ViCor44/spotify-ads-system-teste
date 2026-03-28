<?php
// api/delete_schedule.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if (isset($_GET['id'])) {
    try {
        $pdo = Database::getInstance();
        $sql = "DELETE FROM schedules WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_GET['id']]);
        header('Location: ../public/index.php?page=manage_schedules&status=deleted');
        exit();
    } catch (Exception $e) {
        die("Erro ao apagar o agendamento: " . $e->getMessage());
    }
}
header('Location: ../public/index.php?page=dashboard');
exit();
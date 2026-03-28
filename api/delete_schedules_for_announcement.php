<?php
// api/delete_schedules_for_announcement.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['announcement_id'])) {
    $announcementId = (int)$_POST['announcement_id'];

    try {
        $pdo = Database::getInstance();

        $sql = "DELETE FROM schedules WHERE announcement_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$announcementId]);

        header('Location: ../public/index.php?page=manage_schedules&action=list&status=group_deleted');
        exit();

    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Erro ao apagar os agendamentos do anúncio: " . $e->getMessage();
        header('Location: ../public/index.php?page=manage_schedules&action=list');
        exit();
    }
} else {
    header('Location: ../public/index.php?page=dashboard');
    exit();
}

<?php
// api/delete_closing_schedules.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::getInstance();

        // Obtém os IDs dos anúncios de fecho de forma segura
        $closingTitles = "'Fecho - 15 minutos', 'Fecho - 10 minutos', 'Fecho - 5 minutos', 'Fecho - Parque Fechado'";
        $stmtSelect = $pdo->query("SELECT id FROM announcements WHERE title IN ($closingTitles)");
        $idsToDelete = $stmtSelect->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($idsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            $sqlDelete = "DELETE FROM schedules WHERE announcement_id IN ($placeholders)";
            $stmtDelete = $pdo->prepare($sqlDelete);
            $stmtDelete->execute($idsToDelete);
        }

        header('Location: ../public/index.php?page=manage_schedules&action=list&status=closing_deleted');
        exit();

    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Erro ao apagar os agendamentos de fecho: " . $e->getMessage();
        header('Location: ../public/index.php?page=manage_schedules&action=list');
        exit();
    }
} else {
    header('Location: ../public/index.php?page=dashboard');
    exit();
}

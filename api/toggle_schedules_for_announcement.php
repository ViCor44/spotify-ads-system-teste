<?php
// api/toggle_schedules_for_announcement.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['action'])) {
    $announcementId = (int)$_GET['id'];
    $action = $_GET['action'];

    // Determina se vamos ativar (1) ou desativar (0)
    $newState = ($action === 'activate') ? 1 : 0;

    try {
        $pdo = Database::getInstance();
        
        // Atualiza o estado de TODOS os agendamentos para este anúncio específico
        $sql = "UPDATE schedules SET is_active = ? WHERE announcement_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newState, $announcementId]);

        header('Location: ../public/index.php?page=manage_schedules&action=list&status=group_toggled');
        exit();

    } catch (Exception $e) {
        die("Erro ao alterar o estado dos agendamentos: " . $e->getMessage());
    }
}
header('Location: ../public/index.php?page=manage_schedules');
exit();

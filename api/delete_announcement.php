<?php
// api/delete_announcement.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $announcementId = (int)$_GET['id'];
    
    try {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        // 1. Obter o nome do ficheiro ANTES de apagar o registo
        $stmtSelect = $pdo->prepare("SELECT file_path FROM announcements WHERE id = ?");
        $stmtSelect->execute([$announcementId]);
        $announcement = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$announcement) {
            throw new Exception("Anúncio não encontrado.");
        }
        $fileName = $announcement['file_path'];

        // 2. Apagar o registo do anúncio da base de dados
        // Nota: Graças ao "ON DELETE CASCADE" na nossa tabela `schedules`, 
        // todos os agendamentos associados a este anúncio serão apagados automaticamente.
        $stmtDelete = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmtDelete->execute([$announcementId]);

        // 3. Apagar o ficheiro de áudio do servidor
        $filePath = __DIR__ . '/../public/uploads/' . $fileName;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // 4. Confirmar as alterações
        $pdo->commit();

        header('Location: ../public/index.php?page=manage_announcements&status=deleted');
        exit();

    } catch (Exception $e) {
        // Se algo correr mal, desfaz as alterações na base de dados
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Erro ao apagar o anúncio: " . $e->getMessage());
    }
} else {
    // Redireciona se o ID for inválido
    header('Location: ../public/index.php?page=manage_announcements');
    exit();
}
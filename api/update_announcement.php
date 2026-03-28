<?php
// api/update_announcement.php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;
use getID3; // Importa a biblioteca para ler a duração

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['announcement_id'], $_POST['title'])) {
        $announcementId = (int)$_POST['announcement_id'];
        $newTitle = trim($_POST['title']);

        if (empty($newTitle)) {
            die("O título não pode estar em branco.");
        }

        try {
            $pdo = Database::getInstance();
            
            // Verifica se foi enviado um novo ficheiro de áudio
            $hasNewFile = isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK;

            if ($hasNewFile) {
                // --- LÓGICA PARA QUANDO HÁ UM NOVO FICHEIRO ---

                // 1. Processa o novo ficheiro
                $audioFile = $_FILES['audio_file'];
                $uploadDir = __DIR__ . '/../public/uploads/';
                $newFileName = uniqid() . '-' . basename($audioFile['name']);
                $newFilePath = $uploadDir . $newFileName;

                if (!move_uploaded_file($audioFile['tmp_name'], $newFilePath)) {
                    throw new Exception("Erro ao mover o novo ficheiro de áudio.");
                }

                // 2. Calcula a duração do novo ficheiro
                $getID3 = new getID3;
                $fileInfo = $getID3->analyze($newFilePath);
                $newDuration = isset($fileInfo['playtime_seconds']) ? (int)round($fileInfo['playtime_seconds']) : 0;
                
                // 3. Obtém o nome do ficheiro antigo para o apagar
                $stmtSelect = $pdo->prepare("SELECT file_path FROM announcements WHERE id = ?");
                $stmtSelect->execute([$announcementId]);
                $oldFileName = $stmtSelect->fetchColumn();

                // 4. Atualiza a base de dados com TODA a nova informação
                $sql = "UPDATE announcements SET title = ?, file_path = ?, duration_seconds = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newTitle, $newFileName, $newDuration, $announcementId]);

                // 5. Apaga o ficheiro de áudio antigo do servidor
                if ($oldFileName) {
                    $oldFilePath = $uploadDir . $oldFileName;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

            } else {
                // --- LÓGICA PARA QUANDO NÃO HÁ NOVO FICHEIRO ---
                // Atualiza apenas o título
                $sql = "UPDATE announcements SET title = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newTitle, $announcementId]);
            }

            header('Location: ../public/index.php?page=manage_announcements&status=updated');
            exit();

        } catch (Exception $e) {
            die("Erro ao atualizar o anúncio: " . $e->getMessage());
        }
    }
}
header('Location: ../public/index.php?page=dashboard');
exit();
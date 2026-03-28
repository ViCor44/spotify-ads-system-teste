<?php
// public/pages/edit_announcement.php

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de anúncio inválido.");
}
$announcementId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT title, file_path FROM announcements WHERE id = ?");
    $stmt->execute([$announcementId]);
    $announcementToEdit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$announcementToEdit) {
        die("Anúncio não encontrado.");
    }
} catch (Exception $e) {
    die("Erro ao carregar o anúncio para edição: " . $e->getMessage());
}

// Verifica se este é um anúncio de sistema protegido
$protectedTitles = ['Fecho - 15 minutos', 'Fecho - 10 minutos', 'Fecho - 5 minutos', 'Fecho - Parque Fechado'];
$isProtected = in_array($announcementToEdit['title'], $protectedTitles);

?>

<h1><i class="fa-solid fa-pencil"></i> Editar Anúncio</h1>

<div class="box">
    <form action="../api/update_announcement.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="announcement_id" value="<?= $announcementId ?>">

        <label for="title">Título do Anúncio:</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($announcementToEdit['title']) ?>" 
               <?= $isProtected ? 'readonly' : '' ?> required>

        <?php if ($isProtected): ?>
            <p style="font-size: 0.9em; color: #6c757d; margin-top: -10px;">
                <i class="fa-solid fa-lock"></i> Este é um anúncio de sistema. O título não pode ser alterado.
            </p>
        <?php endif; ?>

        <p style="font-size: 0.9em; color: #6c757d; margin-top: 10px;">Ficheiro de áudio atual: <code><?= htmlspecialchars($announcementToEdit['file_path']) ?></code></p>
        
        <label for="audio_file">Substituir Ficheiro de Áudio (Opcional):</label>
        <input type="file" id="audio_file" name="audio_file" accept=".mp3,.wav">
        <p style="font-size: 0.9em; color: #6c757d; margin-top: -10px; margin-bottom: 20px;">Carregue um novo ficheiro para substituir o atual.</p>

        <button type="submit">Guardar Alterações</button>
        <a href="index.php?page=manage_announcements" style="margin-left: 15px; text-decoration: none; color: #6c757d;">Cancelar</a>
    </form>
</div>
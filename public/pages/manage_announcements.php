<?php
// public/pages/manage_announcements.php
?>
<h1><i class="fa-solid fa-upload"></i> Gerir Anúncios</h1>

<div class="manage-layout">
    
    <div class="box">
        <h2>Carregar Novo Anúncio</h2>
        <form action="../api/upload_announcement.php" method="post" enctype="multipart/form-data">
            <label for="title">Título do Anúncio:</label>
            <input type="text" id="title" name="title" required>

            <label for="audio_file">Ficheiro de Áudio (MP3, WAV):</label>
            <input type="file" id="audio_file" name="audio_file" accept=".mp3,.wav" required>

            <button type="submit">Carregar Anúncio</button>
        </form>
    </div>

    <div class="box">
        <h2>Anúncios Existentes</h2>
        <?php if (empty($announcements)): ?>
            <p>Ainda não foram carregados anúncios.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Duração</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td><?= htmlspecialchars($announcement['title']) ?></td>
                            <td>
                                <?php 
                                    // Formata os segundos para Minutos:Segundos
                                    if ($announcement['duration_seconds']) {
                                        echo floor($announcement['duration_seconds'] / 60) . ':' . str_pad($announcement['duration_seconds'] % 60, 2, '0', STR_PAD_LEFT);
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td style="text-align: right;" class="actions-cell">
                                <a href="index.php?page=edit_announcement&id=<?= $announcement['id'] ?>" class="action-btn edit-btn" title="Editar Anúncio">
                                    <i class="fa-solid fa-pencil"></i> Editar
                                </a>
                                <a href="../api/delete_announcement.php?id=<?= $announcement['id'] ?>" 
                                   class="action-btn delete-btn" 
                                   title="Apagar Anúncio" 
                                   onclick="return confirm('Tem a certeza que quer apagar permanentemente o anúncio \'<?= htmlspecialchars(addslashes($announcement['title'])) ?>\'? Todos os agendamentos associados também serão apagados.');">
                                    <i class="fa-solid fa-trash-can"></i> Apagar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
<?php
// public/pages/play_announcements.php
?>
<h1><i class="fa-solid fa-play"></i> Tocar Anúncios Manualmente</h1>
<p>Clique no botão "Tocar Agora" para pausar a música do Spotify e reproduzir o anúncio imediatamente.</p>

<div class="card-container">
    <?php if (empty($announcements)): ?>
        <p>Não há anúncios para tocar. Carregue um na página "Gerir Anúncios".</p>
    <?php else: ?>
        <?php
        // Define a lista de nomes de anúncios protegidos
        $protectedTitles = [
            'Fecho - 15 minutos',
            'Fecho - 10 minutos',
            'Fecho - 5 minutos',
            'Fecho - Parque Fechado'
        ];

        foreach ($announcements as $announcement):
            // --- NOVA LÓGICA DE VERIFICAÇÃO ---
            $isProtected = in_array($announcement['title'], $protectedTitles);
            // str_starts_with verifica se o nome do ficheiro começa com "silent_"
            $isPlaceholder = isset($announcement['file_path']) && str_starts_with($announcement['file_path'], 'silent_');
            
            $isDisabled = $isProtected && $isPlaceholder;
            // --- FIM DA NOVA LÓGICA ---
        ?>
            <div class="card">
                <h3><?= htmlspecialchars($announcement['title']) ?></h3>
                
                <a href="<?= $isDisabled ? '#' : '../api/play_now.php?id=' . $announcement['id'] ?>" 
                   class="play-button <?= $isDisabled ? 'disabled' : '' ?>"
                   title="<?= $isDisabled ? 'Edite este anúncio e carregue o áudio final para o poder tocar.' : 'Tocar Anúncio Agora' ?>">
                   Tocar Agora
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
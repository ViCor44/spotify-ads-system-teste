<?php
// public/pages/dashboard.php
?>

<!-- Contentor para os alertas dinâmicos -->
<div id="alerts-container"></div>

<!-- Barra de estado superior (agora só mostra os cards quando está ligado) -->
<div class="top-bar-layout">
    <!-- Indicador de Estado Spotify -->
    <div class="status-indicator-wrapper">
        <?php if ($token_exists): ?>
            <div class="status-indicator status-ok">
                <i class="fa-solid fa-check-circle"></i> Ligado ao Spotify
                <a href="../api/disconnect_spotify.php" class="disconnect-btn" title="Desligar a ligação ao Spotify">(Desligar)</a>
            </div>
        <?php else: ?>
            <div class="status-indicator status-error"><i class="fa-solid fa-times-circle"></i> Não ligado. <a href="spotify_authorize.php">Ligar agora</a></div>
        <?php endif; ?>
    </div>
    <!-- Contentor para o card de Próximo Fecho (será preenchido por JS) -->
    <div id="next-closing-card-container">
        <?php if ($nextParkClosing): ?>
        <div class="closing-time-card">
            <i class="fa-solid fa-door-closed"></i>
            <span>Próximo Fecho Agendado: <strong><?= $nextParkClosing['day'] ?> às <?= $nextParkClosing['time'] ?></strong></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- NOVO ALERTA INFORMATIVO PARA QUANDO ESTÁ DESLIGADO -->
<?php if (!$token_exists): ?>
<div class="alert-box alert-danger">
    <div class="alert-content">
        <i class="fa-solid fa-plug-circle-xmark"></i>
        <div>
            <strong>Aplicação Desligada do Spotify</strong>
            <p style="margin: 5px 0 0 0; font-weight: 400; font-size: 0.95em;">
                Enquanto a aplicação não estiver ligada, nenhuma funcionalidade de reprodução (manual ou automática) irá funcionar. A música não será pausada e os anúncios não serão tocados.
            </p>
        </div>
    </div>
</div>
<div style="text-align: center; margin-bottom: 30px;">
     <a href="spotify_authorize.php" class="spotify-link-button">
        <i class="fa-brands fa-spotify"></i> Ligar ao Spotify Agora
    </a>
</div>
<?php endif; ?>

<div class="page-header">
    <h1><i class="fa-solid fa-house"></i> Dashboard</h1>
    <div id="live-clock" class="live-clock">--:--:--</div>
</div>

<!-- GRELHA SUPERIOR DO DASHBOARD (3 COLUNAS) -->
<div class="dashboard-grid-top">
    <!-- Card "A Tocar Agora" -->
    <div id="now-playing-component" class="box" <?= !($playbackState && isset($playbackState->item)) ? 'style="display: none;"' : '' ?>>
        <h2><i class="fa-solid fa-music"></i> A Tocar Agora</h2>
        <div id="now-playing-content" class="now-playing-card">
            <div id="album-art" class="album-art">
                <?php if (!empty($playbackState->item->album->images)): ?>
                    <img id="album-art-img" src="<?= htmlspecialchars($playbackState->item->album->images[0]->url) ?>" alt="Capa" style="width: 100px; height: 100px; border-radius: 8px;">
                <?php else: ?><i class="fa-solid fa-music"></i><?php endif; ?>
            </div>
            <div class="track-info">
                <strong id="track-name"><?= htmlspecialchars($playbackState->item->name ?? '') ?></strong>
                <span id="artist-name"><?= isset($playbackState->item->artists) ? htmlspecialchars(implode(', ', array_column($playbackState->item->artists, 'name'))) : '' ?></span>
                <hr style="border: none; border-top: 1px solid #282828; margin: 15px 0;">
                <div class="track-detail-line"><i class="fa-solid fa-compact-disc fa-fw"></i><strong>Álbum:</strong><span id="album-name"><?= htmlspecialchars($playbackState->item->album->name ?? '') ?></span></div>
                <div class="track-detail-line"><i class="fa-solid fa-computer fa-fw"></i><strong>Dispositivo:</strong><span id="device-name"><?= htmlspecialchars($playbackState->device->name ?? '') ?></span></div>
            </div>
        </div>
    </div>

    <!-- Card "Próximo Anúncio" -->
    <div class="box" id="next-ad-card" data-next-ad-timestamp="<?= $nextAnnouncement['timestamp'] ?? '' ?>">
        <h2 id="next-ad-card-title"><i class="fa-solid fa-forward-step"></i> Próximo Anúncio</h2>
        <div id="next-ad-content">
            <?php if ($nextAnnouncement): ?>
                <div class="next-ad-info">
                    <div class="ad-title"><?= htmlspecialchars($nextAnnouncement['title']) ?></div>
                    <div id="next-ad-time-display">
                        <div class="ad-time" id="next-ad-day"><i class="fa-solid fa-calendar-day"></i> <?= htmlspecialchars($nextAnnouncement['day']) ?></div>
                        <div class="ad-time" id="next-ad-time"><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($nextAnnouncement['time']) ?></div>
                    </div>
                    <div id="countdown-display" class="countdown-container" style="display: none;">Começa em: <span id="countdown-timer">00:00</span></div>
                </div>
            <?php else: ?>
                <p id="no-next-ad" style="text-align: center; color: #6c757d; padding: 20px 0;">Não existem anúncios agendados.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card "Tempo Agora" -->
    <div id="weather-card" class="box" style="display: none;">
        <h2><i class="fa-solid fa-cloud-sun"></i> Tempo Agora</h2>
        <div id="weather-content" class="weather-card-content">
            <div class="loader">A carregar dados do tempo...</div>
        </div>
    </div>
</div>

<!-- GRELHA INFERIOR DO DASHBOARD (2 COLUNAS) -->
<div class="dashboard-grid-bottom">
    <!-- Card "Agenda de Hoje" -->
    <div class="box">
        <h2><i class="fa-solid fa-calendar-day"></i> Agenda de Hoje</h2>
        <div id="todays-agenda-content" class="agenda-list">
            <?php if (empty($todaysAgenda)): ?>
                <p class="empty-state">Não há mais anúncios agendados para hoje.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($todaysAgenda as $item): ?>
                        <li>
                            <span class="agenda-time"><?= date("H:i", strtotime($item['play_at'])) ?></span>
                            <span class="agenda-title"><?= htmlspecialchars($item['title']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card "Atividade Recente" -->
    <div class="box">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Atividade Recente</h2>
        <div id="activity-log-content" class="activity-log">
            <?php if (empty($activityLogs)): ?>
                <p class="empty-state">Nenhuma atividade registada ainda.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($activityLogs as $log): ?>
                        <li>
                            <span class="log-time"><?= date("H:i", strtotime($log['played_at'])) ?></span>
                            <span class="log-title"><?= htmlspecialchars($log['announcement_title']) ?></span>
                            <span class="log-type <?= strtolower($log['play_type']) ?>"><?= htmlspecialchars($log['play_type']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
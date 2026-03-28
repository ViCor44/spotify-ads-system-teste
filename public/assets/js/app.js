document.addEventListener('DOMContentLoaded', function () {
    // --- 1. CONFIGURAÇÃO E VARIÁVEIS GLOBAIS ---
    const rootPath = '/spotify-ads-system-teste'; // Verifique se este é o nome da sua pasta de projeto
    const adPlayer = document.getElementById('adPlayer');
    const gongPlayer = document.getElementById('gongPlayer');
    const audioPrompt = document.getElementById('audioPrompt');
    const enableAudioButton = document.getElementById('enableAudioButton');
    
    let pollingInterval;
    let countdownInterval;
    let liveCountdownInterval;
    let isPlayingAd = false;
    let lastAdInitialState = 'paused';
    let lastAdTitle = null;
    
    // --- 2. FUNÇÕES ---

    // Impede o utilizador de sair da página enquanto um anúncio está a tocar
    const beforeUnloadListener = (event) => {
        event.preventDefault();
        event.returnValue = "Um anúncio está a ser reproduzido. Se sair agora, a música do Spotify pode não ser retomada.";
        return "Um anúncio está a ser reproduzido. Se sair agora, a música do Spotify pode não ser retomada.";
    };

    // Inicia o leitor de anúncios e o polling para o status.json
    function startPlayer() {
        if (!adPlayer) return;
        adPlayer.play().catch(() => {});
        adPlayer.pause();
        if (audioPrompt) audioPrompt.style.display = 'none';
        if (!pollingInterval) pollingInterval = setInterval(checkStatus, 1500);
        console.log('Leitor de anuncios ativado.');
    }

    // Limpa o ficheiro de status no servidor
    const clearStatusFile = () => fetch(`${rootPath}/api/clear_status.php`, { method: 'POST' });

    // Verifica o status.json e toca o anúncio se houver uma ordem
    const checkStatus = async () => {
        if (isPlayingAd) return;
        try {
            const response = await fetch(`${rootPath}/public/status.json?t=${new Date().getTime()}`);
            if (response.ok) {
                const data = await response.json();
                if (data.status === 'play') {
                    
                    isPlayingAd = true;
                    window.addEventListener('beforeunload', beforeUnloadListener);
                    
                    lastAdInitialState = data.initial_state || 'paused';
                    lastAdTitle = data.title || null;

                    const nextAdCardTitle = document.getElementById('next-ad-card-title');
                    const nextAdContent = document.getElementById('next-ad-content');
                    if (nextAdCardTitle && nextAdContent && data.title) {
                        nextAdCardTitle.innerHTML = `<i class="fa-solid fa-wave-square"></i> A Passar Agora`;
                        if (data.duration && data.duration > 0) {
                            let remaining = data.duration;
                            nextAdContent.innerHTML = `<div class="next-ad-info"><div class="ad-title">${data.title}</div><div id="live-countdown" class="countdown-container">Termina em: <span id="live-countdown-timer">00:00</span></div></div>`;
                            const timerSpan = document.getElementById('live-countdown-timer');
                            if (liveCountdownInterval) clearInterval(liveCountdownInterval);
                            liveCountdownInterval = setInterval(() => {
                                if (remaining >= 0) {
                                    const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
                                    const seconds = String(remaining % 60).padStart(2, '0');
                                    if (timerSpan) timerSpan.textContent = `${minutes}:${seconds}`;
                                    remaining--;
                                } else { clearInterval(liveCountdownInterval); }
                            }, 1000);
                        } else {
                            nextAdContent.innerHTML = `<div class="next-ad-info"><div class="ad-title">${data.title}</div></div>`;
                        }
                    }
                    
                    // Lógica de Sequência com Gong
                    if (data.has_gong && gongPlayer) {
                        gongPlayer.play().catch(e => console.error("Erro ao tocar gong:", e));
                        gongPlayer.onended = () => {
                            adPlayer.src = `${rootPath}/public${data.url}`;
                            adPlayer.play().catch(e => {
                                isPlayingAd = false;
                                window.removeEventListener('beforeunload', beforeUnloadListener);
                            });
                        };
                    } else {
                        adPlayer.src = `${rootPath}/public${data.url}`;
                        adPlayer.play().catch(e => {
                            isPlayingAd = false;
                            window.removeEventListener('beforeunload', beforeUnloadListener);
                        });
                    }
                }
            }
        } catch (e) {}
    };

    // Atualiza o card "A Tocar Agora"
    const updateNowPlayingCard = async () => {
        const component = document.getElementById('now-playing-component');
        const content = document.getElementById('now-playing-content');
        if (!component || !content) return;
        try {
            const response = await fetch(`${rootPath}/api/get_playback_state.php?t=${new Date().getTime()}`);
            const data = await response.json();
            if (data && data.item) {
                content.innerHTML = `
                    <div id="album-art" class="album-art">
                        ${(data.item.album.images && data.item.album.images.length > 0) ? `<img id="album-art-img" src="${data.item.album.images[0].url}" alt="Capa" style="width: 100px; height: 100px; border-radius: 8px;">` : `<i class="fa-solid fa-music"></i>`}
                    </div>
                    <div class="track-info">
                        <strong id="track-name">${data.item.name}</strong>
                        <span id="artist-name">${data.item.artists.map(a => a.name).join(', ')}</span>
                        <hr style="border: none; border-top: 1px solid #282828; margin: 15px 0;">
                        <div class="track-detail-line"><i class="fa-solid fa-compact-disc fa-fw"></i><strong>Álbum:</strong><span id="album-name">${data.item.album.name}</span></div>
                        <div class="track-detail-line"><i class="fa-solid fa-computer fa-fw"></i><strong>Dispositivo:</strong><span id="device-name">${data.device.name}</span></div>
                    </div>`;
                component.style.display = 'block';
            } else { component.style.display = 'none'; }
        } catch (error) {}
    };

    // Atualiza o card "Próximo Anúncio"
    const updateNextAnnouncementCard = async () => {
        const card = document.getElementById('next-ad-card');
        if (!card) return;
        try {
            const response = await fetch(`${rootPath}/api/get_next_announcement.php?t=${new Date().getTime()}`);
            const data = await response.json();
            const content = document.getElementById('next-ad-content');
            if (data && data.timestamp) {
                card.setAttribute('data-next-ad-timestamp', data.timestamp);
                content.innerHTML = `<div class="next-ad-info"><div class="ad-title">${data.title}</div><div id="next-ad-time-display"><div class="ad-time" id="next-ad-day"><i class="fa-solid fa-calendar-day"></i> ${data.day}</div><div class="ad-time" id="next-ad-time"><i class="fa-solid fa-clock"></i> ${data.time}</div></div><div id="countdown-display" class="countdown-container" style="display: none;">Começa em: <span id="countdown-timer">00:00</span></div></div>`;
            } else {
                card.setAttribute('data-next-ad-timestamp', '');
                content.innerHTML = `<p style="text-align: center; color: #6c757d; padding: 20px 0;">Não existem anúncios agendados.</p>`;
            }
        } catch (error) {}
    };
    
    // Atualiza o card "Atividade Recente"
    const updateActivityLog = async () => {
        const logContent = document.getElementById('activity-log-content');
        if (!logContent) return;
        try {
            const response = await fetch(`${rootPath}/api/getActivityLog.php?t=${new Date().getTime()}`);
            const logs = await response.json();
            if (logs && logs.length > 0) {
                let logHTML = '<ul>';
                logs.forEach(log => {
                    logHTML += `<li><span class="log-time">${log.time}</span><span class="log-title">${log.announcement_title}</span><span class="log-type ${log.type_class}">${log.play_type}</span></li>`;
                });
                logHTML += '</ul>';
                logContent.innerHTML = logHTML;
            } else {
                logContent.innerHTML = `<p class="empty-state">Nenhuma atividade registada ainda.</p>`;
            }
        } catch (error) {}
    };

    // Atualiza o card do "Tempo Agora"
    const updateWeatherCard = async () => {
        const weatherCard = document.getElementById('weather-card');
        const weatherContent = document.getElementById('weather-content');
        if (!weatherCard || !weatherContent) return;
        try {
            const response = await fetch(`${rootPath}/api/get_weather.php?t=${new Date().getTime()}`);
            if (!response.ok) throw new Error('Falha na resposta da API do tempo');
            const data = await response.json();
            if (data.error) {
                weatherContent.innerHTML = `<p class="status-error">${data.error}</p>`;
            } else {
                weatherContent.innerHTML = `<div class="weather-info"><div class="location">${data.location}</div><div class="description">${data.description}</div></div><div class="weather-temp">${data.temperature}°C</div><div class="weather-icon"><i class="fa-solid ${data.icon}"></i></div>`;
            }
            weatherCard.style.display = 'block';
        } catch (error) {
            console.error("Erro ao obter dados do tempo:", error);
            weatherContent.innerHTML = `<p class="status-error">Não foi possível carregar o estado do tempo.</p>`;
            weatherCard.style.display = 'block';
        }
    };

    // Atualiza os alertas do sistema e o card de Próximo Fecho
    const updateSystemAlerts = async () => {
        const alertsContainer = document.getElementById('alerts-container');
        const nextClosingContainer = document.getElementById('next-closing-card-container');
        if (!alertsContainer || !nextClosingContainer) return;
        try {
            const response = await fetch(`${rootPath}/api/get_system_status.php?t=${new Date().getTime()}`);
            const status = await response.json();
            let alertsHTML = '';
            if (!status.robotStatusOk) {
                alertsHTML += `<div class="alert-box alert-danger"><div class="alert-content"><i class="fa-solid fa-heart-pulse"></i><span><strong>CRÍTICO:</strong> O "Spot Master Robot" está offline.</span></div></div>`;
            }
            if (!status.closingScheduleExists) {
                alertsHTML += `<div class="alert-box alert-warning"><div class="alert-content"><i class="fa-solid fa-triangle-exclamation"></i><span><strong>Aviso:</strong> Não existe agendamento de fecho.</span></div></div>`;
            }
            if (status.placeholderWarning) {
                alertsHTML += `<div class="alert-box alert-info"><div class="alert-content"><i class="fa-solid fa-circle-info"></i><span><strong>Ação Necessária:</strong> Substitua os áudios de fecho.</span></div></div>`;
            }
            alertsContainer.innerHTML = alertsHTML;
            if (status.nextClosing) {
                nextClosingContainer.innerHTML = `<div class="closing-time-card"><i class="fa-solid fa-door-closed"></i><span>Próximo Fecho: <strong>${status.nextClosing.day} às ${status.nextClosing.time}</strong></span></div>`;
            } else {
                nextClosingContainer.innerHTML = '';
            }
        } catch (error) { console.error("Erro ao verificar o estado do sistema:", error); }
    };

    // Atualiza a agenda do dia
    const updateTodaysAgenda = async () => {
        const agendaContent = document.getElementById('todays-agenda-content');
        if (!agendaContent) return;
        try {
            const response = await fetch(`${rootPath}/api/get_todays_agenda.php?t=${new Date().getTime()}`);
            const agenda = await response.json();
            if (agenda && agenda.length > 0) {
                let agendaHTML = '<ul>';
                agenda.forEach(item => {
                    agendaHTML += `<li><span class="agenda-time">${item.time}</span><span class="agenda-title">${item.title}</span></li>`;
                });
                agendaHTML += '</ul>';
                agendaContent.innerHTML = agendaHTML;
            } else {
                agendaContent.innerHTML = `<p class="empty-state">Não há mais anúncios agendados para hoje.</p>`;
            }
        } catch (error) {}
    };
    
    // Inicia ou reinicia a contagem regressiva para o próximo anúncio
    function startCountdown() {
        if (countdownInterval) clearInterval(countdownInterval);
        const card = document.getElementById('next-ad-card');
        if (!card) return;
        const timestamp = parseInt(card.getAttribute('data-next-ad-timestamp'), 10);
        if (!timestamp) return;
        countdownInterval = setInterval(() => {
            const now = Math.floor(new Date().getTime() / 1000);
            const remaining = timestamp - now;
            const countdownDisplay = document.getElementById('countdown-display');
            const timerSpan = document.getElementById('countdown-timer');
            const timeDisplay = document.getElementById('next-ad-time-display');
            if (remaining <= 60 && remaining >= 0) {
                if (timeDisplay) timeDisplay.style.display = 'none';
                if (countdownDisplay) countdownDisplay.style.display = 'block';
                if (timerSpan) timerSpan.textContent = `00:${String(remaining % 60).padStart(2, '0')}`;
            } else {
                if (timeDisplay) timeDisplay.style.display = 'block';
                if (countdownDisplay) countdownDisplay.style.display = 'none';
                if (remaining < -1) clearInterval(countdownInterval);
            }
        }, 1000);
    }

    // Inicia o relógio digital
    function startLiveClock() {
        const clockElement = document.getElementById('live-clock');
        if (!clockElement) return;
        const updateClock = () => {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            clockElement.textContent = `${hours}:${minutes}:${seconds}`;
        };
        updateClock();
        setInterval(updateClock, 1000);
    }

    // Alterna o modo de ecrã inteiro
    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => console.error(err));
        } else {
            document.exitFullscreen();
        }
    }

    // --- 3. INICIALIZAÇÃO E EVENTOS ---
    if (adPlayer) {
        adPlayer.addEventListener('ended', () => {
            isPlayingAd = false;
            clearStatusFile();
            window.removeEventListener('beforeunload', beforeUnloadListener);
            if (liveCountdownInterval) clearInterval(liveCountdownInterval);
            const nextAdCardTitle = document.getElementById('next-ad-card-title');
            if (nextAdCardTitle) {
                nextAdCardTitle.innerHTML = `<i class="fa-solid fa-forward-step"></i> Próximo Anúncio`;
            }
            fetch(`${rootPath}/api/announcement_finished.php?initial_state=${lastAdInitialState}&title=${encodeURIComponent(lastAdTitle)}`)
                .then(() => {
                    updateNextAnnouncementCard().then(startCountdown);
                    updateActivityLog();
                    updateTodaysAgenda();
                });
        });
    }

    if (enableAudioButton) {
        enableAudioButton.addEventListener('click', () => {
            localStorage.setItem('audioEnabled', 'true');
            startPlayer();
        });
    }

    if (localStorage.getItem('audioEnabled') === 'true') {
        startPlayer();
    }

    // Lógica que corre APENAS no Dashboard
    if (document.getElementById('now-playing-component')) {
        updateNowPlayingCard();
        setInterval(updateNowPlayingCard, 10000);
        
        updateNextAnnouncementCard().then(startCountdown);
        
        startLiveClock();
        
        updateSystemAlerts();
        setInterval(updateSystemAlerts, 30000);
        
        updateWeatherCard();
        setInterval(updateWeatherCard, 900000);

        updateActivityLog();
        setInterval(updateActivityLog, 15000);

        updateTodaysAgenda();
        setInterval(updateTodaysAgenda, 60000);
    }

    // Lógica do botão de ecrã inteiro
    const fullscreenBtn = document.getElementById('fullscreen-btn');
    if (fullscreenBtn) {
        const fullscreenIcon = fullscreenBtn.querySelector('i');
        const fullscreenText = fullscreenBtn.querySelector('span');
        fullscreenBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleFullScreen();
        });
        function updateFullscreenButton() {
            if (document.fullscreenElement) {
                fullscreenIcon.classList.replace('fa-expand', 'fa-compress');
                fullscreenText.textContent = 'Sair do Ecrã Inteiro';
            } else {
                fullscreenIcon.classList.replace('fa-compress', 'fa-expand');
                fullscreenText.textContent = 'Ecrã Inteiro';
            }
        }
        document.addEventListener('fullscreenchange', updateFullscreenButton);
    }

    // Lógica para o acordeão de agendamentos
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    accordionHeaders.forEach(header => {
        header.addEventListener('click', () => {
            accordionHeaders.forEach(otherHeader => {
                if (otherHeader !== header && otherHeader.classList.contains('active')) {
                    otherHeader.classList.remove('active');
                    otherHeader.nextElementSibling.style.maxHeight = null;
                }
            });
            header.classList.toggle('active');
            const panel = header.nextElementSibling;
            if (panel.style.maxHeight) {
                panel.style.maxHeight = null;
            } else {
                panel.style.maxHeight = panel.scrollHeight + "px";
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const ttsForm = document.getElementById('tts-form');
    if (!ttsForm) return;

    const generateBtn = document.getElementById('generate-btn');
    const announcementTypeSelect = document.getElementById('announcement_type');
    
    // Grupos (inclui o novo)
    const plateGroup = document.getElementById('plate-input-group');
    const childGroup = document.getElementById('child-input-group');
    const personGroup = document.getElementById('person-input-group');
    const customGroup = document.getElementById('custom-input-group'); // NOVO

    // Inputs com required
    const plateInput = document.getElementById('license_plate');
    const childInput = document.getElementById('child_name');
    const personInput = document.getElementById('person_name');
    const customInput = document.getElementById('custom_text'); // NOVO
    
    function toggleInputs() {
        const selectedType = announcementTypeSelect.value;

        [plateGroup, childGroup, personGroup, customGroup].forEach(g => g && (g.style.display = 'none'));
        [plateInput, childInput, personInput, customInput].forEach(i => i && (i.required = false));

        if (selectedType === 'plate') {
            plateGroup.style.display = 'block';
            plateInput.required = true;
        } else if (selectedType === 'child') {
            childGroup.style.display = 'block';
            childInput.required = true;
        } else if (selectedType === 'person') {
            personGroup.style.display = 'block';
            personInput.required = true;
        } else if (selectedType === 'custom') {
            customGroup.style.display = 'block';
            customInput.required = true;
        }
    }
    
    announcementTypeSelect.addEventListener('change', toggleInputs);
    toggleInputs();

    ttsForm.addEventListener('submit', function(e) {
        const languages = ttsForm.querySelectorAll('input[name="languages[]"]:checked');
        if (languages.length === 0) {
            e.preventDefault();
            alert('Por favor, selecione pelo menos um idioma para o anúncio.');
            return;
        }
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A gerar áudio...';
        }
    });

    // --- Pré-visualização da voz selecionada (ElevenLabs) ---
    const voiceSelect    = document.getElementById('voice_id');
    const previewBtn     = document.getElementById('preview-voice-btn');
    const previewPlayer  = document.getElementById('voice-preview-player');
    const accentFilter   = document.getElementById('voice_accent_filter');

    // --- Filtro por sotaque ---
    function applyAccentFilter() {
        if (!voiceSelect || !accentFilter) return;
        const wanted = accentFilter.value;

        let firstVisible = null;
        Array.from(voiceSelect.options).forEach(opt => {
            const accent = opt.getAttribute('data-accent') || '';
            const show   = (wanted === 'all') || (accent === wanted);
            opt.hidden   = !show;
            opt.disabled = !show;
            if (show && !firstVisible) firstVisible = opt;
        });

        // Esconde/mostra optgroups vazios
        Array.from(voiceSelect.querySelectorAll('optgroup')).forEach(g => {
            const accent = g.getAttribute('data-accent') || '';
            g.hidden = (wanted !== 'all' && accent !== wanted);
        });

        // Se a voz atualmente selecionada deixou de estar visível, escolhe a 1ª visível
        const cur = voiceSelect.options[voiceSelect.selectedIndex];
        if ((!cur || cur.hidden) && firstVisible) {
            voiceSelect.value = firstVisible.value;
            voiceSelect.dispatchEvent(new Event('change'));
        }
    }

    if (accentFilter) {
        accentFilter.addEventListener('change', applyAccentFilter);
        applyAccentFilter(); // estado inicial (default: "all" -> mostra tudo)
    }

    // --- Definir voz como predefinida ---
    const setDefaultBtn  = document.getElementById('set-default-voice-btn');
    const defaultStatus  = document.getElementById('default-voice-status');
    const defaultNameEl  = document.getElementById('default-voice-name');

    function currentDefaultVoiceId() {
        return defaultStatus ? (defaultStatus.getAttribute('data-current') || '') : '';
    }

    function refreshDefaultButtonState() {
        if (!setDefaultBtn || !voiceSelect) return;
        const sel = voiceSelect.value;
        const isCurrent = sel && sel === currentDefaultVoiceId();
        setDefaultBtn.classList.toggle('is-current', !!isCurrent);
        setDefaultBtn.disabled = !!isCurrent;
        setDefaultBtn.innerHTML = isCurrent
            ? '<i class="fa-solid fa-check"></i> Já é predefinida'
            : '<i class="fa-solid fa-star"></i> Predefinir';
    }

    function updateStatusLabel(voiceId) {
        if (!defaultStatus) return;
        defaultStatus.setAttribute('data-current', voiceId);

        // Procura o nome da voz na <option> correspondente
        let name = voiceId;
        if (voiceSelect) {
            const opt = Array.from(voiceSelect.options).find(o => o.value === voiceId);
            if (opt) {
                // Remove prefixos "★ [PT-PT] " e sufixo " (predefinida)"
                name = opt.textContent.replace(/^\s*★\s*/, '').replace(/^\[[A-Z\-]+\]\s*/, '').replace(/\s*\(predefinida\)\s*$/, '').trim();
            }
        }
        if (defaultNameEl) {
            defaultNameEl.textContent = name;
            defaultStatus.innerHTML = '<i class="fa-solid fa-star"></i> Voz predefinida atual: <strong id="default-voice-name">' + name + '</strong>';
            defaultStatus.setAttribute('data-current', voiceId);
        }
    }

    if (setDefaultBtn && voiceSelect) {
        setDefaultBtn.addEventListener('click', async function () {
            const voiceId = voiceSelect.value;
            if (!voiceId) return;

            const originalHtml = setDefaultBtn.innerHTML;
            setDefaultBtn.disabled = true;
            setDefaultBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A guardar...';

            try {
                const fd = new FormData();
                fd.append('voice_id', voiceId);
                const resp = await fetch('../api/tts_settings.php', { method: 'POST', body: fd });
                const data = await resp.json().catch(() => ({}));

                if (!resp.ok || !data.ok) {
                    throw new Error(data.error || ('HTTP ' + resp.status));
                }
                updateStatusLabel(voiceId);
                refreshDefaultButtonState();
            } catch (err) {
                console.error('Falha a guardar voz predefinida:', err);
                alert('Não foi possível guardar a voz predefinida: ' + err.message);
                setDefaultBtn.disabled = false;
                setDefaultBtn.innerHTML = originalHtml;
            }
        });

        voiceSelect.addEventListener('change', refreshDefaultButtonState);
        refreshDefaultButtonState();
    }

    function setPreviewPlaying(isPlaying) {
        if (!previewBtn) return;
        previewBtn.classList.toggle('playing', isPlaying);
        previewBtn.innerHTML = isPlaying
            ? '<i class="fa-solid fa-stop"></i> Parar'
            : '<i class="fa-solid fa-play"></i> Pré-visualizar';
    }

    function currentPreviewUrl() {
        if (!voiceSelect) return '';
        const opt = voiceSelect.options[voiceSelect.selectedIndex];
        return opt ? (opt.getAttribute('data-preview') || '') : '';
    }

    if (previewBtn && voiceSelect && previewPlayer) {
        previewBtn.addEventListener('click', function () {
            if (!previewPlayer.paused) {
                previewPlayer.pause();
                previewPlayer.currentTime = 0;
                setPreviewPlaying(false);
                return;
            }
            const url = currentPreviewUrl();
            if (!url) {
                alert('Esta voz não tem amostra de pré-visualização disponível.');
                return;
            }
            previewPlayer.src = url;
            previewPlayer.play().then(() => setPreviewPlaying(true))
                .catch(err => {
                    console.error('Erro a reproduzir pré-visualização:', err);
                    setPreviewPlaying(false);
                });
        });

        previewPlayer.addEventListener('ended', () => setPreviewPlaying(false));
        previewPlayer.addEventListener('pause', () => {
            if (previewPlayer.currentTime === 0) setPreviewPlaying(false);
        });

        voiceSelect.addEventListener('change', () => {
            if (!previewPlayer.paused) {
                previewPlayer.pause();
                previewPlayer.currentTime = 0;
            }
            setPreviewPlaying(false);
        });
    }
});

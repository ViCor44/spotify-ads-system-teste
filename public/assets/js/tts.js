document.addEventListener('DOMContentLoaded', function () {
    const ttsForm = document.getElementById('tts-form');
    if (!ttsForm) return;

    const generateBtn = document.getElementById('generate-btn');
    const announcementTypeSelect = document.getElementById('announcement_type');

    // Grupos de input
    const plateGroup  = document.getElementById('plate-input-group');
    const childGroup  = document.getElementById('child-input-group');
    const personGroup = document.getElementById('person-input-group');
    const customGroup = document.getElementById('custom-input-group');

    const plateInput  = document.getElementById('license_plate');
    const childInput  = document.getElementById('child_name');
    const personInput = document.getElementById('person_name');
    const customInput = document.getElementById('custom_text');

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

    ttsForm.addEventListener('submit', function (e) {
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

    // =================== Voz por Idioma ===================
    const previewPlayer = document.getElementById('voice-preview-player');
    let currentPreviewBtn = null;

    function getSelectForLang(lang) {
        return document.getElementById('voice-select-' + lang);
    }
    function getSelectedOption(select) {
        return select ? select.options[select.selectedIndex] || null : null;
    }

    function setPreviewPlaying(btn, isPlaying) {
        if (!btn) return;
        btn.classList.toggle('playing', isPlaying);
        btn.innerHTML = isPlaying
            ? '<i class="fa-solid fa-stop"></i>'
            : '<i class="fa-solid fa-play"></i>';
    }
    function stopAllPreviews() {
        if (previewPlayer && !previewPlayer.paused) {
            previewPlayer.pause();
            previewPlayer.currentTime = 0;
        }
        if (currentPreviewBtn) {
            setPreviewPlaying(currentPreviewBtn, false);
            currentPreviewBtn = null;
        }
    }

    // ---- Botões de pré-visualização ----
    document.querySelectorAll('.preview-voice-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const lang   = btn.getAttribute('data-lang');
            const select = getSelectForLang(lang);
            const opt    = getSelectedOption(select);
            const url    = opt ? (opt.getAttribute('data-preview') || '') : '';

            if (currentPreviewBtn === btn && previewPlayer && !previewPlayer.paused) {
                stopAllPreviews();
                return;
            }
            stopAllPreviews();
            if (!url) {
                alert('Esta voz não tem amostra de pré-visualização disponível.');
                return;
            }
            previewPlayer.src = url;
            previewPlayer.play().then(() => {
                currentPreviewBtn = btn;
                setPreviewPlaying(btn, true);
            }).catch(err => {
                console.error('Erro a reproduzir pré-visualização:', err);
                stopAllPreviews();
            });
        });
    });

    if (previewPlayer) {
        previewPlayer.addEventListener('ended', stopAllPreviews);
        previewPlayer.addEventListener('pause', () => {
            if (previewPlayer.currentTime === 0) stopAllPreviews();
        });
    }

    // ---- Filtro de sotaque (por linha) ----
    function applyAccentFilter(lang) {
        const select = getSelectForLang(lang);
        const filter = document.querySelector('.voice-accent-filter[data-target-lang="' + lang + '"]');
        if (!select || !filter) return;

        const wanted = filter.value;
        let firstVisible = null;

        Array.from(select.options).forEach(opt => {
            const accent = opt.getAttribute('data-accent') || '';
            const show   = (wanted === 'all') || (accent === wanted);
            opt.hidden   = !show;
            opt.disabled = !show;
            if (show && !firstVisible) firstVisible = opt;
        });
        Array.from(select.querySelectorAll('optgroup')).forEach(g => {
            const accent = g.getAttribute('data-accent') || '';
            g.hidden = (wanted !== 'all' && accent !== wanted);
        });

        const cur = getSelectedOption(select);
        if ((!cur || cur.hidden) && firstVisible) {
            select.value = firstVisible.value;
            select.dispatchEvent(new Event('change'));
        }
    }

    document.querySelectorAll('.voice-accent-filter').forEach(filter => {
        const lang = filter.getAttribute('data-target-lang');
        filter.addEventListener('change', () => applyAccentFilter(lang));
        applyAccentFilter(lang); // estado inicial
    });

    // ---- Botão "Predefinir" (por idioma) ----
    function refreshDefaultBtnState(btn) {
        const lang    = btn.getAttribute('data-lang');
        const current = btn.getAttribute('data-current') || '';
        const select  = getSelectForLang(lang);
        const value   = select ? select.value : '';
        const isCurr  = value && value === current;

        btn.classList.toggle('is-current', !!isCurr);
        btn.disabled = !!isCurr;
        btn.innerHTML = isCurr
            ? '<i class="fa-solid fa-check"></i> Predefinida'
            : '<i class="fa-solid fa-star"></i> Predefinir';
    }

    /** Atualiza o ★ e o "(predefinida)" das opções deste idioma. */
    function updateOptionLabels(lang, newDefaultVoiceId) {
        const select = getSelectForLang(lang);
        if (!select) return;
        Array.from(select.options).forEach(opt => {
            const name   = opt.getAttribute('data-name') || '';
            const accent = (opt.getAttribute('data-accent') || '').toUpperCase();
            // Tenta preservar os extras "— gender, description..."
            const m = opt.textContent.match(/—\s*([^()]+?)(?:\s*\(predefinida\))?\s*$/);
            const extra = m ? ' — ' + m[1].trim() : '';
            const star   = (opt.value === newDefaultVoiceId) ? '★ ' : '';
            const suffix = (opt.value === newDefaultVoiceId) ? ' (predefinida)' : '';
            opt.textContent = star + '[' + accent + '] ' + name + extra + suffix;
        });
    }

    document.querySelectorAll('.set-default-btn').forEach(btn => {
        const lang   = btn.getAttribute('data-lang');
        const select = getSelectForLang(lang);

        btn.addEventListener('click', async function () {
            const voiceId = select ? select.value : '';
            if (!voiceId) return;

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const fd = new FormData();
                fd.append('voice_id', voiceId);
                fd.append('lang', lang);
                const resp = await fetch('../api/tts_settings.php', { method: 'POST', body: fd });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.ok) {
                    throw new Error(data.error || ('HTTP ' + resp.status));
                }
                btn.setAttribute('data-current', voiceId);
                updateOptionLabels(lang, voiceId);
                refreshDefaultBtnState(btn);
            } catch (err) {
                console.error('Falha a guardar voz predefinida:', err);
                alert('Não foi possível guardar a voz predefinida: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });

        if (select) {
            select.addEventListener('change', () => refreshDefaultBtnState(btn));
        }
        refreshDefaultBtnState(btn);
    });

    // ---- Ativar/desativar linha consoante o checkbox do idioma ----
    function syncLangRowStates() {
        const checked = new Set(
            Array.from(ttsForm.querySelectorAll('input[name="languages[]"]:checked'))
                 .map(cb => cb.value)
        );
        document.querySelectorAll('.voice-lang-row').forEach(row => {
            const lang = row.getAttribute('data-lang');
            const active = checked.has(lang);
            row.classList.toggle('is-active', active);
            row.classList.toggle('is-disabled', !active);

            row.querySelectorAll('.voice-accent-filter, .preview-voice-btn').forEach(el => {
                el.disabled = !active;
            });
            // O select de voz fica disponível para o utilizador poder predefinir
            // mesmo um idioma que não esteja ativo agora.
        });
    }

    ttsForm.querySelectorAll('input[name="languages[]"]').forEach(cb => {
        cb.addEventListener('change', syncLangRowStates);
    });
    syncLangRowStates();
});

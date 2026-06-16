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

    function getSelectForLang(lang) {
        return document.getElementById('voice-select-' + lang);
    }
    function getSelectedOption(select) {
        return select ? select.options[select.selectedIndex] || null : null;
    }

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
            const name = opt.getAttribute('data-name') || '';
            // Preferir o `data-extra` (contém naturalidade + género + descrição);
            // como fallback, tenta extrair do texto actual.
            let extra = opt.getAttribute('data-extra');
            if (extra === null) {
                const m = opt.textContent.match(/—\s*([^()]+?)(?:\s*\(predefinida\))?\s*$/);
                extra = m ? ' — ' + m[1].trim() : '';
            }
            const star   = (opt.value === newDefaultVoiceId) ? '★ ' : '';
            const suffix = (opt.value === newDefaultVoiceId) ? ' (predefinida)' : '';
            opt.textContent = star + name + extra + suffix;
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

    // ---- Adicionar voz à conta ElevenLabs ----
    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function getPanel(lang) {
        return document.getElementById('add-voice-panel-' + lang);
    }

    function renderResults(lang, voices) {
        const container = document.querySelector('.add-voice-results[data-lang="' + lang + '"]');
        if (!container) return;
        if (!voices || voices.length === 0) {
            container.innerHTML = '<p class="add-voice-hint">Sem resultados. Tente outro termo ou outro género.</p>';
            return;
        }
        container.innerHTML = voices.map(v => {
            // Naturalidade = sotaque (ou idioma como fallback) — mostrada como badge
            const naturalidade = (v.accent || v.language || '').trim();
            const meta = [v.gender, v.age, v.descriptive].filter(Boolean).join(' · ');
            return (
                '<div class="add-voice-card" data-voice-id="' + escapeHtml(v.voice_id) + '" data-public-owner-id="' + escapeHtml(v.public_owner_id) + '">' +
                    '<div class="name">' + escapeHtml(v.name || 'Sem nome') + '</div>' +
                    (naturalidade
                        ? '<div class="naturalidade"><i class="fa-solid fa-earth-europe"></i> <span class="naturalidade-label">Naturalidade:</span> <span class="naturalidade-value">' + escapeHtml(naturalidade) + '</span></div>'
                        : '') +
                    (meta ? '<div class="meta">' + escapeHtml(meta) + '</div>' : '') +
                    (v.use_case ? '<div class="meta">Uso: ' + escapeHtml(v.use_case) + '</div>' : '') +
                    '<button type="button" class="add-btn" data-add-name="' + escapeHtml(v.name || 'Voz importada') + '">' +
                        '<i class="fa-solid fa-plus"></i> Adicionar à minha conta' +
                    '</button>' +
                '</div>'
            );
        }).join('');
    }

    async function searchSharedVoices(lang) {
        const container = document.querySelector('.add-voice-results[data-lang="' + lang + '"]');
        const search    = document.querySelector('.add-voice-search[data-lang="' + lang + '"]');
        const gender    = document.querySelector('.add-voice-gender[data-lang="' + lang + '"]');
        if (!container) return;

        container.innerHTML = '<p class="add-voice-hint"><i class="fa-solid fa-spinner fa-spin"></i> A procurar vozes ' + escapeHtml(lang.toUpperCase()) + '…</p>';

        const params = new URLSearchParams({ lang });
        if (search && search.value.trim()) params.set('q', search.value.trim());
        if (gender && gender.value)         params.set('gender', gender.value);

        try {
            const resp = await fetch('../api/elevenlabs_shared_voices.php?' + params.toString());
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || ('HTTP ' + resp.status));
            }
            renderResults(lang, data.voices);
        } catch (err) {
            container.innerHTML = '<p class="add-voice-hint error">Erro: ' + escapeHtml(err.message) + '</p>';
        }
    }

    async function addSharedVoice(card) {
        const panel       = card.closest('.add-voice-panel');
        const lang        = panel ? (panel.id || '').replace('add-voice-panel-', '') : '';
        const voiceId     = card.getAttribute('data-voice-id');
        const publicOwner = card.getAttribute('data-public-owner-id');
        const addBtn      = card.querySelector('.add-btn');
        const newName     = addBtn ? addBtn.getAttribute('data-add-name') : 'Voz importada';

        if (!voiceId || !publicOwner) return;

        const originalHtml = addBtn.innerHTML;
        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A adicionar…';

        try {
            const fd = new FormData();
            fd.append('voice_id', voiceId);
            fd.append('public_owner_id', publicOwner);
            fd.append('new_name', newName);

            const resp = await fetch('../api/elevenlabs_shared_voices.php', { method: 'POST', body: fd });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || ('HTTP ' + resp.status));
            }
            addBtn.classList.add('is-added');
            addBtn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionada — recarregue a página';
        } catch (err) {
            console.error('Erro a adicionar voz:', err);
            alert('Não foi possível adicionar a voz: ' + err.message);
            addBtn.disabled = false;
            addBtn.innerHTML = originalHtml;
        }
    }

    document.querySelectorAll('.add-voice-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const lang  = btn.getAttribute('data-lang');
            const panel = getPanel(lang);
            if (!panel) return;
            const willOpen = panel.hasAttribute('hidden');
            panel.toggleAttribute('hidden', !willOpen);
            if (willOpen) searchSharedVoices(lang); // pesquisa logo ao abrir
        });
    });

    document.querySelectorAll('.add-voice-close-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const lang  = btn.getAttribute('data-lang');
            const panel = getPanel(lang);
            if (panel) panel.setAttribute('hidden', '');
        });
    });

    document.querySelectorAll('.add-voice-search-btn').forEach(btn => {
        btn.addEventListener('click', () => searchSharedVoices(btn.getAttribute('data-lang')));
    });

    document.querySelectorAll('.add-voice-search').forEach(inp => {
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchSharedVoices(inp.getAttribute('data-lang'));
            }
        });
    });

    // Delegação para os botões "Adicionar à minha conta" (renderizados dinamicamente)
    document.querySelectorAll('.add-voice-results').forEach(container => {
        container.addEventListener('click', e => {
            const addBtn = e.target.closest('.add-btn');
            if (!addBtn) return;
            const card = addBtn.closest('.add-voice-card');
            if (card) addSharedVoice(card);
        });
    });

});

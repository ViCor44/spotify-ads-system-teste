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

    // Marcas e modelos mais comuns. O modelo é filtrado automaticamente pela marca.
    const vehicleModels = {
        'Alfa Romeo': ['147', '156', '159', 'Giulia', 'Giulietta', 'Stelvio', 'Tonale'],
        'Audi': ['A1', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'Q2', 'Q3', 'Q4 e-tron', 'Q5', 'Q7', 'Q8', 'TT'],
        'BMW': ['Série 1', 'Série 2', 'Série 3', 'Série 4', 'Série 5', 'Série 7', 'X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'i3', 'i4', 'iX'],
        'Chevrolet': ['Aveo', 'Captiva', 'Cruze', 'Matiz', 'Spark', 'Trax'],
        'Citroën': ['Berlingo', 'C1', 'C2', 'C3', 'C3 Aircross', 'C4', 'C4 Cactus', 'C4 Picasso', 'C5', 'C5 Aircross', 'Saxo', 'Xsara'],
        'Cupra': ['Ateca', 'Born', 'Formentor', 'Leon', 'Tavascan', 'Terramar'],
        'Dacia': ['Duster', 'Jogger', 'Logan', 'Sandero', 'Spring'],
        'Fiat': ['500', '500X', 'Bravo', 'Doblo', 'Grande Punto', 'Panda', 'Punto', 'Tipo'],
        'Ford': ['B-Max', 'C-Max', 'EcoSport', 'Fiesta', 'Focus', 'Galaxy', 'Ka', 'Kuga', 'Mondeo', 'Mustang', 'Puma', 'S-Max', 'Transit'],
        'Honda': ['Accord', 'Civic', 'CR-V', 'e', 'FR-V', 'HR-V', 'Jazz'],
        'Hyundai': ['Bayon', 'Getz', 'i10', 'i20', 'i30', 'Ioniq', 'Kauai', 'Santa Fe', 'Tucson'],
        'Jaguar': ['E-Pace', 'F-Pace', 'F-Type', 'I-Pace', 'XE', 'XF', 'X-Type'],
        'Jeep': ['Avenger', 'Cherokee', 'Compass', 'Grand Cherokee', 'Renegade', 'Wrangler'],
        'Kia': ['Ceed', 'EV3', 'EV6', 'Niro', 'Picanto', 'Rio', 'Sorento', 'Sportage', 'Stonic', 'XCeed'],
        'Land Rover': ['Defender', 'Discovery', 'Discovery Sport', 'Freelander', 'Range Rover', 'Range Rover Evoque', 'Range Rover Sport', 'Range Rover Velar'],
        'Lexus': ['CT', 'ES', 'IS', 'LBX', 'NX', 'RX', 'UX'],
        'Mazda': ['2', '3', '5', '6', 'CX-3', 'CX-30', 'CX-5', 'MX-5'],
        'Mercedes-Benz': ['Classe A', 'Classe B', 'Classe C', 'Classe E', 'Classe S', 'CLA', 'CLS', 'EQA', 'EQB', 'GLA', 'GLB', 'GLC', 'GLE', 'Vito'],
        'MG': ['MG3', 'MG4', 'MG5', 'HS', 'Marvel R', 'ZS'],
        'MINI': ['Cabrio', 'Clubman', 'Cooper', 'Countryman', 'One'],
        'Mitsubishi': ['ASX', 'Colt', 'L200', 'Lancer', 'Outlander', 'Space Star'],
        'Nissan': ['Almera', 'Juke', 'Leaf', 'Micra', 'Note', 'Primera', 'Qashqai', 'X-Trail'],
        'Opel': ['Adam', 'Astra', 'Corsa', 'Crossland', 'Grandland', 'Insignia', 'Meriva', 'Mokka', 'Vectra', 'Zafira'],
        'Peugeot': ['106', '206', '207', '208', '2008', '306', '307', '308', '3008', '406', '407', '508', '5008', 'Partner', 'Rifter'],
        'Polestar': ['Polestar 2', 'Polestar 3', 'Polestar 4'],
        'Porsche': ['718', '911', 'Cayenne', 'Macan', 'Panamera', 'Taycan'],
        'Renault': ['Austral', 'Captur', 'Clio', 'Espace', 'Kadjar', 'Kangoo', 'Laguna', 'Mégane', 'Scénic', 'Twingo', 'Zoe'],
        'SEAT': ['Alhambra', 'Arona', 'Arosa', 'Ateca', 'Cordoba', 'Ibiza', 'Leon', 'Toledo'],
        'Škoda': ['Enyaq', 'Fabia', 'Kamiq', 'Karoq', 'Kodiaq', 'Octavia', 'Scala', 'Superb'],
        'Smart': ['Forfour', 'Fortwo', '#1', '#3'],
        'Suzuki': ['Alto', 'Baleno', 'Ignis', 'Jimny', 'S-Cross', 'Swift', 'Vitara'],
        'Tesla': ['Model 3', 'Model S', 'Model X', 'Model Y'],
        'Toyota': ['Auris', 'Aygo', 'C-HR', 'Corolla', 'Hilux', 'Land Cruiser', 'Prius', 'RAV4', 'Yaris', 'Yaris Cross'],
        'Volkswagen': ['Arteon', 'Beetle', 'Golf', 'ID.3', 'ID.4', 'ID.5', 'Passat', 'Polo', 'T-Cross', 'T-Roc', 'Tiguan', 'Touran', 'Up'],
        'Volvo': ['C30', 'C40', 'EX30', 'S40', 'S60', 'S90', 'V40', 'V60', 'V90', 'XC40', 'XC60', 'XC90']
    };
    const makeInput = document.getElementById('vehicle_make');
    const modelInputVehicle = document.getElementById('vehicle_model');
    const colorInput = document.getElementById('vehicle_color');
    const makeList = document.getElementById('vehicle-make-list');
    const modelList = document.getElementById('vehicle-model-list');
    const colorList = document.getElementById('vehicle-color-list');
    const vehicleOptionsStorageKey = 'tts_vehicle_custom_options_v1';
    const defaultVehicleColors = ['Amarelo', 'Azul', 'Bege', 'Bordô', 'Branco', 'Castanho', 'Cinzento', 'Dourado', 'Laranja', 'Prateado', 'Preto', 'Roxo', 'Verde', 'Vermelho'];

    function addListOption(list, value) {
        const option = document.createElement('option');
        option.value = value;
        list.appendChild(option);
    }

    function readCustomVehicleOptions() {
        try {
            const stored = JSON.parse(localStorage.getItem(vehicleOptionsStorageKey));
            return stored && typeof stored === 'object'
                ? { makes: stored.makes || [], models: stored.models || {}, colors: stored.colors || [] }
                : { makes: [], models: {}, colors: [] };
        } catch (error) {
            return { makes: [], models: {}, colors: [] };
        }
    }

    let customVehicleOptions = readCustomVehicleOptions();

    function uniqueSorted(values) {
        return Array.from(new Set(values.filter(Boolean))).sort((a, b) => a.localeCompare(b, 'pt', { sensitivity: 'base' }));
    }

    function renderVehicleMakes() {
        if (!makeList) return;
        makeList.innerHTML = '';
        uniqueSorted(Object.keys(vehicleModels).concat(customVehicleOptions.makes)).forEach(make => addListOption(makeList, make));
    }

    function findKnownMake(value) {
        const allMakes = Object.keys(vehicleModels).concat(customVehicleOptions.makes);
        return allMakes.find(make => make.localeCompare(value.trim(), 'pt', { sensitivity: 'base' }) === 0) || value.trim();
    }

    function populateVehicleModels() {
        if (!modelList) return;
        modelList.innerHTML = '';
        const make = makeInput ? findKnownMake(makeInput.value) : '';
        uniqueSorted((vehicleModels[make] || []).concat(customVehicleOptions.models[make] || []))
            .forEach(model => addListOption(modelList, model));
    }

    function renderVehicleColors() {
        if (!colorList) return;
        colorList.innerHTML = '';
        uniqueSorted(defaultVehicleColors.concat(customVehicleOptions.colors)).forEach(color => addListOption(colorList, color));
    }

    function saveNewVehicleOptions() {
        if (!makeInput || !modelInputVehicle || !colorInput) return;
        const make = findKnownMake(makeInput.value);
        const model = modelInputVehicle.value.trim();
        const color = colorInput.value.trim();

        if (make && !Object.keys(vehicleModels).some(item => item.localeCompare(make, 'pt', { sensitivity: 'base' }) === 0)) {
            if (!customVehicleOptions.makes.some(item => item.localeCompare(make, 'pt', { sensitivity: 'base' }) === 0)) {
                customVehicleOptions.makes.push(make);
            }
        }
        if (make && model) {
            customVehicleOptions.models[make] = customVehicleOptions.models[make] || [];
            const standardModels = vehicleModels[make] || [];
            const knownModels = standardModels.concat(customVehicleOptions.models[make]);
            if (!knownModels.some(item => item.localeCompare(model, 'pt', { sensitivity: 'base' }) === 0)) {
                customVehicleOptions.models[make].push(model);
            }
        }
        if (color && !defaultVehicleColors.concat(customVehicleOptions.colors)
            .some(item => item.localeCompare(color, 'pt', { sensitivity: 'base' }) === 0)) {
            customVehicleOptions.colors.push(color);
        }

        try {
            localStorage.setItem(vehicleOptionsStorageKey, JSON.stringify(customVehicleOptions));
        } catch (error) {
            console.warn('Não foi possível guardar as opções do veículo:', error);
        }
        renderVehicleMakes();
        populateVehicleModels();
        renderVehicleColors();
    }

    if (makeInput && modelInputVehicle && colorInput) {
        renderVehicleMakes();
        populateVehicleModels();
        renderVehicleColors();
        makeInput.addEventListener('input', populateVehicleModels);
        [makeInput, modelInputVehicle, colorInput].forEach(input => input.addEventListener('change', saveNewVehicleOptions));
    }

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
        saveNewVehicleOptions();
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
    // =================== Voice Settings (Sliders) ===================
    const voiceSettingSliders = ttsForm.querySelectorAll('.voice-setting-slider');
    const voiceBoostCheckbox = ttsForm.querySelector('#voice-boost');
    let voiceSettingTimeout = null;

    function updateVoiceSettingValue(slider) {
        const target = slider.getAttribute('data-target');
        const valueSpan = ttsForm.querySelector('[data-target="' + target + '"]');
        if (valueSpan) {
            valueSpan.textContent = slider.value + '%';
        }
    }

    function saveVoiceSettings() {
        const fd = new FormData();
        voiceSettingSliders.forEach(slider => {
            const setting = slider.getAttribute('data-setting');
            const value = Math.max(0, Math.min(100, parseInt(slider.value))) / 100;
            fd.append(setting, value);
        });
        if (voiceBoostCheckbox) {
            fd.append('use_speaker_boost', voiceBoostCheckbox.checked ? '1' : '0');
        }

        fetch('../api/tts_settings.php', { method: 'POST', body: fd })
            .then(r => r.json().catch(() => ({})))
            .then(data => {
                if (!data.ok) {
                    console.error('Erro ao guardar voice_settings:', data);
                }
            })
            .catch(err => console.error('Erro ao guardar voice_settings:', err));
    }

    voiceSettingSliders.forEach(slider => {
        updateVoiceSettingValue(slider);
        slider.addEventListener('input', () => {
            updateVoiceSettingValue(slider);
            clearTimeout(voiceSettingTimeout);
            voiceSettingTimeout = setTimeout(saveVoiceSettings, 800);
        });
    });

    if (voiceBoostCheckbox) {
        voiceBoostCheckbox.addEventListener('change', saveVoiceSettings);
    }

    // =================== Seletor de Modelo (v2 / v3 / turbo / flash) ===================
    const modelSelect = document.getElementById('voice-model');
    const modelStatus = document.getElementById('voice-model-status');

    function setModelStatus(msg, cls) {
        if (!modelStatus) return;
        modelStatus.className = 'voice-model-status' + (cls ? ' ' + cls : '');
        modelStatus.textContent = msg || '';
        if (msg && cls === 'is-ok') {
            setTimeout(() => {
                if (modelStatus.textContent === msg) setModelStatus('', '');
            }, 2500);
        }
    }

    if (modelSelect) {
        modelSelect.addEventListener('change', async () => {
            const modelId = modelSelect.value;
            setModelStatus('A guardar…', '');
            try {
                const fd = new FormData();
                fd.append('action', 'set_model');
                fd.append('model_id', modelId);
                const resp = await fetch('../api/tts_settings.php', { method: 'POST', body: fd });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.ok) {
                    throw new Error(data.error || ('HTTP ' + resp.status));
                }
                setModelStatus('Modelo guardado: ' + data.model_id, 'is-ok');
            } catch (err) {
                console.error('Erro ao guardar modelo:', err);
                setModelStatus('Erro: ' + err.message, 'is-err');
            }
        });
    }


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

    // ---- Adicionar voz à conta ElevenLabs (modal global) ----
    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    const modal          = document.getElementById('add-voice-modal');
    const modalLangLabel = modal ? modal.querySelector('.add-voice-modal__lang') : null;
    const modalSearchInp = modal ? modal.querySelector('.add-voice-search')       : null;
    const modalGenderSel = modal ? modal.querySelector('.add-voice-gender')       : null;
    const modalSearchBtn = modal ? modal.querySelector('.add-voice-search-btn')   : null;
    const modalResults   = modal ? modal.querySelector('.add-voice-results')      : null;
    const modalIdInput   = modal ? modal.querySelector('.add-voice-id-input')     : null;
    const modalIdName    = modal ? modal.querySelector('.add-voice-id-name')      : null;
    const modalIdBtn     = modal ? modal.querySelector('.add-voice-id-btn')       : null;
    const modalIdFeedback= modal ? modal.querySelector('.add-voice-id-feedback')  : null;
    let modalLang        = '';

    function openModal(lang, label) {
        if (!modal) return;
        modalLang = lang || '';
        if (modalLangLabel) modalLangLabel.textContent = label || (lang ? lang.toUpperCase() : '—');
        if (modalSearchInp) modalSearchInp.value = '';
        if (modalGenderSel) modalGenderSel.value = '';
        if (modalIdInput)   modalIdInput.value = '';
        if (modalIdName)    modalIdName.value = '';
        if (modalIdFeedback) { modalIdFeedback.textContent = ''; modalIdFeedback.className = 'add-voice-id-feedback'; }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        searchSharedVoices();
        if (modalSearchInp) setTimeout(() => modalSearchInp.focus(), 50);
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function renderResults(voices) {
        if (!modalResults) return;
        if (!voices || voices.length === 0) {
            modalResults.innerHTML = '<p class="add-voice-hint">Sem resultados. Tente outro termo ou outro género.</p>';
            return;
        }
        modalResults.innerHTML = voices.map(v => {
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
                    (v.preview_url
                        ? '<audio controls preload="none" src="' + escapeHtml(v.preview_url) + '" style="width:100%;margin-top:4px;"></audio>'
                        : '') +
                    '<button type="button" class="add-btn" data-add-name="' + escapeHtml(v.name || 'Voz importada') + '">' +
                        '<i class="fa-solid fa-plus"></i> Adicionar à minha conta' +
                    '</button>' +
                '</div>'
            );
        }).join('');
    }

    async function searchSharedVoices() {
        if (!modalResults || !modalLang) return;

        modalResults.innerHTML = '<p class="add-voice-hint"><i class="fa-solid fa-spinner fa-spin"></i> A procurar vozes ' + escapeHtml(modalLang.toUpperCase()) + '…</p>';

        const params = new URLSearchParams({ lang: modalLang });
        if (modalSearchInp && modalSearchInp.value.trim()) params.set('q', modalSearchInp.value.trim());
        if (modalGenderSel && modalGenderSel.value)         params.set('gender', modalGenderSel.value);

        try {
            const resp = await fetch('../api/elevenlabs_shared_voices.php?' + params.toString());
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || ('HTTP ' + resp.status));
            }
            renderResults(data.voices);
        } catch (err) {
            modalResults.innerHTML = '<p class="add-voice-hint error">Erro: ' + escapeHtml(err.message) + '</p>';
        }
    }

    async function addSharedVoice(card) {
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
            const lang  = btn.getAttribute('data-lang') || '';
            const label = btn.getAttribute('data-label') || '';
            openModal(lang, label);
        });
    });

    if (modal) {
        // Fechar (backdrop e botão ×)
        modal.addEventListener('click', e => {
            if (e.target.closest('[data-close]')) {
                closeModal();
                return;
            }
            const addBtn = e.target.closest('.add-btn');
            if (addBtn) {
                const card = addBtn.closest('.add-voice-card');
                if (card) addSharedVoice(card);
            }
        });
        // ESC
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });
        if (modalSearchBtn) {
            modalSearchBtn.addEventListener('click', () => searchSharedVoices());
        }
        if (modalSearchInp) {
            modalSearchInp.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchSharedVoices();
                }
            });
        }
        if (modalGenderSel) {
            modalGenderSel.addEventListener('change', () => searchSharedVoices());
        }

        // Adicionar voz manualmente por ID
        async function addVoiceById() {
            if (!modalIdBtn || !modalIdInput) return;
            const voiceId = (modalIdInput.value || '').trim();
            const name    = modalIdName ? (modalIdName.value || '').trim() : '';
            if (!voiceId) {
                setIdFeedback('Cola um voice_id primeiro.', 'is-err');
                modalIdInput.focus();
                return;
            }
            const original = modalIdBtn.innerHTML;
            modalIdBtn.disabled = true;
            modalIdBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A guardar…';
            setIdFeedback('', '');
            try {
                const fd = new FormData();
                fd.append('action', 'add_custom_voice');
                fd.append('voice_id', voiceId);
                if (name) fd.append('name', name);
                if (modalLang) fd.append('voice_lang', modalLang);
                const resp = await fetch('../api/tts_settings.php', { method: 'POST', body: fd });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.ok) {
                    throw new Error(data.error || ('HTTP ' + resp.status));
                }
                if (data.added === false) {
                    setIdFeedback('Essa voz já estava guardada. Recarregue a página para a ver no seletor.', 'is-ok');
                } else {
                    setIdFeedback('Voz "' + (data.name || voiceId) + '" adicionada. Recarregue a página para a ver no seletor.', 'is-ok');
                    modalIdInput.value = '';
                    if (modalIdName) modalIdName.value = '';
                }
            } catch (err) {
                console.error('Erro a adicionar voz por ID:', err);
                setIdFeedback('Erro: ' + err.message, 'is-err');
            } finally {
                modalIdBtn.disabled = false;
                modalIdBtn.innerHTML = original;
            }
        }

        function setIdFeedback(msg, cls) {
            if (!modalIdFeedback) return;
            modalIdFeedback.className = 'add-voice-id-feedback' + (cls ? ' ' + cls : '');
            modalIdFeedback.textContent = msg || '';
        }

        if (modalIdBtn)   modalIdBtn.addEventListener('click', addVoiceById);
        if (modalIdInput) {
            modalIdInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); addVoiceById(); }
            });
        }
    }

});

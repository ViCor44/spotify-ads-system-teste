<?php
// public/pages/tts_announcement.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/list_elevenlabs_voices.php';
require_once __DIR__ . '/../../api/tts_settings.php';

$lastData = $_SESSION['last_tts_data'] ?? [];
$defaultGong = array_key_exists('custom_gong', $lastData)
    ? (int)!empty($lastData['custom_gong'])
    : 1; // <- predefinição ligada
// Carrega voice_settings (estabilidade, similaridade, estilo, amplificador)
$ttsVoiceSettings = tts_get_voice_settings();

// Modelo ativo (v2 / v3 / turbo / flash) e lista de modelos suportados
$ttsModelId       = tts_get_model_id();
$ttsSupportedMods = tts_supported_models();


// Carrega vozes ElevenLabs (com cache). Em caso de falha, mostra apenas a voz default.
$ttsVoices  = [];
$voicesError = null;
try {
    $ttsVoices = get_elevenlabs_voices();
} catch (Throwable $e) {
    $voicesError = $e->getMessage();
}
// Voz predefinida: preferência do utilizador (storage/tts_settings.json) > constante em config/database.php
$defaultVoiceId  = tts_get_default_voice_id();
$selectedVoiceId = $lastData['voice_id'] ?? '';
$favoriteVoiceIds = tts_get_favorite_voice_ids();

/**
 * Tenta identificar o sotaque/idioma de uma voz a partir dos labels da ElevenLabs.
 * Devolve uma chave normalizada (ex.: 'pt-pt', 'pt-br', 'en-gb', 'en-us', 'es-es', 'fr-fr', 'other').
 */
function classifyVoiceAccent(array $v): array {
    $labels = is_array($v['labels'] ?? null) ? $v['labels'] : [];
    $haystack = strtolower(implode(' | ', [
        $v['name'] ?? '',
        $labels['accent']      ?? '',
        $labels['description'] ?? '',
        $labels['language']    ?? '',
        $labels['use_case']    ?? '',
    ]));

    // Português
    $isPortuguese =
        strpos($haystack, 'portug') !== false ||
        strpos($haystack, 'pt-') !== false ||
        strpos($haystack, ' pt ') !== false ||
        strpos($haystack, 'brazil') !== false ||
        strpos($haystack, 'brasil') !== false;

    if ($isPortuguese) {
        $isPT = (
            strpos($haystack, 'portugal') !== false ||
            strpos($haystack, 'european') !== false ||
            strpos($haystack, 'pt-pt') !== false ||
            strpos($haystack, 'lusitan') !== false
        );
        $isBR = (
            strpos($haystack, 'brazil') !== false ||
            strpos($haystack, 'brasil') !== false ||
            strpos($haystack, 'pt-br') !== false
        );
        if ($isPT && !$isBR) return ['key' => 'pt-pt', 'label' => 'Português (Portugal)'];
        if ($isBR && !$isPT) return ['key' => 'pt-br', 'label' => 'Português (Brasil)'];
        return ['key' => 'pt', 'label' => 'Português'];
    }

    // Inglês
    if (strpos($haystack, 'british') !== false || strpos($haystack, 'en-gb') !== false || strpos($haystack, 'uk') !== false) {
        return ['key' => 'en-gb', 'label' => 'Inglês (Reino Unido)'];
    }
    if (strpos($haystack, 'american') !== false || strpos($haystack, 'en-us') !== false) {
        return ['key' => 'en-us', 'label' => 'Inglês (EUA)'];
    }
    if (strpos($haystack, 'english') !== false) {
        return ['key' => 'en', 'label' => 'Inglês'];
    }

    // Espanhol
    if (strpos($haystack, 'spanish') !== false || strpos($haystack, 'espa') !== false || strpos($haystack, 'es-') !== false) {
        if (strpos($haystack, 'latin') !== false || strpos($haystack, 'mex') !== false) {
            return ['key' => 'es-419', 'label' => 'Espanhol (América Latina)'];
        }
        return ['key' => 'es-es', 'label' => 'Espanhol (Espanha)'];
    }

    // Francês
    if (strpos($haystack, 'french') !== false || strpos($haystack, 'fran') !== false || strpos($haystack, 'fr-') !== false) {
        return ['key' => 'fr-fr', 'label' => 'Francês'];
    }

    // Alemão
    if (strpos($haystack, 'german') !== false || strpos($haystack, 'deutsch') !== false || strpos($haystack, 'de-') !== false) {
        return ['key' => 'de-de', 'label' => 'Alemão'];
    }

    // Italiano
    if (strpos($haystack, 'italian') !== false || strpos($haystack, 'italia') !== false || strpos($haystack, 'it-') !== false) {
        return ['key' => 'it-it', 'label' => 'Italiano'];
    }

    // Holandês
    if (strpos($haystack, 'dutch') !== false || strpos($haystack, 'nederland') !== false || strpos($haystack, 'nl-') !== false) {
        return ['key' => 'nl-nl', 'label' => 'Holandês'];
    }

    // Polaco
    if (strpos($haystack, 'polish') !== false || strpos($haystack, 'polski') !== false || strpos($haystack, 'pl-') !== false) {
        return ['key' => 'pl-pl', 'label' => 'Polaco'];
    }

    return ['key' => 'other', 'label' => 'Outro / Multilingue'];
}

// Anota cada voz com o sotaque classificado
foreach ($ttsVoices as &$_v) {
    $_v['_accent'] = classifyVoiceAccent($_v);
}
unset($_v);

// Ordena: primeiro PT-PT, depois variantes PT, depois outros idiomas comuns
$accentOrder = [
    'pt-pt' => 0, 'pt' => 1, 'pt-br' => 2,
    'es-es' => 3, 'es-419' => 4,
    'en-gb' => 5, 'en-us' => 6, 'en' => 7,
    'fr-fr' => 8,
    'de-de' => 9,
    'it-it' => 10,
    'nl-nl' => 11,
    'pl-pl' => 12,
    'other' => 99,
];
usort($ttsVoices, function ($a, $b) use ($accentOrder, $favoriteVoiceIds) {
    $fa = in_array($a['voice_id'], $favoriteVoiceIds, true) ? 0 : 1;
    $fb = in_array($b['voice_id'], $favoriteVoiceIds, true) ? 0 : 1;
    if ($fa !== $fb) return $fa <=> $fb;
    $oa = $accentOrder[$a['_accent']['key']] ?? 99;
    $ob = $accentOrder[$b['_accent']['key']] ?? 99;
    if ($oa !== $ob) return $oa <=> $ob;
    return strcasecmp($a['name'], $b['name']);
});

// Agrupa por sotaque (em vez de categoria) para o <select>
$voicesByAccent = [];
foreach ($ttsVoices as $v) {
    $key   = $v['_accent']['key'];
    $label = $v['_accent']['label'];
    $voicesByAccent[$key]['label']    = $label;
    $voicesByAccent[$key]['voices'][] = $v;
}

// =================== Configuração por idioma ===================
$languageDefs = [
    'pt' => [
        'label'           => 'Português',
        'flag'            => 'PT',
        'preferred_accents' => ['pt-pt', 'pt', 'pt-br'],
    ],
    'en' => [
        'label'           => 'Inglês',
        'flag'            => 'EN',
        'preferred_accents' => ['en-gb', 'en-us', 'en'],
    ],
    'es' => [
        'label'           => 'Espanhol',
        'flag'            => 'ES',
        'preferred_accents' => ['es-es', 'es-419'],
    ],
    'fr' => [
        'label'           => 'Francês',
        'flag'            => 'FR',
        'preferred_accents' => ['fr-fr'],
    ],
];

// Para cada idioma calcula: voz predefinida, voz selecionada, sotaques disponíveis
// e a lista de vozes a mostrar (apenas vozes desse idioma).
$defaultVoiceByLang = tts_get_all_default_voices_by_lang();
$selectedVoiceByLang = $lastData['voice_id_by_lang'] ?? [];

// Como `eleven_multilingual_v2` consegue usar qualquer voz para qualquer idioma,
// mostramos TODAS as vozes da conta em cada linha de idioma. O utilizador escolhe
// e define como predefinida a que preferir para cada idioma.
$allVoicesGroup = [
    'label'  => 'Todas as vozes',
    'voices' => $ttsVoices,
];

$voiceByLangState = [];
foreach ($languageDefs as $lang => $def) {
    $langVoicesByAccent = !empty($ttsVoices)
        ? ['all' => $allVoicesGroup]
        : [];

    $defaultVoice = $defaultVoiceByLang[$lang] ?? '';
    $selected     = !empty($selectedVoiceByLang[$lang]) ? $selectedVoiceByLang[$lang] : $defaultVoice;

    // Valida: se a voz selecionada não existir entre as vozes da conta,
    // cai para a primeira voz disponível.
    $validIds = array_column($ttsVoices, 'voice_id');
    if (!in_array($selected, $validIds, true)) {
        $selected = !empty($ttsVoices) ? $ttsVoices[0]['voice_id'] : '';
    }

    $voiceByLangState[$lang] = [
        'label'             => $def['label'],
        'flag'              => $def['flag'],
        'default_voice'     => $defaultVoice,
        'selected'          => $selected,
        'voices_by_accent'  => $langVoicesByAccent,
        'has_voices'        => !empty($ttsVoices),
    ];
}
?>
<h1><i class="fa-solid fa-microphone-lines"></i> Anúncio Dinâmico (Texto para Voz)</h1>
<p>Selecione o tipo de anúncio, preencha a informação e selecione os idiomas desejados.</p>

<div class="box box-compact">
    <h2>Gerar Anúncio Dinâmico</h2>
    <form id="tts-form" action="../api/generate_tts_announcement_test.php" method="post">
        
        <label for="announcement_type">Tipo de Anúncio:</label>
        <select id="announcement_type" name="announcement_type">
            <option value="plate" <?= ($lastData['announcement_type'] ?? 'plate') === 'plate' ? 'selected' : '' ?>>Matrícula de Veículo</option>
            <option value="child" <?= ($lastData['announcement_type'] ?? '') === 'child' ? 'selected' : '' ?>>Criança Perdida</option>
            <option value="person" <?= ($lastData['announcement_type'] ?? '') === 'person' ? 'selected' : '' ?>>Chamar Pessoa</option>
            <option value="custom" <?= ($lastData['announcement_type'] ?? '') === 'custom' ? 'selected' : '' ?>>Personalizado</option> <!-- NOVA OPÇÃO -->
        </select>

        <!-- Grupos de Campos -->
        <div id="plate-input-group" style="display: none;">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1;">
                    <label for="vehicle_make">Marca:</label>
                    <input type="text" id="vehicle_make" name="vehicle_make" list="vehicle-make-list"
                           placeholder="Selecione ou escreva uma marca"
                           value="<?= htmlspecialchars($lastData['vehicle_make'] ?? '') ?>" autocomplete="off">
                    <datalist id="vehicle-make-list"></datalist>
                </div>
                <div style="flex: 1;">
                    <label for="vehicle_model">Modelo:</label>
                    <input type="text" id="vehicle_model" name="vehicle_model" list="vehicle-model-list"
                           placeholder="Selecione ou escreva um modelo"
                           value="<?= htmlspecialchars($lastData['vehicle_model'] ?? '') ?>" autocomplete="off">
                    <datalist id="vehicle-model-list"></datalist>
                </div>
                <div style="flex: 1;">
                    <label for="vehicle_color">Cor:</label>
                    <input type="text" id="vehicle_color" name="vehicle_color" list="vehicle-color-list"
                           placeholder="Selecione ou escreva uma cor"
                           value="<?= htmlspecialchars($lastData['vehicle_color'] ?? '') ?>" autocomplete="off">
                    <datalist id="vehicle-color-list"></datalist>
                </div>
            </div>
            <label for="license_plate">Matrícula do Veículo:</label>
            <input type="text" id="license_plate" name="license_plate" placeholder="Ex: AA 25 ZB" 
                   value="<?= htmlspecialchars($lastData['license_plate'] ?? '') ?>"
                   style="text-transform:uppercase; font-size: 1.2em; text-align: center; letter-spacing: 2px;">
            <div class="vehicle-fields-actions">
                <button type="button" id="clear-vehicle-fields" class="clear-fields-btn">
                    <i class="fa-solid fa-eraser" aria-hidden="true"></i> Limpar campos
                </button>
            </div>
        </div>

        <div id="child-input-group" style="display: none;">
            <label for="child_name">Nome da Criança:</label>
            <input type="text" id="child_name" name="child_name" placeholder="Ex: Maria Silva"
                   value="<?= htmlspecialchars($lastData['child_name'] ?? '') ?>">
        </div>

        <div id="person-input-group" style="display: none;">
            <label for="person_name">Nome da Pessoa:</label>
            <input type="text" id="person_name" name="person_name" placeholder="Ex: João Martins"
                   value="<?= htmlspecialchars($lastData['person_name'] ?? '') ?>">
        </div>

        <!-- NOVO GRUPO DE CAMPOS PARA O ANÚNCIO PERSONALIZADO -->
        <!-- NOVO: Campo para Anúncio Personalizado -->
        <div id="custom-input-group">
            <label for="custom_text">Texto do Anúncio:</label>
            <textarea id="custom_text" name="custom_text" placeholder="Escreva aqui o seu anúncio personalizado..."><?= htmlspecialchars($lastData['custom_text'] ?? '') ?></textarea>
        </div>

        <div class="gong-option">
            <input type="checkbox" id="custom_gong" name="custom_gong" value="1" <?= $defaultGong ? 'checked' : '' ?>>
            <label for="custom_gong">Tocar gong antes do anúncio</label>
        </div>
        <p class="gong-hint">Dica: frases curtas melhoram a síntese de voz.</p>

        <!-- Seletor de Idiomas (mantém-se igual) -->
        <label>Idiomas para o Anúncio:</label>
        <div class="day-selector">
            <?php $lastLangs = $lastData['languages'] ?? ['pt']; ?>
            <input type="checkbox" name="languages[]" value="pt" id="lang-pt" <?= in_array('pt', $lastLangs) ? 'checked' : '' ?>>
            <label for="lang-pt">Português</label>
            <input type="checkbox" name="languages[]" value="en" id="lang-en" <?= in_array('en', $lastLangs) ? 'checked' : '' ?>>
            <label for="lang-en">Inglês</label>
            <input type="checkbox" name="languages[]" value="es" id="lang-es" <?= in_array('es', $lastLangs) ? 'checked' : '' ?>>
            <label for="lang-es">Espanhol</label>
            <input type="checkbox" name="languages[]" value="fr" id="lang-fr" <?= in_array('fr', $lastLangs) ? 'checked' : '' ?>>
            <label for="lang-fr">Francês</label>
        </div>
        
        <p style="font-size: 0.9em; color: #6c757d; margin-top: -10px; margin-bottom: 20px;">Pelo menos um idioma deve ser selecionado.</p>
    <!-- Controlos de voz (naturalidade e estilo) -->
    <label style="display: block; margin-bottom: 8px;"><i class="fa-solid fa-sliders"></i> Ajustes de Voz</label>
    <div class="voice-settings-group">
      <div class="voice-setting-row voice-setting-model">
        <label for="voice-model">Modelo ElevenLabs:</label>
        <select id="voice-model" class="voice-model-select">
          <?php foreach ($ttsSupportedMods as $mid => $mlabel): ?>
            <option value="<?= htmlspecialchars($mid) ?>" <?= ($mid === $ttsModelId) ? 'selected' : '' ?>>
              <?= htmlspecialchars($mlabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="voice-model-status" id="voice-model-status" aria-live="polite"></span>
      </div>
      <div class="voice-setting-row">
        <label for="voice-stability">Estabilidade:</label>
        <input type="range" id="voice-stability" class="voice-setting-slider" name="voice_settings[stability]" min="0" max="100" value="<?= (int)($ttsVoiceSettings['stability'] * 100) ?>" data-setting="stability">
        <span class="voice-setting-value" data-target="voice-stability"><?= (int)($ttsVoiceSettings['stability'] * 100) ?>%</span>
      </div>
      <div class="voice-setting-row">
        <label for="voice-similarity">Similaridade da Voz:</label>
        <input type="range" id="voice-similarity" class="voice-setting-slider" name="voice_settings[similarity_boost]" min="0" max="100" value="<?= (int)($ttsVoiceSettings['similarity_boost'] * 100) ?>" data-setting="similarity_boost">
        <span class="voice-setting-value" data-target="voice-similarity"><?= (int)($ttsVoiceSettings['similarity_boost'] * 100) ?>%</span>
      </div>
      <div class="voice-setting-row">
        <label for="voice-style">Estilo:</label>
        <input type="range" id="voice-style" class="voice-setting-slider" name="voice_settings[style]" min="0" max="100" value="<?= (int)($ttsVoiceSettings['style'] * 100) ?>" data-setting="style">
        <span class="voice-setting-value" data-target="voice-style"><?= (int)($ttsVoiceSettings['style'] * 100) ?>%</span>
      </div>
      <div class="voice-setting-row voice-setting-checkbox">
        <input type="checkbox" id="voice-boost" name="voice_settings[use_speaker_boost]" value="1" <?= $ttsVoiceSettings['use_speaker_boost'] ? 'checked' : '' ?>>
        <label for="voice-boost">Amplificador do Orador (melhora a clareza)</label>
      </div>
    </div>
    <p class="gong-hint" style="margin-bottom: 20px;">
      <strong>Dicas:</strong> Estabilidade (0-100) define a variação da voz; inferior = mais expressivo, superior = mais consistente. Similaridade (0-100) = quão próximo da voz original. Estilo (0-100) = intensidade do caractér. Amplificador = melhora a presença da voz.
    </p>


        <!-- Seletores de Voz por Idioma (ElevenLabs) -->
        <label>Voz por Idioma:</label>

        <?php if (empty($ttsVoices)): ?>
            <p class="gong-hint" style="color:#b91c1c;">
                <?= $voicesError
                    ? 'Não foi possível obter a lista de vozes da ElevenLabs: ' . htmlspecialchars($voicesError)
                    : 'Sem vozes disponíveis na conta ElevenLabs.' ?>
            </p>
        <?php else: ?>
            <div class="voices-by-lang">
            <?php foreach ($voiceByLangState as $lang => $st): ?>
                <div class="voice-lang-row"
                     data-lang="<?= htmlspecialchars($lang) ?>">
                  <div class="voice-lang-row-inner">
                    <div class="voice-lang-head">
                        <span class="lang-flag"><?= htmlspecialchars($st['flag']) ?></span>
                        <span class="lang-name"><?= htmlspecialchars($st['label']) ?></span>
                    </div>

                    <?php if (!$st['has_voices']): ?>
                        <div class="voice-lang-controls voice-lang-empty">
                            <em>Sem vozes <?= htmlspecialchars($st['label']) ?> na sua conta ElevenLabs.</em>
                            <input type="hidden" name="voice_id_by_lang[<?= htmlspecialchars($lang) ?>]" value="">
                            <button type="button" class="add-voice-btn"
                                    data-lang="<?= htmlspecialchars($lang) ?>"
                                    data-label="<?= htmlspecialchars($st['label']) ?>"
                                    title="Procurar vozes <?= htmlspecialchars($st['label']) ?> na biblioteca pública">
                                <i class="fa-solid fa-plus"></i> Adicionar voz
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="voice-lang-controls">
                            <select name="voice_id_by_lang[<?= htmlspecialchars($lang) ?>]"
                                    class="voice-select voice-select-lang"
                                    data-lang="<?= htmlspecialchars($lang) ?>"
                                    id="voice-select-<?= htmlspecialchars($lang) ?>">
                                <?php foreach ($st['voices_by_accent'] as $accentKey => $group): ?>
                                    <?php foreach ($group['voices'] as $v):
                                        $accentLabel = $v['_accent']['label'] ?? '';
                                        $bits = [];
                                        if ($accentLabel !== '')                  $bits[] = $accentLabel;
                                        if (!empty($v['labels']['gender']))      $bits[] = $v['labels']['gender'];
                                        if (!empty($v['labels']['description'])) $bits[] = $v['labels']['description'];
                                        $extra = $bits ? ' — ' . implode(' · ', $bits) : '';
                                        $isDefault = ($v['voice_id'] === $st['default_voice']);
                                        $isFavorite = in_array($v['voice_id'], $favoriteVoiceIds, true);
                                    ?>
                                        <option value="<?= htmlspecialchars($v['voice_id']) ?>"
                                            data-preview="<?= htmlspecialchars($v['preview_url']) ?>"
                                            data-name="<?= htmlspecialchars($v['name']) ?>"
                                            data-extra="<?= htmlspecialchars($extra) ?>"
                                            data-accent="<?= htmlspecialchars($accentLabel) ?>"
                                            data-favorite="<?= $isFavorite ? '1' : '0' ?>"
                                            <?= ($v['voice_id'] === $st['selected']) ? 'selected' : '' ?>>
                                            <?= $isFavorite ? '♥ ' : '' ?><?= $isDefault ? '★ ' : '' ?><?= htmlspecialchars($v['name'] . $extra) ?><?= $isDefault ? ' (predefinida)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>

                            <button type="button" class="favorite-btn"
                                    data-lang="<?= htmlspecialchars($lang) ?>"
                                    title="Adicionar ou remover a voz selecionada das preferidas"
                                    aria-label="Alternar voz preferida">
                                <i class="fa-regular fa-heart"></i>
                            </button>

                            <button type="button" class="default-btn set-default-btn"
                                    data-lang="<?= htmlspecialchars($lang) ?>"
                                    data-current="<?= htmlspecialchars($st['default_voice']) ?>"
                                    title="Definir como voz predefinida para <?= htmlspecialchars($st['label']) ?>">
                                <i class="fa-solid fa-star"></i> Predefinir
                            </button>
                            <button type="button" class="add-voice-btn add-voice-btn-compact"
                                    data-lang="<?= htmlspecialchars($lang) ?>"
                                    data-label="<?= htmlspecialchars($st['label']) ?>"
                                    title="Adicionar mais vozes <?= htmlspecialchars($st['label']) ?>">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                  </div>
                </div>
            <?php endforeach; ?>
            </div>

            <p class="gong-hint">
                <strong>Dica:</strong> cada idioma mostra apenas vozes desse idioma. Escolha a voz e clique em <em>Predefinir</em> para guardar.
                Se algum idioma não tiver vozes, adicione uma voz desse idioma à sua biblioteca ElevenLabs e atualize a cache:
                <code>api/list_elevenlabs_voices.php?refresh=1</code>.
            </p>
        <?php endif; ?>

        <button id="generate-btn" type="submit">Gerar e Tocar Anúncio</button>
    </form>
</div>

<!-- Modal global de pesquisa/adicionar vozes (Voice Library da ElevenLabs) -->
<div class="add-voice-modal" id="add-voice-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="add-voice-modal-title">
    <div class="add-voice-modal__backdrop" data-close="1"></div>
    <div class="add-voice-modal__dialog" role="document">
        <div class="add-voice-modal__header">
            <h3 id="add-voice-modal-title">
                <i class="fa-solid fa-microphone"></i>
                Adicionar voz — <span class="add-voice-modal__lang">—</span>
            </h3>
            <button type="button" class="add-voice-modal__close" data-close="1" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="add-voice-modal__body">
            <div class="add-voice-toolbar">
                <input type="text" class="add-voice-search" placeholder="Procurar por nome, sotaque, descrição…">
                <select class="add-voice-gender">
                    <option value="">Qualquer género</option>
                    <option value="female">Feminino</option>
                    <option value="male">Masculino</option>
                </select>
                <button type="button" class="add-voice-search-btn">
                    <i class="fa-solid fa-magnifying-glass"></i> Procurar
                </button>
            </div>

            <!-- Adicionar voz manualmente por ID (v3, partilhadas, etc.) -->
            <div class="add-voice-by-id">
                <div class="add-voice-by-id__title">
                    <i class="fa-solid fa-key"></i> Adicionar voz por ID
                </div>
                <p class="add-voice-by-id__hint">
                    Cola aqui o <code>voice_id</code> (útil para vozes do modelo <strong>v3</strong> ou outras não listadas). Fica guardado localmente e passa a aparecer no seletor.
                </p>
                <div class="add-voice-by-id__row">
                    <input type="text" class="add-voice-id-input" placeholder="voice_id (ex.: 21m00Tcm4TlvDq8ikWAM)">
                    <input type="text" class="add-voice-id-name" placeholder="Nome (opcional)">
                    <button type="button" class="add-voice-id-btn">
                        <i class="fa-solid fa-plus"></i> Adicionar por ID
                    </button>
                </div>
                <p class="add-voice-id-feedback" aria-live="polite"></p>
            </div>

            <div class="add-voice-results">
                <p class="add-voice-hint"><i class="fa-solid fa-info-circle"></i> A pesquisar vozes na biblioteca pública da ElevenLabs…</p>
            </div>
        </div>
    </div>
</div>

<style>
  .vehicle-fields-actions {
    display: flex;
    justify-content: flex-end;
    margin: -10px 0 18px;
  }

  form .clear-fields-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #fff;
    color: #475569;
    font-size: .9rem;
    font-weight: 600;
  }

  form .clear-fields-btn:hover {
    border-color: #94a3b8;
    background: #f8fafc;
    color: #1e293b;
  }

  /* Caixa de texto */
  #custom-input-group textarea {
    width: 100%;
    min-height: 120px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    line-height: 1.5;
    background: #fff;
    resize: vertical;
    box-shadow: inset 0 1px 2px rgba(0,0,0,.04);
    transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
    box-sizing: border-box;        /* garante que padding + border contam no width */
  }

  #custom-input-group textarea:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 4px rgba(34,197,94,.15), inset 0 1px 2px rgba(0,0,0,.04);
    background-color: #fcfffc;
  }

  #custom-input-group textarea::placeholder {
    color: #9ca3af;
  }

  #custom-input-group > label[for="custom_text"] {
    display: inline-block;
    margin-bottom: 8px;
    font-weight: 600;
  }

  /* Linha do checkbox + texto */
  .gong-option {
    display: flex;
    align-items: center;
    gap: 8px;              /* espaço entre a caixa e o texto */
    margin: 8px 0 4px;
  }

  .gong-option input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;             /* remove offset estranho */
  }

  .gong-option label {
    margin: 0;
    font-weight: 500;
  }

  .gong-hint {
    font-size: 0.85rem;
    color: #6b7280;
    margin-left: 26px;     /* alinha com o texto da opção */
  }

  /* Seletor de voz */
  .voice-row {
    display: flex;
    gap: 10px;
    align-items: stretch;
    margin-bottom: 6px;
  }
  .voice-filter {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
  }
  .voice-filter-label {
    margin: 0;
    font-size: 0.9rem;
    color: #374151;
    white-space: nowrap;
  }
  .voice-filter-select {
    flex: 1;
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
    font-size: 0.9rem;
  }
  .voice-select {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    font-size: 0.95rem;
    box-shadow: inset 0 1px 2px rgba(0,0,0,.04);
  }
  .voice-select:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 4px rgba(34,197,94,.15), inset 0 1px 2px rgba(0,0,0,.04);
  }
  .preview-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    background: #f9fafb;
    color: #111827;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
  }
  .preview-btn:hover    { background: #f3f4f6; border-color: #9ca3af; }
  .preview-btn:disabled { opacity: .6; cursor: not-allowed; }
  .preview-btn.playing  { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

  /* Botão "Definir como predefinida" */
  .default-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    border: 1px solid #fbbf24;
    border-radius: 10px;
    background: #fffbeb;
    color: #92400e;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
  }
  .default-btn:hover            { background: #fef3c7; border-color: #f59e0b; }
  .default-btn:disabled         { opacity: .6; cursor: not-allowed; }
  .default-btn.is-current       { background: #ecfdf5; border-color: #34d399; color: #065f46; }
  .default-btn.is-current i     { color: #059669; }

  /* Voz por idioma */
  .voices-by-lang {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 8px;
  }
  .voice-lang-row {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
  }
  .voice-lang-row-inner {
    display: flex;
    align-items: stretch;
    gap: 10px;
  }
  .voice-lang-head {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 70px;
    padding: 4px 8px;
    border-right: 1px solid #f3f4f6;
  }
  .voice-lang-head .lang-flag {
    font-weight: 700;
    font-size: 0.95rem;
    color: #1f2937;
    background: #eef2ff;
    border-radius: 6px;
    padding: 2px 8px;
    letter-spacing: 1px;
  }
  .voice-lang-head .lang-name {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 4px;
  }
  .voice-lang-controls {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) auto auto auto;
    gap: 8px;
    flex: 1;
    align-items: stretch;
  }
  .voice-lang-controls .voice-select {
    margin: 0;
  }
  .voice-lang-controls .default-btn {
    padding: 0 12px;
    font-size: 0.85rem;
  }
  .favorite-btn {
    min-width: 42px;
    padding: 0 11px;
    border: 1px solid #f9a8d4;
    border-radius: 8px;
    background: #fff;
    color: #be185d;
    cursor: pointer;
  }
  .favorite-btn:hover,
  .favorite-btn.is-current { background: #fdf2f8; border-color: #ec4899; }
  .favorite-btn:disabled { opacity: .6; cursor: not-allowed; }
  .voice-lang-empty {
    display: flex;
    align-items: center;
    color: #9ca3af;
    font-size: 0.9rem;
    padding: 8px;
  }

  /* Botão "Adicionar voz" */
  .add-voice-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    border: 1px solid #93c5fd;
    border-radius: 10px;
    background: #eff6ff;
    color: #1e40af;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
  }
  .add-voice-btn:hover      { background: #dbeafe; border-color: #60a5fa; }
  .add-voice-btn-compact    { padding: 0 10px; min-width: 0; }
  .voice-lang-empty .add-voice-btn { margin-left: auto; }

  /* Painel de pesquisa de vozes */
  .add-voice-panel {
    border-top: 1px dashed #d1d5db;
    padding-top: 10px;
    margin-top: 4px;
  }
  .add-voice-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    flex-wrap: wrap;
  }
  .add-voice-toolbar .add-voice-search {
    flex: 1;
    min-width: 180px;
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
  }
  .add-voice-toolbar .add-voice-gender {
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
    font-size: 0.85rem;
  }
  .add-voice-toolbar .add-voice-search-btn,
  .add-voice-toolbar .add-voice-close-btn {
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #f9fafb;
    color: #111827;
    font-size: 0.85rem;
    cursor: pointer;
  }
  .add-voice-toolbar .add-voice-search-btn:hover,
  .add-voice-toolbar .add-voice-close-btn:hover { background: #f3f4f6; }
  .add-voice-toolbar .add-voice-close-btn       { color: #b91c1c; border-color: #fca5a5; background: #fef2f2; }

  .add-voice-results {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px;
    max-height: 420px;
    overflow-y: auto;
    padding: 4px;
  }
  .add-voice-card {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 0.85rem;
  }
  .add-voice-card .name {
    font-weight: 600;
    color: #111827;
  }
  .add-voice-card .naturalidade {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    align-self: flex-start;
    padding: 2px 8px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1e3a8a;
    font-size: 0.78rem;
    line-height: 1.4;
  }
  .add-voice-card .naturalidade i { color: #2563eb; }
  .add-voice-card .naturalidade-label { color: #1e40af; font-weight: 600; }
  .add-voice-card .naturalidade-value { color: #1e3a8a; }
  .add-voice-card .meta {
    color: #6b7280;
    font-size: 0.8rem;
  }
  .add-voice-card .add-btn {
    margin-top: auto;
    padding: 6px 10px;
    border: 1px solid #34d399;
    border-radius: 8px;
    background: #ecfdf5;
    color: #065f46;
    font-size: 0.8rem;
    cursor: pointer;
  }
  .add-voice-card .add-btn:hover    { background: #d1fae5; }
  .add-voice-card .add-btn:disabled { opacity: .6; cursor: not-allowed; }
  .add-voice-card .add-btn.is-added { background: #ecfdf5; border-color: #10b981; color: #047857; }

  .add-voice-hint {
    grid-column: 1 / -1;
    margin: 0;
    padding: 12px;
    color: #6b7280;
    font-size: 0.85rem;
    background: #f9fafb;
    border: 1px dashed #e5e7eb;
    border-radius: 8px;
  }
  .add-voice-hint.error { color: #b91c1c; border-color: #fca5a5; background: #fef2f2; }
  /* ===================== Voice Settings (Sliders) ===================== */
  .voice-settings-group {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 12px 14px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    margin-bottom: 14px;
  }
  .voice-setting-row {
    display: grid;
    grid-template-columns: 140px 1fr 60px;
    gap: 12px;
    align-items: center;
  }
  .voice-setting-row label {
    font-size: 0.95rem;
    font-weight: 500;
    color: #374151;
    margin: 0;
  }
  .voice-setting-slider {
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: #d1d5db;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
  }
  .voice-setting-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #2563eb;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,.15);
  }
  .voice-setting-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #2563eb;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,.15);
  }
  .voice-setting-slider:focus::-webkit-slider-thumb {
    box-shadow: 0 0 0 4px rgba(37,99,235,.2);
  }
  .voice-setting-value {
    text-align: center;
    font-weight: 600;
    color: #2563eb;
    font-size: 0.9rem;
    min-width: 50px;
  }
  .voice-setting-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    grid-column: 1 / -1;
  }
  .voice-setting-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
  }
  .voice-setting-checkbox label {
    margin: 0;
    cursor: pointer;
  }


  /* ===================== Modal global ===================== */
  .add-voice-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .add-voice-modal[hidden] { display: none; }
  .add-voice-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(2px);
  }
  .add-voice-modal__dialog {
    position: relative;
    background: #fff;
    border-radius: 14px;
    width: min(880px, 100%);
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
    overflow: hidden;
  }
  .add-voice-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
  }
  .add-voice-modal__header h3 {
    margin: 0;
    font-size: 1.05rem;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .add-voice-modal__header h3 i { color: #2563eb; }
  .add-voice-modal__lang { color: #1e3a8a; font-weight: 700; }
  .add-voice-modal__close {
    border: none;
    background: transparent;
    color: #6b7280;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
  }
  .add-voice-modal__close:hover { background: #f3f4f6; color: #111827; }
  .add-voice-modal__body {
    padding: 14px 18px 18px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .add-voice-modal .add-voice-results {
    max-height: none;
    flex: 1;
  }

  @media (max-width: 720px) {
    .voice-lang-row { flex-direction: column; }
    .voice-lang-head {
      flex-direction: row;
      gap: 10px;
      border-right: none;
      border-bottom: 1px solid #f3f4f6;
      padding-bottom: 8px;
      min-width: 0;
      justify-content: flex-start;
    }
    .voice-lang-controls {
      grid-template-columns: 1fr 1fr;
    }
    .voice-lang-controls .voice-select { grid-column: 1 / -1; }
  }

  /* Botão "Definir como predefinida" */
  .default-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    border: 1px solid #fbbf24;
    border-radius: 10px;
    background: #fffbeb;
    color: #92400e;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
  }
  .default-btn:hover            { background: #fef3c7; border-color: #f59e0b; }
  .default-btn:disabled         { opacity: .6; cursor: not-allowed; }
  .default-btn.is-current       { background: #ecfdf5; border-color: #34d399; color: #065f46; }
  .default-btn.is-current i     { color: #059669; }

  /* Seletor de modelo ElevenLabs */
  .voice-setting-model {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding-bottom: 8px;
    border-bottom: 1px dashed #e5e7eb;
    margin-bottom: 8px;
  }
  .voice-setting-model label { min-width: 160px; margin: 0; }
  .voice-model-select {
    flex: 1 1 240px;
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #fff;
    font-size: 0.95rem;
  }
  .voice-model-status {
    font-size: 0.85rem;
    color: #6b7280;
    min-height: 1em;
  }
  .voice-model-status.is-ok    { color: #059669; }
  .voice-model-status.is-err   { color: #b91c1c; }

  /* "Adicionar voz por ID" no modal */
  .add-voice-by-id {
    margin: 12px 0 16px;
    padding: 12px 14px;
    border: 1px dashed #93c5fd;
    background: #eff6ff;
    border-radius: 10px;
  }
  .add-voice-by-id__title {
    font-weight: 600;
    color: #1e3a8a;
    margin-bottom: 4px;
  }
  .add-voice-by-id__title i { color: #2563eb; margin-right: 4px; }
  .add-voice-by-id__hint {
    font-size: 0.85rem;
    color: #475569;
    margin: 0 0 8px;
  }
  .add-voice-by-id__hint code {
    background: #dbeafe;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 0.85em;
  }
  .add-voice-by-id__row {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 8px;
  }
  .add-voice-by-id__row input {
    padding: 6px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    background: #fff;
  }
  .add-voice-id-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid #2563eb;
    background: #2563eb;
    color: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
  }
  .add-voice-id-btn:hover     { background: #1d4ed8; border-color: #1d4ed8; }
  .add-voice-id-btn:disabled  { opacity: .6; cursor: not-allowed; }
  .add-voice-id-feedback {
    margin: 6px 0 0;
    font-size: 0.85rem;
    min-height: 1em;
    color: #334155;
  }
  .add-voice-id-feedback.is-ok  { color: #059669; }
  .add-voice-id-feedback.is-err { color: #b91c1c; }
  @media (max-width: 640px) {
    .add-voice-by-id__row { grid-template-columns: 1fr; }
  }
</style>



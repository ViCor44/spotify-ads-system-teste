<?php
// public/pages/tts_announcement.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/list_elevenlabs_voices.php';
require_once __DIR__ . '/../../api/tts_settings.php';

$lastData = $_SESSION['last_tts_data'] ?? [];
$defaultGong = array_key_exists('custom_gong', $lastData)
    ? (int)!empty($lastData['custom_gong'])
    : 1; // <- predefinição ligada

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
usort($ttsVoices, function ($a, $b) use ($accentOrder) {
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

$voiceByLangState = [];
foreach ($languageDefs as $lang => $def) {
    // Filtra vozes deste idioma (apenas os sotaques preferidos)
    $langVoicesByAccent = [];
    foreach ($def['preferred_accents'] as $acc) {
        if (!empty($voicesByAccent[$acc]['voices'])) {
            $langVoicesByAccent[$acc] = $voicesByAccent[$acc];
        }
    }

    // Lista plana de vozes deste idioma
    $langVoicesFlat = [];
    foreach ($langVoicesByAccent as $group) {
        foreach ($group['voices'] as $v) $langVoicesFlat[] = $v;
    }

    $defaultVoice = $defaultVoiceByLang[$lang] ?? '';
    $selected     = !empty($selectedVoiceByLang[$lang]) ? $selectedVoiceByLang[$lang] : $defaultVoice;

    // Valida: se a voz selecionada não existir entre as vozes deste idioma,
    // cai para a primeira voz do sotaque preferido com vozes disponíveis.
    $langValidIds = array_column($langVoicesFlat, 'voice_id');
    if (!in_array($selected, $langValidIds, true)) {
        $selected = !empty($langVoicesFlat) ? $langVoicesFlat[0]['voice_id'] : '';
    }

    $voiceByLangState[$lang] = [
        'label'             => $def['label'],
        'flag'              => $def['flag'],
        'default_voice'     => $defaultVoice,
        'selected'          => $selected,
        'voices_by_accent'  => $langVoicesByAccent,
        'has_voices'        => !empty($langVoicesFlat),
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
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="vehicle_make">Marca:</label>
                    <input type="text" id="vehicle_make" name="vehicle_make" placeholder="Ex: BMW" 
                           value="<?= htmlspecialchars($lastData['vehicle_make'] ?? '') ?>">
                </div>
                <div style="flex: 1;">
                    <label for="vehicle_model">Modelo:</label>
                    <input type="text" id="vehicle_model" name="vehicle_model" placeholder="Ex: Série 1"
                           value="<?= htmlspecialchars($lastData['vehicle_model'] ?? '') ?>">
                </div>
            </div>
            <label for="license_plate">Matrícula do Veículo:</label>
            <input type="text" id="license_plate" name="license_plate" placeholder="Ex: AA 25 ZB" 
                   value="<?= htmlspecialchars($lastData['license_plate'] ?? '') ?>"
                   style="text-transform:uppercase; font-size: 1.2em; text-align: center; letter-spacing: 2px;">
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
                    <div class="voice-lang-head">
                        <span class="lang-flag"><?= htmlspecialchars($st['flag']) ?></span>
                        <span class="lang-name"><?= htmlspecialchars($st['label']) ?></span>
                    </div>

                    <?php if (!$st['has_voices']): ?>
                        <div class="voice-lang-controls voice-lang-empty">
                            <em>Sem vozes <?= htmlspecialchars($st['label']) ?> na sua conta ElevenLabs.</em>
                            <input type="hidden" name="voice_id_by_lang[<?= htmlspecialchars($lang) ?>]" value="">
                        </div>
                    <?php else: ?>
                        <div class="voice-lang-controls">
                            <?php $accentCount = count($st['voices_by_accent']); ?>
                            <?php if ($accentCount > 1): ?>
                                <select class="voice-accent-filter" data-target-lang="<?= htmlspecialchars($lang) ?>">
                                    <option value="all">Todas as variantes</option>
                                    <?php foreach ($st['voices_by_accent'] as $key => $group): ?>
                                        <option value="<?= htmlspecialchars($key) ?>">
                                            <?= htmlspecialchars($group['label']) ?> (<?= count($group['voices']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <?php $onlyKey = array_key_first($st['voices_by_accent']); ?>
                                <div class="voice-lang-singletag">
                                    <?= htmlspecialchars($st['voices_by_accent'][$onlyKey]['label']) ?>
                                </div>
                            <?php endif; ?>

                            <select name="voice_id_by_lang[<?= htmlspecialchars($lang) ?>]"
                                    class="voice-select voice-select-lang"
                                    data-lang="<?= htmlspecialchars($lang) ?>"
                                    id="voice-select-<?= htmlspecialchars($lang) ?>">
                                <?php foreach ($st['voices_by_accent'] as $accentKey => $group): ?>
                                    <optgroup label="<?= htmlspecialchars($group['label']) ?>" data-accent="<?= htmlspecialchars($accentKey) ?>">
                                        <?php foreach ($group['voices'] as $v):
                                            $bits = [];
                                            if (!empty($v['labels']['gender']))      $bits[] = $v['labels']['gender'];
                                            if (!empty($v['labels']['description'])) $bits[] = $v['labels']['description'];
                                            if (!empty($v['labels']['accent']))      $bits[] = $v['labels']['accent'];
                                            $extra = $bits ? ' — ' . implode(', ', $bits) : '';
                                            $isDefault = ($v['voice_id'] === $st['default_voice']);
                                        ?>
                                            <option value="<?= htmlspecialchars($v['voice_id']) ?>"
                                                data-accent="<?= htmlspecialchars($accentKey) ?>"
                                                data-preview="<?= htmlspecialchars($v['preview_url']) ?>"
                                                data-name="<?= htmlspecialchars($v['name']) ?>"
                                                <?= ($v['voice_id'] === $st['selected']) ? 'selected' : '' ?>>
                                                <?= $isDefault ? '★ ' : '' ?><?= htmlspecialchars($v['name'] . $extra) ?><?= $isDefault ? ' (predefinida)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>

                            <button type="button" class="default-btn set-default-btn"
                                    data-lang="<?= htmlspecialchars($lang) ?>"
                                    data-current="<?= htmlspecialchars($st['default_voice']) ?>"
                                    title="Definir como voz predefinida para <?= htmlspecialchars($st['label']) ?>">
                                <i class="fa-solid fa-star"></i> Predefinir
                            </button>
                        </div>
                    <?php endif; ?>
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

<script src="assets/js/tts.js"></script>
<style>
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
    align-items: stretch;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
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
    grid-template-columns: minmax(160px, 1fr) minmax(220px, 2fr) auto;
    gap: 8px;
    flex: 1;
    align-items: stretch;
  }
  .voice-lang-controls .voice-accent-filter {
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
    font-size: 0.85rem;
  }
  .voice-lang-controls .voice-select {
    margin: 0;
  }
  .voice-lang-controls .default-btn {
    padding: 0 12px;
    font-size: 0.85rem;
  }
  .voice-lang-singletag {
    display: inline-flex;
    align-items: center;
    padding: 0 10px;
    font-size: 0.8rem;
    color: #4b5563;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 8px;
    white-space: nowrap;
  }
  .voice-lang-empty {
    display: flex;
    align-items: center;
    color: #9ca3af;
    font-size: 0.9rem;
    padding: 8px;
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
</style>



<?php
// public/pages/tts_announcement.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/list_elevenlabs_voices.php';

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
$defaultVoiceId  = defined('ELEVENLABS_VOICE_ID') ? ELEVENLABS_VOICE_ID : '';
$selectedVoiceId = $lastData['voice_id'] ?? $defaultVoiceId;

// Agrupa por categoria (premade / cloned / generated / professional ...)
$voicesByCategory = [];
foreach ($ttsVoices as $v) {
    $cat = $v['category'] !== '' ? $v['category'] : 'outras';
    $voicesByCategory[$cat][] = $v;
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

        <!-- Seletor de Voz (ElevenLabs) -->
        <label for="voice_id">Voz do Anúncio:</label>
        <div class="voice-row">
            <select id="voice_id" name="voice_id" class="voice-select">
                <?php if (empty($ttsVoices)): ?>
                    <option value="<?= htmlspecialchars($defaultVoiceId) ?>" selected>
                        Voz predefinida (<?= htmlspecialchars($defaultVoiceId ?: 'sem voz configurada') ?>)
                    </option>
                <?php else: ?>
                    <?php foreach ($voicesByCategory as $cat => $list): ?>
                        <optgroup label="<?= htmlspecialchars(ucfirst($cat)) ?>">
                            <?php foreach ($list as $v):
                                $labelBits = [];
                                if (!empty($v['labels']['gender']))      $labelBits[] = $v['labels']['gender'];
                                if (!empty($v['labels']['accent']))      $labelBits[] = $v['labels']['accent'];
                                if (!empty($v['labels']['description'])) $labelBits[] = $v['labels']['description'];
                                $extra = $labelBits ? ' — ' . implode(', ', $labelBits) : '';
                                $isDefault = ($v['voice_id'] === $defaultVoiceId);
                            ?>
                                <option value="<?= htmlspecialchars($v['voice_id']) ?>"
                                    <?= ($v['voice_id'] === $selectedVoiceId) ? 'selected' : '' ?>
                                    data-preview="<?= htmlspecialchars($v['preview_url']) ?>">
                                    <?= htmlspecialchars($v['name'] . $extra) ?><?= $isDefault ? ' (predefinida)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button type="button" id="preview-voice-btn" class="preview-btn" title="Ouvir amostra da voz">
                <i class="fa-solid fa-play"></i> Pré-visualizar
            </button>
        </div>
        <?php if ($voicesError): ?>
            <p class="gong-hint" style="color:#b91c1c;">
                Não foi possível obter a lista de vozes da ElevenLabs: <?= htmlspecialchars($voicesError) ?>
            </p>
        <?php else: ?>
            <p class="gong-hint">A mesma voz é usada em todos os idiomas selecionados.</p>
        <?php endif; ?>

        <audio id="voice-preview-player" preload="none" style="display:none;"></audio>

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
</style>



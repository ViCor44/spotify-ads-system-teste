<?php
// public/pages/tts_announcement.php
$lastData = $_SESSION['last_tts_data'] ?? [];
$defaultGong = array_key_exists('custom_gong', $lastData)
    ? (int)!empty($lastData['custom_gong'])
    : 1; // <- predefinição ligada
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
</style>



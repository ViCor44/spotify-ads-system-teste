<?php
// public/pages/partials/park_closing_form.php
$daysOfWeek = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
?>
<div class="box box-compact">
    <h2>Agendar Sequência de Fecho do Parque</h2>
    
    <?php if ($placeholderWarning): ?>
        <div class="alert-box alert-warning" style="margin-bottom: 20px;">
            <div class="alert-content">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>Função desativada:</strong> Para agendar o fecho, primeiro deve substituir os ficheiros de áudio provisórios dos quatro anúncios de fecho em "Gerir Anúncios".</span>
            </div>
        </div>
    <?php endif; ?>

    <p style="margin-top: -15px; font-size: 0.9em; color: #6c757d;">Defina a hora a que o parque fecha. O sistema agendará automaticamente os avisos de 15, 10 e 5 minutos.</p>
    
    <form action="../api/schedule_park_closing.php" method="post">
        <fieldset <?= $placeholderWarning ? 'disabled' : '' ?>> <!-- Desativa o formulário inteiro -->
            <label>Selecione os Dias da Semana para o Fecho:</label>
            <div class="day-selector">
                <?php foreach ($daysOfWeek as $num => $day): ?>
                    <input type="checkbox" name="days[]" value="<?= $num ?>" id="day-closing-<?= $num ?>">
                    <label for="day-closing-<?= $num ?>"><?= $day ?></label>
                <?php endforeach; ?>
            </div>

            <label for="closing_time">Hora de Fecho (formato 24h):</label>
            <input type="time" id="closing_time" name="closing_time" required>

            <button type="submit">Agendar Sequência de Fecho</button>
        </fieldset>
    </form>
</div>

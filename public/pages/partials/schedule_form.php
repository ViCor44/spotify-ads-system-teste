<?php
// public/pages/partials/schedule_form.php
$daysOfWeek = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
?>
<div class="box box-compact">
    <h2>Criar Novo Agendamento</h2>
    <form action="../api/create_schedule.php" method="post">
        <label for="announcement_id">Escolha o Anúncio:</label>
        <select id="announcement_id" name="announcement_id" required>
            <option value="">-- Selecione um anúncio --</option>
            <?php foreach ($announcements as $announcement): ?>
                <option value="<?= $announcement['id'] ?>"><?= htmlspecialchars($announcement['title']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Selecione os Dias da Semana:</label>
        <div class="day-selector">
            <!-- Botão "Todos os dias" -->
            <input type="checkbox" id="select-all-days-create">
            <label for="select-all-days-create" class="select-all-label">Todos os dias</label>
            <div class="day-separator"></div>

            <!-- Checkboxes individuais -->
            <?php foreach ($daysOfWeek as $num => $day): ?>
                <input type="checkbox" name="days[]" value="<?= $num ?>" id="day-create-<?= $num ?>" class="day-checkbox-create">
                <label for="day-create-<?= $num ?>"><?= $day ?></label>
            <?php endforeach; ?>
        </div>

        <label for="play_at">Horas de Reprodução (separadas por vírgula):</label>
        <input type="text" id="play_at" name="play_at" placeholder="Ex: 09:00, 14:30, 18:00" required>

        <button type="submit">Guardar Agendamento</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-days-create');
    const dayCheckboxes = document.querySelectorAll('.day-checkbox-create');

    if (selectAllCheckbox && dayCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            dayCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        dayCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    const allChecked = Array.from(dayCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });
    }
});
</script>

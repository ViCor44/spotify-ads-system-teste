<?php
// public/pages/edit_schedule.php

if (!isset($_GET['announcement_id']) || !is_numeric($_GET['announcement_id'])) {
    die("ID de anúncio inválido.");
}
$announcementId = (int)$_GET['announcement_id'];

$schedulesToEdit = [];
$announcementTitle = '';
try {
    $stmt = $pdo->prepare("SELECT day_of_week, play_at FROM schedules WHERE announcement_id = ?");
    $stmt->execute([$announcementId]);
    $schedulesToEdit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtTitle = $pdo->prepare("SELECT title FROM announcements WHERE id = ?");
    $stmtTitle->execute([$announcementId]);
    $announcementTitle = $stmtTitle->fetchColumn();

    if (!$announcementTitle) { die("Anúncio não encontrado."); }
} catch (Exception $e) {
    die("Erro ao carregar os dados para edição: " . $e->getMessage());
}

$selectedDays = array_unique(array_column($schedulesToEdit, 'day_of_week'));
$timesArray = array_unique(array_column($schedulesToEdit, 'play_at'));
$timesString = implode(', ', array_map(fn($time) => date("H:i", strtotime($time)), $timesArray));
$daysOfWeek = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
?>

<h1><i class="fa-solid fa-pencil"></i> Editar Agendamentos de "<?= htmlspecialchars($announcementTitle) ?>"</h1>

<div class="box box-compact">
    <form action="../api/update_schedule.php" method="post">
        <input type="hidden" name="announcement_id" value="<?= $announcementId ?>">

        <label>Dias da Semana Agendados:</label>
        <div class="day-selector">
            <input type="checkbox" id="select-all-days-edit" <?= count($selectedDays) === 7 ? 'checked' : '' ?>>
            <label for="select-all-days-edit" class="select-all-label">Todos os dias</label>
            <div class="day-separator"></div>

            <?php foreach ($daysOfWeek as $num => $day): ?>
                <input type="checkbox" name="days[]" value="<?= $num ?>" id="day-edit-<?= $num ?>" class="day-checkbox-edit" <?= in_array($num, $selectedDays) ? 'checked' : '' ?>>
                <label for="day-edit-<?= $num ?>"><?= $day ?></label>
            <?php endforeach; ?>
        </div>

        <label for="play_at">Horas de Reprodução (separadas por vírgula):</label>
        <input type="text" id="play_at" name="play_at" value="<?= htmlspecialchars($timesString) ?>" placeholder="Ex: 09:00, 14:30, 18:00" required>

        <button type="submit">Guardar Alterações</button>
        <a href="index.php?page=manage_schedules&action=list" style="margin-left: 15px; text-decoration: none; color: #6c757d;">Cancelar</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-days-edit');
    const dayCheckboxes = document.querySelectorAll('.day-checkbox-edit');

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

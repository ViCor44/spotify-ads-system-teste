<?php
// public/pages/partials/schedule_list.php
$daysOfWeek = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
$closingSchedulesExist = false;
if (!empty($closingAnnouncementIds)) {
    foreach ($closingAnnouncementIds as $id) {
        if (isset($schedulesAgrupados[$id])) {
            $closingSchedulesExist = true;
            break;
        }
    }
}

?>
<div class="box">
    <h2>Agendamentos Ativos</h2>
    <?php if (empty($schedulesAgrupados)): ?>
        <p>Não existem agendamentos ativos.</p>
    <?php else: ?>
        <?php foreach ($schedulesAgrupados as $announcementId => $details): ?>
            <div class="accordion-item">
                <div class="accordion-header">
                    <div class="accordion-title">
                        
                        <h3><?= htmlspecialchars($details['title']) ?></h3>
                    </div>
                    <div class="accordion-actions">
                        <a href="index.php?page=edit_schedule&announcement_id=<?= $announcementId ?>" class="action-btn edit-btn-group">
                            <i class="fa-solid fa-pencil"></i> Editar horários
                        </a>
                        
                        <?php
                        // --- LÓGICA PARA O BOTÃO INTELIGENTE ---
                        $allInactive = true;
                        foreach ($details['days'] as $times) {
                            foreach ($times as $time) {
                                if ($time['is_active']) {
                                    $allInactive = false;
                                    break 2; // Sai de ambos os loops
                                }
                            }
                        }
                        
                        if ($allInactive):
                        ?>
                            <a href="../api/toggle_schedules_for_announcement.php?id=<?= $announcementId ?>&action=activate" class="action-btn activate-all-btn">
                                <i class="fa-solid fa-check"></i> Ativar Todos
                            </a>
                        <?php else: ?>
                            <a href="../api/toggle_schedules_for_announcement.php?id=<?= $announcementId ?>&action=deactivate" class="action-btn disable-btn-group">
                                <i class="fa-solid fa-power-off"></i> Desativar Todos
                            </a>
                        <?php endif; ?>
                        
                        <a href="../api/delete_schedules_for_announcement.php?id=<?= $announcementId ?>" class="action-btn delete-group-btn" onclick="return confirm('Tem a certeza que quer apagar TODOS os agendamentos para este anúncio?');">
                            <i class="fa-solid fa-trash-can"></i> Apagar Todos
                        </a>
                        <i class="fa-solid fa-chevron-down accordion-icon"></i>
                    </div>
                </div>
                <div id="grp-<?= $announcementId ?>" class="accordion-panel">
                    <div class="accordion-content">
                        <?php foreach ($details['days'] as $dayNum => $times): ?>
                            <div class="day-group">
                                <strong><?= $daysOfWeek[$dayNum] ?>:</strong>
                                <div class="times-container">
                                    <?php foreach ($times as $time): ?>
                                        <div class="time-entry <?= $time['is_active'] ? 'active' : 'inactive' ?>">
                                            <span><?= htmlspecialchars($time['formatted_time']) ?></span>
                                            <div class="actions">
                                                <a href="../api/toggle_schedule.php?id=<?= $time['id'] ?>" class="toggle-switch <?= $time['is_active'] ? 'on' : 'off' ?>" title="<?= $time['is_active'] ? 'Desativar' : 'Ativar' ?>">
                                                    <div class="slider"></div>
                                                </a>
                                                <a href="../api/delete_schedule.php?id=<?= $time['id'] ?>" onclick="return confirm('Tem a certeza?');" class="delete-btn" title="Apagar">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>                        
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ... (A sua Zona de Perigo mantém-se aqui inalterada) ... -->
</div>

<?php if ($closingSchedulesExist || !empty($schedulesAgrupados)): ?>
<div class="box danger-zone">
    <h2><i class="fa-solid fa-biohazard"></i> Zona de Perigo</h2>
    <div class="danger-zone-actions">
        <?php if ($closingSchedulesExist): ?>
        <div class="danger-action">
            <p>Apagar permanentemente **todos** os agendamentos relacionados com a **sequência de fecho**.</p>
            <form action="../api/delete_closing_schedules.php" method="post" onsubmit="return confirm('Tem a certeza que quer apagar TODOS os agendamentos de fecho do parque? Esta ação não pode ser desfeita.');">
                <button type="submit" class="danger-btn">Apagar Agendamentos de Fecho</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($schedulesAgrupados)): ?>
        <div class="danger-action">
            <p>Apagar permanentemente **absolutamente todos** os agendamentos do sistema.</p>
            <form action="../api/delete_all_schedules.php" method="post" onsubmit="return confirm('PERIGO! Tem a certeza que quer apagar TODOS os agendamentos do sistema? Esta ação não pode ser desfeita.');">
                <button type="submit" class="danger-btn">Apagar TODOS os Agendamentos</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


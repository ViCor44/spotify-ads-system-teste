<?php
// public/pages/partials/park_closing_form.php
$daysOfWeek = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
?>
<div class="box box-compact">
    <h2>Agendar Sequência de Fecho do Parque</h2>

    <?php if ($flashError): ?>
        <div class="flash-message flash-error"><?= htmlspecialchars($flashError) ?></div>
    <?php elseif (($_GET['status'] ?? '') === 'closing_scheduled'): ?>
        <div class="flash-message flash-success">A mudança de horário foi agendada.</div>
    <?php elseif (($_GET['status'] ?? '') === 'closing_period_deleted'): ?>
        <div class="flash-message flash-success">A mudança de horário foi apagada.</div>
    <?php endif; ?>
    
    <?php if ($placeholderWarning): ?>
        <div class="alert-box alert-warning" style="margin-bottom: 20px;">
            <div class="alert-content">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>Função desativada:</strong> Para agendar o fecho, primeiro deve substituir os ficheiros de áudio provisórios dos quatro anúncios de fecho em "Gerir Anúncios".</span>
            </div>
        </div>
    <?php endif; ?>

    <p class="closing-form-intro">Escolha a data em que o horário entra em vigor. Este mantém-se até à próxima data agendada, e o sistema cria automaticamente os avisos de 15, 10 e 5 minutos.</p>
    
    <form action="../api/schedule_park_closing.php" method="post">
        <fieldset <?= $placeholderWarning ? 'disabled' : '' ?>> <!-- Desativa o formulário inteiro -->
            <label for="effective_from">Data de início:</label>
            <input type="date" id="effective_from" name="effective_from" value="<?= date('Y-m-d') ?>" required>

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

    <?php if (!empty($closingPeriods)): ?>
        <section class="closing-periods" aria-labelledby="closing-periods-title">
            <h3 id="closing-periods-title">Mudanças de horário agendadas</h3>
            <div class="closing-period-list">
                <?php foreach ($closingPeriods as $period): ?>
                    <?php
                    $effectiveFrom = $period['effective_from'];
                    $isLegacyActive = $effectiveFrom === null && $activeClosingEffectiveFrom === null;
                    $isActive = $effectiveFrom === $activeClosingEffectiveFrom || $isLegacyActive;
                    $isFuture = $effectiveFrom !== null && $effectiveFrom > date('Y-m-d');
                    $periodDays = array_filter(explode(',', (string)$period['days']));
                    $dayNames = array_map(fn($day) => $daysOfWeek[(int)$day], $periodDays);
                    ?>
                    <article class="closing-period<?= $isActive ? ' active' : '' ?>">
                        <div class="closing-period-date">
                            <i class="fa-regular fa-calendar"></i>
                            <div>
                                <strong><?= $effectiveFrom ? date('d/m/Y', strtotime($effectiveFrom)) : 'Configuração anterior' ?></strong>
                                <span><?= $isActive ? 'Em vigor' : ($isFuture ? 'Programado' : 'Substituído') ?></span>
                            </div>
                        </div>
                        <div class="closing-period-details">
                            <strong><?= date('H:i', strtotime($period['play_at'])) ?></strong>
                            <span><?= htmlspecialchars(implode(', ', $dayNames)) ?></span>
                        </div>
                        <?php if ($effectiveFrom): ?>
                            <form action="../api/delete_closing_period.php" method="post" onsubmit="return confirm('Apagar esta mudança de horário?');">
                                <input type="hidden" name="effective_from" value="<?= htmlspecialchars($effectiveFrom) ?>">
                                <button type="submit" class="closing-period-delete" title="Apagar mudança de horário" aria-label="Apagar mudança de horário">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

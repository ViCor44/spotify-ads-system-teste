<?php
// public/pages/partials/park_closing_form.php
$daysOfWeek = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
$currentYear = date('Y');
$yearStart = $currentYear . '-01-01';
$yearEnd = $currentYear . '-12-31';
$timelinePeriods = [];
$carryOverPeriod = null;

foreach ($closingPeriods as $period) {
    if ($period['effective_from'] === null || $period['effective_from'] < $yearStart) {
        $carryOverPeriod = $period;
    } elseif ($period['effective_from'] <= $yearEnd) {
        $period['timeline_date'] = $period['effective_from'];
        $period['is_carry_over'] = false;
        $timelinePeriods[] = $period;
    }
}

if ($carryOverPeriod) {
    $carryOverPeriod['timeline_date'] = $yearStart;
    $carryOverPeriod['is_carry_over'] = true;
    array_unshift($timelinePeriods, $carryOverPeriod);
}

$activeClosingPeriod = null;
foreach ($closingPeriods as $period) {
    $isLegacyActive = $period['effective_from'] === null && $activeClosingEffectiveFrom === null;
    if ($period['effective_from'] === $activeClosingEffectiveFrom || $isLegacyActive) {
        $activeClosingPeriod = $period;
        break;
    }
}

$selectedDays = array_map('intval', (array)($formData['days'] ?? ($activeClosingPeriod ? explode(',', $activeClosingPeriod['days']) : array_keys($daysOfWeek))));
$defaultClosingTime = $activeClosingPeriod['play_at'] ?? '';
$selectedHour = (string)($formData['closing_hour'] ?? ($defaultClosingTime ? date('H', strtotime($defaultClosingTime)) : ''));
$selectedMinute = (string)($formData['closing_minute'] ?? ($defaultClosingTime ? date('i', strtotime($defaultClosingTime)) : ''));
$selectedEffectiveFrom = (string)($formData['effective_from'] ?? date('Y-m-d'));
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
            <input type="date" id="effective_from" name="effective_from" value="<?= htmlspecialchars($selectedEffectiveFrom) ?>" required>

            <label>Selecione os Dias da Semana para o Fecho:</label>
            <div class="day-selector">
                <?php foreach ($daysOfWeek as $num => $day): ?>
                    <input type="checkbox" name="days[]" value="<?= $num ?>" id="day-closing-<?= $num ?>" <?= in_array($num, $selectedDays, true) ? 'checked' : '' ?>>
                    <label for="day-closing-<?= $num ?>"><?= $day ?></label>
                <?php endforeach; ?>
            </div>

            <label for="closing_hour">Hora de Fecho (formato 24h):</label>
            <div class="time-24-control">
                <select id="closing_hour" name="closing_hour" required aria-label="Hora de fecho">
                    <option value="">Hora</option>
                    <?php for ($hour = 0; $hour <= 23; $hour++): ?>
                        <?php $hourValue = sprintf('%02d', $hour); ?>
                        <option value="<?= $hourValue ?>" <?= $hourValue === $selectedHour ? 'selected' : '' ?>><?= $hourValue ?></option>
                    <?php endfor; ?>
                </select>
                <span aria-hidden="true">:</span>
                <select name="closing_minute" required aria-label="Minuto de fecho">
                    <option value="">Minuto</option>
                    <?php for ($minute = 0; $minute <= 59; $minute++): ?>
                        <?php $minuteValue = sprintf('%02d', $minute); ?>
                        <option value="<?= $minuteValue ?>" <?= $minuteValue === $selectedMinute ? 'selected' : '' ?>><?= $minuteValue ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="submit">Agendar Sequência de Fecho</button>
        </fieldset>
    </form>

    <section class="closing-periods" aria-labelledby="closing-periods-title">
        <div class="closing-timeline-heading">
            <h3 id="closing-periods-title">Linha do tempo <?= $currentYear ?></h3>
        </div>

        <?php if ($timelinePeriods): ?>
            <div class="closing-year-timeline">
                <ol class="closing-year-track" style="--timeline-count: <?= count($timelinePeriods) ?>">
                <?php foreach ($timelinePeriods as $period): ?>
                    <?php
                    $effectiveFrom = $period['effective_from'];
                    $isLegacyActive = $effectiveFrom === null && $activeClosingEffectiveFrom === null;
                    $isActive = $effectiveFrom === $activeClosingEffectiveFrom || $isLegacyActive;
                    $isFuture = $period['timeline_date'] > date('Y-m-d');
                    $timelineState = $isActive ? 'active' : ($isFuture ? 'future' : 'past');
                    ?>
                    <li class="closing-year-event <?= $timelineState ?>">
                        <div class="closing-year-circle" title="<?= $isActive ? 'Em vigor' : ($isFuture ? 'Futuro' : 'Passado') ?>">
                            <time datetime="<?= htmlspecialchars($period['timeline_date']) ?>"><?= date('d/m', strtotime($period['timeline_date'])) ?></time>
                            <strong><?= date('H:i', strtotime($period['play_at'])) ?></strong>
                        </div>
                        <?php if (!$period['is_carry_over']): ?>
                            <form action="../api/delete_closing_period.php" method="post" onsubmit="return confirm('Apagar esta mudança de horário?');">
                                <input type="hidden" name="effective_from" value="<?= htmlspecialchars($effectiveFrom) ?>">
                                <button type="submit" class="closing-period-delete" title="Apagar mudança de horário" aria-label="Apagar mudança de horário">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ol>
            </div>
        <?php else: ?>
            <p class="closing-timeline-empty">Ainda não existem horários de fecho para <?= $currentYear ?>.</p>
        <?php endif; ?>
    </section>
</div>

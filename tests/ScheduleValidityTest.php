<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\ScheduleValidity;

$closingSchedules = [
    ['title' => 'Fecho - Parque Fechado', 'day_of_week' => 3, 'play_at' => '20:00:00', 'effective_from' => '2026-04-01'],
    ['title' => 'Fecho - Parque Fechado', 'day_of_week' => 3, 'play_at' => '21:00:00', 'effective_from' => '2026-07-01']
];
$transitions = ['2026-04-01', '2026-07-01'];

$next = ScheduleValidity::findNext($closingSchedules, $transitions, new DateTimeImmutable('2026-06-30 22:00:00'));
if (!$next || $next['date']->format('Y-m-d H:i') !== '2026-07-01 21:00') {
    throw new RuntimeException('A configuração nova não entrou em vigor na data da transição.');
}

$regularSchedules = [
    ['title' => 'Bem-Vindos', 'day_of_week' => 2, 'play_at' => '10:00:00', 'effective_from' => null]
];
$next = ScheduleValidity::findNext($regularSchedules, $transitions, new DateTimeImmutable('2026-06-30 09:00:00'));
if (!$next || $next['date']->format('Y-m-d H:i') !== '2026-06-30 10:00') {
    throw new RuntimeException('Um agendamento comum foi afetado pela regra de fecho.');
}

echo "ScheduleValidityTest: OK\n";
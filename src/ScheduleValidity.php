<?php

namespace App;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;

class ScheduleValidity
{
    private const CLOSING_TITLES = [
        'Fecho - 15 minutos',
        'Fecho - 10 minutos',
        'Fecho - 5 minutos',
        'Fecho - Parque Fechado'
    ];

    public static function closingTitles(): array
    {
        return self::CLOSING_TITLES;
    }

    public static function fetchClosingTransitions(PDO $pdo): array
    {
        $placeholders = implode(',', array_fill(0, count(self::CLOSING_TITLES), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT s.effective_from
                               FROM schedules s
                               JOIN announcements a ON a.id = s.announcement_id
                               WHERE a.title IN ($placeholders)
                                 AND s.is_active = 1
                                 AND s.effective_from IS NOT NULL
                               ORDER BY s.effective_from");
        $stmt->execute(self::CLOSING_TITLES);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function findNext(array $schedules, array $closingTransitions, DateTimeInterface $now): ?array
    {
        $nowImmutable = DateTimeImmutable::createFromInterface($now);
        $next = null;

        foreach ($schedules as $schedule) {
            $isClosing = in_array($schedule['title'], self::CLOSING_TITLES, true);
            $effectiveFrom = $schedule['effective_from'] ?: null;
            $base = $nowImmutable;

            if ($isClosing && $effectiveFrom && $effectiveFrom > $nowImmutable->format('Y-m-d')) {
                $base = new DateTimeImmutable($effectiveFrom . ' 00:00:00');
            }

            $potential = new DateTimeImmutable($base->format('Y-m-d') . ' ' . $schedule['play_at']);
            $dayDiff = (int)$schedule['day_of_week'] - (int)$potential->format('N');
            if ($dayDiff < 0) {
                $dayDiff += 7;
            }
            if ($dayDiff > 0) {
                $potential = $potential->modify("+$dayDiff days");
            }
            if ($potential < $base) {
                $potential = $potential->modify('+7 days');
            }

            if ($isClosing && $effectiveFrom !== self::activeClosingEffectiveFrom($closingTransitions, $potential)) {
                continue;
            }

            if ($next === null || $potential < $next['date']) {
                $next = ['schedule' => $schedule, 'date' => $potential];
            }
        }

        return $next;
    }

    private static function activeClosingEffectiveFrom(array $transitions, DateTimeInterface $date): ?string
    {
        $active = null;
        $targetDate = $date->format('Y-m-d');

        foreach ($transitions as $transition) {
            if ($transition > $targetDate) {
                break;
            }
            $active = $transition;
        }

        return $active;
    }
}
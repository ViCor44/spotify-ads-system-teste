<?php

session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\ScheduleValidity;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/index.php?page=manage_schedules&action=closing');
    exit();
}

$effectiveFrom = (string)($_POST['effective_from'] ?? '');
$effectiveDate = DateTime::createFromFormat('!Y-m-d', $effectiveFrom);

if (!$effectiveDate || $effectiveDate->format('Y-m-d') !== $effectiveFrom) {
    $_SESSION['flash_error'] = 'Não foi possível identificar a mudança de horário.';
    header('Location: ../public/index.php?page=manage_schedules&action=closing');
    exit();
}

try {
    $pdo = Database::getInstance();
    $titles = ScheduleValidity::closingTitles();
    $placeholders = implode(',', array_fill(0, count($titles), '?'));
    $stmt = $pdo->prepare("DELETE s FROM schedules s
                           JOIN announcements a ON a.id = s.announcement_id
                           WHERE a.title IN ($placeholders) AND s.effective_from = ?");
    $stmt->execute([...$titles, $effectiveFrom]);

    header('Location: ../public/index.php?page=manage_schedules&action=closing&status=closing_period_deleted');
    exit();
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Erro ao apagar a mudança de horário: ' . $e->getMessage();
    header('Location: ../public/index.php?page=manage_schedules&action=closing');
    exit();
}
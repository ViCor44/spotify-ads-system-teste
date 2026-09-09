<?php
// api/schedule_park_closing.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/index.php?page=manage_schedules&action=closing');
    exit();
}

$days = array_values(array_unique(array_map('intval', (array)($_POST['days'] ?? []))));
$closingHour = (string)($_POST['closing_hour'] ?? '');
$closingMinute = (string)($_POST['closing_minute'] ?? '');
$closingTime = $closingHour !== '' && $closingMinute !== ''
    ? $closingHour . ':' . $closingMinute
    : (string)($_POST['closing_time'] ?? '');
$effectiveFrom = (string)($_POST['effective_from'] ?? '');

$_SESSION['form_data'] = $_POST;

try {
    if (!$days || array_diff($days, range(1, 7)) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $closingTime)) {
        throw new InvalidArgumentException('Selecione pelo menos um dia e indique uma hora de fecho válida.');
    }

    $effectiveDate = DateTime::createFromFormat('!Y-m-d', $effectiveFrom);
    if (!$effectiveDate || $effectiveDate->format('Y-m-d') !== $effectiveFrom) {
        throw new InvalidArgumentException('A data de início é inválida.');
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $announcementTitles = [
        'Fecho - 15 minutos' => -15,
        'Fecho - 10 minutos' => -10,
        'Fecho - 5 minutos' => -5,
        'Fecho - Parque Fechado' => 0
    ];
    $announcements = [];
    foreach ($announcementTitles as $title => $offset) {
        $stmt = $pdo->prepare("SELECT id FROM announcements WHERE title = ?");
        $stmt->execute([$title]);
        if (!$announcement = $stmt->fetch()) {
            throw new Exception("O anúncio obrigatório \"$title\" não foi encontrado. Por favor, carregue-o na página 'Gerir Anúncios'.");
        }
        $announcements[] = ['id' => $announcement['id'], 'offset' => $offset];
    }

    $announcementIdsToDelete = array_column($announcements, 'id');
    $placeholders = implode(',', array_fill(0, count($announcementIdsToDelete), '?'));
    $stmtDelete = $pdo->prepare("DELETE FROM schedules WHERE announcement_id IN ($placeholders) AND effective_from = ?");
    $stmtDelete->execute([...$announcementIdsToDelete, $effectiveFrom]);

    foreach ($days as $day) {
        $closingDateTime = new DateTime($closingTime);

        foreach ($announcements as $details) {
            $adDateTime = clone $closingDateTime;
            if ($details['offset'] !== 0) {
                $adDateTime->modify("{$details['offset']} minutes");
            }

            $stmtInsert = $pdo->prepare("INSERT INTO schedules (announcement_id, day_of_week, play_at, effective_from) VALUES (?, ?, ?, ?)");
            $stmtInsert->execute([$details['id'], $day, $adDateTime->format('H:i:s'), $effectiveFrom]);
        }
    }

    $pdo->commit();
    unset($_SESSION['form_data']);
    header('Location: ../public/index.php?page=manage_schedules&action=closing&status=closing_scheduled');
    exit();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = "Erro ao agendar o fecho: " . $e->getMessage();
    header('Location: ../public/index.php?page=manage_schedules&action=closing');
    exit();
}
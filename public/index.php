<?php
// public/index.php (O nosso Router)
session_start();

// --- CONFIGURAÇÃO E CARREGAMENTO DE DADOS ---
require_once __DIR__ . '/../vendor/autoload.php';

define('SPOT_MASTER_INIT', true); // Define uma constante para segurança
require_once __DIR__ . '/../init.php';

use App\Database;
use App\SpotifyClient;

// --- GESTÃO CENTRALIZADA DE SESSÕES FLASH ---
$flashError = $_SESSION['flash_error'] ?? null;
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['flash_error']);
unset($_SESSION['form_data']);

$pdo = Database::getInstance();
$token_exists = $pdo->query("SELECT access_token FROM spotify_tokens WHERE id = 1")->fetchColumn();

$playbackState = null;
$error_message = null;
$announcements = [];
$schedulesAgrupados = [];
$daysOfWeekMap = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];

if ($token_exists) {
    try {
        $spotifyClient = new SpotifyClient();
        $playbackState = $spotifyClient->getPlaybackState();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

try {
    $stmtAnn = $pdo->query("SELECT id, title, duration_seconds, file_path FROM announcements ORDER BY created_at DESC");
    $announcements = $stmtAnn->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = ($error_message ?? '') . ' Erro ao carregar anúncios: ' . $e->getMessage();
}

try {
    $sql = "SELECT s.id, s.announcement_id, s.day_of_week, s.play_at, s.is_active, a.title AS announcement_title
            FROM schedules s
            JOIN announcements a ON s.announcement_id = a.id
            ORDER BY a.title, s.day_of_week, s.play_at";
    $stmtSch = $pdo->query($sql);
    $schedules = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schedules as $schedule) {
        $announcementId = $schedule['announcement_id'];
        $title = $schedule['announcement_title'];
        $dayNum = $schedule['day_of_week'];

        if (!isset($schedulesAgrupados[$announcementId])) {
            $schedulesAgrupados[$announcementId]['title'] = $title;
            $schedulesAgrupados[$announcementId]['days'] = [];
        }
        
        $schedulesAgrupados[$announcementId]['days'][$dayNum][] = [
            'id' => $schedule['id'],
            'formatted_time' => date("H:i", strtotime($schedule['play_at'])),
            'is_active' => $schedule['is_active']
        ];
    }
} catch (Exception $e) {
    $error_message = ($error_message ?? '') . ' Erro ao carregar agendamentos: ' . $e->getMessage();
}

// --- LÓGICA DE VERIFICAÇÃO DE ESTADO DO SISTEMA (CORRIGIDA) ---
$placeholderWarning = false;
$closingAnnouncementIds = []; 
try {
    $closingTitles = "'Fecho - 15 minutos', 'Fecho - 10 minutos', 'Fecho - 5 minutos', 'Fecho - Parque Fechado'";
    $sqlPlaceholders = "SELECT id, title, file_path FROM announcements WHERE title IN ($closingTitles)";
    $stmtPlaceholders = $pdo->query($sqlPlaceholders);
    $closingAnnouncements = $stmtPlaceholders->fetchAll(PDO::FETCH_ASSOC);

    foreach ($closingAnnouncements as $ann) {
        $closingAnnouncementIds[] = $ann['id']; // Popula o array com os IDs
        if (str_starts_with($ann['file_path'], 'silent_')) {
            $placeholderWarning = true;
        }
    }
} catch (Exception $e) {
    error_log("Erro ao verificar placeholders: " . $e->getMessage());
}




// --- LÓGICA PARA VERIFICAR A PULSAÇÃO DO ROBÔ AGENDADOR ---
$robotStatusOk = false;
$heartbeatFile = __DIR__ . '/../public/robot_heartbeat.log'; // Caminho corrigido

if (file_exists($heartbeatFile)) {
    $lastHeartbeat = file_get_contents($heartbeatFile);
    if ((time() - (int)$lastHeartbeat) < 180) {
        $robotStatusOk = true;
    }
}

// --- LÓGICA MATEMÁTICA FINAL PARA ENCONTRAR O PRÓXIMO ANÚNCIO ---
$nextAnnouncement = null;
try {
    $sqlNext = "SELECT s.day_of_week, s.play_at, a.title 
                FROM schedules s
                JOIN announcements a ON s.announcement_id = a.id
                WHERE s.is_active = 1";
    $stmtNext = $pdo->query($sqlNext);
    $allSchedules = $stmtNext->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($allSchedules)) {
        date_default_timezone_set('Europe/Lisbon');
        $now = new DateTime();
        $nextTimestamp = PHP_INT_MAX;
        
        foreach ($allSchedules as $schedule) {
            $scheduleDay = (int)$schedule['day_of_week'];
            $potentialDate = new DateTime('today ' . $schedule['play_at']);
            $currentDayNum = (int)$potentialDate->format('N');
            $dayDiff = $scheduleDay - $currentDayNum;
            if ($dayDiff < 0) { $dayDiff += 7; }
            if ($dayDiff > 0) { $potentialDate->modify("+$dayDiff days"); }
            if ($potentialDate < $now) { $potentialDate->modify('+7 days'); }
            $potentialTimestamp = $potentialDate->getTimestamp();

            if ($potentialTimestamp < $nextTimestamp) {
                $nextTimestamp = $potentialTimestamp;
                $dayName = $daysOfWeekMap[$scheduleDay];
                // A LINHA CORRIGIDA ESTÁ AQUI
                if (date('W', $nextTimestamp) != date('W', $now->getTimestamp())) {
                    $dayName = "Próxima " . $dayName;
                }
                $nextAnnouncement = [
                    'title' => $schedule['title'],
                    'day' => $dayName,
                    'time' => date("H:i", $nextTimestamp),
                    'timestamp' => $nextTimestamp
                ];
            }
        }
    }
} catch (Exception $e) {
    $error_message = ($error_message ?? '') . ' Erro ao calcular o próximo anúncio: ' . $e->getMessage();
}

// --- LÓGICA PARA CARREGAR DADOS EXTRA DO DASHBOARD ---
$nextParkClosing = null;
$activityLogs = [];
$todaysAgenda = [];


// --- ROTEAMENTO ---
include __DIR__ . '/templates/header.php';

$page = $_GET['page'] ?? 'dashboard';

switch ($page) {
    case 'manage_announcements':
        include __DIR__ . '/pages/manage_announcements.php';
        break;
    case 'edit_announcement':
        include __DIR__ . '/pages/edit_announcement.php';
        break;
    case 'play_announcements':
        if (!isset($_GET['status']) || $_GET['status'] !== 'triggered') {
            $statusFilePath = __DIR__ . '/status.json';
            if (file_exists($statusFilePath)) {
                file_put_contents($statusFilePath, '{}');
            }
        }
        include __DIR__ . '/pages/play_announcements.php';
        break;
    case 'manage_schedules':
        include __DIR__ . '/pages/manage_schedules.php';
        break;
    case 'edit_schedule':
        include __DIR__ . '/pages/edit_schedule.php';
        break;
    case 'tts_announcement':
        include __DIR__ . '/pages/tts_announcement.php';
        break;
    case 'about':
        include __DIR__ . '/pages/about.php';
        break;
    case 'dashboard':
    default:
        include __DIR__ . '/pages/dashboard.php';
        break;
}

include __DIR__ . '/templates/footer.php';

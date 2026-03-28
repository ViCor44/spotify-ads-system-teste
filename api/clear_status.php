<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/StatusStore.php';

use SpotMaster\Api\StatusStore;

if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

header('Content-Type: application/json; charset=UTF-8');

try {
    (new StatusStore())->clear();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/logger.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $me  = require_admin(); // Nur Admins erlaubt

    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $logFile = hh_log_path();

    // ---------------------------------------------------------
    // POST: Log leeren
    // ---------------------------------------------------------
    if ($method === 'POST') {
    
        $in = json_decode(file_get_contents('php://input'), true) ?: [];

        if (($in['action'] ?? '') === 'clear') {
            if ($fh = @fopen($logFile, 'c+')) {
                @ftruncate($fh, 0);
                @fclose($fh);
            }
            echo json_encode(['ok' => true, 'message' => 'cleared']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'unknown action']);
        exit;
    }

    // ---------------------------------------------------------
    // GET: Download?
    // ---------------------------------------------------------
    if (isset($_GET['download'])) {
        if (!is_file($logFile)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Logfile not found']);
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="hunthub.log"');
        readfile($logFile);
        exit;
    }

    // ---------------------------------------------------------
    // GET: Log lesen
    // ---------------------------------------------------------
    $lines = isset($_GET['lines']) ? max(10, min(5000, (int)$_GET['lines'])) : 500;
    $exists = is_file($logFile);
    $size   = $exists ? filesize($logFile) : 0;
    $log    = '';

    if ($exists && $size > 0) {
        $data = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($data !== false) {
            $log = implode("\n", array_slice($data, -$lines));
        }
    }

    echo json_encode([
        'ok'       => true,
        'lines'    => $lines,
        'path'     => $logFile,
        'exists'   => $exists,
        'writable' => is_writable(dirname($logFile)),
        'size'     => $size,
        'log'      => $log,
    ]);
    exit;

} catch (Throwable $e) {
    // Immer JSON zurückgeben, selbst bei Fehlern
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit;
}

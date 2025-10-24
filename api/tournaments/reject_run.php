<?php
declare(strict_types=1);

/**
 * /api/tournaments/reject_run.php
 * Reject eines eingereichten Runs (Admin-only).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $me  = require_admin();

    // Input
    $runId  = (int)($_POST['run_id'] ?? $_POST['id'] ?? $_GET['run_id'] ?? $_GET['id'] ?? 0);
    $reason = trim((string)($_REQUEST['reason'] ?? ''));

    // CSRF (POST/GET/Header/Cookie) + Admin-Fallback auf gleicher Origin
    $token = $_REQUEST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!$token && isset($_COOKIE['csrf'])) $token = (string)$_COOKIE['csrf'];
    $ref = $_SERVER['HTTP_REFERER'] ?? ''; $host = $_SERVER['HTTP_HOST'] ?? '';
    $sameOrigin = $ref && (parse_url($ref, PHP_URL_HOST) === $host);
    $csrfOk = function_exists('verify_csrf') && verify_csrf($pdo, (string)$token);
    if (!$csrfOk && $sameOrigin && !empty($me) && !empty($me['is_admin'])) $csrfOk = true;
    if (!$csrfOk) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }
    if ($runId <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_run_id']); exit; }

    // Spalten
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM tournament_runs', PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = $c;
    if (!isset($cols['status'])) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'no_status_column']); exit; }

    // Status-Wert bestimmen
    $type = strtolower((string)($cols['status']['Type'] ?? ''));
    $valRejected = str_contains($type, 'int') ? 2 : 'rejected';

    // Update bauen
    $set = 'status = ?';
    $params = [$valRejected];

    if (isset($cols['rejected_at'])) $set .= ', rejected_at = NOW()';
    if (isset($cols['moderator_user_id'])) { $set .= ', moderator_user_id = ?'; $params[] = (int)$me['id']; }
    if (isset($cols['mod_comment'])) { $set .= ', mod_comment = ?'; $params[] = $reason; }

    $params[] = $runId;

    $pdo->prepare("UPDATE tournament_runs SET $set WHERE id = ?")->execute($params);

    if (!empty($ref)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Location: ' . $ref, true, 303);
        echo 'OK'; exit;
    }

    echo json_encode(['ok'=>true,'id'=>$runId,'status_set'=>$valRejected]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage(),'where'=>basename(__FILE__).':'.$e->getLine()]);
}

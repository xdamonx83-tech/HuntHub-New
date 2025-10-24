<?php
declare(strict_types=1);

/**
 * /api/lfg/request_create.php – robuste JSON-API
 * - Kein _bootstrap / keine Layout-Includes
 * - Kein Redirect: bei fehlendem Login => { ok:false, error:"unauthorized" }
 * - Prüft Tabellen, validiert Input, vermeidet Duplikate
 * - Immer JSON (auch bei Fehlern)
 */

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'server_error', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function($severity,$message,$file,$line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../../auth/db.php';
$pdo = db();

/** ---- Auth ohne Redirect ---- */
$me = null;
if (is_file(__DIR__ . '/../../auth/guards.php')) {
    require_once __DIR__ . '/../../auth/guards.php';
    if (function_exists('optional_auth')) {
        $me = optional_auth();
    } elseif (function_exists('current_user')) {
        $me = current_user();
    }
}
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!$me && isset($_SESSION['user']['id'])) {
    $me = ['id' => (int)$_SESSION['user']['id']];
}
$user_id = (int)($me['id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(['ok'=>false, 'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** ---- Ping ---- */
if (isset($_GET['__ping'])) {
    echo json_encode(['ok'=>true, 'pong'=>true, 'route'=>'/api/lfg/request_create.php']);
    exit;
}

/** ---- Tabellen da? ---- */
foreach (['lfg_posts','lfg_requests','users'] as $tbl) {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl));
    if (!$st || !$st->fetchColumn()) {
        echo json_encode(['ok'=>false, 'error'=>'missing_tables', 'missing'=>$tbl], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/** ---- Input ---- */
$post_id = (int)($_POST['post_id'] ?? $_GET['post_id'] ?? 0);
$message = (string)($_POST['message'] ?? $_GET['message'] ?? '');
$message = trim($message);
if (mb_strlen($message) > 1000) { $message = mb_substr($message, 0, 1000); }

if ($post_id <= 0) {
    echo json_encode(['ok'=>false, 'error'=>'invalid_post_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** ---- Post holen ---- */
$sql = "SELECT p.*, u.id AS owner_id
        FROM lfg_posts p
        JOIN users u ON u.id = p.user_id
        WHERE p.id=:id
          AND p.visible=1
          AND (p.expires_at IS NULL OR p.expires_at > NOW())";
$st = $pdo->prepare($sql);
$st->execute([':id'=>$post_id]);
$post = $st->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    echo json_encode(['ok'=>false, 'error'=>'post_not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}
$owner_id = (int)$post['owner_id'];

/** ---- Eigenen Post anfragen? ---- */
if ($owner_id === $user_id) {
    echo json_encode(['ok'=>false, 'error'=>'own_post_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** ---- Bereits angefragt? ---- */
$st = $pdo->prepare("SELECT id, status FROM lfg_requests
                     WHERE requester_id=:me AND post_id=:pid
                     ORDER BY id DESC LIMIT 1");
$st->execute([':me'=>$user_id, ':pid'=>$post_id]);
$exists = $st->fetch(PDO::FETCH_ASSOC);
if ($exists && in_array($exists['status'], ['pending','accepted'], true)) {
    echo json_encode(['ok'=>false, 'error'=>'already_requested'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** ---- Insert ---- */
$ins = $pdo->prepare(
    "INSERT INTO lfg_requests (post_id, owner_id, requester_id, message, status, created_at)
     VALUES (:pid, :owner, :req, :msg, 'pending', NOW())"
);
$ins->execute([
    ':pid'   => $post_id,
    ':owner' => $owner_id,
    ':req'   => $user_id,
    ':msg'   => $message,
]);

$request_id = (int)$pdo->lastInsertId();

echo json_encode([
    'ok' => true,
    'request_id' => $request_id,
    'status' => 'pending'
], JSON_UNESCAPED_UNICODE);

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'server_error', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function($sev,$msg,$file,$line){
    throw new ErrorException($msg, 0, $sev, $file, $line);
});

require_once __DIR__ . '/../../auth/db.php';
$pdo = db();

/* Auth */
$me = null;
if (is_file(__DIR__ . '/../../auth/guards.php')) {
    require_once __DIR__ . '/../../auth/guards.php';
    if (function_exists('optional_auth')) $me = optional_auth();
}
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!$me && isset($_SESSION['user']['id'])) $me = ['id'=>(int)$_SESSION['user']['id']];
$user_id = (int)($me['id'] ?? 0);
if ($user_id <= 0) { echo json_encode(['ok'=>false, 'error'=>'unauthorized']); exit; }

/* Tabellen? */
foreach (['lfg_requests'] as $tbl) {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl));
    if (!$st || !$st->fetchColumn()) { echo json_encode(['ok'=>false, 'error'=>'missing_tables','missing'=>$tbl]); exit; }
}

/* Input */
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$allowed = ['accept','decline','block','cancel'];
if (!in_array($action, $allowed, true) || $id<=0) { echo json_encode(['ok'=>false,'error'=>'invalid_params']); exit; }

/* Request holen */
$st = $pdo->prepare("SELECT * FROM lfg_requests WHERE id=:id");
$st->execute([':id'=>$id]);
$req = $st->fetch(PDO::FETCH_ASSOC);
if (!$req) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

$owner_id     = (int)$req['owner_id'];
$requester_id = (int)$req['requester_id'];
$status       = $req['status'];

/* Berechtigung & Statuswechsel */
if ($action === 'cancel') {
    if ($requester_id !== $user_id) { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
    if ($status !== 'pending')      { echo json_encode(['ok'=>false,'error'=>'not_pending']); exit; }
    $upd = $pdo->prepare("UPDATE lfg_requests SET status='cancelled' WHERE id=:id");
    $upd->execute([':id'=>$id]);
    echo json_encode(['ok'=>true, 'status'=>'cancelled']); exit;
}

if (!in_array($action, ['accept','decline','block'], true)) { echo json_encode(['ok'=>false,'error'=>'invalid_action']); exit; }
if ($owner_id !== $user_id) { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
if ($status !== 'pending')  { echo json_encode(['ok'=>false,'error'=>'not_pending']); exit; }

$new = ['accept'=>'accepted','decline'=>'declined','block'=>'blocked'][$action];
$upd = $pdo->prepare("UPDATE lfg_requests SET status=:st WHERE id=:id");
$upd->execute([':st'=>$new, ':id'=>$id]);

echo json_encode(['ok'=>true, 'status'=>$new]);

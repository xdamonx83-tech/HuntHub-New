<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

$pdo  = db();
$user = require_auth();
// NEU
$cfg = require __DIR__ . '/../../auth/config.php';
$sessionName = $cfg['cookies']['session_name'] ?? '';
if (!check_csrf($pdo, $_COOKIE[$sessionName] ?? '', (string)($_POST['csrf'] ?? ''))) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'csrf']);
  exit;
}


$requestId = (int)($_POST['request_id'] ?? 0);
$action    = (string)($_POST['action'] ?? '');
if ($requestId <= 0 || !in_array($action, ['accept','decline'], true)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit;
}

$pdo->beginTransaction();
try {
  $st = $pdo->prepare("SELECT * FROM friendships WHERE id = ? FOR UPDATE");
  $st->execute([$requestId]);
  $f = $st->fetch(PDO::FETCH_ASSOC);
  if (!$f) { throw new RuntimeException('not_found'); }
  if ($f['status'] !== 'pending') { throw new RuntimeException('not_pending'); }
  if ((int)$f['addressee_id'] !== (int)$user['id']) { throw new RuntimeException('not_addressee'); }

  if ($action === 'accept') {
    $pdo->prepare("UPDATE friendships SET status='accepted', responded_at=NOW() WHERE id=?")->execute([$requestId]);
    $pdo->commit(); echo json_encode(['ok'=>true,'status'=>'friends']); exit;
  } else {
    $pdo->prepare("UPDATE friendships SET status='declined', responded_at=NOW() WHERE id=?")->execute([$requestId]);
    $pdo->commit(); echo json_encode(['ok'=>true,'status'=>'declined']); exit;
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

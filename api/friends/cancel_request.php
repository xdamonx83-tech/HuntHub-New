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


$otherId = (int)($_POST['user_id'] ?? 0);
if ($otherId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit; }

$pdo->beginTransaction();
try {
  $st = $pdo->prepare("
    SELECT id, requester_id, status FROM friendships
    WHERE pair_key = CONCAT(LEAST(?,?), ':', GREATEST(?,?))
    LIMIT 1 FOR UPDATE
  ");
  $st->execute([(int)$user['id'],$otherId,(int)$user['id'],$otherId]);
  $f = $st->fetch(PDO::FETCH_ASSOC);
  if (!$f || $f['status'] !== 'pending' || (int)$f['requester_id'] !== (int)$user['id']) {
    throw new RuntimeException('not_cancellable');
  }

  $pdo->prepare("UPDATE friendships SET status='cancelled', responded_at=NOW() WHERE id=?")->execute([(int)$f['id']]);
  $pdo->commit(); echo json_encode(['ok'=>true,'status'=>'cancelled']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

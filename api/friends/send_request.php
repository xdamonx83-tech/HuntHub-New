<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/friends.php';

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


$targetId = (int)($_POST['user_id'] ?? 0);
if ($targetId <= 0 || $targetId === (int)$user['id']) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_target']); exit;
}

$pdo->beginTransaction();
try {
  // Existenz prüfen
  $u = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
  $u->execute([$targetId]);
  if (!$u->fetchColumn()) { throw new RuntimeException('user_not_found'); }

  $existing = get_friendship($pdo, (int)$user['id'], $targetId);

  if ($existing) {
    $status = (string)$existing['status'];
    if ($status === FRIEND_STATUS_ACCEPTED) {
      $pdo->commit(); echo json_encode(['ok'=>true,'status'=>'friends']); exit;
    }
    if ($status === FRIEND_STATUS_PENDING) {
      $dir = ((int)$existing['requester_id'] === (int)$user['id']) ? 'pending_outgoing' : 'pending_incoming';
      $pdo->commit(); echo json_encode(['ok'=>true,'status'=>$dir,'request_id'=>(int)$existing['id']]); exit;
    }
    // declined/cancelled/blocked -> neu anstoßen (sofern nicht blocked)
    if ($status === FRIEND_STATUS_BLOCKED) {
      $pdo->commit(); echo json_encode(['ok'=>false,'error'=>'blocked']); exit;
    }
    $st = $pdo->prepare("UPDATE friendships SET requester_id=?, addressee_id=?, status='pending', created_at=NOW(), responded_at=NULL WHERE id=?");
    $st->execute([(int)$user['id'], $targetId, (int)$existing['id']]);
    $pdo->commit(); echo json_encode(['ok'=>true,'status'=>'pending_outgoing']); exit;
  }

  // neu anlegen
  $st = $pdo->prepare("INSERT INTO friendships (requester_id, addressee_id, status, created_at) VALUES (?,?, 'pending', NOW())");
  $st->execute([(int)$user['id'], $targetId]);

  $pdo->commit();
  echo json_encode(['ok'=>true,'status'=>'pending_outgoing']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

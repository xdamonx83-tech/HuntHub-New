<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $me = current_user();
  if (!$me) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

  $other = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
  if ($other <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_user']); exit; }

  $pdo = db();
  $st = $pdo->prepare("
    SELECT COALESCE(MAX(id),0) AS last_seen_id
    FROM messages
    WHERE sender_id = :me AND recipient_id = :other AND read_at IS NOT NULL
  ");
  $st->execute([':me'=>$me['id'], ':other'=>$other]);
  $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['last_seen_id'=>0];

  echo json_encode(['ok'=>true, 'last_seen_id'=>(int)$row['last_seen_id']]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()]);
}

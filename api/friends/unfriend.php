<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

$pdo  = db();
$user = require_auth();

$cfg = require __DIR__ . '/../../auth/config.php';
$sessionName = $cfg['cookies']['session_name'] ?? '';
if (!check_csrf($pdo, $_COOKIE[$sessionName] ?? '', (string)($_POST['csrf'] ?? ''))) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}

$otherId = (int)($_POST['user_id'] ?? 0);
$meId    = (int)$user['id'];
if ($otherId <= 0 || $otherId === $meId) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_target']); exit;
}

$pair = $meId < $otherId ? "{$meId}:{$otherId}" : "{$otherId}:{$meId}";

try {
  // Nur löschen, wenn aktuell wirklich 'accepted'
  $st = $pdo->prepare("SELECT id, status FROM friendships WHERE pair_key=? LIMIT 1");
  $st->execute([$pair]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row || $row['status'] !== 'accepted') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'not_friends']); exit;
  }

  $del = $pdo->prepare("DELETE FROM friendships WHERE id=? LIMIT 1");
  $del->execute([(int)$row['id']]);

  echo json_encode(['ok'=>true, 'status'=>'none']); // Frontend stellt auf „Freund hinzufügen“
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}

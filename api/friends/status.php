<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/friends.php';

$pdo  = db();
$me   = require_auth();
$other = (int)($_GET['user_id'] ?? 0);
if ($other <= 0 || $other === (int)$me['id']) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_user']); exit;
}
$status = friendship_status($pdo, (int)$me['id'], $other);
echo json_encode(['ok'=>true, 'status'=>$status], JSON_UNESCAPED_SLASHES);

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method']); exit;
}

$pdo = db();
$me  = require_auth();
$cfg = require __DIR__ . '/../../auth/config.php';
$sn  = $cfg['cookies']['session_name'] ?? '';

if (!check_csrf($pdo, $_COOKIE[$sn] ?? '', (string)($_POST['csrf'] ?? ''))) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}

$otherId = (int)($_POST['user_id'] ?? 0);
$upToId  = (int)($_POST['up_to_id'] ?? 0);
if ($otherId <= 0 || $upToId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

$st = $pdo->prepare("
  UPDATE messages
     SET read_at = IF(read_at IS NULL, NOW(), read_at)
   WHERE recipient_id = ? AND sender_id = ? AND id <= ? AND read_at IS NULL
");
$st->execute([(int)$me['id'], $otherId, $upToId]);

echo json_encode(['ok'=>true, 'updated'=>$st->rowCount()]);

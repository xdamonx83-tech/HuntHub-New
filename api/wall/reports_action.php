<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/wall_reports.php';

$me = require_admin(); // nur Admin

$token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
if (!check_csrf_request($_POST)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }

$type = $_POST['type'] ?? '';          // 'post' | 'comment'
$id   = (int)($_POST['id'] ?? 0);
$act  = $_POST['action'] ?? '';        // 'clear' | 'remove'

if (!in_array($type,['post','comment'],true) || $id<=0 || !in_array($act,['clear','remove'],true)) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit;
}

try {
  $db = db();
  if ($act === 'clear') wall_admin_clear_reports($db, $type, $id, (int)$me['id']);
  else wall_admin_remove_entity($db, $type, $id, (int)$me['id']);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'fail']);
}

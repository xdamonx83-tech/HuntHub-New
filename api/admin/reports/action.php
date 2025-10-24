<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../lib/wall_reports.php';

$me = require_admin();

$raw = (string)file_get_contents('php://input');
$in  = json_decode($raw, true) ?: [];

$hdr = $_SERVER['HTTP_X_CSRF'] ?? ($in['csrf'] ?? '');
$cfg = require __DIR__ . '/../../auth/config.php';
$sn  = $cfg['cookies']['session_name'] ?? session_name();
if (function_exists('verify_csrf')) {
  if (!verify_csrf(db(), $hdr, $sn)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
}

$type = (string)($in['type'] ?? '');
$id   = (int)($in['id'] ?? 0);
$act  = (string)($in['action'] ?? '');

if (!in_array($type,['post','comment'],true) || $id<=0 || !in_array($act,['clear','remove'],true)) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit;
}

$db = db();
if ($act === 'clear') wall_admin_clear_reports($db, $type, $id, (int)$me['id']);
else                  wall_admin_remove_entity($db, $type, $id, (int)$me['id']);

echo json_encode(['ok'=>true]);

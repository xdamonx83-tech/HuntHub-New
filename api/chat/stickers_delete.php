<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../auth/roles.php';
optional_auth();
require_auth();

global $USER;
$role = (string)($USER['role'] ?? 'user');
if (!in_array($role, ['moderator','administrator','admin'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'FORBIDDEN']); exit;
}

$CFG = require __DIR__ . '/../../auth/config.php';
$dir = $CFG['stickers']['dir'] ?? (__DIR__ . '/../../uploads/stickers');

$file = (string)($_POST['file'] ?? $_GET['file'] ?? '');
$base = basename($file); // Path Traversal verhindern
$path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base;

if ($base === '' || !is_file($path)) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>'NOT_FOUND']); exit;
}
if (!@unlink($path)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DELETE_FAILED']); exit;
}

echo json_encode(['ok'=>true,'deleted'=>$base]);

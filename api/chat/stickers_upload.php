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
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DIR_CREATE_FAILED']); exit;
}

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'NO_FILE']); exit;
}

$allowedMime = ['image/png','image/webp','image/jpeg','image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowedMime, true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'INVALID_MIME','mime'=>$mime]); exit;
}

$ext = [
  'image/png'  => 'png',
  'image/webp' => 'webp',
  'image/jpeg' => 'jpg',
  'image/gif'  => 'gif',
][$mime] ?? 'png';

$base = preg_replace('/[^a-z0-9_-]+/i', '-', (string)($_POST['name'] ?? 'sticker'));
$base = trim($base, '-');
if ($base === '') $base = 'sticker';
$filename = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

$dest = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'MOVE_FAILED']); exit;
}
@chmod($dest, 0664);

$baseUrl = $CFG['stickers']['base_url'] ?? '/uploads/stickers';
echo json_encode([
  'ok'=>true,
  'file'=>$filename,
  'url'=>rtrim($baseUrl,'/') . '/' . rawurlencode($filename),
]);

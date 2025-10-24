<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../auth/bootstrap.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/layout.php';

require_auth();
$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (!function_exists('csrf_verify') || !csrf_verify($token)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }

$keys = ['image','file','photo','picture'];
$f = null; foreach($keys as $k){ if (!empty($_FILES[$k]['name'])) { $f = $_FILES[$k]; break; } }
if (!$f) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no_file']); exit; }
if (!empty($f['error'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'upload_error']); exit; }

$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
$fi = new finfo(FILEINFO_MIME_TYPE);
$mime = $fi->file($f['tmp_name']) ?: '';
if (!isset($allowed[$mime])) { http_response_code(415); echo json_encode(['ok'=>false,'error'=>'bad_type']); exit; }

$ext = $allowed[$mime];
$base = dirname(__DIR__);
$dir  = $base . '/uploads/images';
@mkdir($dir, 0775, true);

$name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dst  = $dir . '/' . $name;

if (!move_uploaded_file($f['tmp_name'], $dst)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'move_failed']); exit; }

$url = rtrim(app_base(), '/') . '/uploads/images/' . $name;
echo json_encode(['ok'=>true,'url'=>$url], JSON_UNESCAPED_UNICODE);

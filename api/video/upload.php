<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/layout.php';

require_auth();
$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (!function_exists('csrf_verify') || !csrf_verify($token)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }

$f = $_FILES['file'] ?? $_FILES['video'] ?? null;
if (!$f || empty($f['name'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no_file']); exit; }
if (!empty($f['error'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'upload_error']); exit; }

$allowed = ['video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm','application/octet-stream'=>'mp4'];
$fi = new finfo(FILEINFO_MIME_TYPE);
$mime = $fi->file($f['tmp_name']) ?: '';
if (!isset($allowed[$mime])) { http_response_code(415); echo json_encode(['ok'=>false,'error'=>'bad_type']); exit; }

$ext = $allowed[$mime];
$base = dirname(__DIR__, 2);
$dir  = $base . '/uploads/videos';
@mkdir($dir, 0775, true);

$name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dst  = $dir . '/' . $name;

if (!move_uploaded_file($f['tmp_name'], $dst)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'move_failed']); exit; }

$url = rtrim(app_base(), '/') . '/uploads/videos/' . $name;
echo json_encode(['ok'=>true,'video'=>$url,'poster'=>null], JSON_UNESCAPED_UNICODE);

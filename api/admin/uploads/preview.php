<?php
declare(strict_types=1);

// Kein _bootstrap (das erzwingt POST). Wir erlauben GET + Admin-Auth.
require_once __DIR__ . '/../../../auth/db.php';
require_once __DIR__ . '/../../../auth/guards.php';

header('X-Content-Type-Options: nosniff');

$pdo = db();
$me  = require_admin(); // blockt Nicht-Admins

$root = realpath(__DIR__ . '/../../../');
if ($root === false) {
  http_response_code(500);
  exit('root_resolve_failed');
}

$rel = isset($_GET['rel']) ? (string)$_GET['rel'] : '';
if ($rel === '' || str_contains($rel, '..')) {
  http_response_code(400);
  exit('bad_rel');
}

$abs = realpath($root . '/' . ltrim($rel, '/'));
if (!$abs || !is_file($abs)) {
  http_response_code(404);
  exit('not_found');
}

// Nur innerhalb dieser Wurzeln ausliefern
$allowed = [
  realpath($root . '/uploads') ?: $root . '/uploads',
  realpath($root . '/public/uploads') ?: $root . '/public/uploads',
];
$inside = false;
foreach ($allowed as $base) {
  if ($base && str_starts_with($abs, rtrim($base, '/'))) { $inside = true; break; }
}
if (!$inside) {
  http_response_code(403);
  exit('outside_uploads_root');
}

// Content-Type nach Endung
$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mime = match ($ext) {
  'jpg','jpeg'        => 'image/jpeg',
  'png'               => 'image/png',
  'webp'              => 'image/webp',
  'gif'               => 'image/gif',
  'svg'               => 'image/svg+xml',
  'mp4'               => 'video/mp4',
  'webm'              => 'video/webm',
  'mov'               => 'video/quicktime',
  'mkv'               => 'video/x-matroska',
  default             => 'application/octet-stream',
};

$size = filesize($abs);
$etag = '"' . md5($abs . $size . filemtime($abs)) . '"';

header('Cache-Control: private, max-age=600');
header('ETag: ' . $etag);
header('Accept-Ranges: bytes');

// ETag kurz-circuit
if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag)) {
  http_response_code(304);
  exit;
}

// Einfaches Range-Handling (gut genug für Preview)
$start = 0; $end = $size - 1; $code = 200;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
  $start = (int)$m[1];
  if ($m[2] !== '') $end = (int)$m[2];
  if ($end >= $size) $end = $size - 1;
  if ($start > $end || $start >= $size) { http_response_code(416); exit; }
  $code = 206;
  header("Content-Range: bytes $start-$end/$size");
}

$length = $end - $start + 1;
http_response_code($code);
header('Content-Type: ' . $mime);
header('Content-Length: ' . $length);

$fp = fopen($abs, 'rb');
if ($start > 0) fseek($fp, $start);
$chunk = 8192;
while (!feof($fp) && $length > 0) {
  $read = ($length > $chunk) ? $chunk : $length;
  echo fread($fp, $read);
  $length -= $read;
}
fclose($fp);

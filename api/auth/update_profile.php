<?php
declare(strict_types=1);

// Immer JSON ausliefern
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../../lib/urls.php';
// Einheitliche Fehlerbehandlung
ini_set('display_errors', '0');
set_exception_handler(function ($e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','message'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
  exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'fatal'], JSON_UNESCAPED_SLASHES);
  }
});

// Abhängigkeiten
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/points.php'; // ✅ Punkte-System

// Nur POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
  exit;
}

$pdo  = db();
$user = require_auth();

$cfg      = require __DIR__ . '/../../auth/config.php';
$uploads  = $cfg['uploads'] ?? [];
$appBase  = rtrim((string)($cfg['app_base'] ?? '/'), '/');

// CSRF prüfen
$csrf = $_POST['csrf']
     ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
$sessionCookie = $_COOKIE[$cfg['cookies']['session_name']] ?? '';
if (!check_csrf($pdo, $sessionCookie, $csrf)) {
  http_response_code(419);
  echo json_encode(['ok'=>false,'error'=>'csrf_failed']);
  exit;
}

/* ---------- Eingaben ---------- */
$displayProvided = array_key_exists('display_name', $_POST);
$bioProvided     = array_key_exists('bio', $_POST);

$display_name_in    = $displayProvided ? trim((string)($_POST['display_name'] ?? '')) : null;
$bio_in             = $bioProvided     ? trim((string)($_POST['bio'] ?? '')) : null;

if ($displayProvided && $display_name_in === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'display_name_required']);
  exit;
}

$display_name = $displayProvided ? $display_name_in : ($user['display_name'] ?? '');
$bio          = $bioProvided     ? ($bio_in !== '' ? $bio_in : null) : ($user['bio'] ?? null);

$avatar_path  = $user['avatar_path'] ?? null;
$cover_path   = $user['cover_path']  ?? null;

// Cover-Position
$cover_x     = array_key_exists('cover_x', $_POST)     ? max(0.0, min(1.0, (float)$_POST['cover_x'])) : ($user['cover_x'] ?? null);
$cover_y     = array_key_exists('cover_y', $_POST)     ? max(0.0, min(1.0, (float)$_POST['cover_y'])) : ($user['cover_y'] ?? null);
$cover_scale = array_key_exists('cover_scale', $_POST) ? max(0.5, min(5.0, (float)$_POST['cover_scale'])) : ($user['cover_scale'] ?? null);

/* ---------- Social Eingaben ---------- */
$socialFields = ['twitch','tiktok','youtube','instagram','twitter','facebook'];

$inSocial = [];
foreach ($socialFields as $f) {
  if (array_key_exists($f, $_POST)) {
    $raw = (string)($_POST[$f] ?? '');
    if ($f === 'youtube') {
      $norm = norm_youtube($raw);
    } else {
      $norm = norm_handle_basic($raw);
    }
    $inSocial[$f] = $norm;
  } else {
    $inSocial[$f] = $user['social_'.$f] ?? null;
  }
}

/* ---------- Helpers ---------- */
function detect_mime(string $tmp): string {
  if (class_exists('finfo')) {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $m  = (string)$fi->file($tmp);
    if ($m) return $m;
  }
  if (function_exists('mime_content_type')) {
    $m = (string)@mime_content_type($tmp);
    if ($m) return $m;
  }
  return '';
}
function move_image_upload(array $file, string $targetDirFs, array $allowed, int $maxSize, int $uid): array {
  if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return [false,'no_file'];
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK)               return [false,'upload_error'];
  if (($file['size']  ?? 0) > $maxSize)                                  return [false,'file_too_large'];

  $mime = detect_mime($file['tmp_name']);
  if (!in_array($mime, $allowed, true)) return [false,'unsupported_media'];

  $ext = match($mime){ 'image/jpeg'=>'.jpg','image/png'=>'.png','image/gif'=>'.gif','image/webp'=>'.webp', default=>'.bin' };

  if (!is_dir($targetDirFs) && !@mkdir($targetDirFs,0775,true) && !is_dir($targetDirFs)) {
    return [false,'mkdir_failed'];
  }

  $name = 'u'.$uid.'_'.bin2hex(random_bytes(6)).$ext;
  $dest = rtrim($targetDirFs,'/').'/'.$name;

  if (!@move_uploaded_file($file['tmp_name'],$dest)) return [false,'move_failed'];
  return [true,$name];
}

/* ---------- Avatar-Upload ---------- */
if (!empty($_FILES['avatar']['tmp_name'])) {
  [$ok,$nameOrErr] = move_image_upload(
    $_FILES['avatar'],
    (string)($uploads['avatars_dir'] ?? __DIR__.'/../../uploads/avatars'),
    (array)($uploads['allowed'] ?? ['image/jpeg','image/png','image/gif','image/webp']),
    (int)($uploads['max_size'] ?? 2*1024*1024),
    (int)$user['id']
  );
  if (!$ok) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'avatar_'.$nameOrErr]); exit; }

  if ($avatar_path) {
    $oldFs = rtrim((string)($uploads['avatars_dir'] ?? ''),'/').'/'.basename($avatar_path);
    if ($oldFs && is_file($oldFs)) @unlink($oldFs);
  }
  $avatar_path = ($appBase ? $appBase : '').'/uploads/avatars/'.$nameOrErr;

  // ✅ Punkte vergeben für Avatar-Upload (mit try/catch)
  try {
    $POINTS = get_points_mapping();
    award_points($pdo, (int)$user['id'], 'upload_avatar', $POINTS['upload_avatar'] ?? 5);
  } catch (Throwable $e) {
    error_log("award_points avatar failed: ".$e->getMessage());
  }
}

/* ---------- Cover entfernen ---------- */
if (!empty($_POST['cover_remove']) && $cover_path) {
  $oldFs = rtrim((string)($uploads['covers_dir'] ?? ''),'/').'/'.basename($cover_path);
  if ($oldFs && is_file($oldFs)) @unlink($oldFs);
  $cover_path = null;
  $cover_x = $cover_y = $cover_scale = null;
}

/* ---------- Cover-Upload ---------- */
if (!empty($_FILES['cover']['tmp_name'])) {
  [$ok,$nameOrErr] = move_image_upload(
    $_FILES['cover'],
    (string)($uploads['covers_dir'] ?? __DIR__.'/../../uploads/covers'),
    (array)($uploads['allowed'] ?? ['image/jpeg','image/png','image/gif','image/webp']),
    (int)($uploads['max_size'] ?? 5*1024*1024),
    (int)$user['id']
  );
  if (!$ok) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'cover_'.$nameOrErr]); exit; }

  if ($cover_path) {
    $oldFs = rtrim((string)($uploads['covers_dir'] ?? ''),'/').'/'.basename($cover_path);
    if ($oldFs && is_file($oldFs)) @unlink($oldFs);
  }
  $cover_path = ($appBase ? $appBase : '').'/uploads/covers/'.$nameOrErr;

  // ✅ Punkte vergeben für Cover-Upload (mit try/catch)
  try {
    $POINTS = get_points_mapping();
    award_points($pdo, (int)$user['id'], 'upload_cover', $POINTS['upload_cover'] ?? 10);
  } catch (Throwable $e) {
    error_log("award_points cover failed: ".$e->getMessage());
  }
}

/* ---------- Persistieren ---------- */
$stmt = $pdo->prepare("
  UPDATE users
     SET display_name = ?,
         bio          = ?,
         avatar_path  = ?,
         cover_path   = ?,
         cover_x      = ?,
         cover_y      = ?,
         cover_scale  = ?,
         social_twitch    = ?,
         social_tiktok    = ?,
         social_youtube   = ?,
         social_instagram = ?,
         social_twitter   = ?,
         social_facebook  = ?
   WHERE id = ?
");
$stmt->execute([
  $display_name,
  $bio,
  $avatar_path ?: null,
  $cover_path  ?: null,
  $cover_x,
  $cover_y,
  $cover_scale,
  $inSocial['twitch'],
  $inSocial['tiktok'],
  $inSocial['youtube'],
  $inSocial['instagram'],
  $inSocial['twitter'],
  $inSocial['facebook'],
  $user['id']
]);

echo json_encode([
  'ok'          => true,
  'avatar'      => $avatar_path,
  'cover'       => $cover_path,
  'cover_x'     => $cover_x,
  'cover_y'     => $cover_y,
  'cover_scale' => $cover_scale
], JSON_UNESCAPED_SLASHES);

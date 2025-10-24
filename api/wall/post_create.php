<?php 
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/../../lib/wall.php';
require_once __DIR__ . '/../../lib/points.php'; // ✅ Punkte-System
require_once __DIR__ . '/../../lib/wall_media.php'; // 🆕 Upload/WEBP

// Login erzwingen
if (function_exists('require_user'))      { $me = require_user(); }
elseif (function_exists('require_login')) { $me = require_login(); }
elseif (function_exists('require_auth'))  { $me = require_auth(); }
else                                      { $me = require_admin(); }

// CSRF (Body oder Header)
$csrf = $_POST['csrf'] ?? $_POST['csrf_token'] ??
        $_SERVER['HTTP_X_CSRF'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';

if (function_exists('csrf_verify')) {
    if (!csrf_verify($csrf)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
} elseif (function_exists('verify_csrf')) {
    $cfg = require __DIR__ . '/../../auth/config.php';
    $sessionName = $cfg['cookies']['session_name'] ?? session_name();
    if (!verify_csrf(db(), $csrf, $sessionName)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
} else {
    if (!check_csrf_request($_POST)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
}

// Text/HTML aus mehreren Keys
$plain = trim((string)(
    $_POST['content_plain'] ?? $_POST['content'] ?? $_POST['text'] ?? $_POST['message'] ?? $_POST['body'] ?? ''
));
$html  = trim((string)($_POST['content_html'] ?? ''));

// Dateien vorhanden?
$hasFiles = false;
foreach (['files','file'] as $up) {
  if (!empty($_FILES[$up])) {
    if (is_array($_FILES[$up]['name'])) {
      if (count(array_filter($_FILES[$up]['name'])) > 0) { $hasFiles = true; break; }
    } else {
      if (($_FILES[$up]['name'] ?? '') !== '') { $hasFiles = true; break; }
    }
  }
}

// Wenn KEINE Dateien: Standardprüfung
if (!$hasFiles && !wall_validate_content($plain, $html)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'empty']);
  exit;
}

try {
  // Post anlegen
  $id  = wall_insert_post((int)$me['id'], $plain !== '' ? $plain : null, $html !== '' ? $html : null);
  $db  = db();

  // Punkte gutschreiben
  $POINTS = get_points_mapping();
  award_points($db, (int)$me['id'], 'wall_post', $POINTS['wall_post'] ?? 5);

  // Medien speichern (WEBP außer GIF)
  $savedUrls = [];
  if ($hasFiles) {
    $savedUrls = wall_media_save_uploads((int)$id);
  }

  // Karte rendern (mit Medien)
  $row = wall_get_post($db, $id);
  $htmlOut = wall_render_post_with_media($row);

  echo json_encode([
    'ok'   => true,
    'post' => [
      'id'   => $id,
      'html' => $htmlOut
    ],
    'media' => $savedUrls,
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('[wall.post_create] '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'create_failed']);
}

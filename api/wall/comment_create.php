<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/wall.php';
require_once __DIR__ . '/../../lib/points.php';      // ✅ Punkte-System
require_once __DIR__ . '/../../lib/wall_media.php';  // ✅ Kommentar-Medien

// Login (dein Fallback-Stil)
if (function_exists('require_user'))      { $me = require_user(); }
elseif (function_exists('require_login')) { $me = require_login(); }
elseif (function_exists('require_auth'))  { $me = require_auth(); }
else                                      { $me = require_admin(); }

// CSRF prüfen (Body oder Header akzeptieren)
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

// Eingaben
$post_id = (int)($_POST['post_id'] ?? $_POST['pid'] ?? 0);
$parent  = isset($_POST['parent_comment_id']) && $_POST['parent_comment_id'] !== '' ? (int)$_POST['parent_comment_id'] : null;

// Mehr Kompatibilität bei Textfeldern
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

// Validierung
if ($post_id <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'post_id']); exit; }
// ⬇️ Wichtig: Mit Dateien darf der Kommentartext leer sein.
if (!$hasFiles && !wall_validate_content($plain, $html)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'empty']);
  exit;
}

try {
  // Kommentar speichern
  $id = wall_insert_comment(
    (int)$me['id'],
    $post_id,
    $parent,
    $plain !== '' ? $plain : null,
    $html  !== '' ? $html  : null
  );

  // ✅ Punkte gutschreiben für Wall-Kommentar (deine Logik beibehalten)
  $db = db();
  $POINTS = get_points_mapping();
  award_points($db, (int)$me['id'], 'wall_comment', $POINTS['wall_comment'] ?? 2);

  // 🔼 Medien speichern (WEBP außer GIF) unter /uploads/comments/c{ID}/
  $savedUrls = [];
  if ($hasFiles) {
    $savedUrls = comment_media_save_uploads((int)$id); // dedup via sha1 in Helper
  }

  // Optionales HTML für sofortige Anzeige
  $mediaHtml = $savedUrls ? comment_render_attachments_html((int)$id) : '';

  echo json_encode([
    'ok'         => true,
    'id'         => $id,
    'media'      => $savedUrls, // Debug/Client-Use
    'media_html' => $mediaHtml, // direkt nach dem Kommentartext einfügbar
  ], JSON_UNESCAPED_UNICODE);

} catch (RuntimeException $ex) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>$ex->getMessage()]);
} catch (Throwable $e) {
  error_log('[wall.comment_create] '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'create_failed']);
}

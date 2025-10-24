<?php
// /api/forum/comment_update.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
  exit;
}

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../forum/lib_forum_text.php';

$pdo  = db();
$user = require_auth();

$cfg  = require __DIR__ . '/../../auth/config.php';
$csrf = $_POST['csrf']
     ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));

$sid  = $_COOKIE[$cfg['cookies']['session_name']] ?? '';

if (!check_csrf($pdo, $sid, $csrf)) {
  http_response_code(419);
echo json_encode(['ok'=>false,'error'=>'csrf_failed']);
  exit;
}

$commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$content   = isset($_POST['content']) ? (string)$_POST['content'] : '';

if ($commentId <= 0) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'invalid_comment_id']);
  exit;
}

// optional: minimale Validierung
if (mb_strlen($content) > 100000) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'content_too_long']);
  exit;
}

// Kommentar laden
$st = $pdo->prepare("SELECT id, author_id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$st->execute([$commentId]);
$comment = $st->fetch(PDO::FETCH_ASSOC);
if (!$comment) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>'comment_not_found']);
  exit;
}

// Berechtigung: Admin/Mod oder Autor
$role    = (string)($user['role'] ?? 'user');
$allowed = in_array($role, ['administrator','moderator'], true) || ((int)$user['id'] === (int)$comment['author_id']);
if (!$allowed) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'forbidden']);
  exit;
}

$pdo->beginTransaction();
try {
  // Edit setzen (content + edited_at + updated_at)
  $q = $pdo->prepare("UPDATE posts SET content = ?, edited_at = NOW(), updated_at = NOW() WHERE id = ?");
  $q->execute([$content, $commentId]);

  $pdo->commit();

  // Server-seitiges Rendering für sofortige DOM-Aktualisierung
  $rendered = forum_render_text($content);
  $editedAtDisplay = date('d.m.Y, H:i');

  echo json_encode([
    'ok' => true,
    'comment_id' => $commentId,
    'rendered_html' => $rendered,
    'edited_at_display' => $editedAtDisplay,
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

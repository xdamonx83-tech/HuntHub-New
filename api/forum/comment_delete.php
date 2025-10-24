<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false, 'error'=>'method_not_allowed']);
  exit;
}

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';

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
if ($commentId <= 0) {
  http_response_code(422);
  echo json_encode(['ok'=>false, 'error'=>'invalid_comment_id']);
  exit;
}

// Kommentar laden
$st = $pdo->prepare('SELECT id, thread_id, author_id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$st->execute([$commentId]);
$comment = $st->fetch(PDO::FETCH_ASSOC);
if (!$comment) {
  http_response_code(404);
  echo json_encode(['ok'=>false, 'error'=>'comment_not_found']);
  exit;
}

// Berechtigung prüfen
$role    = (string)($user['role'] ?? 'user');
$allowed = in_array($role, ['administrator','moderator'], true) || ((int)$user['id'] === (int)$comment['author_id']);
if (!$allowed) {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'error'=>'forbidden']);
  exit;
}

$pdo->beginTransaction();
try {
  // Soft-Delete via deleted_at
  $pdo->prepare('UPDATE posts SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$commentId]);

  // Thread-Postzähler best-effort senken
  $threadId = (int)$comment['thread_id'];
  $pdo->prepare('UPDATE threads SET posts_count = CASE WHEN posts_count>0 THEN posts_count-1 ELSE 0 END WHERE id = ?')->execute([$threadId]);

  // Optional Boards-Zähler
  $q = $pdo->prepare('SELECT board_id FROM threads WHERE id = ? LIMIT 1');
  $q->execute([$threadId]);
  if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $pdo->prepare('UPDATE boards SET posts_count = CASE WHEN posts_count>0 THEN posts_count-1 ELSE 0 END WHERE id = ?')
        ->execute([(int)$row['board_id']]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'comment_id'=>$commentId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

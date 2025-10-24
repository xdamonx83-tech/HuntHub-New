<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../forum/lib_forum_text.php';

try {
  $pdo = db();
  $me  = require_auth(); // wir brauchen einen eingeloggten Nutzer

  // CSRF: aus Header X-CSRF oder aus POST 'csrf'
  $csrf = $_SERVER['HTTP_X_CSRF'] ?? $_POST['csrf'] ?? '';
  if (!verify_csrf($pdo, $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'unauthorized']);
    exit;
  }

  // Input
  $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
  $content   = trim((string)($_POST['content'] ?? ''));

  if ($commentId <= 0)   throw new RuntimeException('comment_id missing');
  if ($content === '')   throw new RuntimeException('content empty');

  // Kommentar + Thread-Status laden
  $st = $pdo->prepare("
    SELECT p.id, p.author_id, p.thread_id, t.is_locked
    FROM posts p
    JOIN threads t ON t.id = p.thread_id
    WHERE p.id = ? AND p.deleted_at IS NULL
    LIMIT 1
  ");
  $st->execute([$commentId]);
  $row = $st->fetch();
  if (!$row) throw new RuntimeException('not found');

  if ((int)$row['is_locked'] === 1) throw new RuntimeException('thread locked');

  // Berechtigung: Owner, Admin, Moderator
  $role = (string)($me['role'] ?? 'user');
  $isOwner = ((int)$me['id'] === (int)$row['author_id']);
  $isMod   = in_array($role, ['administrator','moderator'], true);
  if (!$isOwner && !$isMod) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'forbidden']);
    exit;
  }

  // Update
  $up = $pdo->prepare("UPDATE posts SET content = ?, edited_at = NOW() WHERE id = ? LIMIT 1");
  $up->execute([$content, $commentId]);

  // Aufbereitete Darstellung (wie im Frontend erwartet)
  $rendered = forum_render_text($content);
  $editedAt = $pdo->query("SELECT DATE_FORMAT(edited_at, '%Y-%m-%d %H:%i:%s') FROM posts WHERE id = ".(int)$commentId)->fetchColumn();

  echo json_encode([
    'ok' => true,
    'rendered_html' => $rendered,
    'edited_at_display' => $editedAt ?: date('Y-m-d H:i:s'),
  ]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

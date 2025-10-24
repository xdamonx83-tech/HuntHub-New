<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';

try {
  $me  = require_auth();
  $uid = (int)($me['id'] ?? 0);
  $pdo = db();

  check_csrf_request($_POST);

  $commentId = (int)($_POST['comment_id'] ?? 0);
  if ($commentId <= 0) throw new RuntimeException('invalid_comment_id');

  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT id, user_id, content_plain, content_html, content_deleted_at FROM wall_comments WHERE id=? FOR UPDATE");
  $st->execute([$commentId]);
  $c = $st->fetch(PDO::FETCH_ASSOC);
  if (!$c) throw new RuntimeException('not_found');

  $ownerId = (int)$c['user_id'];
  $isAdmin = (string)($me['role'] ?? '') === 'administrator';
  if ($uid !== $ownerId && !$isAdmin) throw new RuntimeException('forbidden');

  // Bereits gelöscht? -> idempotent ok
  if (!empty($c['content_deleted_at'])) {
    $pdo->commit();
    echo json_encode(['ok'=>true, 'comment_id'=>$commentId, 'deleted_content'=>true]); exit;
  }

  // Inhalt leeren, aber Kommentar behalten
  $up = $pdo->prepare("UPDATE wall_comments
                          SET content_plain=NULL,
                              content_html=NULL,
                              content_deleted_at=NOW(),
                              content_deleted_by=?
                        WHERE id=?");
  $up->execute([$uid, $commentId]);

  $pdo->commit();
  echo json_encode(['ok'=>true, 'comment_id'=>$commentId, 'deleted_content'=>true]);
} catch (Throwable $e) {
  if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

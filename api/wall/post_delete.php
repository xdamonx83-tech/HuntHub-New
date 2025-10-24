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

  $postId = (int)($_POST['post_id'] ?? 0);
  if ($postId <= 0) throw new RuntimeException('invalid_post_id');

  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT id, user_id, deleted_at FROM wall_posts WHERE id=? FOR UPDATE");
  $st->execute([$postId]);
  $p = $st->fetch(PDO::FETCH_ASSOC);
  if (!$p) throw new RuntimeException('not_found');

  $ownerId = (int)$p['user_id'];
  $isAdmin = (string)($me['role'] ?? '') === 'administrator';
  if ($uid !== $ownerId && !$isAdmin) throw new RuntimeException('forbidden');

  if (empty($p['deleted_at'])) {
    $up1 = $pdo->prepare("UPDATE wall_posts SET deleted_at=NOW() WHERE id=?");
    $up1->execute([$postId]);

    // dazugehörige Kommentare ebenfalls ausblenden
    $up2 = $pdo->prepare("UPDATE wall_comments SET deleted_at=NOW() WHERE post_id=? AND deleted_at IS NULL");
    $up2->execute([$postId]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'post_id'=>$postId]);
} catch (Throwable $e) {
  if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

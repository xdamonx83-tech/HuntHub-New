<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/wall_edits.php';

function detect_text_cols(PDO $pdo, string $table): array {
  $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $st->execute([$table]);
  $names = array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
  $set = array_flip($names);

  $plainCandidates = ['content_plain','body_plain','text_plain','content','body','text','message'];
  $htmlCandidates  = ['content_html','body_html','html','message_html'];

  $plain = null; foreach ($plainCandidates as $c) if (isset($set[$c])) { $plain = $c; break; }
  $html  = null; foreach ($htmlCandidates as $c) if (isset($set[$c])) { $html = $c; break; }

  if (!$plain) throw new RuntimeException('text_column_not_found_comments');
  return [$plain, $html];
}

try {
  $me  = require_auth();
  $uid = (int)($me['id'] ?? 0);
  $pdo = db();

  check_csrf_request($_POST);

  $commentId = (int)($_POST['comment_id'] ?? 0);
  $new       = trim((string)($_POST['body'] ?? ''));

  if ($commentId <= 0) throw new RuntimeException('invalid_comment_id');
  if ($new === '')     throw new RuntimeException('empty_body');

  [$plainCol, $htmlCol] = detect_text_cols($pdo, 'wall_comments');

  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT id, user_id, `$plainCol` AS body FROM wall_comments WHERE id=? FOR UPDATE");
  $st->execute([$commentId]);
  $c = $st->fetch(PDO::FETCH_ASSOC);
  if (!$c) throw new RuntimeException('not_found');

  $ownerId = (int)$c['user_id'];
  $isAdmin = (string)($me['role'] ?? '') === 'administrator';
  if ($uid !== $ownerId && !$isAdmin) throw new RuntimeException('forbidden');

  $old = (string)$c['body'];
  if ($old === $new) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'no_change']); exit; }

  wall_save_comment_edit($pdo, (int)$c['id'], $uid, $old, $new);

  $up = $pdo->prepare("UPDATE wall_comments SET `$plainCol`=?, updated_at=NOW() WHERE id=?");
  $up->execute([$new, $commentId]);

  $pdo->commit();

  echo json_encode(['ok'=>true,'comment_id'=>$commentId,'body'=>$new,'has_edits'=>true]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in = in_json();
  $id = (int)($in['id'] ?? 0);
  if ($id<=0){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

  // prüfe, ob post_likes existiert
  $hasLikes = (bool)$pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='post_likes'
  ")->fetchColumn();

  $pdo->beginTransaction();
  if ($hasLikes) {
    $pdo->prepare("DELETE FROM post_likes WHERE post_id = :id")->execute([':id'=>$id]);
  }
  $pdo->prepare("DELETE FROM posts WHERE id = :id")->execute([':id'=>$id]);
  $pdo->commit();

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in  = in_json();
  $id  = (int)($in['id'] ?? 0);
  $txt = (string)($in['content'] ?? '');
  if ($id<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

  // Spalten bestimmen
  $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='posts'");
  $colsStmt->execute();
  $have = array_flip(array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
  $has  = fn(string $c) => isset($have[$c]);

  $candidates = ['content','body','text','message'];
  $contentCol = null;
  foreach ($candidates as $c) if ($has($c)) { $contentCol = $c; break; }
  if (!$contentCol) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'no_content_column']); exit; }

  $sql = "UPDATE posts SET `$contentCol` = :c";
  if ($has('updated_at')) $sql .= ", updated_at = NOW()";
  $sql .= " WHERE id = :id";

  $st = $pdo->prepare($sql);
  $st->execute([':c'=>$txt, ':id'=>$id]);

  echo json_encode(['ok'=>true,'changed'=>$st->rowCount()]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in  = in_json();
  $tid = (int)($in['thread_id'] ?? 0);
  $q   = trim((string)($in['q'] ?? ''));
  $lim = max(1, min(200, (int)($in['limit'] ?? 50)));
  $off = max(0, (int)($in['offset'] ?? 0));
  if ($tid <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_thread_id']); exit; }

  // Spalten in posts erkennen
  $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='posts'");
  $colsStmt->execute();
  $have = array_flip(array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
  $has  = fn(string $c) => isset($have[$c]);

  // Content-Feld herausfinden
  $candidates = ['content','body','text','message'];
  $contentCol = null;
  foreach ($candidates as $c) if ($has($c)) { $contentCol = $c; break; }
  if (!$contentCol) $contentCol = 'content'; // Alias auf NULL
  $contentSelect = $has($contentCol) ? "p.`$contentCol`" : "NULL";

  // optional Felder
  $createdSel = $has('created_at') ? "p.created_at" : "NULL AS created_at";
  $userSel    = $has('user_id')    ? "p.user_id"    : "NULL AS user_id";

  // Autor-Name über Subquery (vermeidet JOIN-Probleme)
  $authorSel = $has('user_id')
    ? "(SELECT u.display_name FROM users u WHERE u.id = p.user_id LIMIT 1) AS author"
    : "NULL AS author";

  $sql = "SELECT p.id, p.thread_id, {$userSel}, {$contentSelect} AS content, {$createdSel}, {$authorSel}
          FROM posts p WHERE p.thread_id = :tid";

  $par = [':tid'=>$tid];
  if ($q !== '') {
    $sql .= " AND {$contentSelect} LIKE :q";
    $par[':q'] = "%{$q}%";
  }
  $sql .= " ORDER BY p.id ASC LIMIT :lim OFFSET :off";

  $st = $pdo->prepare($sql);
  foreach ($par as $k=>$v) $st->bindValue($k,$v);
  $st->bindValue(':lim',$lim,PDO::PARAM_INT);
  $st->bindValue(':off',$off,PDO::PARAM_INT);
  $st->execute();

  echo json_encode(['ok'=>true,'items'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

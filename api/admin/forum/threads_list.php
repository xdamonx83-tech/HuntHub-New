<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in   = in_json();
  $q    = trim((string)($in['q'] ?? ''));
  $bid  = (int)($in['board_id'] ?? 0);
  $lim  = max(1, min(200, (int)($in['limit'] ?? 50)));
  $off  = max(0, (int)($in['offset'] ?? 0));

  // Spalten in threads prüfen
  $colsStmt = $pdo->prepare("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'threads'
  ");
  $colsStmt->execute();
  $haveThreads = array_flip(array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
  $hasT = fn(string $c) => isset($haveThreads[$c]);

  // Spalten in posts (für Autor-Subquery)
  $colsPosts = $pdo->prepare("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts'
  ");
  $colsPosts->execute();
  $havePosts = array_flip(array_column($colsPosts->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
  $hasP = fn(string $c) => isset($havePosts[$c]);

  // SELECT-Felder (ohne JOINs, damit keine ON-Fehler auftreten)
  $authorExpr = "NULL AS author";
  if ($hasT('user_id')) {
    // threads.user_id -> Nutzername
    $authorExpr = "(SELECT u.display_name FROM users u WHERE u.id = t.user_id LIMIT 1) AS author";
  } elseif ($hasP('user_id') && $hasP('thread_id')) {
    // erster Post in diesem Thread -> Nutzername
    $authorExpr = "(SELECT u.display_name
                     FROM posts p JOIN users u ON u.id = p.user_id
                    WHERE p.thread_id = t.id
                    ORDER BY p.id ASC LIMIT 1) AS author";
  }

  $boardNameExpr = "NULL AS board_name";
  if ($hasT('board_id')) {
    $boardNameExpr = "(SELECT b.name FROM boards b WHERE b.id = t.board_id LIMIT 1) AS board_name";
  }

  $slugExpr      = $hasT('slug')       ? "t.slug"        : "NULL AS slug";
  $boardIdExpr   = $hasT('board_id')   ? "t.board_id"    : "NULL AS board_id";
  $userIdExpr    = $hasT('user_id')    ? "t.user_id"     : "NULL AS user_id";
  $lockedExpr    = $hasT('is_locked')  ? "COALESCE(t.is_locked,0) AS is_locked" : "0 AS is_locked";
  $pinnedExpr    = $hasT('is_pinned')  ? "COALESCE(t.is_pinned,0) AS is_pinned" : "0 AS is_pinned";
  $createdExpr   = $hasT('created_at') ? "t.created_at"  : "NULL AS created_at";
  $updatedExpr   = $hasT('updated_at') ? "t.updated_at"  : "NULL AS updated_at";

  $postsCountExpr = "(SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id) AS posts_count";

  $select = implode(", ", [
    "t.id",
    "t.title",
    $slugExpr,
    $boardIdExpr,
    $userIdExpr,
    $lockedExpr,
    $pinnedExpr,
    $createdExpr,
    $updatedExpr,
    $authorExpr,
    $postsCountExpr,
    $boardNameExpr
  ]);

  $sql = "SELECT {$select} FROM threads t";

  // WHERE dynamisch bauen
  $where = [];
  $par   = [];

  if ($q !== '') {
    $wc = ["t.title LIKE :q"];
    if ($hasT('slug')) $wc[] = "t.slug LIKE :q";
    $where[] = "(" . implode(" OR ", $wc) . ")";
    $par[':q'] = "%{$q}%";
  }
  if ($bid > 0 && $hasT('board_id')) {
    $where[] = "t.board_id = :bid";
    $par[':bid'] = $bid;
  }

  if ($where) $sql .= " WHERE " . implode(" AND ", $where);
  $sql .= " ORDER BY t.id DESC LIMIT :lim OFFSET :off";

  $st = $pdo->prepare($sql);
  foreach ($par as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $lim, PDO::PARAM_INT);
  $st->bindValue(':off', $off, PDO::PARAM_INT);
  $st->execute();

  echo json_encode(['ok' => true, 'items' => $st->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

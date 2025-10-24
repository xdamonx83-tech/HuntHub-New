<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  // Pfade robust auflösen (…/api/admin/reports → …/api → /auth)
  $API_DIR = __DIR__;
  $ROOT    = dirname($API_DIR, 2);
  require_once $ROOT . '/../auth/db.php';
  require_once $ROOT . '/../auth/guards.php';

  $pdo = db();
  require_admin();

  // Hilfsfunktion: passende User-Namensspalte ermitteln
  $userCols = [];
  foreach ($pdo->query('SHOW COLUMNS FROM `users`', PDO::FETCH_ASSOC) as $c) {
    $userCols[strtolower($c['Field'])] = true;
  }
  $pick = function(array $cands) use ($userCols) {
    foreach ($cands as $c) if (isset($userCols[strtolower($c)])) return $c;
    return null;
  };
  $nameCol = $pick(['display_name','username','user_name','name','fullname','login','nick','nickname','email']);
  $nameSel = $nameCol ? "u.`$nameCol`" : "CONCAT('User #', u.id)";

  // Grundliste: wie viele Reports pro Entity?
  $base = $pdo->query("
    SELECT entity_type, entity_id, COUNT(*) AS reports, MAX(created_at) AS last_report_at
    FROM wall_reports
    GROUP BY entity_type, entity_id
    ORDER BY last_report_at DESC
    LIMIT 500
  ")->fetchAll(PDO::FETCH_ASSOC);

  $items = [];

  // kleiner Helper: Gründe je Entity zählen
  $reasonsStmt = $pdo->prepare("
    SELECT reason, COUNT(*) AS c
    FROM wall_reports
    WHERE entity_type = ? AND entity_id = ?
    GROUP BY reason
  ");

  foreach ($base as $row) {
    $type = (string)$row['entity_type'];
    $id   = (int)$row['entity_id'];

    if ($type === 'post') {
      // Nur holen, wenn Post noch existiert und NICHT gelöscht
      $st = $pdo->prepare("
        SELECT p.id, p.user_id, p.content_plain, p.content_html, p.created_at, p.under_review_at, p.deleted_at,
               $nameSel AS author_name
        FROM wall_posts p
        JOIN users u ON u.id = p.user_id
        WHERE p.id = ?
        LIMIT 1
      ");
      $st->execute([$id]);
      $p = $st->fetch(PDO::FETCH_ASSOC);
      if (!$p || !empty($p['deleted_at'])) continue; // GELÖSCHTE aus Liste entfernen

      $plain = (string)($p['content_plain'] ?? '');
      $html  = (string)($p['content_html']  ?? '');
      $preview = $plain !== '' ? $plain : (string)preg_replace('~<[^>]+>~', '', $html);
      $preview = mb_substr(trim($preview), 0, 220);

      // Gründe aggregieren
      $reasonsStmt->execute(['post', $id]);
      $rs = [];
      foreach ($reasonsStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rs[$r['reason']] = (int)$r['c'];

      $items[] = [
        'type'         => 'post',
        'entity_id'    => $id,
        'reports'      => (int)$row['reports'],
        'reasons'      => $rs,
        'author_id'    => (int)$p['user_id'],
        'author_name'  => (string)$p['author_name'],
        'preview'      => $preview,
        'under_review' => !empty($p['under_review_at']),
        'created_at'   => (string)$p['created_at'],
      ];
    } elseif ($type === 'comment') {
      // Nur holen, wenn Kommentar noch existiert und NICHT gelöscht
      $st = $pdo->prepare("
        SELECT c.id, c.user_id, c.content_plain, c.content_html, c.created_at, c.under_review_at, c.deleted_at,
               $nameSel AS author_name
        FROM wall_comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.id = ?
        LIMIT 1
      ");
      $st->execute([$id]);
      $c = $st->fetch(PDO::FETCH_ASSOC);
      if (!$c || !empty($c['deleted_at'])) continue; // GELÖSCHTE aus Liste entfernen

      $plain = (string)($c['content_plain'] ?? '');
      $html  = (string)($c['content_html']  ?? '');
      $preview = $plain !== '' ? $plain : (string)preg_replace('~<[^>]+>~', '', $html);
      $preview = mb_substr(trim($preview), 0, 220);

      // Gründe aggregieren
      $reasonsStmt->execute(['comment', $id]);
      $rs = [];
      foreach ($reasonsStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rs[$r['reason']] = (int)$r['c'];

      $items[] = [
        'type'         => 'comment',
        'entity_id'    => $id,
        'reports'      => (int)$row['reports'],
        'reasons'      => $rs,
        'author_id'    => (int)$c['user_id'],
        'author_name'  => (string)$c['author_name'],
        'preview'      => $preview,
        'under_review' => !empty($c['under_review_at']),
        'created_at'   => (string)$c['created_at'],
      ];
    }
  }

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'server_error','hint'=>$e->getMessage()]);
  exit;
}

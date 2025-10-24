<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  // /api/admin/reports → hoch bis /api, dann zu /auth
  $API_DIR = __DIR__;
  $ROOT    = dirname($API_DIR, 2);
  require_once $ROOT . '/../auth/db.php';
  require_once $ROOT . '/../auth/guards.php';

  $pdo = db();
  require_admin();

  // Payload: JSON oder FormData
  $raw = file_get_contents('php://input') ?: '';
  $in  = json_decode($raw, true);
  if (!is_array($in)) $in = $_POST;

  $type = isset($in['type']) ? (string)$in['type'] : '';
  $id   = isset($in['entity_id']) ? (int)$in['entity_id'] : 0;

  if (!$type || !$id || !in_array($type, ['post','comment'], true)) {
    echo json_encode(['ok'=>false,'error'=>'bad_request']); exit;
  }

  // User-Spalte für Namen robust ermitteln
  $cols = [];
  foreach ($pdo->query('SHOW COLUMNS FROM `users`', PDO::FETCH_ASSOC) as $c) {
    $cols[strtolower($c['Field'])] = true;
  }
  $pick = function(array $cands) use ($cols) {
    foreach ($cands as $c) if (isset($cols[strtolower($c)])) return $c;
    return null;
  };
  $nameCol = $pick(['display_name','username','user_name','name','fullname','login','nick','nickname','email']);
  $nameSel = $nameCol ? "u.`$nameCol`" : "CONCAT('User #', u.id)";

  // Reports zählen + Gründe
  $stR = $pdo->prepare('SELECT reason, COUNT(*) AS c FROM wall_reports WHERE entity_type=? AND entity_id=? GROUP BY reason');
  $stR->execute([$type, $id]);
  $reasons = [];
  $reports = 0;
  foreach ($stR->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $reasons[(string)$r['reason']] = (int)$r['c'];
    $reports += (int)$r['c'];
  }

  // Entity laden
  if ($type === 'post') {
    $st = $pdo->prepare("
      SELECT p.id, p.user_id, p.content_plain, p.content_html, p.created_at, p.under_review_at,
             $nameSel AS author_name
      FROM wall_posts p
      JOIN users u ON u.id = p.user_id
      WHERE p.id = ? LIMIT 1
    ");
  } else {
    $st = $pdo->prepare("
      SELECT c.id, c.user_id, c.content_plain, c.content_html, c.created_at, c.under_review_at,
             $nameSel AS author_name
      FROM wall_comments c
      JOIN users u ON u.id = c.user_id
      WHERE c.id = ? LIMIT 1
    ");
  }
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  echo json_encode([
    'ok'           => true,
    'type'         => $type,
    'entity_id'    => $id,
    'reports'      => $reports,
    'reasons'      => $reasons,
    'author_id'    => (int)$row['user_id'],
    'author_name'  => (string)$row['author_name'],
    'created_at'   => (string)$row['created_at'],
    'under_review' => !empty($row['under_review_at']),
    'content'      => [
      'plain' => (string)($row['content_plain'] ?? ''),
      'html'  => (string)($row['content_html']  ?? '')
    ]
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'server_error','hint'=>$e->getMessage()]);
  exit;
}

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../../auth/db.php';
require_once __DIR__.'/../../auth/guards.php';

$pdo = db();
$me  = require_auth();

// Eingaben
$limit = (int)($_GET['limit'] ?? 20);
$limit = max(1, min(50, $limit)); // 1..50

$onlyUnread = isset($_GET['only_unread']) && (string)$_GET['only_unread'] === '1';
$beforeId   = (int)($_GET['before_id'] ?? 0); // Cursor: nur IDs < before_id holen

// Unread total für Badge (unabhängig vom Limit)
$cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
$cnt->execute([(int)$me['id']]);
$unreadTotal = (int)$cnt->fetchColumn();

// Haupt-Query
$sql = "
  SELECT
    n.id, n.type, n.thread_id, n.post_id, n.created_at, n.read_at,
    u.id   AS actor_id,
    u.display_name AS actor_name,
    u.avatar_path  AS actor_avatar,
    t.title AS thread_title,
    t.slug  AS thread_slug
  FROM notifications n
  JOIN users   u ON u.id = n.actor_id
  LEFT JOIN threads t ON t.id = n.thread_id
  WHERE n.user_id = :uid
";
$params = [':uid' => (int)$me['id']];

if ($onlyUnread) {
  $sql .= " AND n.read_at IS NULL";
}
if ($beforeId > 0) {
  $sql .= " AND n.id < :before_id";
  $params[':before_id'] = $beforeId;
}

$sql .= " ORDER BY n.id DESC LIMIT {$limit}";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Nächster Cursor (falls weitere Einträge vorhanden sein könnten)
$nextBefore = null;
if (count($rows) === $limit) {
  // kleinste ID in diesem Batch als nächster 'before_id'
  $ids = array_column($rows, 'id');
  if ($ids) $nextBefore = min($ids);
}

echo json_encode([
  'ok'           => true,
  'items'        => $rows,
  'unread_total' => $unreadTotal,
  'next_before'  => $nextBefore, // für "Mehr laden"
], JSON_UNESCAPED_UNICODE);

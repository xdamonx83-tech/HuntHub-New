<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

$pdo = db();
$me  = require_auth();

$q     = trim((string)($_GET['q'] ?? ''));
$limit = max(5, min(200, (int)($_GET['limit'] ?? 100)));

$sql = "
  WITH my_msgs AS (
    SELECT
      IF(sender_id = :me, receiver_id, sender_id) AS other_id,
      id, sender_id, receiver_id, read_at, created_at
    FROM messages
    WHERE sender_id = :me OR receiver_id = :me
  ),
  conv AS (
    SELECT
      other_id,
      MAX(id)         AS last_id,
      MAX(created_at) AS last_at,
      SUM(CASE WHEN receiver_id = :me AND read_at IS NULL THEN 1 ELSE 0 END) AS unread
    FROM my_msgs
    GROUP BY other_id
  )
  SELECT
    c.other_id AS user_id,
    u.display_name,
    u.avatar_path,
    c.last_at,
    c.unread,
    m.body AS last_text
  FROM conv c
  JOIN users u ON u.id = c.other_id
  JOIN messages m ON m.id = c.last_id
  /** optional Suche nach Name */
  ".($q !== '' ? "WHERE u.display_name LIKE :q " : "")."
  ORDER BY c.last_at DESC
  LIMIT :lim
";

$st = $pdo->prepare($sql);
$st->bindValue(':me',  (int)$me['id'], PDO::PARAM_INT);
if ($q !== '') $st->bindValue(':q', '%'.$q.'%');
$st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);
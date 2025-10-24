<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

$pdo = db();
$me  = require_auth();
$limit = max(1, min(20, (int)($_GET['limit'] ?? 5)));

/*
  Liefert die letzten Konversationen (je Gegenüber 1 Zeile),
  sortiert nach der neuesten Nachricht.
  other_id = CASE WHEN sender_id=:me THEN recipient_id ELSE sender_id END
  last_msg_id = MAX(id) pro other_id (id ist monoton steigend).
*/
$sql = "
  SELECT
    u.id          AS user_id,
    u.display_name,
    u.avatar_path,
    m2.id         AS last_msg_id,
    m2.body       AS last_body,
    m2.attachment_type,
    m2.attachment_url,
    m2.created_at AS last_at,
    (
      SELECT COUNT(*) FROM messages um
      WHERE um.sender_id = u.id
        AND um.recipient_id = :me
        AND um.read_at IS NULL
    ) AS unread_count
  FROM (
    SELECT
      CASE WHEN sender_id = :me THEN recipient_id ELSE sender_id END AS other_id,
      MAX(id) AS last_msg_id
    FROM messages
    WHERE sender_id = :me OR recipient_id = :me
    GROUP BY other_id
  ) t
  JOIN messages m2 ON m2.id = t.last_msg_id
  JOIN users u     ON u.id  = t.other_id
  ORDER BY m2.created_at DESC
  LIMIT {$limit}
";
$st = $pdo->prepare($sql);
$st->execute([':me' => (int)$me['id']]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode(['ok'=>true, 'items'=>$rows], JSON_UNESCAPED_UNICODE);

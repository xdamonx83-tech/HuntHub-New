<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../auth/db.php';

try {
  $db = db();
  $db->exec("
    CREATE TABLE IF NOT EXISTS global_chat_messages (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL DEFAULT 0,
      username VARCHAR(64) NULL,
      body TEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_created_at (created_at),
      INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ");

  $since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
  $limit    = (int)($_GET['limit'] ?? 100);
  if ($limit < 20) $limit = 20;
  if ($limit > 200) $limit = 200;

  $select = "id, user_id, body, created_at,
             COALESCE(username, CASE WHEN user_id=0 THEN 'Gast' ELSE CONCAT('User#',user_id) END) AS username";

  if ($since_id > 0) {
    $sql = "SELECT $select FROM global_chat_messages
            WHERE created_at >= (NOW() - INTERVAL 24 HOUR) AND id > :sid
            ORDER BY id ASC LIMIT {$limit}";
    $st = $db->prepare($sql);
    $st->bindValue(':sid', $since_id, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $sql = "SELECT $select FROM global_chat_messages
            WHERE created_at >= (NOW() - INTERVAL 24 HOUR)
            ORDER BY id DESC LIMIT {$limit}";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $rows = array_reverse($rows);
  }

  echo json_encode(['ok'=>true,'messages'=>$rows]); exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}

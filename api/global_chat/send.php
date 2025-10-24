<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../auth/db.php';

try {
  $db = db();

  // Tabelle mit username-Spalte sicherstellen
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

  // Eingabe lesen
  $raw  = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = $_POST ?? [];
  $body = trim((string)($data['body'] ?? ''));
  $username = trim((string)($data['username'] ?? 'Gast'));

  if ($body === '' || mb_strlen($body) > 2000) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Empty or too long']); exit;
  }
  if ($username === '') $username = 'Gast';
  if (mb_strlen($username) > 64) $username = mb_substr($username, 0, 64);

  $userId = 0; // bis Auth repariert ist

  // Alte Nachrichten löschen (>24h)
  $db->exec("DELETE FROM global_chat_messages WHERE created_at < (NOW() - INTERVAL 24 HOUR)");

  // Speichern
  $ins = $db->prepare("INSERT INTO global_chat_messages (user_id, username, body) VALUES (?, ?, ?)");
  $ins->execute([$userId, $username, $body]);
  $id = (int)$db->lastInsertId();

  echo json_encode([
    'ok'=>true,
    'message'=>[
      'id'=>$id,
      'user_id'=>$userId,
      'username'=>$username,
      'body'=>$body,
      'created_at'=>date('Y-m-d H:i:s')
    ]
  ]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}

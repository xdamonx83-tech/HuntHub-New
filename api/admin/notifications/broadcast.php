<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$in = in_json();
$title = trim((string)($in['title'] ?? ''));
$body  = trim((string)($in['body']  ?? ''));
$link  = trim((string)($in['link']  ?? ''));
$level = strtolower(trim((string)($in['level'] ?? 'info'))); // info|warn|critical
$fan  = !empty($in['fan_out']);

if ($title === '' || $body === '') {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_title_or_body']); exit;
}
if (!in_array($level, ['info','warn','critical'], true)) $level = 'info';

// Tabelle für Broadcasts anlegen (id, title, body, link, level, created_by, is_active)
$pdo->exec("
  CREATE TABLE IF NOT EXISTS site_broadcasts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    body TEXT NOT NULL,
    link VARCHAR(255) NULL,
    level ENUM('info','warn','critical') NOT NULL DEFAULT 'info',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$st = $pdo->prepare("INSERT INTO site_broadcasts (title,body,link,level,created_by) VALUES (:t,:b,:l,:lv,:uid)");
$st->execute([':t'=>$title, ':b'=>$body, ':l'=>($link?:null), ':lv'=>$level, ':uid'=>$me['id']]);
$bid = (int)$pdo->lastInsertId();

$fanCount = 0;
if ($fan) {
  // notifications.type um 'broadcast' erweitern, falls nicht vorhanden
  $col = $pdo->query("
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='type'
  ")->fetchColumn();

  if ($col && str_starts_with($col, "enum(") && strpos($col, "'broadcast'") === false) {
    // enum-Liste extrahieren + 'broadcast' anhängen
    $enumVals = trim(substr($col, 5, -1)); // ohne enum(...)
    $new = $enumVals . ",'broadcast'";
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM($new) NOT NULL");
  }

  // Fan-Out: Eintrag pro User (actor = Admin), thread_id/post_id = NULL
  $ins = $pdo->prepare("
    INSERT INTO notifications (user_id, actor_id, type, thread_id, post_id, created_at)
    SELECT id, :actor, 'broadcast', NULL, NULL, NOW()
    FROM users WHERE id <> :actor
  ");
  $ins->execute([':actor'=>$me['id']]);
  $fanCount = $ins->rowCount();
}

echo json_encode(['ok'=>true,'broadcast_id'=>$bid,'fan_out_inserted'=>$fanCount]);

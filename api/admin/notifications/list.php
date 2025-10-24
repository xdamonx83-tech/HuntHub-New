<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

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

$st = $pdo->query("SELECT id,title,level,LEFT(body,200) AS body_preview,link,is_active,created_at,created_by FROM site_broadcasts ORDER BY id DESC LIMIT 200");
echo json_encode(['ok'=>true,'items'=>$st->fetchAll()]);

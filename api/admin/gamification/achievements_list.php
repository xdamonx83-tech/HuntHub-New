<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$st = $pdo->query("
  SELECT id, `key`, title, description, icon, rule_event, rule_stat, threshold, points, is_active, created_at
  FROM achievements ORDER BY id DESC
");
echo json_encode(['ok'=>true,'items'=>$st->fetchAll()]);

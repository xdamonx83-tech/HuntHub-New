<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$st = $pdo->query("SELECT id, name, slug FROM boards ORDER BY id ASC");
echo json_encode(['ok'=>true,'items'=>$st->fetchAll()]);

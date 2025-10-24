<?php
declare(strict_types=1);
require_once __DIR__.'/../../auth/db.php';
require_once __DIR__.'/../../auth/guards.php';
require_once __DIR__.'/../../lib/reels.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false]); exit; }

$row = reels_get($id);
if (!$row) { echo json_encode(['ok'=>false]); exit; }

echo json_encode(['ok'=>true, 'item'=>$row], JSON_UNESCAPED_UNICODE);

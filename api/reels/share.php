<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';   // <-- NEU
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/reels.php';

$me = require_auth();
verify_csrf(db(), (string)($_POST['csrf'] ?? ''));  // <-- NEU


$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$pdo = reels_db();
$pdo->prepare('UPDATE reels SET shares_count=shares_count+1 WHERE id=?')->execute([$id]);

echo json_encode(['ok'=>true]);
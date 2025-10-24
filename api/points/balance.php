<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../lib/points.php';

$me  = require_auth();
$pdo = db();

echo json_encode(['ok'=>true,'balance'=>get_user_points($pdo, (int)$me['id'])]);

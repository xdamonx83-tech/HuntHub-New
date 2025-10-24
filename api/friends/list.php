<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/friends.php';

$pdo  = db();
$user = require_auth();

$uid    = (int)($_GET['user_id'] ?? $user['id']);
$limit  = max(1, min(500, (int)($_GET['limit'] ?? 200)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$friends = list_friends($pdo, $uid, $limit, $offset);
echo json_encode(['ok'=>true, 'user_id'=>$uid, 'friends'=>$friends], JSON_UNESCAPED_SLASHES);

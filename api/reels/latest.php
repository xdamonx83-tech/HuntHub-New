<?php
declare(strict_types=1);
require_once __DIR__.'/../../auth/db.php';
require_once __DIR__.'/../../auth/guards.php';
require_once __DIR__.'/../../lib/reels.php';

header('Content-Type: application/json; charset=utf-8');

$me = optional_auth(); // wenn du so eine Helper-Funktion nicht hast: $me = is_logged_in()? current_user() : null;
$viewerId = $me['id'] ?? null;

$limit = (int)($_GET['limit'] ?? 6);
$limit = max(1, min($limit, 12)); // Safety

$items = reels_feed($viewerId, null, $limit); // liefert neueste zuerst
echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE);

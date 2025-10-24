<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/reels.php';
$me = optional_auth();
$after = (int)($_GET['after_id'] ?? 0);
$out = reels_feed((int)($me['id'] ?? 0), $after, 8);
// Vorsicht: Domains/BASE bei dir ggf. über lib/layout.php -> base URL

echo json_encode(['ok'=>true,'items'=>$out]);
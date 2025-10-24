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

$res = reels_like_toggle((int)$me['id'],$id);

// Optional Notification
$reel = reels_get($id);
if ($reel && ($reel['user_id'] ?? 0) && (int)$reel['user_id'] !== (int)$me['id']) {
  $lib = __DIR__ . '/../notifications/lib_notify.php';
  if (is_file($lib)) {
    require_once $lib;
    if (function_exists('notify_user')) {
      @notify_user((int)$reel['user_id'], 'reel_like', [
        'from_user_id' => (int)$me['id'],
        'reel_id' => $id,
      ]);
    }
  }
}

echo json_encode(['ok'=>true] + $res);
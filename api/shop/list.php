<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
$cfg = require __DIR__ . '/../../auth/config.php';

$pdo = db();
$me  = require_auth();

try {
  $st = $pdo->prepare("
    SELECT si.id, si.type, si.title, si.image, si.description, si.price, si.meta,
           (ui.user_id IS NOT NULL)          AS owned,
           COALESCE(ui.is_active, 0)         AS is_active
      FROM shop_items si
      LEFT JOIN user_items ui
        ON ui.item_id = si.id AND ui.user_id = ?
     ORDER BY si.id ASC
  ");
  $st->execute([(int)$me['id']]);
  $items = [];
  $base  = rtrim($cfg['app_base_public'] ?? '', '/');

  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $img = (string)($r['image'] ?? '');
    $abs = preg_match('~^https?://|^/~i', $img) ? $img : ($base . '/' . ltrim($img, '/'));
    $meta = $r['meta'] ? json_decode((string)$r['meta'], true) : null;
    $r['image_url'] = $abs;
    $r['meta'] = $meta ?: null;
    $r['owned'] = (bool)$r['owned'];
    $r['is_active'] = (int)$r['is_active'];
    $items[] = $r;
  }

  echo json_encode(['ok'=>true, 'items'=>$items]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}

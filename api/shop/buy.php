<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../lib/points.php';

$me  = require_auth();
$pdo = db();

$itemId = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
if ($itemId <= 0) { http_response_code(400); exit(json_encode(['ok'=>false,'error'=>'invalid_item'])); }

try {
  $pdo->beginTransaction();

  // Item laden & sperren (optional)
  $st = $pdo->prepare("SELECT id, type, title, price, meta FROM shop_items WHERE id=?");
  $st->execute([$itemId]);
  $item = $st->fetch(PDO::FETCH_ASSOC);
  if (!$item) { $pdo->rollBack(); http_response_code(404); exit(json_encode(['ok'=>false,'error'=>'item_not_found'])); }

  $price = max(0, (int)($item['price'] ?? 0));

  // user_points-Zeile für den User sperren/anlegen
  $sel = $pdo->prepare("SELECT points FROM user_points WHERE user_id=? FOR UPDATE");
  $sel->execute([(int)$me['id']]);
  $row  = $sel->fetch(PDO::FETCH_ASSOC);
  $have = $row ? (int)$row['points'] : 0;
  if (!$row) {
    $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, 0)")
        ->execute([(int)$me['id']]);
  }

  if ($have < $price) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(['ok'=>false,'error'=>'not_enough_points','have'=>$have,'need'=>$price]));
  }

  // Duplikatkauf verhindern
  $chk = $pdo->prepare("SELECT id FROM user_items WHERE user_id=? AND item_id=? LIMIT 1");
  $chk->execute([(int)$me['id'], $itemId]);
  if (!$chk->fetchColumn()) {
    $pdo->prepare("INSERT INTO user_items (user_id, item_id, is_active) VALUES (?, ?, 0)")
        ->execute([(int)$me['id'], $itemId]);
  }

  // Punkte abbuchen + Transaktion loggen (nutzt neue points.php)
// ...
// Punkte abbuchen + Transaktion loggen
$res = award_points(
  $pdo,
  (int)$me['id'],
  'shop_purchase',
  -$price,
  [
    'item_id'    => $itemId,
    'type'       => $item['type'] ?? null,
    'item_title' => $item['title'] ?? null,   // << neu
    'price'      => $price
  ]
);
// ...

  if (!($res['ok'] ?? false)) {
    throw new RuntimeException($res['msg'] ?? 'award_points failed');
  }

  // Sofort-Effekte je nach Typ
  // Sofort-Effekte je nach Typ
  $type = (string)($item['type'] ?? '');
  $meta = $item['meta'] ? (json_decode((string)$item['meta'], true) ?: []) : [];

  if ($type === 'vip') {
    $pdo->prepare("UPDATE users SET is_vip=1 WHERE id=?")->execute([(int)$me['id']]);

  } elseif ($type === 'status') {
    $status = (string)($meta['status'] ?? '');
    if ($status !== '') {
      $pdo->prepare("UPDATE users SET custom_status=? WHERE id=?")->execute([$status, (int)$me['id']]);
    }

  } elseif ($type === 'color') {
    $color = (string)($meta['color'] ?? ($item['color'] ?? '')); // neu: Spalte color berücksichtigen
    if ($color !== '') {
      $pdo->prepare("UPDATE users SET name_color=? WHERE id=?")->execute([$color, (int)$me['id']]);
    }

  } elseif ($type === 'avatar') {
    if (!empty($item['image'])) {
      $pdo->prepare("UPDATE users SET avatar_path=? WHERE id=?")->execute([$item['image'], (int)$me['id']]);
    }
  }


  $pdo->commit();
  echo json_encode(['ok'=>true, 'item'=>$item, 'have'=>$res['balance'] ?? max(0, $have - $price)]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('shop.buy error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}

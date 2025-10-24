<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';

$me  = require_auth();
$pdo = db();

$itemId   = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
$action   = strtolower((string)($_POST['action'] ?? $_GET['action'] ?? 'activate'));
$offFlag  = (string)($_POST['off'] ?? $_GET['off'] ?? '') === '1';
$typeIn   = strtolower((string)($_POST['type'] ?? $_GET['type'] ?? ''));

// „off“-Modus (deaktivieren) erkennen
$deactivate = $offFlag || in_array($action, ['off','deactivate','disable','0'], true);

// Hilfsfunktion: Typ aus Item_id laden
$loadItem = function(int $id) use ($pdo, $me): ?array {
  $st = $pdo->prepare("
    SELECT si.*, ui.id AS user_item_id
      FROM shop_items si
      JOIN user_items ui ON ui.item_id = si.id AND ui.user_id = ?
     WHERE si.id = ?
     LIMIT 1
  ");
  $st->execute([(int)$me['id'], $id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ? $row : null;
};

// === Deaktivieren =====================================================
if ($deactivate) {
  // Typ bestimmen (aus item_id oder aus ?type=)
  $type = '';
  if ($itemId > 0) {
    $row = $loadItem($itemId);
    if (!$row) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'not_owned']); exit; }
    $type = (string)($row['type'] ?? '');
  } else {
    $type = $typeIn;
  }
  if ($type === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'type_required_for_deactivate']);
    exit;
  }

  try {
    $pdo->beginTransaction();

    // Alle Items dieses Typs für den User deaktivieren
    $pdo->prepare("
      UPDATE user_items ui
      JOIN shop_items si ON si.id = ui.item_id
         SET ui.is_active = 0
       WHERE ui.user_id = ? AND si.type = ?
    ")->execute([(int)$me['id'], $type]);

    // Spiegel-Felder in users je nach Typ leeren
    if ($type === 'color') {
      $pdo->prepare("UPDATE users SET name_color = NULL WHERE id = ?")->execute([(int)$me['id']]);
    } elseif ($type === 'status') {
      $pdo->prepare("UPDATE users SET custom_status = NULL WHERE id = ?")->execute([(int)$me['id']]);
    } elseif ($type === 'vip') {
      // Falls VIP „abschaltbar“ sein soll:
      $pdo->prepare("UPDATE users SET is_vip = 0 WHERE id = ?")->execute([(int)$me['id']]);
    }
    // Für frame/avatar lassen wir bewusst alles wie es ist – kannst du analog ergänzen.

    $pdo->commit();
    echo json_encode(['ok'=>true, 'action'=>'off', 'type'=>$type]);
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
    exit;
  }
}

// === Aktivieren (Standardweg) ========================================
if ($itemId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid_item']);
  exit;
}

try {
  // Besitz prüfen + Item laden
  $row = $loadItem($itemId);
  if (!$row) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'not_owned']);
    exit;
  }

  $type = (string)($row['type'] ?? '');
  $meta = $row['meta'] ? (json_decode((string)$row['meta'], true) ?: []) : [];

  $pdo->beginTransaction();

  // pro Typ genau ein aktives Item
  $pdo->prepare("
    UPDATE user_items ui
      JOIN shop_items si ON si.id = ui.item_id
       SET ui.is_active = CASE WHEN ui.item_id = ? THEN 1 ELSE 0 END
     WHERE ui.user_id = ? AND si.type = ?
  ")->execute([$itemId, (int)$me['id'], $type]);

  // Spiegel-Felder je nach Typ setzen
  if ($type === 'avatar') {
    $img = (string)($row['image'] ?? '');
    if ($img !== '') {
      $pdo->prepare("UPDATE users SET avatar_path=? WHERE id=?")->execute([$img, (int)$me['id']]);
    }
  } elseif ($type === 'cover') {
    $img = (string)($row['image'] ?? '');
    if ($img !== '') {
      $pdo->prepare("UPDATE users SET cover_path=? WHERE id=?")->execute([$img, (int)$me['id']]);
    }
  } elseif ($type === 'vip') {
    $pdo->prepare("UPDATE users SET is_vip=1 WHERE id=?")->execute([(int)$me['id']]);
  } elseif ($type === 'status') {
    $status = (string)($meta['status'] ?? '');
    if ($status !== '') {
      $pdo->prepare("UPDATE users SET custom_status=? WHERE id=?")->execute([$status, (int)$me['id']]);
    }
  } elseif ($type === 'color') {
    // Farbe aus meta[color] oder Spalte color
    $color = (string)($meta['color'] ?? ($row['color'] ?? ''));
    if ($color !== '') {
      $pdo->prepare("UPDATE users SET name_color=? WHERE id=?")->execute([$color, (int)$me['id']]);
    }
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'item'=>['id'=>$itemId,'type'=>$type],'action'=>'on']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}

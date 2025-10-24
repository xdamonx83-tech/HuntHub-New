<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../auth/db.php';
// KORREKTER PFAD: Stellt alle Gamification-Funktionen bereit
require_once __DIR__ . '/../../lib/gamification.php';

/* ---- helpers (inline, keine neuen Dateien) ---- */
function notif_cols(PDO $pdo): array {
  static $cols = null;
  if ($cols !== null) return $cols;
  $cols = [];
  $st = $pdo->query("SHOW COLUMNS FROM notifications");
  foreach ($st as $row) {
    $cols[] = $row['Field'] ?? array_values($row)[0];
  }
  return $cols;
}

function notif_delete(PDO $pdo, int $recipientId, int $actorId, string $type, string $objectType, int $objectId): void {
  $cols = notif_cols($pdo);
  $where = ['user_id=?','actor_id=?','type=?'];
  $args  = [$recipientId, $actorId, $type];
  if (in_array('object_id', $cols, true))   { $where[] = 'object_id=?';   $args[] = $objectId; }
  if (in_array('object_type', $cols, true)) { $where[] = 'object_type=?'; $args[] = $objectType; }
  $sql = "DELETE FROM notifications WHERE ".implode(' AND ',$where);
  $pdo->prepare($sql)->execute($args);
}

function notif_insert(PDO $pdo, int $recipientId, int $actorId, string $type, string $objectType, int $objectId, array $meta = []): void {
  if ($recipientId === $actorId) return; // kein Self-Notify
  $cols = notif_cols($pdo);
  notif_delete($pdo, $recipientId, $actorId, $type, $objectType, $objectId);
  $fields = ['user_id','actor_id','type'];
  $ph     = ['?','?','?'];
  $args   = [$recipientId,$actorId,$type];
  if (in_array('object_type',$cols,true)) { $fields[]='object_type'; $ph[]='?'; $args[]=$objectType; }
  if (in_array('object_id'  ,$cols,true)) { $fields[]='object_id';   $ph[]='?'; $args[]=$objectId;   }
  $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if (in_array('meta_json',$cols,true))   { $fields[]='meta_json';   $ph[]='?'; $args[]=$metaJson; }
  elseif (in_array('meta',$cols,true))    { $fields[]='meta';        $ph[]='?'; $args[]=$metaJson; }
  if (in_array('created_at',$cols,true))  { $fields[]='created_at';  $ph[]='NOW()'; }
  $sql = "INSERT INTO notifications (".implode(',',$fields).") VALUES (".implode(',',$ph).")";
  $pdo->prepare($sql)->execute($args);
}
/* ---- /helpers ---- */

// Lade die Konfiguration VOR dem try-Block
$cfg  = require __DIR__ . '/../../auth/config.php';

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
  }

  $pdo = db();
  $me  = require_auth();

  $csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
  if (!check_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '', $csrf)) {
    http_response_code(419);
    echo json_encode(['ok'=>false,'error'=>'csrf_failed']);
    exit;
  }

  $type = $_POST['type'] ?? '';
  $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if (!in_array($type, ['thread','post'], true) || $id <= 0) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_params']);
    exit;
  }

  // Mapping
  if ($type === 'thread') {
    $existsSql = "SELECT 1 FROM threads WHERE id=? AND deleted_at IS NULL";
    $incSql    = "UPDATE threads SET likes_count = likes_count + 1 WHERE id=?";
    $decSql    = "UPDATE threads SET likes_count = GREATEST(likes_count - 1, 0) WHERE id=?";
    $countSql  = "SELECT likes_count AS c FROM threads WHERE id=?";
    $ownerSql  = "SELECT user_id AS owner_id FROM threads WHERE id=?";
    $notifyType= 'like_thread';
    $objectType= 'thread';
    $likeTable = 'thread_likes';
    $likeFk    = 'thread_id';
  } else {
    $existsSql = "SELECT 1 FROM posts WHERE id=? AND deleted_at IS NULL";
    $incSql    = "UPDATE posts SET likes_count = likes_count + 1 WHERE id=?";
    $decSql    = "UPDATE posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id=?";
    $countSql  = "SELECT likes_count AS c FROM posts WHERE id=?";
    $ownerSql  = "SELECT author_id AS owner_id, thread_id FROM posts WHERE id=?";
    $notifyType= 'like_post';
    $objectType= 'post';
    $likeTable = 'post_likes';
    $likeFk    = 'post_id';
  }

  // Ziel existiert?
  $st = $pdo->prepare($existsSql);
  $st->execute([$id]);
  if (!$st->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'not_found']);
    exit;
  }

  $pdo->beginTransaction();

  // Owner
  $st = $pdo->prepare($ownerSql);
  $st->execute([$id]);
  $ownerRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $ownerId  = (int)($ownerRow['owner_id'] ?? 0);
  $threadIdForPost = isset($ownerRow['thread_id']) ? (int)$ownerRow['thread_id'] : null;

  // Toggle Like
  $ins = $pdo->prepare("INSERT IGNORE INTO {$likeTable} ({$likeFk}, user_id) VALUES (?, ?)");
  $ins->execute([$id, (int)$me['id']]);
  $liked = ($ins->rowCount() === 1);

  if ($liked) {
    $pdo->prepare($incSql)->execute([$id]);
    if ($ownerId && $ownerId !== (int)$me['id']) {
      $meta = ($type === 'post')
        ? ['url' => "/forum/thread.php?t=".$threadIdForPost."#post-".$id, 'thread_id'=>$threadIdForPost]
        : ['url' => "/forum/thread.php?t=".$id];
      notif_insert($pdo, $ownerId, (int)$me['id'], $notifyType, $objectType, $id, $meta);
    }
  } else {
    $del = $pdo->prepare("DELETE FROM {$likeTable} WHERE {$likeFk}=? AND user_id=?");
    $del->execute([$id, (int)$me['id']]);
    $pdo->prepare($decSql)->execute([$id]);
    if ($ownerId) {
      notif_delete($pdo, $ownerId, (int)$me['id'], $notifyType, $objectType, $id);
    }
  }

  // neuen Zähler
  $cnt = $pdo->prepare($countSql);
  $cnt->execute([$id]);
  $likes = (int)($cnt->fetchColumn() ?: 0);

  $pdo->commit();

  // GAMIFY: nach erfolgreichem Commit Zähler pflegen + Achievements/Quests prüfen
  $achGiver = [];
  try {
    if ($liked) {
      // Achievements für den Liker
      gamify_bump($pdo, (int)$me['id'], 'likes_given', 1);
      $achGiver = gamify_check($pdo, (int)$me['id'], 'like_given');
      
      // Quest-Fortschritt für den Liker aktualisieren
      update_quest_progress($pdo, (int)$me['id'], 'like_given');

      // Achievements für den Owner
      if ($ownerId && $ownerId !== (int)$me['id']) {
        gamify_bump($pdo, (int)$ownerId, 'likes_received', 1);
        gamify_check($pdo, (int)$ownerId, 'like_received');
      }
    } else {
      // Unlike: Stats zurücknehmen
      gamify_bump($pdo, (int)$me['id'], 'likes_given', -1);
      if ($ownerId && $ownerId !== (int)$me['id']) {
        gamify_bump($pdo, (int)$ownerId, 'likes_received', -1);
      }
    }
  } catch (Throwable $e) {
    error_log("Gamify Error in toggle_like: " . $e->getMessage());
    $achGiver = []; // Gamification darf die Hauptaktion nicht stören
  }

  echo json_encode([
    'ok'                    => true,
    'liked'                 => $liked,
    'likes'                 => $likes,
    'achievements_unlocked' => $achGiver,
  ]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();

  $errId = bin2hex(random_bytes(8));
  error_log(sprintf('[%s] %s in %s:%d | %s', $errId, $e->getMessage(), $e->getFile(), $e->getLine(), $_SERVER['REQUEST_URI'] ?? ''));

  http_response_code(500);
  $debug = (getenv('APP_DEBUG') === '1') || (!empty($cfg['debug']));
  $payload = ['ok'=>false,'error'=>'internal_error','error_id'=>$errId];
  if ($debug) { $payload['message']=$e->getMessage(); $payload['file']=$e->getFile(); $payload['line']=$e->getLine(); }
  echo json_encode($payload);
}

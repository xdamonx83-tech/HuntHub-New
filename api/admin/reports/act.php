<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  $API_DIR = __DIR__;
  $ROOT    = dirname($API_DIR, 2);               // .../api
  require_once $ROOT . '/../auth/db.php';
  require_once $ROOT . '/../auth/guards.php';

  $pdo = db();
  require_admin();

  // Payload akzeptieren: JSON oder FormData
  $raw = file_get_contents('php://input') ?: '';
  $in  = json_decode($raw, true);
  if (!is_array($in)) $in = $_POST;

  $type   = isset($in['type']) ? (string)$in['type'] : '';
  $id     = isset($in['entity_id']) ? (int)$in['entity_id'] : 0;
  $action = isset($in['action']) ? (string)$in['action'] : '';

  if (!$type || !$id || !in_array($type, ['post','comment'], true)) {
    echo json_encode(['ok'=>false, 'error'=>'bad_request']); exit;
  }

  if ($action === 'delete') {
    if ($type === 'post') {
      $st = $pdo->prepare('UPDATE wall_posts SET deleted_at = NOW() WHERE id = ?');
    } else {
      $st = $pdo->prepare('UPDATE wall_comments SET deleted_at = NOW() WHERE id = ?');
    }
    $st->execute([$id]);
  } elseif ($action === 'release') {
    if ($type === 'post') {
      $st = $pdo->prepare('UPDATE wall_posts SET under_review_at = NULL WHERE id = ?');
    } else {
      $st = $pdo->prepare('UPDATE wall_comments SET under_review_at = NULL WHERE id = ?');
    }
    $st->execute([$id]);
  } else {
    echo json_encode(['ok'=>false, 'error'=>'bad_request']); exit;
  }

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>'server_error', 'hint'=>$e->getMessage()]);
  exit;
}

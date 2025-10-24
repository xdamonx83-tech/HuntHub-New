<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/db.php';

try {
  $pdo  = db();
  $type = ($_GET['type'] ?? '');
  $id   = (int)($_GET['id'] ?? 0);

  if (!in_array($type, ['thread','post'], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad_request']);
    exit;
  }

  if ($type === 'thread') {
    $sql = "SELECT u.id, u.display_name, u.avatar_path
            FROM thread_likes tl
            JOIN users u ON u.id = tl.user_id
            WHERE tl.thread_id = ?
            ORDER BY tl.created_at DESC";
  } else {
    $sql = "SELECT u.id, u.display_name, u.avatar_path
            FROM post_likes pl
            JOIN users u ON u.id = pl.user_id
            WHERE pl.post_id = ?
            ORDER BY pl.created_at DESC";
  }

  $st = $pdo->prepare($sql);
  $st->execute([$id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'users'=>$rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}

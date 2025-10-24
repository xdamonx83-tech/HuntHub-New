<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../lib/wall_edits.php';

try {
  // Lesen reicht (kein CSRF nötig); optional_auth falls Wall öffentlich
  $me  = optional_auth();
  $pdo = db();

  $type = strtolower((string)($_GET['type'] ?? $_POST['type'] ?? ''));
  $id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

  if (!in_array($type, ['post','comment'], true)) throw new RuntimeException('invalid_type');
  if ($id <= 0) throw new RuntimeException('invalid_id');

  $pair = wall_latest_edit_pair($pdo, $type, $id);
  if (!$pair) {
    echo json_encode(['ok'=>false,'error'=>'no_edits']); exit;
  }

  echo json_encode([
    'ok'   => true,
    'type' => $type,
    'id'   => $id,
    'before'    => $pair['before'],
    'after'     => $pair['after'],
    'edited_at' => $pair['edited_at'],
    'editor'    => [
      'id'   => $pair['editor_id'],
      'name' => $pair['editor_name'],
    ],
  ]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

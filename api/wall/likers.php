<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../lib/wall_likes.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $type = $_GET['type'] ?? 'post';
  $id   = (int)($_GET['id'] ?? 0);
  if ($id <= 0) throw new InvalidArgumentException('Invalid ID');

  if (!function_exists('db')) {
    require_once __DIR__ . '/../../auth/db.php';
  }
  $db = db();

  $users = wall_like_users($db, $type, $id);
  echo json_encode(['ok' => true, 'users' => $users]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

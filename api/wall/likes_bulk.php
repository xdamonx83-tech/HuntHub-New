<?php
// /api/wall/likes_bulk.php

declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/wall_likes.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $me = optional_auth(); // falls vorhanden; sonst $me = ['id'=>0]
  $userId = (int)($me['id'] ?? 0);

  $type = $_POST['entity_type'] ?? $_GET['entity_type'] ?? 'post';
  $ids  = $_POST['ids'] ?? $_GET['ids'] ?? '';
  if (is_string($ids)) {
    $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $ids)));
  } elseif (!is_array($ids)) {
    $ids = [];
  }

  /** @var PDO $db */
  $db = $GLOBALS['db'] ?? $GLOBALS['pdo'] ?? null;
  if (!$db instanceof PDO) throw new RuntimeException('DB handle missing');

  $counts = wall_like_counts_bulk($db, $type, $ids);
  $flags  = wall_like_flags_bulk($db, $userId, $type, $ids);

  echo json_encode(['ok' => true, 'counts' => $counts, 'flags' => $flags]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
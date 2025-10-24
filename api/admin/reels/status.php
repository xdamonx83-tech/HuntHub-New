<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__, 3);
require_once $ROOT . '/auth/db.php';
require_once $ROOT . '/auth/guards.php';
require_once $ROOT . '/auth/csrf.php';

try {
  $pdo = db();
  require_admin();

  $in   = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
  $csrf = $_SERVER['HTTP_X_CSRF'] ?? ($in['csrf'] ?? '');
  if (function_exists('verify_csrf') && !verify_csrf($pdo, (string)$csrf)) {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'invalid_csrf']); exit;
  }

  $id = (int)($in['id'] ?? 0);
  $st = (string)($in['status'] ?? '');
  $allowed = ['visible','hidden','blocked','under_review'];
  if (!$id || !in_array($st,$allowed,true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }

  $cols=[]; foreach ($pdo->query("SHOW COLUMNS FROM reels", PDO::FETCH_ASSOC) as $c) $cols[$c['Field']]=true;

  if (isset($cols['status'])) {
    $q = $pdo->prepare("UPDATE reels SET status=:s WHERE id=:id");
    $q->execute([':s'=>$st, ':id'=>$id]);
  } elseif (isset($cols['is_hidden'])) {
    $q = $pdo->prepare("UPDATE reels SET is_hidden=:h WHERE id=:id");
    $q->execute([':h'=>($st==='visible'?0:1), ':id'=>$id]);
  } else {
    http_response_code(409); echo json_encode(['ok'=>false,'error'=>'no_status_column']); exit;
  }

  echo json_encode(['ok'=>true]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','hint'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__.'/../../auth/db.php';
require_once __DIR__.'/../../auth/guards.php';
require_once __DIR__.'/../../auth/csrf.php';

$pdo = db();
$me  = require_auth();

// CSRF aus Header ODER Body, und cookie-gebunden prüfen (wie bei deinen anderen Endpoints)
$cfg = require __DIR__ . '/../../auth/config.php';
$sessionCookie = $_COOKIE[$cfg['cookies']['session_name']] ?? '';
$csrf = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? '');

$ok = false;
if (function_exists('check_csrf')) {
  $ok = check_csrf($pdo, $sessionCookie, $csrf);
} elseif (function_exists('verify_csrf')) {
  // Fallback, falls check_csrf nicht existiert
  $ok = verify_csrf($pdo, $csrf);
}

if (!$ok) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

// Einzelne ID -> eine Notification, sonst alle
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
  $st = $pdo->prepare("UPDATE notifications SET read_at = NOW() WHERE id=? AND user_id=? AND read_at IS NULL");
  $st->execute([$id, (int)$me['id']]);
  $affected = $st->rowCount();
} else {
  $st = $pdo->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id=? AND read_at IS NULL");
  $st->execute([(int)$me['id']]);
  $affected = $st->rowCount();
}

echo json_encode(['ok'=>true,'affected'=>$affected]);

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

$pdo = db();
$me  = require_admin(); // blockt Nicht-Admins

$cfg  = require __DIR__ . '/../../auth/config.php';

// CSRF (Header X-CSRF oder JSON-Body csrf)
$input = file_get_contents('php://input'); $json = $input ? (json_decode($input, true) ?: []) : [];
$csrf = $json['csrf'] ?? ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)));
if (!check_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '', $csrf)) {
  http_response_code(419); echo json_encode(['ok'=>false,'error'=>'csrf_failed']); exit;
}

function in_json(): array {
  static $j=null; if ($j!==null) return $j;
  $raw = file_get_contents('php://input'); $j = $raw ? (json_decode($raw, true) ?: []) : [];
  return $j;
}

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/settings.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

$pdo  = db();
$user = require_auth();

if (!is_csrf_valid($pdo, (string)($_POST['csrf'] ?? ''))) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}
$policy = (string)($_POST['message_policy'] ?? '');
if (!in_array($policy, ['friends_only','everyone'], true)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_policy']); exit;
}

$ok = set_message_policy($pdo, (int)$user['id'], $policy);
echo json_encode(['ok'=>$ok, 'message_policy'=>$policy]);

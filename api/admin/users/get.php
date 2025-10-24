<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../auth/guards.php';
require_once __DIR__ . '/../../../auth/db.php';
@include_once __DIR__ . '/../../../auth/csrf.php';
header('Content-Type: application/json; charset=utf-8');

require_admin();

/* Input cache */
if (!isset($GLOBALS['__HH_RAW__'])) {
  $GLOBALS['__HH_RAW__']  = file_get_contents('php://input') ?: '';
  $GLOBALS['__HH_JSON__'] = json_decode($GLOBALS['__HH_RAW__'], true) ?: [];
}
$HH_JSON = $GLOBALS['__HH_JSON__'];

/* CSRF shim */
function hh_verify_csrf(): void {
  $HH_JSON = $GLOBALS['__HH_JSON__'] ?? [];
  $token = $HH_JSON['csrf'] ?? ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
  $cfg = @require __DIR__ . '/../../../auth/config.php';
  $sessionCookieName = $cfg['cookies']['session_name'] ?? 'sess_id';
  $sessionCookie = $_COOKIE[$sessionCookieName] ?? ($_COOKIE['sess_id'] ?? '');
  $invoke = function ($fn) use ($token, $sessionCookie) {
    try {
      $pdo = function_exists('db') ? db() : null;
      $rf  = new ReflectionFunction($fn);
      $argc= $rf->getNumberOfParameters();
      if ($argc >= 3) { $rf->invoke($pdo, $sessionCookie, $token); return; }
      if ($argc === 2){ $rf->invoke($pdo, $token);                 return; }
      if ($argc === 1){ $rf->invoke($token);                       return; }
                       $rf->invoke();                               return;
    } catch (Throwable $e) {}
  };
  foreach (['verify_csrf','csrf_verify','check_csrf','assert_csrf','require_csrf'] as $cand) {
    if (function_exists($cand)) { $invoke($cand); return; }
  }
}
hh_verify_csrf();

$pdo = db();

/* Spalten-Erkennung */
$cols = [];
foreach ($pdo->query("SHOW COLUMNS FROM users", PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = true;
$hasTw  = isset($cols['is_twitch_streamer']);
$hasUrl = isset($cols['twitch_url']);

$twCol  = $hasTw  ? 'is_twitch_streamer' : '0 AS is_twitch_streamer';
$urlCol = $hasUrl ? 'twitch_url'        : 'NULL AS twitch_url';

$id = (int)($HH_JSON['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }

$st = $pdo->prepare("SELECT id, display_name, email, role, {$twCol}, {$urlCol} FROM users WHERE id = :id");
$st->execute([':id'=>$id]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

echo json_encode(['ok'=>true, 'user'=>$user]);

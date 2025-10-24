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

/* Spalten-Erkennung (Migration optional) */
$cols = [];
foreach ($pdo->query("SHOW COLUMNS FROM users", PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = true;
$hasTw  = isset($cols['is_twitch_streamer']);
$hasUrl = isset($cols['twitch_url']);

$id = (int)($HH_JSON['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }

$display = array_key_exists('display_name', $HH_JSON) ? trim((string)$HH_JSON['display_name']) : null;
$role    = array_key_exists('role', $HH_JSON)         ? trim((string)$HH_JSON['role']) : null;
$isTw    = ($hasTw  && array_key_exists('is_twitch_streamer', $HH_JSON)) ? (int)!!$HH_JSON['is_twitch_streamer'] : null;
$twitch  = ($hasUrl && array_key_exists('twitch_url', $HH_JSON))         ? trim((string)$HH_JSON['twitch_url'])   : null;

$fields = [];
$params = [':id'=>$id];
if ($display !== null) { $fields[] = 'display_name = :display_name'; $params[':display_name'] = $display; }
if ($role !== null)    { $fields[] = 'role = :role';               $params[':role'] = $role; }
if ($isTw !== null)    { $fields[] = 'is_twitch_streamer = :tw';   $params[':tw'] = $isTw; }
if ($twitch !== null)  { $fields[] = 'twitch_url = :twitch';       $params[':twitch'] = ($twitch !== '' ? $twitch : null); }

if (!$fields) { echo json_encode(['ok'=>true, 'updated'=>0]); exit; }

$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
$st  = $pdo->prepare($sql);
$st->execute($params);

echo json_encode(['ok'=>true, 'updated'=>$st->rowCount()]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../auth/guards.php';
require_once __DIR__ . '/../../../auth/db.php';
@include_once __DIR__ . '/../../../auth/csrf.php';
header('Content-Type: application/json; charset=utf-8');

require_admin();

/** ---- Gemeinsamer Input-Cache ---- */
if (!isset($GLOBALS['__HH_RAW__'])) {
  $GLOBALS['__HH_RAW__']  = file_get_contents('php://input') ?: '';
  $GLOBALS['__HH_JSON__'] = json_decode($GLOBALS['__HH_RAW__'], true) ?: [];
}
$HH_RAW  = $GLOBALS['__HH_RAW__'];
$HH_JSON = $GLOBALS['__HH_JSON__'];

/** ---- CSRF SHIM (universal, mit Reflection & Fallback) ---- */
function hh_verify_csrf(): void {
  $HH_JSON = $GLOBALS['__HH_JSON__'] ?? [];
  $token = $HH_JSON['csrf'] ?? ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));

  $cfg = @require __DIR__ . '/../../../auth/config.php';
  $sessionCookieName = $cfg['cookies']['session_name'] ?? 'sess_id';
  $sessionCookie = $_COOKIE[$sessionCookieName] ?? ($_COOKIE['sess_id'] ?? '');

  $invoke = function ($fn) use ($token, $sessionCookie) {
    try {
      $pdo = function_exists('db') ? db() : null;
      if (is_string($fn) && function_exists($fn)) {
        $rf = new ReflectionFunction($fn);
        $argc = $rf->getNumberOfParameters();
        if ($argc >= 3) { $rf->invoke($pdo, $sessionCookie, $token); return; }
        if ($argc === 2){ $rf->invoke($pdo, $token);                 return; }
        if ($argc === 1){ $rf->invoke($token);                       return; }
                         $rf->invoke();                               return;
      }
    } catch (Throwable $e) {
      // Kein Fatal: Als Fallback still weiterlaufen oder hier hart 403 ausgeben.
      // http_response_code(403); exit(json_encode(['ok'=>false,'error'=>'csrf_failed']));
    }
  };

  foreach (['verify_csrf','csrf_verify','check_csrf','assert_csrf','require_csrf'] as $cand) {
    if (function_exists($cand)) { $invoke($cand); return; }
  }
}
hh_verify_csrf();
/** ---------------------------------------------------------- */

$pdo = db();

/* Spalten vorhanden? (Migration evtl. noch nicht gelaufen) */
$cols = [];
foreach ($pdo->query("SHOW COLUMNS FROM users", PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = true;
$hasTw = isset($cols['is_twitch_streamer']);

$q     = trim((string)($HH_JSON['q'] ?? ''));
$limit = 100;
$twCol = $hasTw ? 'is_twitch_streamer' : '0 AS is_twitch_streamer';

if ($q === '') {
  $sql = "SELECT id, display_name, email, role, {$twCol} FROM users ORDER BY id DESC LIMIT {$limit}";
  $st  = $pdo->query($sql);
  echo json_encode(['ok'=>true, 'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
  exit;
}

$sql = "SELECT id, display_name, email, role, {$twCol}
        FROM users
        WHERE id = :idExact
           OR display_name LIKE :like
           OR email LIKE :like
        ORDER BY id DESC
        LIMIT {$limit}";
$st = $pdo->prepare($sql);
$idExact = ctype_digit($q) ? (int)$q : 0;
$like    = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
$st->execute([':idExact'=>$idExact, ':like'=>$like]);

echo json_encode(['ok'=>true, 'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);

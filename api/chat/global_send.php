<?php
declare(strict_types=1);

/**
 * Global Chat: Send (mit Debug-Ausgaben bei NOT_AUTH)
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

$__sent = false;
function __out(array $p, int $code = 200): void {
  global $__sent;
  if ($__sent) return;
  $__sent = true;
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($p);
}
set_error_handler(function($errno,$errstr,$errfile,$errline){
  __out(['ok'=>false,'error'=>'PHP_ERROR','detail'=>"$errstr @ $errfile:$errline"],500);
  return true;
});
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && !$GLOBALS['__sent']) {
    __out(['ok'=>false,'error'=>'PHP_FATAL','detail'=>$e['message'].' @ '.$e['file'].':'.$e['line']],500);
  }
});

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../auth/db.php';
$CFG = require __DIR__ . '/../../auth/config.php';

optional_auth(); // füllt $USER, wenn möglich

try {
  // --- CSRF (nimmt 'csrf' oder 'csrf_token') ---
  $payload = $_POST ?? [];
  if (!isset($payload['csrf']) && isset($payload['csrf_token'])) {
    $payload['csrf'] = $payload['csrf_token'];
  }
  if (!check_csrf_request($payload)) {
    __out(['ok'=>false,'error'=>'CSRF_INVALID'], 403);
  }

  // --- Message validieren ---
  $message = trim((string)($payload['message'] ?? ''));
  if ($message === '' || mb_strlen($message) > 2000) {
    __out(['ok'=>false,'error'=>'INVALID_MESSAGE'], 400);
  }

  // --- Auth prüfen (mit Debug-Infos) ---
  global $USER;
  if (!isset($USER['id'])) {
    // Diagnose: Cookie / Tabelle / Key
    $cookieName = $CFG['session_cookie'] ?? ($CFG['cookies']['session_name'] ?? 'sess_id');
    $sid = (string)($_COOKIE[$cookieName] ?? '');
    require_once __DIR__ . '/../../auth/csrf.php';
    require_once __DIR__ . '/../../auth/db.php';
    $pdo = db();
    $table = sessions_table_from_config();
    $key   = session_key_col($pdo, $table);

    __out([
      'ok' => false,
      'error' => 'NOT_AUTH',
      'debug' => [
        'cookie_name' => $cookieName,
        'cookie_present' => $sid !== '',
        'cookie_prefix' => $sid ? substr($sid,0,8) : null,
        'session_table' => $table,
        'session_key'   => $key,
        'request_uri'   => $_SERVER['REQUEST_URI'] ?? null,
        'host'          => $_SERVER['HTTP_HOST'] ?? null,
        'https'         => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
      ]
    ], 401);
  }

  // Username-Fallback: display_name -> username -> 'User#ID'
  $uname =
    (isset($USER['display_name']) && $USER['display_name'] !== '' ? (string)$USER['display_name'] : null) ??
    (isset($USER['username'])     && $USER['username']     !== '' ? (string)$USER['username']     : null) ??
    ('User#'.$USER['id']);

  // --- Insert ---
  $db = db();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $db->prepare(
    "INSERT INTO chat_global (user_id, username, message)
     VALUES (:uid,:un,:msg)"
  );
  $stmt->execute([
    ':uid' => (int)$USER['id'],
    ':un'  => $uname,
    ':msg' => $message,
  ]);

  $id = (int)$db->lastInsertId();
  __out([
    'ok'=>true,
    'message'=>[
      'id'=>$id,
      'user_id'=>(int)$USER['id'],
      'username'=>$uname,
      'message'=>$message,
      'created_at'=>(new DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]
  ]);
} catch (Throwable $e) {
  __out(['ok'=>false,'error'=>'SERVER_ERROR','detail'=>$e->getMessage()],500);
}

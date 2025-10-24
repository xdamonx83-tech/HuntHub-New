<?php
declare(strict_types=1);

/**
 * Global Chat: Poll
 * - Gibt IMMER JSON zurück, auch bei fatalen Fehlern (Shutdown-Handler).
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// JSON-Failsafe für JEGLICHEN Fehler (inkl. Fatal)
$__sent = false;
function __out(array $p, int $code = 200): void {
  global $__sent;
  if ($__sent) return;
  $__sent = true;
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($p);
}
set_error_handler(function($errno, $errstr, $errfile, $errline) {
  __out(['ok'=>false,'error'=>'PHP_ERROR','detail'=>"$errstr @ $errfile:$errline"], 500);
  return true;
});
register_shutdown_function(function() {
  $e = error_get_last();
  if ($e && !$GLOBALS['__sent']) {
    __out(['ok'=>false,'error'=>'PHP_FATAL','detail'=>$e['message'].' @ '.$e['file'].':'.$e['line']], 500);
  }
});

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../auth/db.php';

optional_auth();
require_auth();

$afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
$limit   = 100;

try {
  $db = db();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if ($afterId > 0) {
    $stmt = $db->prepare(
      "SELECT id, user_id, username, message, created_at
       FROM chat_global
       WHERE id > :afterId
       ORDER BY id ASC
       LIMIT :lim"
    );
    $stmt->bindValue(':afterId', $afterId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
  } else {
    $stmt = $db->query(
      "SELECT id, user_id, username, message, created_at
       FROM (SELECT * FROM chat_global ORDER BY id DESC LIMIT 50) t
       ORDER BY id ASC"
    );
  }

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  __out(['ok'=>true,'messages'=>$rows,'server_time'=>(new DateTimeImmutable())->format(DateTime::ATOM)]);
} catch (Throwable $e) {
  __out(['ok'=>false,'error'=>'SERVER_ERROR','detail'=>$e->getMessage()], 500);
}

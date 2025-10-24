<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$cfg = require __DIR__ . '/../../auth/config.php';
$cookieName = $cfg['cookies']['session_name'] ?? 'hunthub_session';
$secure     = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

// (Optional) Server-seitige Tokenentwertung, falls du eine Tabelle dafür hast.
// Ohne Annahmen: nur defensiv versuchen – Fehler still ignorieren.
try {
  require_once __DIR__ . '/../../auth/db.php';
  if (isset($_COOKIE[$cookieName])) {
    $token = (string)$_COOKIE[$cookieName];
    $pdo   = db();
    foreach ([
      ['table' => 'sessions',       'col' => 'token'],
      ['table' => 'auth_sessions',  'col' => 'token'],
      ['table' => 'user_sessions',  'col' => 'token'],
    ] as $t) {
      try {
        $sql = "DELETE FROM {$t['table']} WHERE {$t['col']} = :tok";
        $st  = $pdo->prepare($sql);
        $st->execute([':tok' => $token]);
      } catch (Throwable $ignored) {}
    }
  }
} catch (Throwable $ignored) {}

// Auth-Cookie löschen – Attribute MÜSSEN zu denen beim Setzen passen.
$params = [
  'expires'  => time() - 3600,
  'path'     => '/',          // genau wie beim Setzen!
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',
];
// Nur setzen, wenn du es auch beim Login gesetzt hast – sonst keinesfalls!
if (!empty($cfg['cookies']['domain'])) {
  $params['domain'] = $cfg['cookies']['domain'];
}
setcookie($cookieName, '', $params);

// PHP-Session ebenfalls beenden (falls genutzt)
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $sp = session_get_cookie_params();
  setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => $sp['path'] ?: '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
    // domain hier i. d. R. nicht nötig – nur wenn explizit konfiguriert
  ]);
}
session_destroy();

echo json_encode(['ok' => true]);

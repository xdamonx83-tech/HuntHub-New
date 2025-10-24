<?php
// /auth/session_bootstrap.php
declare(strict_types=1);

// Ein EINZIGER, fixer Name für die Session (statt PHPSESSID)
session_name('hunthub_sid');

// Cookie-Parameter so setzen, dass ALLE Pfade (auch /api) sie sehen:
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime' => 60*60*24*14, // 14 Tage
  'path'     => '/',         // <— GANZ WICHTIG
  'domain'   => '',          // aktuelle Domain
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',       // bei reinen Same‑Site Navigations ok
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

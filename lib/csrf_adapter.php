<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/csrf.php';

/**
 * Vereinheitlicht den CSRF-Check, egal wie deine csrf.php die Funktion nennt.
 * Nutzt die vorhandene Implementierung, fällt sonst auf Header-Prüfung zurück.
 */
function hh_enforce_csrf(): void {
  // Verwende, was es in deiner csrf.php gibt:
  if (function_exists('enforce_csrf'))   { enforce_csrf();   return; }
  if (function_exists('csrf_require'))   { csrf_require();   return; }
  if (function_exists('require_csrf'))   { require_csrf();   return; }
  if (function_exists('assert_csrf'))    { assert_csrf();    return; }
  if (function_exists('verify_csrf'))    { verify_csrf();    return; }

  // Minimal-Fallback: Header muss gesetzt sein (Name wie im Frontend: X-CSRF)
  $hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
  if (!$hdr) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false, 'error'=>'CSRF header missing']);
    exit;
  }
}

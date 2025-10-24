<?php
// /lib/hh_boot.php
declare(strict_types=1);

/** Finde den PDO-Handle unabhängig vom Projektnamen. */
function hh_db(): PDO {
  // 1) Übliche Globals
  foreach (['db','pdo','dbh','database'] as $k) {
    if (isset($GLOBALS[$k]) && $GLOBALS[$k] instanceof PDO) return $GLOBALS[$k];
  }
  // 2) App-Container-Muster
  if (isset($GLOBALS['app']['db']) && $GLOBALS['app']['db'] instanceof PDO) return $GLOBALS['app']['db'];

  // 3) Übliche Helper-Funktionen
  foreach (['db','pdo','get_db','app_db','database','getPdo'] as $fn) {
    if (function_exists($fn)) {
      $v = @call_user_func($fn);
      if ($v instanceof PDO) return $v;
    }
  }

  throw new RuntimeException('DB missing');
}

/** Wrapper, ruft require_auth() mit oder ohne Param auf. */
function hh_require_auth(): array {
  if (!function_exists('require_auth')) throw new RuntimeException('auth required');
  try { return require_auth(hh_db()); }                 // Variante mit Param
  catch (ArgumentCountError $e) { return require_auth(); } // Variante ohne Param
}

/** Wrapper für optional_auth(), fällt sonst auf [] zurück. */
function hh_optional_auth(): array {
  if (!function_exists('optional_auth')) return [];
  try { return optional_auth(hh_db()) ?: []; }
  catch (ArgumentCountError $e) { return optional_auth() ?: []; }
}

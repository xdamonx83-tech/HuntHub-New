<?php
declare(strict_types=1);

/**
 * CSRF-Utilities – DB-basiert (auth_sessions.csrf_token)
 *
 * Kompatibel zu bestehendem Flow:
 *  - Token wird in Tabelle aus config.php (default: auth_sessions) gespeichert
 *  - Schlüsselspalte auto-erkannt: 'token' oder 'id'
 *  - Session-Cookie-Name aus config.php (fallback: 'sess_id')
 *
 * Neue Helper:
 *  - csrf_token(): string                   -> Token erzeugen/holen (für <meta>)
 *  - check_csrf_request(array $data): bool  -> prüft $data['csrf'] / ['csrf_token']
 *  - csrf_meta_tag(): string                -> fertiges <meta name="csrf" ...>
 */

/* ---------------- Grundhelfer (belassen / weiterverwenden) ---------------- */

function sessions_table_from_config(): string {
  $cfg = require __DIR__ . '/config.php';
  $t = $cfg['sessions_table'] ?? 'auth_sessions';
  $t = strtolower((string)$t);
  return preg_match('/^[a-z0-9_]+$/', $t) ? $t : 'auth_sessions';
}

function session_key_col(\PDO $pdo, string $table): string {
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_COLUMN, 0);
    if (in_array('token', $cols, true)) return 'token';
    if (in_array('id', $cols, true))    return 'id';
  } catch (\PDOException $e) {
    // Fallback
  }
  return 'token';
}

function random_hex(int $bytes = 32): string {
  return bin2hex(random_bytes($bytes));
}

/** Name des Session-Cookies aus config.php – Fallback 'sess_id' */
function session_cookie_name_from_config(): string {
  $cfg = require __DIR__ . '/config.php';
  // versuche mehrere mögliche Keys
  $name =
    $cfg['session_cookie'] ??
    $cfg['cookie_name'] ??
    $cfg['sess_cookie'] ??
    'sess_id';
  $name = (string)$name;
  return $name !== '' ? $name : 'sess_id';
}

/**
 * Gibt vorhandenes CSRF-Token zur Session zurück oder legt es an.
 * Wirft RuntimeException, wenn Spalte csrf_token fehlt (mit Migrationshinweis).
 */
function issue_csrf(\PDO $pdo, string $sessionCookie): string {
  if ($sessionCookie === '') return '';
  $table = sessions_table_from_config();
  $key   = session_key_col($pdo, $table);

  try {
    $st = $pdo->prepare("SELECT `csrf_token` FROM `{$table}` WHERE `{$key}` = ? LIMIT 1");
    $st->execute([$sessionCookie]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);
  } catch (\PDOException $e) {
    if ($e->getCode() === '42S22') {
      throw new \RuntimeException(
        "CSRF: Spalte `csrf_token` fehlt in Tabelle `{$table}`. Migration:\n".
        "ALTER TABLE `{$table}` ADD COLUMN `csrf_token` VARCHAR(64) NULL, ADD INDEX(`csrf_token`);"
      );
    }
    throw $e;
  }

  if ($row && !empty($row['csrf_token'])) {
    return (string)$row['csrf_token'];
  }

  $token = random_hex(32);
  $up = $pdo->prepare("UPDATE `{$table}` SET `csrf_token` = ? WHERE `{$key}` = ? LIMIT 1");
  $up->execute([$token, $sessionCookie]);
  return $token;
}

/** Prüft übermitteltes CSRF-Token gegen die Session in der DB. */
function check_csrf(\PDO $pdo, string $sessionCookie, ?string $token): bool {
  if ($sessionCookie === '' || !$token) return false;
  $table = sessions_table_from_config();
  $key   = session_key_col($pdo, $table);

  try {
    $st = $pdo->prepare("SELECT `csrf_token` FROM `{$table}` WHERE `{$key}` = ? LIMIT 1");
    $st->execute([$sessionCookie]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);
  } catch (\PDOException $e) {
    if ($e->getCode() === '42S22') {
      return false;
    }
    throw $e;
  }

  return $row && is_string($row['csrf_token']) && hash_equals((string)$row['csrf_token'], (string)$token);
}

/* ------------------------- Neue, bequeme Helper --------------------------- */

/**
 * Liefert das aktuelle CSRF-Token der eingeloggten Session (und erzeugt es, falls fehlt).
 * Nutzt automatisch db() aus /auth/db.php.
 */
function csrf_token(): string {
  // Hole Session-Cookie
  $cookieName = session_cookie_name_from_config();
  $sess = (string)($_COOKIE[$cookieName] ?? '');

  // Ohne Session-Cookie kein Token
  if ($sess === '') return '';

  // DB holen
  if (!function_exists('db')) {
    require_once __DIR__ . '/db.php';
  }
  $pdo = db();

  try {
    return issue_csrf($pdo, $sess);
  } catch (\Throwable $e) {
    // Wenn Spalte fehlt o.ä., liefere leer -> Callsite kann damit umgehen
    return '';
  }
}

/**
 * Prüft $_POST/$_GET-ähnliche Daten auf gültiges CSRF-Feld.
 * Akzeptierte Felder: 'csrf' oder 'csrf_token'
 */
function check_csrf_request(array $data): bool {
  // Token aus Body/Query
  $token = null;
  foreach (['csrf','csrf_token','_csrf','_token'] as $k) {
    if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') { $token = $data[$k]; break; }
  }
  // Fallback: AJAX-Header
  if ($token === null) {
    foreach (['HTTP_X_CSRF','HTTP_X_CSRF_TOKEN','HTTP_X_XSRF_TOKEN'] as $h) {
      if (!empty($_SERVER[$h])) { $token = (string)$_SERVER[$h]; break; }
    }
  }

  $cookieName = session_cookie_name_from_config();
  $sess = (string)($_COOKIE[$cookieName] ?? '');
  if ($sess === '' || $token === null) return false;

  if (!function_exists('db')) require_once __DIR__ . '/db.php';
  $pdo = db();

  try { return check_csrf($pdo, $sess, $token); }
  catch (\Throwable $e) { return false; }
}


/** Komfort-Helfer: fertiges <meta> für den <head> */
function csrf_meta_tag(): string {
  $t = htmlspecialchars(csrf_token(), ENT_QUOTES);
  return '<meta name="csrf" content="'.$t.'">';
}
// Drop-in Wrapper: sammelt Token & Session-Cookie selbst ein und ruft check_csrf($pdo, $sessionVal, $token)
if (!function_exists('verify_csrf')) {
    /**
     * Verifiziert CSRF. Token kann explizit übergeben werden; wenn leer, wird es aus
     * POST/GET/HTTP_X_CSRF gelesen. Der Session-Cookie-Name kommt aus config.php oder session_name().
     */
function verify_csrf(PDO $pdo, ?string $token = null, ?string $sessionName = null): bool
{
  // Token finden (Body/Query/Headers)
  if ($token === null || $token === '') {
    $token = $_POST['csrf'] ?? $_POST['csrf_token'] ?? $_GET['csrf'] ?? $_GET['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
  }

  // Cookie-Name konsistent bestimmen
  if ($sessionName === null || $sessionName === '') {
    $sessionName = session_cookie_name_from_config();
  }

  $sessionVal = $_COOKIE[$sessionName] ?? '';
  return check_csrf($pdo, $sessionVal, (string)$token);
}

}
<?php
declare(strict_types=1);

/**
 * Auth-Utilities
 * - DB-gestützte Sessions (Tabelle aus config.php, default: auth_sessions)
 * - Session-Key-Spalte wird automatisch erkannt (token|id)
 * - CSRF-Token je Session (Spalte csrf_token in der Session-Tabelle)
 * - Benutzer-Auflösung und Guards (optional_auth/require_auth)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/roles.php'; // Rollen-Array/Const: ROLES
$CFG = require __DIR__ . '/config.php';

////////////////////////////////////////////////////////////////////////////////
// Helpers: Config / Cookie
////////////////////////////////////////////////////////////////////////////////

/** Name des Session-Cookies (Top-Level Keys bevorzugt, sonst Legacy-Block). */
function session_cookie_name(): string {
  $cfg = $GLOBALS['CFG'] ?? [];
  return (string)(
    $cfg['session_cookie']
    ?? ($cfg['cookies']['session_name'] ?? 'sess_id')
  );
}

/** setcookie()-Parameter aus Config erstellen. */
function session_cookie_params(): array {
  $cfg = $GLOBALS['CFG'] ?? [];

  $path   = $cfg['cookie_path']     ?? ($cfg['cookies']['path']     ?? '/');
  $domain = $cfg['cookie_domain']   ?? ($cfg['cookies']['domain']   ?? null);
  $secure = $cfg['cookie_secure']   ?? ($cfg['cookies']['secure']   ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));
  $http   = $cfg['cookie_httponly'] ?? ($cfg['cookies']['httponly'] ?? true);
  $same   = $cfg['cookie_samesite'] ?? ($cfg['cookies']['samesite'] ?? 'Lax');
  $life   = $cfg['cookies']['lifetime'] ?? (60*60*24*14);

  return [
    'expires'  => time() + (int)$life,
    'path'     => $path,
    'domain'   => $domain ?: '',
    'secure'   => (bool)$secure,
    'httponly' => (bool)$http,
    'samesite' => (string)$same,
  ];
}

/** Liefert die Session-Tabellen- und Keyspalten-Infos. */
function session_table_and_key(PDO $pdo): array {
  $table = sessions_table_from_config();          // aus csrf.php
  $key   = session_key_col($pdo, $table);        // 'token' oder 'id'
  return [$table, $key];
}

////////////////////////////////////////////////////////////////////////////////
// Utils
////////////////////////////////////////////////////////////////////////////////

function email_valid(string $email): bool {
  return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function password_hash_strong(string $password): string {
  return password_hash($password, PASSWORD_ARGON2ID);
}

function verify_password(array $user, string $password): bool {
  return password_verify($password, $user['password_hash']);
}
// auth/auth.php (Beispiel)
function load_user_by_id(PDO $pdo, int $id): ?array {
  $cols = "
    id, email, display_name, bio,
    avatar_path, cover_path, cover_x, cover_y, cover_scale,
    social_twitch, social_tiktok, social_youtube,
    social_instagram, social_twitter, social_facebook
  ";
  $stmt = $pdo->prepare("SELECT $cols FROM users WHERE id = ?");
  $stmt->execute([$id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  return $user ?: null;
}

// Falls du eine Variante per Session-Token hast, dort ebenfalls $cols anpassen.

////////////////////////////////////////////////////////////////////////////////
// User CRUD (Basis)
////////////////////////////////////////////////////////////////////////////////

function create_user(string $email, string $password, string $displayName, string $role = 'user'): int {
  if (!defined('ROLES')) {
    define('ROLES', ['user','uploader','moderator','administrator']);
  }
  if (!in_array($role, ROLES, true)) {
    $role = 'user';
  }

  $pdo = db();

  // Slug generieren
  $base = slugify($displayName, 80);
  $slug = unique_slug($pdo, $base, 80);

  $stmt = $pdo->prepare(
    "INSERT INTO users (email, password_hash, display_name, slug, role) 
     VALUES (?, ?, ?, ?, ?)"
  );
  $stmt->execute([
    $email,
    password_hash_strong($password),
    $displayName,
    $slug,
    $role
  ]);

  return (int)$pdo->lastInsertId();
}
/**
 * Slugify: Display-Name → Slug
 */
function slugify(string $text, int $maxLen = 80): string {
    // Unicode → ASCII
    if (function_exists('iconv')) {
        $out = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($out !== false) {
            $text = $out;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text); // alles außer a–z0–9 → "-"
    $text = trim($text, '-');
    if ($text === '') $text = 'user';
    if (strlen($text) > $maxLen) $text = substr($text, 0, $maxLen);
    return $text;
}

/**
 * Sorgt für eindeutigen Slug (hängt -2, -3, ... an, wenn schon belegt)
 */
function unique_slug(PDO $pdo, string $base, int $maxLen = 80): string {
    $slug = $base;
    $i = 2;
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE slug = ? LIMIT 1");
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $suffix = '-' . $i++;
        $trimLen = $maxLen - strlen($suffix);
        $slug = substr($base, 0, max(1, $trimLen)) . $suffix;
    }
}
function find_user_by_email(string $email): ?array {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

////////////////////////////////////////////////////////////////////////////////
// Sessions
////////////////////////////////////////////////////////////////////////////////

/**
 * Legt eine neue Session für $userId an und setzt den Cookie.
 * - Erstellt auch ein CSRF-Token in der Session-Tabelle (Spalte csrf_token)
 * - Aktualisiert ggf. created_at/last_seen wenn vorhanden
 */
function create_session(int $userId): array {
  $pdo = db();
  [$table, $key] = session_table_and_key($pdo);

  $sid  = bin2hex(random_bytes(32));
  $csrf = bin2hex(random_bytes(32));

  // Generische Insert-Strategie mit dynamischem Key
  // Annahme: Tabelle hat mind. (key, user_id, csrf_token)
  $sql = "INSERT INTO `{$table}` (`{$key}`, `user_id`, `csrf_token`, `created_at`, `last_seen`)
          VALUES (?, ?, ?, NOW(), NOW())";
  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sid, $userId, $csrf]);
  } catch (PDOException $e) {
    // Fallback ohne Zeitspalten
    if ($e->getCode() === '42S22') {
      $stmt = $pdo->prepare("INSERT INTO `{$table}` (`{$key}`, `user_id`, `csrf_token`) VALUES (?, ?, ?)");
      $stmt->execute([$sid, $userId, $csrf]);
    } else {
      throw $e;
    }
  }

  // Cookie setzen
  $cookieName = session_cookie_name();
  $params     = session_cookie_params();
  setcookie($cookieName, $sid, $params);

  return ['id' => $sid, 'csrf' => $csrf];
}

/** Löscht die aktuelle Session (Cookie + DB). */
function destroy_session(): void {
  $cookieName = session_cookie_name();
  $sid = $_COOKIE[$cookieName] ?? null;
  if (!$sid) return;

  $pdo = db();
  [$table, $key] = session_table_and_key($pdo);
  $pdo->prepare("DELETE FROM `{$table}` WHERE `{$key}` = ? LIMIT 1")->execute([$sid]);

  // Cookie entfernen
  $params = session_cookie_params();
  $params['expires'] = time() - 3600;
  setcookie($cookieName, '', $params);
}

////////////////////////////////////////////////////////////////////////////////
// Request-gebundene Auth
////////////////////////////////////////////////////////////////////////////////

/** Globaler User-Cache dieses Requests. */
$USER = null;

/**
 * Versucht, den eingeloggten User zu laden (falls Cookie vorhanden).
 * Setzt global $USER.
 * Gibt true zurück, wenn eine gültige Session aufgelöst werden konnte.
 */
function optional_auth(): bool {
  global $USER;
  if ($USER !== null) return (bool)$USER;

  $cookieName = session_cookie_name();
  $sid = (string)($_COOKIE[$cookieName] ?? '');
  if ($sid === '') { $USER = null; return false; }

  $pdo = db();
  [$table, $key] = session_table_and_key($pdo);

  // Session -> User
$sql = "SELECT u.* FROM `{$table}` s
        JOIN users u ON u.id = s.user_id
        WHERE s.`token` = ?
        LIMIT 1";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$sid]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) { $USER = null; return false; }

  // last_seen touchen, falls Spalte konfiguriert ist
  $cfg = $GLOBALS['CFG'] ?? [];
  $touchCol = $cfg['sessions_touch_col'] ?? null;
  if (is_string($touchCol) && $touchCol !== '') {
    try {
      $pdo->prepare("UPDATE `{$table}` SET `{$touchCol}` = NOW() WHERE `{$key}` = ? LIMIT 1")->execute([$sid]);
    } catch (PDOException $e) {
      // falls Spalte fehlt: ignorieren
    }
  }

  $USER = $user;
  return true;
}

/**
 * Erfordert Login. Bei APIs liefert 401 JSON, sonst 302 auf Login (falls vorhanden).
 */
function require_auth(): void {
  if (optional_auth()) return;

  // API-Heuristik: Wenn JSON erwartet wird oder im /api/ Pfad
  $isApi = (php_sapi_name() !== 'cli') && (
    (isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/api/')) ||
    (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
  );

  if ($isApi) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'NOT_AUTH']);
    exit;
  }

  // Fallback: Redirect auf Login-Seite, falls vorhanden
  $base = $GLOBALS['CFG']['app_base'] ?? '';
  header('Location: ' . $base . '/login.php');
  exit;
}

/** Liefert den aktuell eingeloggten Benutzer (oder null). */
function current_user(): ?array {
  global $USER;
  if ($USER === null) optional_auth();
  return $USER ?: null;
}

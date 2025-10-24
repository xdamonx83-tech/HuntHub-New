<?php
declare(strict_types=1);

/**
 * Auth-Guards & Helfer
 * - current_user():   liest eingeloggten User aus auth_sessions + "touch"
 * - optional_auth():  gibt User oder null zurück
 * - require_auth():   erzwingt Login (401 JSON)
 * - require_role():   erzwingt Mindestrolle (403 JSON)
 * - require_admin():  erzwingt Admin-Rechte (403 JSON)
 *
 * Abhängigkeiten:
 * - /auth/db.php        -> db()
 * - /auth/config.php    -> cookies.session_name
 * - /auth/roles.php     -> is_admin($user), role_rank($role) (Fallbacks hier enthalten)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/roles.php'; // stellt idealerweise is_admin() & role_rank() bereit

/* ---- Fallbacks, falls roles.php sie nicht definiert hat ---- */
if (!function_exists('role_rank')) {
  /**
   * Gibt eine numerische Rangfolge zurück (user < mod < admin)
   */
  function role_rank(?string $role): int {
    $map = ['user' => 0, 'mod' => 1, 'admin' => 2];
    $r = strtolower((string)($role ?? 'user'));
    return $map[$r] ?? 0;
  }
}
if (!function_exists('is_admin')) {
  /**
   * Admin ist, wer role='admin' ODER is_admin=1 besitzt
   */
  function is_admin(array $u): bool {
    if (!$u) return false;
    $role = strtolower((string)($u['role'] ?? ''));
    return $role === 'admin' || !empty($u['is_admin'] ?? 0);
  }
}

/**
 * Liefert den eingeloggten User (per Session-Cookie aus config.php)
 * oder null, wenn niemand eingeloggt ist. Aktualisiert last_seen der Session.
 */
function current_user(): ?array {
  $cfg    = require __DIR__ . '/config.php';
  $cookie = $_COOKIE[$cfg['cookies']['session_name']] ?? null;
  if (!$cookie) return null;

  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT s.token AS session_id, u.*
    FROM auth_sessions s
    JOIN users u ON u.id = s.user_id
    WHERE s.token = ?
    LIMIT 1
  ");
  $stmt->execute([$cookie]);
  $row = $stmt->fetch();
  if (!$row) return null;

  // Session „touch“
  $pdo->prepare("UPDATE auth_sessions SET last_seen = NOW() WHERE token = ?")->execute([$cookie]);

  return $row;
}
function is_twitch_streamer(array $u = null): bool {
    if ($u === null) {
        $u = function_exists('current_user') ? current_user() : [];
    }
    return !empty($u) && !empty($u['is_twitch_streamer']);
}
/**
 * Weiche Variante: gibt User oder null zurück – kein Exit, kein Redirect.
 * Ideal für Views (z. B. Forum-Board/Thread), um Formulare konditional einzublenden.
 */
function optional_auth(): ?array {
  return current_user();
}

/**
 * Harte Variante: erzwingt Login; bei fehlender Auth Abbruch mit 401 JSON.
 * Ideal für APIs.
 */
function require_auth(): array {
  $user = current_user();
  if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
  }
  return $user;
}

/**
 * Rollen-Guard für APIs: erzwingt Mindestrolle, sonst 403 JSON.
 */
function require_role(string $minRole): array {
  $user = require_auth();
  if (role_rank($user['role'] ?? 'user') < role_rank($minRole)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
  }
  return $user;
}

/**
 * Admin-Guard: erzwingt Admin-Rechte, sonst 403 JSON.
 */
function require_admin(): array {
  $me = require_auth();
  if (!is_admin($me)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
  }
  return $me;
}
/**
 * Auth mit Weiterleitung für Gäste:
 * - Eingeloggt -> User zurückgeben
 * - Gast       -> Redirect zu /wallguest.php
 */
function require_auth_or_redirect(string $redirect = '/wallguest.php'): array {
  $user = current_user();
  if (!$user) {
    header('Location: ' . $redirect);
    exit;
  }
  return $user;
}

// … deine bestehenden Funktionen …
if (!function_exists('can_view_thread_wall')) {
function can_view_thread_wall(?array $me, array $thread): bool {
$vis = $thread['visibility'] ?? 'public';
$ownerId = (int)($thread['wall_owner_id'] ?? 0);
$authorId = (int)($thread['user_id'] ?? 0);


if ($vis === 'public') return true;
if (!$me) return false;
if ((int)$me['id'] === $ownerId || (int)$me['id'] === $authorId) return true;
if ($vis === 'friends' && function_exists('are_friends')) {
return are_friends((int)$me['id'], $ownerId);
}
return false; // private
}
}


if (!function_exists('can_post_on_wall')) {
function can_post_on_wall(array $me, int $ownerId): bool {
// v1: Nur eigene Wall. Später: Einstellungen erlauben.
return (int)$me['id'] === (int)$ownerId;
}
}
if (!function_exists('require_login') && function_exists('require_user')) {
  function require_login() {
    return require_user();
  }
}

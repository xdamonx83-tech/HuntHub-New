<?php
// /api/auth/oauth_lib.php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/auth.php';   // deine Session-/Login-Helfer
require_once __DIR__ . '/../../auth/oauth_config.php';

/**
 * Mini-HTTP Client
 */
function http_post_json(string $url, array $data, array $headers = []): array {
  $ch = curl_init($url);
  $headers = array_merge(['Accept: application/json'], $headers);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($data),
    CURLOPT_HTTPHEADER => $headers,
  ]);
  $res = curl_exec($ch);
  if ($res === false) throw new RuntimeException('HTTP POST failed: '.curl_error($ch));
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $json = json_decode($res, true);
  if ($code >= 400) throw new RuntimeException('HTTP '.$code.': '.$res);
  return $json ?? [];
}

function http_get_json(string $url, array $headers = []): array {
  $ch = curl_init($url);
  $headers = array_merge(['Accept: application/json'], $headers);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
  ]);
  $res = curl_exec($ch);
  if ($res === false) throw new RuntimeException('HTTP GET failed: '.curl_error($ch));
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $json = json_decode($res, true);
  if ($code >= 400) throw new RuntimeException('HTTP '.$code.': '.$res);
  return $json ?? [];
}

/**
 * OAuth State (CSRF-Schutz) – getrennt vom app-weiten CSRF.
 */
function oauth_state_start(string $provider): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $state = bin2hex(random_bytes(16));
  $_SESSION['oauth_state_'.$provider] = $state;
  return $state;
}

function oauth_state_check(string $provider, ?string $incoming): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $expected = $_SESSION['oauth_state_'.$provider] ?? null;
  unset($_SESSION['oauth_state_'.$provider]);
  if (!$expected || !$incoming || !hash_equals($expected, $incoming)) {
    throw new RuntimeException('Invalid OAuth state');
  }
}

/**
 * Display-Name/Slug-Generator (eindeutig).
 * Passt zu deiner Anforderung: Display-Name soll Unikat sein.
 */
function generate_unique_display(string $base): array {
  $pdo = db();
  // Slug
  $slug = preg_replace('~[^a-z0-9]+~i', '-', strtolower(trim($base)));
  $slug = trim($slug, '-');
  if ($slug === '') $slug = 'user';
  $display = $base;

  // Prüfe Eindeutigkeit und hänge -2, -3 usw. an (für slug und display)
  $suffix = 1;
  $slugTry = $slug;
  $displayTry = $display;
  while (true) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE slug = ? OR display_name = ?");
    $stmt->execute([$slugTry, $displayTry]);
    $exists = (int)$stmt->fetchColumn();
    if ($exists === 0) break;
    $suffix++;
    $slugTry = $slug . '-' . $suffix;
    $displayTry = $display . ' ' . $suffix;
  }
  return [$displayTry, $slugTry];
}

/**
 * Upsert + Login
 * - Falls Provider-ID existiert -> login
 * - Sonst: falls gleiche E-Mail existiert -> Provider in bestehenden User verknüpfen
 * - Sonst: neuen User anlegen
 *
 * Passe login_user_internal() an deine Session-Logik an (ein Hook).
 */
function upsert_social_user_and_login(string $provider, string $providerId, ?string $email, string $name, ?string $avatarUrl): int {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    // 1) Provider-Match
    $stmt = $pdo->prepare("SELECT id FROM users WHERE provider = ? AND provider_id = ?");
    $stmt->execute([$provider, $providerId]);
    $userId = (int)($stmt->fetchColumn() ?: 0);
    if ($userId > 0) {
      $pdo->commit();
      login_user_internal($userId);
      return $userId;
    }

    // 2) Email-Match
    if ($email) {
      $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
      $stmt->execute([$email]);
      $userId = (int)($stmt->fetchColumn() ?: 0);
      if ($userId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET provider = ?, provider_id = ?, avatar_url = COALESCE(?, avatar_url) WHERE id = ?");
        $stmt->execute([$provider, $providerId, $avatarUrl, $userId]);
        $pdo->commit();
        login_user_internal($userId);
        return $userId;
      }
    }

    // 3) Neu anlegen
    [$display, $slug] = generate_unique_display($name);
    // Falls Email fehlt (z.B. bei Steam), speicher NULL; wähle Random-Pass.
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);

    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, display_name, slug, role, provider, provider_id, avatar_url) 
                           VALUES (?, ?, ?, ?, 'user', ?, ?, ?)");
    $stmt->execute([$email, $passwordHash, $display, $slug, $provider, $providerId, $avatarUrl]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->commit();

    login_user_internal($userId);
    return $userId;

  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/**
 * 🔐 Adapter: Hier bitte auf deine bestehende Login-Session-Logik mappen.
 * Idealerweise hast du in /auth/auth.php etwas wie login_user($userId).
 */
// /api/auth/oauth_lib.php — ganz unten ersetzen/ergänzen
function login_user_internal(int $userId): void {
  // nutzt deine bestehende DB-Session + Cookie aus auth.php
  require_once __DIR__ . '/../../auth/auth.php';
  create_session($userId);
}

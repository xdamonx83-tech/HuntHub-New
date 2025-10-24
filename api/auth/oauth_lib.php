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
 * OAuth State (CSRF-Schutz)
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
 * Display-Name/Slug-Generator
 */
function generate_unique_display(string $base): array {
  $pdo = db();
  $slug = preg_replace('~[^a-z0-9]+~i', '-', strtolower(trim($base)));
  $slug = trim($slug, '-');
  if ($slug === '') $slug = 'user';
  $display = $base;

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
 * Avatar-Download
 */
function fetch_binary(string $url): ?string {
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_USERAGENT => 'HunthubAvatarDownloader/1.0',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data !== false && $code >= 200 && $code < 300) return $data;
  }
  $ctx = stream_context_create([
    'http' => ['timeout' => 20, 'header' => "User-Agent: HunthubAvatarDownloader/1.0\r\n"],
    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true]
  ]);
  $data = @file_get_contents($url, false, $ctx);
  return ($data !== false) ? $data : null;
}

function download_and_store_avatar(?string $url, int $userId): ?string {
  if (!$url) return null;

  $path = parse_url($url, PHP_URL_PATH) ?: '';
  $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
  if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) $ext = 'jpg';

  $dirAbs = __DIR__ . '/../../uploads/avatars/';
  if (!is_dir($dirAbs)) {
    @mkdir($dirAbs, 0775, true);
  }
  if (!is_dir($dirAbs) || !is_writable($dirAbs)) {
    return null;
  }

  $fileName = 'avatar_'.$userId.'_'.time().'.'.$ext;
  $absFile  = $dirAbs . $fileName;
  $relFile  = '/uploads/avatars/' . $fileName;

  $bin = fetch_binary($url);
  if ($bin === null) return null;

  if (@file_put_contents($absFile, $bin) === false) return null;

  return $relFile;
}

/**
 * Upsert + Login mit Avatar-Download
 */
function upsert_social_user_and_login(string $provider, string $providerId, ?string $email, string $name, ?string $avatarUrl): int {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    // 1) Provider-Match
    $stmt = $pdo->prepare("SELECT id, avatar_path FROM users WHERE provider = ? AND provider_id = ? LIMIT 1");
    $stmt->execute([$provider, $providerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $userId = (int)$row['id'];
      $newPath = null;
      if (empty($row['avatar_path']) && $avatarUrl) {
        $newPath = download_and_store_avatar($avatarUrl, $userId);
      }
      if ($avatarUrl || $newPath) {
        $sql = "UPDATE users SET provider=?, provider_id=?, avatar_url=COALESCE(?, avatar_url)";
        $params = [$provider, $providerId, $avatarUrl];
        if ($newPath) {
          $sql .= ", avatar_path=?";
          $params[] = $newPath;
        }
        $sql .= " WHERE id=?";
        $params[] = $userId;
        $pdo->prepare($sql)->execute($params);
      }
      $pdo->commit();
      login_user_internal($userId);
      return $userId;
    }

    // 2) Email-Match
    if ($email) {
      $stmt = $pdo->prepare("SELECT id, avatar_path FROM users WHERE email = ? LIMIT 1");
      $stmt->execute([$email]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $userId = (int)$row['id'];
        $newPath = null;
        if (empty($row['avatar_path']) && $avatarUrl) {
          $newPath = download_and_store_avatar($avatarUrl, $userId);
        }
        $sql = "UPDATE users SET provider=?, provider_id=?, avatar_url=COALESCE(?, avatar_url)";
        $params = [$provider, $providerId, $avatarUrl];
        if ($newPath) {
          $sql .= ", avatar_path=?";
          $params[] = $newPath;
        }
        $sql .= " WHERE id=?";
        $params[] = $userId;
        $pdo->prepare($sql)->execute($params);

        $pdo->commit();
        login_user_internal($userId);
        return $userId;
      }
    }

    // 3) Neu anlegen
    [$display, $slug] = generate_unique_display($name);
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);

    $stmt = $pdo->prepare("INSERT INTO users 
      (email, password_hash, display_name, slug, role, provider, provider_id, avatar_url) 
      VALUES (?, ?, ?, ?, 'user', ?, ?, ?)");
    $stmt->execute([$email, $passwordHash, $display, $slug, $provider, $providerId, $avatarUrl]);
    $userId = (int)$pdo->lastInsertId();

    if ($avatarUrl) {
      $avatarPath = download_and_store_avatar($avatarUrl, $userId);
      if ($avatarPath) {
        $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?")->execute([$avatarPath, $userId]);
      }
    }

    $pdo->commit();
    login_user_internal($userId);
    return $userId;

  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/**
 * Login-Hook → deine bestehende Session
 */
function login_user_internal(int $userId): void {
  require_once __DIR__ . '/../../auth/auth.php';
  create_session($userId);
}

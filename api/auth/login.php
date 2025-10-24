<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
$cfg = require __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../../lib/points.php'; // ✅ Punkte-System einbinden

/**
 * Login-API
 */
try {
    // -------- Content-Negotiation (JSON vs. klassisch) ----------
    $accept     = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhrHeader  = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $wantsJson  = $xhrHeader || stripos($accept, 'application/json') !== false;

    // -------- Cookie/Session-Konfig -----------------------------
    $cookie      = $cfg['cookies'] ?? [];
    $cookieName  = $cookie['session_name'] ?? 'sess_id';
    $lifetime    = (int)($cookie['lifetime'] ?? 1209600); // 14 Tage
    $sessionsTbl = $cfg['sessions_table'] ?? 'auth_sessions';

    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ---- optionale Spalten für Kompatibilität anlegen ----------
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_seen`  DATETIME NULL DEFAULT NULL AFTER `updated_at`");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login` DATETIME NULL DEFAULT NULL AFTER `last_seen`");
        $pdo->exec("CREATE INDEX IF NOT EXISTS `idx_users_last_seen` ON `users` (`last_seen`)");
    } catch (Throwable $e) {
        // ältere MariaDB-Versionen ohne IF NOT EXISTS — still ok
    }

    // ---------------- Input lesen -------------------------------
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input') ?: '';

    $in = [];
    if (stripos($ct, 'application/json') !== false) {
        $in = json_decode($raw, true) ?: [];
    } elseif (!empty($_POST)) {
        $in = $_POST;
    } else {
        parse_str($raw, $in);
        if (!is_array($in)) $in = [];
    }

    $email = trim((string)($in['email'] ?? ''));
    $pass  = (string)($in['password'] ?? '');
    $next  = (string)($in['next'] ?? ($_GET['next'] ?? ''));
    $redirectTarget = $next !== '' ? $next : '/profile.php';

    if ($email === '' || $pass === '') {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Email/Passwort fehlt']);
        } else {
            header('Location: /login.php?error=missing', true, 303);
        }
        exit;
    }

    // ----------------- User lookup + Passwort --------------------
    $st = $pdo->prepare('SELECT id, email, password_hash, display_name, slug, role FROM users WHERE email=? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u || !password_verify($pass, $u['password_hash'] ?? '')) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['ok'=>false,'error'=>'Ungültige Zugangsdaten']);
        } else {
            header('Location: /login.php?error=invalid', true, 303);
        }
        exit;
    }

    // --------------- last_login / last_seen ---------------------
    try {
        $upd = $pdo->prepare('UPDATE users SET last_login = NOW(), last_seen = NOW() WHERE id = :id');
        $upd->execute([':id' => (int)$u['id']]);
    } catch (Throwable $e) {}

    // ✅ Punkte vergeben für Daily-Login
    try {
        $POINTS = get_points_mapping();
        award_points($pdo, (int)$u['id'], 'daily_login', $POINTS['daily_login'] ?? 1);
    } catch (Throwable $e) {
        error_log("award_points daily_login failed: ".$e->getMessage());
    }

    // ---------------- Session-Tabelle ----------------------------
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$sessionsTbl}` (
           `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
           `token` CHAR(64) NOT NULL,
           `user_id` INT UNSIGNED NOT NULL,
           `created_at` DATETIME NOT NULL,
           `last_seen`  DATETIME NOT NULL,
           `ip` VARCHAR(45) NULL,
           `user_agent` VARCHAR(255) NULL,
           PRIMARY KEY (`id`),
           UNIQUE KEY `uniq_token` (`token`),
           KEY `idx_user` (`user_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // ----------------- Session + Cookie --------------------------
    $token  = bin2hex(random_bytes(32));
    $now    = date('Y-m-d H:i:s');
    $ip     = $_SERVER['REMOTE_ADDR']     ?? null;
    $ua     = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $ins = $pdo->prepare("INSERT INTO `{$sessionsTbl}` (token,user_id,created_at,last_seen,ip,user_agent) VALUES (?,?,?,?,?,?)");
    $ins->execute([$token, (int)$u['id'], $now, $now, $ip, $ua]);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    setcookie($cookieName, $token, [
        'expires'  => time() + $lifetime,
        'path'     => $cookie['path']     ?? '/',
        'domain'   => $cookie['domain']   ?? null,
        'secure'   => (bool)($cookie['secure'] ?? $isHttps),
        'httponly' => (bool)($cookie['httponly'] ?? true),
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);

    // ------------------- Antwort -------------------------------
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'redirect' => $redirectTarget]);
    } else {
        header('Location: ' . $redirectTarget, true, 303);
    }
    exit;

} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Serverfehler: ' . $e->getMessage()]);
}

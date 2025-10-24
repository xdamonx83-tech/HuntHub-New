<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
$cfg = require __DIR__ . '/../../auth/config.php';

/**
 * Passwort mit Token setzen (optional Auto-Login)
 * Input: JSON/Form { token, password }
 * Output: XHR -> JSON {ok:true, redirect}, sonst 303 Redirect
 */
try {
    $accept     = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhrHeader  = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $wantsJson  = $xhrHeader || stripos($accept, 'application/json') !== false;

    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Eingabe
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input') ?: '';
    $in  = [];
    if (stripos($ct, 'application/json') !== false) {
        $in = json_decode($raw, true) ?: [];
    } elseif (!empty($_POST)) {
        $in = $_POST;
    } else {
        parse_str($raw, $in);
        if (!is_array($in)) $in = [];
    }

    $token = trim((string)($in['token'] ?? ($_GET['token'] ?? '')));
    $pass  = (string)($in['password'] ?? '');

    if ($token === '' || $pass === '' || strlen($pass) < 6) {
        if ($wantsJson) { header('Content-Type: application/json'); http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Ungültige Eingabe']); }
        else { header('Location: /reset.php?token='.urlencode($token).'&err=1', true, 303); }
        exit;
    }

    $tbl = $cfg['password_resets_table'] ?? 'auth_password_resets';
    $st = $pdo->prepare("SELECT r.id, r.user_id, r.expires_at, r.used_at, u.id AS uid FROM `{$tbl}` r JOIN users u ON u.id = r.user_id WHERE r.token=? LIMIT 1");
    $st->execute([$token]);
    $row = $st->fetch();

    if (!$row || $row['used_at'] !== null || strtotime((string)$row['expires_at']) < time()) {
        if ($wantsJson) { header('Content-Type: application/json'); http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Token ungültig oder abgelaufen']); }
        else { header('Location: /reset.php?err=token', true, 303); }
        exit;
    }

    // Passwort setzen
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $upd = $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id');
    $upd->execute([':h'=>$hash, ':id'=>(int)$row['user_id']]);

    // Token als verwendet markieren
    $use = $pdo->prepare("UPDATE `{$tbl}` SET used_at = NOW() WHERE id = ?");
    $use->execute([(int)$row['id']]);

    // Bestehende Sessions invalidieren (Security)
    $sessionsTbl = $cfg['sessions_table'] ?? 'auth_sessions';
    $pdo->prepare("DELETE FROM `{$sessionsTbl}` WHERE user_id=?")->execute([(int)$row['user_id']]);

    // Optional: Auto-Login erzeugen (wie in login.php)
    $cookie   = $cfg['cookies'] ?? [];
    $cookieName = $cookie['session_name'] ?? 'sess_id';
    $lifetime   = (int)($cookie['lifetime'] ?? 1209600);
    $isHttps    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

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

    $newToken = bin2hex(random_bytes(32));
    $now      = date('Y-m-d H:i:s');
    $ins = $pdo->prepare("INSERT INTO `{$sessionsTbl}` (token,user_id,created_at,last_seen,ip,user_agent) VALUES (?,?,?,?,?,?)");
    $ins->execute([$newToken, (int)$row['user_id'], $now, $now, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

    setcookie($cookieName, $newToken, [
        'expires'  => time() + $lifetime,
        'path'     => $cookie['path']     ?? '/',
        'domain'   => $cookie['domain']   ?? null,
        'secure'   => (bool)($cookie['secure'] ?? $isHttps),
        'httponly' => (bool)($cookie['httponly'] ?? true),
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);

    $redirect = '/profile.php';

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true, 'redirect'=>$redirect]);
    } else {
        header('Location: ' . $redirect, true, 303);
    }
    exit;

} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Serverfehler: '.$e->getMessage()]);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
$cfg = require __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../../lib/mailer.php';

try {
    // Antwortformat erkennen
    $accept    = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    $isXHR     = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $wantsJson = $isXHR || (stripos($accept, 'application/json') !== false);

    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Logging
    $logDir = __DIR__ . '/../../var';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $logFile = $logDir . '/reset-links.log';
    $log = function (string $m) use ($logFile) {
        @file_put_contents($logFile, '['.date('c').'] '.$m."\n", FILE_APPEND);
    };

    // Eingabe lesen (JSON/FORM)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input'); if ($raw === false) $raw = '';
    $in = [];
    if (stripos($contentType, 'application/json') !== false) {
        $tmp = json_decode($raw, true);
        $in  = is_array($tmp) ? $tmp : [];
    } elseif (!empty($_POST)) {
        $in = $_POST;
    } else {
        parse_str($raw, $in);
        if (!is_array($in)) $in = [];
    }

    // Email normalisieren (case-insensitive)
    $email = trim((string)($in['email'] ?? ''));
    $email = function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email);

    if ($email === '') {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok'=>false, 'message'=>'Bitte E-Mail eingeben.']);
        } else {
            header('Location: /forgot.php?err=1', true, 303);
        }
        exit;
    }

    // Tabelle sicherstellen
    $tbl = $cfg['password_resets_table'] ?? 'auth_password_resets';
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$tbl}` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` INT UNSIGNED NOT NULL,
          `token` CHAR(64) NOT NULL,
          `created_at` DATETIME NOT NULL,
          `expires_at` DATETIME NOT NULL,
          `used_at` DATETIME NULL DEFAULT NULL,
          `ip` VARCHAR(45) NULL,
          `user_agent` VARCHAR(255) NULL,
          PRIMARY KEY(`id`),
          UNIQUE KEY `uniq_token` (`token`),
          KEY `idx_user` (`user_id`),
          KEY `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // User suchen (case-insensitive)
    $st = $pdo->prepare('SELECT id, email, display_name FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u) {
        $log('NO_USER email='.$email);
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            echo json_encode(['ok'=>false, 'message'=>'User/E-Mail Adresse existiert nicht.']);
        } else {
            header('Location: /forgot.php?err=notfound', true, 303);
        }
        exit;
    }

    // Token ggf. wiederverwenden (<10 Min) sonst neu
    $get = $pdo->prepare("SELECT id, token, created_at, expires_at
                          FROM `{$tbl}`
                          WHERE user_id=? AND used_at IS NULL AND expires_at > NOW()
                          ORDER BY id DESC LIMIT 1");
    $get->execute([(int)$u['id']]);
    $row = $get->fetch();

    $token = null;
    if ($row) {
        $createdTs = strtotime((string)$row['created_at']);
        if ($createdTs !== false && $createdTs > time() - 600) {
            $token = (string)$row['token'];
            $log('REUSE user_id='.$u['id'].' token='.$token);
        }
    }
    if ($token === null) {
        $token = bin2hex(random_bytes(32));
        $now   = date('Y-m-d H:i:s');
        $exp   = date('Y-m-d H:i:s', time() + 3600);
        $ins = $pdo->prepare("INSERT INTO `{$tbl}` (user_id, token, created_at, expires_at, ip, user_agent)
                              VALUES (?,?,?,?,?,?)");
        $ins->execute([(int)$u['id'], $token, $now, $exp, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
        $log('NEW_TOKEN user_id='.$u['id'].' token='.$token);
    }

    // Reset-Link
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isSSL = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $proto = $isSSL ? 'https' : 'http';
    $base  = isset($cfg['app_base_public']) && $cfg['app_base_public'] ? rtrim($cfg['app_base_public'], '/') : ($proto.'://'.$host);
    $link  = $base . '/reset.php?token=' . urlencode($token);

    // E-Mail senden (SMTP)
    $subject = 'Passwort zurücksetzen';
    $body = "Hallo {$u['display_name']},\r\n\r\n"
          . "du kannst dein Passwort über diesen Link zurücksetzen (60 Minuten gültig):\r\n"
          . "{$link}\r\n\r\n"
          . "Falls du das nicht warst, ignoriere diese E-Mail.\r\n";

    $sent = mailer_send_smtp($u['email'], $u['display_name'], $subject, $body, $cfg);
    $log(($sent ? 'SENT_OK' : 'SEND_FAIL') . ' user_id='.$u['id'].' email='.$u['email'].' link='.$link);

    if (!$sent) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['ok'=>false, 'message'=>'E-Mail Versand fehlgeschlagen. Bitte später erneut versuchen.']);
        } else {
            header('Location: /forgot.php?err=send', true, 303);
        }
        exit;
    }

    // Erfolg
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true, 'message'=>'Wir haben dir eine E-Mail mit dem Link zum Zurücksetzen gesendet.']);
    } else {
        header('Location: /forgot.php?sent=1', true, 303);
    }
    exit;

} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Serverfehler: '.$e->getMessage()]);
}

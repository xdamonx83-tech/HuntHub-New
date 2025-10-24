<?php
declare(strict_types=1);

// Hunthub – Heartbeat: hält den Online-Status aktuell
// Aufruf: POST /api/messages/heartbeat.php (kein Body nötig)

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
$cfg = require __DIR__ . '/../../auth/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Nutzer muss eingeloggt sein (Token-Cookie)
    $me = require_auth();
    $userId = (int)($me['id'] ?? 0);
    if (!$userId) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthenticated']); exit; }

    $pdo = db();

    // last_seen in users aktualisieren
    $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = :id')->execute([':id'=>$userId]);

    // Optional: auch die aktive Sitzung hochzählen (falls sessions_table genutzt wird)
    $cookieName  = $cfg['cookies']['session_name'] ?? 'sess_id';
    $sessionsTbl = $cfg['sessions_table'] ?? 'auth_sessions';
    $token = $_COOKIE[$cookieName] ?? '';
    if ($token) {
        // Tabelle ggf. existiert – Update best effort
        try {
            $st = $pdo->prepare("UPDATE `{$sessionsTbl}` SET last_seen = NOW(), ip = :ip, user_agent = :ua WHERE token = :t");
            $st->execute([
                ':t'  => $token,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (Throwable $e) { /* optional: error_log($e->getMessage()); */ }
    }

    echo json_encode(['ok'=>true,'now'=>date('c')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Serverfehler: '.$e->getMessage()]);
}

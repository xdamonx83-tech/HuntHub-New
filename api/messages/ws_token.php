<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

function b64url(string $in): string {
    return rtrim(strtr(base64_encode($in), '+/', '-_'), '=');
}
function sign_token(array $payload, string $secret): string {
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $b64  = b64url($body);
    $sig  = hash_hmac('sha256', $body, $secret, true);
    return $b64.'.'.b64url($sig);
}

try {
    $me  = current_user();
    if (!$me || !isset($me['id'])) {
        http_response_code(401);
        echo json_encode(['ok'=>false, 'error'=>'auth']);
        exit;
    }
    $cfg = require __DIR__ . '/../../auth/config.php';
    $secret = $cfg['ws_jwt_secret'] ?? '';
    if (!$secret) {
        http_response_code(500);
        echo json_encode(['ok'=>false, 'error'=>'ws_secret_missing']);
        exit;
    }
    $wsUrl = $cfg['ws_url'] ?? '';
    if (!$wsUrl) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'wss://' : 'ws://';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $wsUrl  = $scheme . $host;
    }

    $payload = ['uid'=>(int)$me['id'], 'exp'=> time()+3600];
    $token = sign_token($payload, $secret);

    echo json_encode(['ok'=>true, 'token'=>$token, 'ws_url'=>$wsUrl], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'exception', 'detail'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';

try {
    // Session + User erzwingen
    $me = require_auth_or_redirect('/wallguest.php');
    $uid = (int)$me['id'];

    $db  = db();
    $pairsParam = $_GET['pairs'] ?? '';
    $pairs = array_filter(array_map('trim', explode(',', $pairsParam)));

    $out = [];

    if ($uid > 0 && $pairs) {
        foreach ($pairs as $pair) {
            [$type, $id] = array_pad(explode(':', $pair, 2), 2, null);
            $id = (int)$id;
            if (!$type || $id <= 0) continue;

            $st = $db->prepare("
                SELECT reaction 
                FROM wall_likes 
                WHERE entity_type = :t 
                  AND entity_id   = :id 
                  AND user_id     = :u 
                LIMIT 1
            ");
            $st->execute([
                ':t' => $type,
                ':id'=> $id,
                ':u' => $uid
            ]);
            $reaction = $st->fetchColumn();

            $out[$type . ':' . $id] = $reaction ?: '';
        }
    }

    echo json_encode(['ok'=>true, 'reactions'=>$out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

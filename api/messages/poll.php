<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

try {
    $pdo = db();
    $me  = current_user();
    if (!$me || !isset($me['id'])) {
        http_response_code(401);
        echo json_encode(['ok'=>false, 'error'=>'auth']);
        exit;
    }
    $sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

    $sql = 'SELECT id, sender_id, recipient_id, body, created_at, read_at
            FROM messages
            WHERE (sender_id = ? OR recipient_id = ?)
              AND id > ?
            ORDER BY id ASC
            LIMIT 200';
    $st = $pdo->prepare($sql);
    $st->execute([(int)$me['id'], (int)$me['id'], $sinceId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $last = 0;
    foreach ($rows as $r) { $last = max($last, (int)$r['id']); }

    echo json_encode(['ok'=>true, 'messages'=>$rows, 'last_id'=>$last], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'exception', 'detail'=>$e->getMessage()]);
}

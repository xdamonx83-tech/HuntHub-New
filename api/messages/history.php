<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

try {
    $pdo = db();

    // weiche Auth wie bei dir; bei Bedarf auf require_auth() ändern
    $me = current_user();
    if (!$me || !isset($me['id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'auth']);
        exit;
    }

    $other   = isset($_GET['user_id'])  ? (int)$_GET['user_id']  : 0;
    $sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

    if ($other <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_user']);
        exit;
    }
    if ($sinceId < 0) $sinceId = 0;

    $sql = '
        SELECT id, sender_id, recipient_id, body, created_at
        FROM messages
        WHERE id > :since
          AND (
            (sender_id = :me AND recipient_id = :other)
            OR
            (sender_id = :other AND recipient_id = :me)
          )
        ORDER BY id ASC
        LIMIT 200
    ';
    $st = $pdo->prepare($sql);
    $st->execute([
        ':since' => $sinceId,
        ':me'    => (int)$me['id'],
        ':other' => $other,
    ]);

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) $rows = [];

    // last_id sicher bestimmen (auch bei leerem Ergebnis)
    $last = $sinceId;
    if ($rows) {
        $last = (int)end($rows)['id'];
    }

    echo json_encode(['ok' => true, 'messages' => $rows, 'last_id' => $last], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Standard: kein 500 werfen, damit der Client keine rote Fehlbox zeigt
    if ($debug) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'exception', 'detail' => $e->getMessage()]);
    } else {
        http_response_code(200);
        echo json_encode(['ok' => true, 'messages' => [], 'last_id' => 0]);
    }
}

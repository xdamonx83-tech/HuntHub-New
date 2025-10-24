<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/guards.php';
if (basename($_SERVER['SCRIPT_FILENAME']) !== 'wallguest.php') {
    $me = require_auth();
} else {
    $me = optional_auth();
}

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../lib/wall.php';

try {
    $db = db();

    $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : null;
    $limit   = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;

    $rows = wall_fetch_feed($db, $afterId, $limit);

    $posts = [];
    foreach ($rows as $r) {
        $posts[] = [
            'id'   => (int)$r['id'],
            'html' => wall_render_post($r),
 // ⬅️ jetzt inkl. Attachments
        ];
    }

    echo json_encode([
        'ok'      => true,
        'posts'   => $posts,
        'afterId' => !empty($rows) ? (int)end($rows)['id'] : null,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

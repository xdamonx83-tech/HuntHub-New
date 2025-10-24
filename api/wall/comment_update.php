<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/wall.php';

try {
    $me = require_auth();
    $pdo = db();

    $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
    $content = isset($_POST['content']) ? (string)$_POST['content'] : '';

    if (function_exists('csrf_verify')) {
        if (!csrf_verify($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'csrf_invalid']);
            exit;
        }
    }

    if ($comment_id <= 0 || trim($content) === '') {
        echo json_encode(['ok'=>false,'error'=>'invalid_input']);
        exit;
    }

    $updated = wall_update_comment($pdo, $comment_id, (int)$me['id'], $content);
    if (!$updated) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'forbidden_or_failed']);
        exit;
    }

    echo json_encode(['ok'=>true,'comment'=>$updated]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}

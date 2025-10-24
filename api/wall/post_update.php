<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php'; // falls vorhanden
require_once __DIR__ . '/../../lib/wall.php';

try {
    $me = require_auth(); // erwartet user array oder redirects; passe an dein require_auth an
    $pdo = db();

    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $content = isset($_POST['content']) ? (string)$_POST['content'] : '';

    // CSRF check - passe an deine Implementierung an
    if (function_exists('csrf_verify')) {
        if (!csrf_verify($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'csrf_invalid']);
            exit;
        }
    }

    if ($post_id <= 0 || trim($content) === '') {
        echo json_encode(['ok'=>false,'error'=>'invalid_input']);
        exit;
    }

    $updated = wall_update_post($pdo, $post_id, (int)$me['id'], $content);
    if (!$updated) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'forbidden_or_failed']);
        exit;
    }

    // Return success and updated content (client will update DOM)
    echo json_encode(['ok'=>true,'post'=>$updated]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');


require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
@require_once __DIR__ . '/../../auth/csrf.php';


try {
$pdo = db();
$me = require_admin();
if (function_exists('require_csrf')) {
require_csrf($pdo);
} else if (function_exists('require_csrf_header')) {
require_csrf_header($pdo);
}


$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = (int)($data['id'] ?? 0);
if ($id <= 0) throw new RuntimeException('id fehlt');


$st = $pdo->prepare('DELETE FROM user_ratings WHERE id = ?');
$st->execute([$id]);


echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
http_response_code(400);
echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
try {
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
@require_once __DIR__ . '/../../auth/csrf.php';


$pdo = db();
require_admin();


// simple CSRF guard (uses the same shim idea)
$hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
if ($hdr === '') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'missing_csrf']); exit; }


$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'id_required']); exit; }


$st = $pdo->prepare('UPDATE content_reports SET resolved=1, resolved_at=NOW() WHERE id=?');
$st->execute([$id]);


echo json_encode(['ok'=>true, 'updated'=>$st->rowCount()]);
} catch (Throwable $e) {
http_response_code(500);
echo json_encode(['ok'=>false,'error'=>'SERVER','message'=>$e->getMessage()]);
}
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';


$pdo = db();
$me = require_auth();
require_csrf_header();


$type = (string)($_POST['content_type'] ?? $_GET['content_type'] ?? 'post');
$id = (int)($_POST['content_id'] ?? $_GET['content_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? '')) ?: null;


if (!in_array($type, ['post','thread','message'], true) || $id <= 0) {
http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'BAD_REQUEST']);
exit;
}


try {
$st = $pdo->prepare("INSERT INTO content_reports (content_type, content_id, reporter_id, reason) VALUES (?, ?, ?, ?)");
$st->execute([$type, $id, (int)$me['id'], $reason]);
echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
// Duplicate (bereits gemeldet)
if ((int)$e->errorInfo[1] === 1062) {
echo json_encode(['ok'=>true, 'duplicate'=>true]);
} else {
http_response_code(500);
echo json_encode(['ok'=>false, 'error'=>'DB', 'message'=>$e->getMessage()]);
}
}
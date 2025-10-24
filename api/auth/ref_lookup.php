<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/db.php';

$pdo = db();
$ref = trim((string)($_GET['ref'] ?? ''));

if ($ref === '') {
  echo json_encode(['ok'=>false, 'error'=>'missing_ref']);
  exit;
}

$stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE ref_code=? LIMIT 1");
$stmt->execute([$ref]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
  echo json_encode(['ok'=>true, 'id'=>(int)$user['id'], 'display_name'=>$user['display_name']]);
} else {
  echo json_encode(['ok'=>false, 'error'=>'not_found']);
}

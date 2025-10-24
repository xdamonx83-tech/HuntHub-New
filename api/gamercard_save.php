<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';

header('Content-Type: application/json; charset=utf-8');

$me = current_user(); // optional – wenn du nur eingeloggten Nutzern erlauben willst, ihre eigene Card zu speichern
$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad user']); exit; }
// Optionaler Schutz:
// if (!$me || (int)$me['id'] !== $userId) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

if (empty($_FILES['image']['tmp_name'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no image']); exit; }

$ext = '.png';
$baseDir = __DIR__ . '/../uploads/gamercards';
@mkdir($baseDir, 0775, true);

$target = $baseDir . '/u_' . $userId . $ext;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'save failed']); exit;
}

// URL zurückgeben
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$url = $APP_BASE . '/uploads/gamercards/u_' . $userId . $ext;

echo json_encode(['ok'=>true, 'url'=>$url]);

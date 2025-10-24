<?php
declare(strict_types=1);

// WICHTIG: from /api/admin/system -> eine Ebene hoch
require_once __DIR__ . '/../_bootstrap.php';

try {
  $pdo->query("SELECT 1");
  echo json_encode(['ok'=>true,'php'=>PHP_VERSION,'db'=>'ok']);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

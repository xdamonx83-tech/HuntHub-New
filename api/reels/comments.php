<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/reels.php';

try {
  $me = optional_auth();
  $id = (int)($_GET['id'] ?? 0);
  $after = (int)($_GET['after_id'] ?? 0);
  if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

  $list = reels_comments_fetch($id, 50, $after);
  echo json_encode(['ok'=>true,'items'=>$list]);
} catch (Throwable $e) {
  // niemals 500 werfen – sauber antworten
  http_response_code(200);
  echo json_encode(['ok'=>false,'error'=>'server']);
}

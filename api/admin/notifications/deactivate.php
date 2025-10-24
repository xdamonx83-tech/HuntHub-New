<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$in = in_json();
$id = (int)($in['id'] ?? 0);
if ($id<=0){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$st = $pdo->prepare("UPDATE site_broadcasts SET is_active=0 WHERE id=:id");
$st->execute([':id'=>$id]);
echo json_encode(['ok'=>true]);

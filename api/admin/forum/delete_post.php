<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$id = (int)($in['id'] ?? 0);
if ($id<=0){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

// Post entfernen + Likes + evtl. Notifications
$pdo->beginTransaction();
$pdo->prepare("DELETE FROM post_likes WHERE post_id=:id")->execute([':id'=>$id]);
$pdo->prepare("DELETE FROM posts WHERE id=:id")->execute([':id'=>$id]);
$pdo->commit();

echo json_encode(['ok'=>true]);

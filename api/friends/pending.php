<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

$pdo  = db();
$user = require_auth();

$sqlIn  = "
  SELECT f.id AS request_id, u.id, u.display_name, u.slug, u.avatar_path, f.created_at
  FROM friendships f
  JOIN users u ON u.id = f.requester_id
  WHERE f.addressee_id = ? AND f.status = 'pending'
  ORDER BY f.created_at DESC
";
$sqlOut = "
  SELECT f.id AS request_id, u.id, u.display_name, u.slug, u.avatar_path, f.created_at
  FROM friendships f
  JOIN users u ON u.id = f.addressee_id
  WHERE f.requester_id = ? AND f.status = 'pending'
  ORDER BY f.created_at DESC
";

$in  = $pdo->prepare($sqlIn);  $in->execute([(int)$user['id']]);
$out = $pdo->prepare($sqlOut); $out->execute([(int)$user['id']]);

echo json_encode(['ok'=>true, 'incoming'=>$in->fetchAll(PDO::FETCH_ASSOC), 'outgoing'=>$out->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_SLASHES);

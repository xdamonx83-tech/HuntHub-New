<?php
declare(strict_types=1);
require_once __DIR__ . '/_util.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) lfg_json(['ok'=>false,'error'=>'invalid_id'], 400);

$sql = "SELECT p.*, u.id as u_id, u.display_name, u.avatar_path, u.slug
        FROM lfg_posts p
        JOIN users u ON u.id = p.user_id
        WHERE p.id=:id AND p.visible=1 AND (p.expires_at IS NULL OR p.expires_at > NOW())";
$st = $pdo->prepare($sql);
$st->execute([':id'=>$id]);
$r = $st->fetch(PDO::FETCH_ASSOC);
if (!$r) lfg_json(['ok'=>false,'error'=>'not_found'], 404);

$post = $r;
$user = lfg_public_user($r);
unset($post['u_id'], $post['display_name'], $post['avatar_path'], $post['slug']);
lfg_json(['ok'=>true,'post'=>$post,'user'=>$user]);

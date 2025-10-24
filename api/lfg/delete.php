<?php
declare(strict_types=1);
require_once __DIR__ . '/_util.php';

$me = lfg_require_auth();
$id = lfg_int($_POST['id'] ?? null, 1);
if (!$id) lfg_json(['ok'=>false,'error'=>'invalid_id'], 400);

// ownership
$chk = $pdo->prepare("SELECT user_id FROM lfg_posts WHERE id=:id");
$chk->execute([':id'=>$id]);
$row = $chk->fetch(PDO::FETCH_ASSOC);
if (!$row || (int)$row['user_id'] !== (int)$me['id']) {
    lfg_json(['ok'=>false,'error'=>'not_owner'], 403);
}

$pdo->prepare("DELETE FROM lfg_posts WHERE id=:id")->execute([':id'=>$id]);
lfg_json(['ok'=>true]);

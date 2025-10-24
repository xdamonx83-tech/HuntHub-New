<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$in = in_json();
$id    = (int)($in['id'] ?? 0);
$key   = trim((string)($in['key'] ?? ''));
$title = trim((string)($in['title'] ?? ''));
$desc  = trim((string)($in['description'] ?? ''));
$icon  = trim((string)($in['icon'] ?? ''));
$event = trim((string)($in['rule_event'] ?? ''));
$stat  = trim((string)($in['rule_stat'] ?? ''));
$thr   = max(0, (int)($in['threshold'] ?? 0));
$pts   = max(0, (int)($in['points'] ?? 0));
$active= !empty($in['is_active']) ? 1 : 0;

if ($key==='' || $title==='' || $event==='' || $stat==='') {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit;
}
if (!preg_match('/^[a-z0-9_:-]{3,64}$/i', $key)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_key']); exit;
}

if ($id>0) {
  $st = $pdo->prepare("UPDATE achievements SET `key`=:k, title=:t, description=:d, icon=:i, rule_event=:e, rule_stat=:s, threshold=:th, points=:p, is_active=:a WHERE id=:id");
  $st->execute([':k'=>$key,':t'=>$title,':d'=>$desc,':i'=>$icon,':e'=>$event,':s'=>$stat,':th'=>$thr,':p'=>$pts,':a'=>$active, ':id'=>$id]);
} else {
  // Key unique?
  $chk = $pdo->prepare("SELECT 1 FROM achievements WHERE `key`=? LIMIT 1");
  $chk->execute([$key]);
  if ($chk->fetch()) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'key_exists']); exit; }

  $st = $pdo->prepare("INSERT INTO achievements (`key`,title,description,icon,rule_event,rule_stat,threshold,points,is_active) VALUES (:k,:t,:d,:i,:e,:s,:th,:p,:a)");
  $st->execute([':k'=>$key,':t'=>$title,':d'=>$desc,':i'=>$icon,':e'=>$event,':s'=>$stat,':th'=>$thr,':p'=>$pts,':a'=>$active]);
  $id = (int)$pdo->lastInsertId();
}

echo json_encode(['ok'=>true,'id'=>$id]);

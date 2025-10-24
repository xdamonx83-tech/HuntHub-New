<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

// Eingaben: user_id ODER email; achievement_id ODER key
$in = in_json();
$uid   = (int)($in['user_id'] ?? 0);
$email = trim((string)($in['email'] ?? ''));
$aid   = (int)($in['achievement_id'] ?? 0);
$akey  = trim((string)($in['key'] ?? ''));

if (!$uid && $email===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'need_user']); exit; }
if (!$aid && $akey===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'need_achievement']); exit; }

if (!$uid) {
  $st = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();
  if (!$u){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'user_not_found']); exit; }
  $uid = (int)$u['id'];
}

if (!$aid) {
  $st = $pdo->prepare("SELECT id, points FROM achievements WHERE `key`=? LIMIT 1");
  $st->execute([$akey]);
  $a = $st->fetch();
  if (!$a){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'achievement_not_found']); exit; }
  $aid = (int)$a['id'];
  $pts = (int)$a['points'];
} else {
  $st = $pdo->prepare("SELECT points FROM achievements WHERE id=? LIMIT 1");
  $st->execute([$aid]);
  $pts = (int)($st->fetchColumn() ?: 0);
}

$pdo->beginTransaction();
// Eintragen, falls noch nicht vorhanden
$pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?,?)")->execute([$uid, $aid]);
// Punkte addieren (falls definiert)
if ($pts > 0) {
  $pdo->prepare("INSERT IGNORE INTO user_stats (user_id) VALUES (?)")->execute([$uid]);
  $pdo->prepare("UPDATE user_stats SET points_total = points_total + :p, updated_at = NOW() WHERE user_id=:u")->execute([':p'=>$pts, ':u'=>$uid]);
}
$pdo->commit();

echo json_encode(['ok'=>true,'user_id'=>$uid,'achievement_id'=>$aid,'points_added'=>$pts]);

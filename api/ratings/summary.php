<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../auth/db.php';
try {
$pdo = db();
$uid = (int)($_GET['user_id'] ?? 0);
if ($uid <= 0) throw new RuntimeException('user_id fehlt');
$st = $pdo->prepare("SELECT COUNT(*) cnt, AVG(play) a, AVG(friendly) b, AVG(helpful) c, AVG((play+friendly+helpful)/3.0) o FROM user_ratings WHERE ratee_id=?");
$st->execute([$uid]);
$r = $st->fetch(PDO::FETCH_ASSOC) ?: ['cnt'=>0,'a'=>0,'b'=>0,'c'=>0,'o'=>0];
$cnt=(int)$r['cnt']; $o=$cnt?(float)$r['o']:0.0; $stars=$cnt?max(1,min(6,(int)round($o))):0;
echo json_encode(['ok'=>true,'summary'=>[
'count'=>$cnt,'avg_play'=>(float)$r['a'],'avg_friendly'=>(float)$r['b'],'avg_helpful'=>(float)$r['c'],'avg_overall_exact'=>$o,'stars_rounded'=>$stars
]]);
} catch (Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
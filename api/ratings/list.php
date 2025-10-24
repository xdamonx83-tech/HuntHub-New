<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../auth/db.php';
try {
$pdo = db();
$ratee=(int)($_GET['ratee_id']??0); if($ratee<=0) throw new RuntimeException('ratee_id fehlt');
$limit = max(1,min(50,(int)($_GET['limit']??10))); $offset=max(0,(int)($_GET['offset']??0));
$st=$pdo->prepare("SELECT r.id, r.rater_id, r.play, r.friendly, r.helpful, r.comment, r.created_at, r.updated_at,
u.display_name, u.slug, u.avatar_path,
((r.play+r.friendly+r.helpful)/3.0) AS score_exact,
ROUND((r.play+r.friendly+r.helpful)/3.0) AS score_stars
FROM user_ratings r JOIN users u ON u.id=r.rater_id
WHERE r.ratee_id=? ORDER BY r.updated_at DESC LIMIT ? OFFSET ?");
$st->bindValue(1,$ratee,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->bindValue(3,$offset,PDO::PARAM_INT);
$st->execute();
echo json_encode(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)?:[]]);
} catch (Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
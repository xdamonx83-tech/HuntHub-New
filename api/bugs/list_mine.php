<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
set_error_handler(static function($s,$m,$f,$l){ throw new ErrorException($m,0,$s,$f,$l); });

try{
  $me  = require_auth();
  $pdo = db();

  $owner = bug_require_owner_col($pdo);

  $hasXp    = db_has_col($pdo,'bugs','xp_awarded');
  $hasBadge = db_has_col($pdo,'bugs','badge_code');

  $select = "id,title,status,priority,created_at,updated_at";
  $select .= $hasXp    ? ", xp_awarded" : ", 0 AS xp_awarded";
  $select .= $hasBadge ? ", badge_code" : ", '' AS badge_code";

  $sql = "SELECT $select FROM bugs WHERE `$owner` = ? ORDER BY updated_at DESC LIMIT 200";
  $st  = $pdo->prepare($sql);
  $st->execute([(int)$me['id']]);

  json_ok(['items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server','hint'=>$e->getMessage()]);
}

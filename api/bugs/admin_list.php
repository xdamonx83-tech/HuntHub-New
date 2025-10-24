<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

require_admin();
$pdo = db();

$status   = $_GET['status']   ?? '';
$priority = $_GET['priority'] ?? '';

$owner = bug_require_owner_col($pdo);
$hasXp    = db_has_col($pdo,'bugs','xp_awarded');
$hasBadge = db_has_col($pdo,'bugs','badge_code');

$sel = "b.id,b.`$owner` AS owner_id,b.title,b.status,b.priority,b.created_at,b.updated_at,u.display_name AS username";
$sel .= $hasXp    ? ", b.xp_awarded" : ", 0 AS xp_awarded";
$sel .= $hasBadge ? ", b.badge_code" : ", '' AS badge_code";

$q="SELECT $sel FROM bugs b LEFT JOIN users u ON u.id = b.`$owner` WHERE 1";
$p=[];
if(in_array($status,['open','waiting','closed'],true)){ $q.=" AND b.status=?";   $p[]=$status; }
if(in_array($priority,['low','medium','high','urgent'],true)){ $q.=" AND b.priority=?"; $p[]=$priority; }
$q.=" ORDER BY FIELD(b.status,'open','waiting','closed'), FIELD(b.priority,'urgent','high','medium','low'), b.updated_at DESC LIMIT 500";

$st=$pdo->prepare($q); $st->execute($p);
json_ok(['items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);

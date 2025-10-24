<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$me  = require_auth();
$pdo = db();

$id  = (int)($_GET['id'] ?? 0);
$owner = bug_require_owner_col($pdo);

$hasXp    = db_has_col($pdo,'bugs','xp_awarded');
$hasBadge = db_has_col($pdo,'bugs','badge_code');

$select = "b.id,b.`$owner` AS owner_id,b.title,b.description,b.status,b.priority,b.created_at,b.updated_at";
$select .= $hasXp    ? ", b.xp_awarded" : ", 0 AS xp_awarded";
$select .= $hasBadge ? ", b.badge_code" : ", '' AS badge_code";

$st=$pdo->prepare("SELECT $select FROM bugs b WHERE b.id=?");
$st->execute([$id]);
$bug=$st->fetch(PDO::FETCH_ASSOC);
if(!$bug) json_err('not_found',404);

// Zugriff: Besitzer oder Admin
$isAdmin = in_array($me['role']??'', ['admin','moderator'], true);
if ((int)$bug['owner_id'] !== (int)$me['id'] && !$isAdmin) json_err('forbidden',403);

// Kommentare/Anhänge
$cs=$pdo->prepare("SELECT id,bug_id,user_id,is_admin,message,created_at FROM bug_comments WHERE bug_id=? ORDER BY id ASC");
$cs->execute([$id]);
$fs=$pdo->prepare("SELECT id,bug_id,user_id,kind,path,mime,size,created_at FROM bug_attachments WHERE bug_id=? ORDER BY id ASC");
$fs->execute([$id]);

json_ok(['bug'=>$bug,'comments'=>$cs->fetchAll(PDO::FETCH_ASSOC),'attachments'=>$fs->fetchAll(PDO::FETCH_ASSOC)]);

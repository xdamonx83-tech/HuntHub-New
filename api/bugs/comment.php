<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$me=require_auth();
verify_csrf_if_available();
$pdo=db();

$bugId=(int)($_POST['bug_id']??0);
$msg=trim($_POST['message']??'');
if($bugId<=0||$msg==='') json_err('missing_fields');

$st=$pdo->prepare("SELECT user_id FROM bugs WHERE id=?"); $st->execute([$bugId]);
$bug=$st->fetch(PDO::FETCH_ASSOC);
if(!$bug) json_err('not_found',404);
$isAdmin = in_array($me['role']??'', ['admin','moderator'], true);
if ((int)$bug['user_id'] !== (int)$me['id'] && !$isAdmin) json_err('forbidden',403);

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO bug_comments (bug_id,user_id,is_admin,message) VALUES (?,?,?,?)")
    ->execute([$bugId,(int)$me['id'],$isAdmin?1:0,$msg]);
$pdo->prepare("UPDATE bugs SET updated_at=NOW() WHERE id=?")->execute([$bugId]);
$pdo->commit();

json_ok();

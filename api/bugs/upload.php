<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$me = require_auth();
verify_csrf_if_available();
$pdo = db();

$bugId = (int)($_POST['bug_id'] ?? 0);
if ($bugId<=0) json_err('missing_bug_id');

$st=$pdo->prepare("SELECT user_id FROM bugs WHERE id=?");
$st->execute([$bugId]);
$bug=$st->fetch(PDO::FETCH_ASSOC);
if (!$bug) json_err('not_found',404);

$isAdmin = in_array($me['role']??'', ['admin','moderator'], true);
if ((int)$bug['user_id'] !== (int)$me['id'] && !$isAdmin) json_err('forbidden',403);

if (empty($_FILES['file']['tmp_name'])) json_err('upload_missing');

$allowed=['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm','video/quicktime'];
$f=$_FILES['file'];
$mime=@mime_content_type($f['tmp_name'])?:($f['type']??'');
$size=(int)($f['size']??0);
if ($size>50*1024*1024) json_err('file_too_large');
if (!in_array($mime,$allowed,true)) json_err('file_type_not_allowed');

$dirAbs=__DIR__.'/../../uploads/bugs/';
if(!is_dir($dirAbs)) @mkdir($dirAbs,0775,true);
$ext=pathinfo($f['name']??'',PATHINFO_EXTENSION)?:'bin';
$fname="bug{$bugId}_".bin2hex(random_bytes(6)).".$ext";
$abs=$dirAbs.$fname;
$rel="/uploads/bugs/$fname";

if (!move_uploaded_file($f['tmp_name'],$abs)) json_err('move_failed',500);

$kind = str_starts_with($mime,'image/') ? 'image' : (str_starts_with($mime,'video/') ? 'video' : 'other');

$st=$pdo->prepare("INSERT INTO bug_attachments (bug_id,user_id,kind,path,mime,size) VALUES (?,?,?,?,?,?)");
$st->execute([$bugId,(int)$me['id'],$kind,$rel,$mime,$size]);

json_ok(['path'=>$rel,'kind'=>$kind]);

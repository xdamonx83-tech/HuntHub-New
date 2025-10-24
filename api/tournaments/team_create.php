<?php
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','1');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';


$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (function_exists('require_user')) { $me = require_user(); }
elseif (function_exists('require_login')) { $me = require_login(); }
elseif (function_exists('require_auth')) { $me = require_auth(); }
else { $me = require_admin(); }
$uid = (int)($me['id'] ?? 0);


$token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
if (function_exists('verify_csrf') && !verify_csrf($pdo,(string)$token)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }


$tid = (int)($_POST['tournament_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
if ($tid<=0 || $name===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }


// Prüfen, ob user schon Team hat
$st = $pdo->prepare('SELECT 1 FROM tournament_teams tt JOIN tournament_team_members m ON m.tournament_team_id=tt.id WHERE tt.tournament_id=? AND m.user_id=? LIMIT 1');
$st->execute([$tid, $uid]);
if ($st->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'already_in_team']); exit; }


// Teamgröße ermitteln
$ts = 3; $st2=$pdo->prepare('SELECT team_size FROM tournaments WHERE id=?'); $st2->execute([$tid]); $ts=(int)($st2->fetchColumn() ?: 3);


// Join-Code
$joinCode = strtoupper(substr(bin2hex(random_bytes(4)),0,6));


// Spalten prüfen
$cols=[]; foreach($pdo->query('SHOW COLUMNS FROM tournament_teams',PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=true; }
$teamData=['tournament_id'=>$tid,'name'=>$name,'captain_user_id'=>$uid]; if(isset($cols['join_code'])) $teamData['join_code']=$joinCode;


$keys=array_keys($teamData); $place=implode(',',array_fill(0,count($keys),'?'));
$pdo->prepare('INSERT INTO tournament_teams ('.implode(',',$keys).') VALUES ('.$place.')')->execute(array_values($teamData));
$teamId = (int)$pdo->lastInsertId();


// Mitglied eintragen
$colsM=[]; foreach($pdo->query('SHOW COLUMNS FROM tournament_team_members',PDO::FETCH_ASSOC) as $c){ $colsM[$c['Field']]=true; }
$mem = ['tournament_team_id'=>$teamId,'user_id'=>$uid]; if(isset($colsM['role'])) $mem['role']='captain';
$k=array_keys($mem); $p=implode(',',array_fill(0,count($k),'?'));
$pdo->prepare('INSERT INTO tournament_team_members ('.implode(',',$k).') VALUES ('.$p.')')->execute(array_values($mem));


echo json_encode(['ok'=>true,'team_id'=>$teamId,'join_code'=>$joinCode], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>
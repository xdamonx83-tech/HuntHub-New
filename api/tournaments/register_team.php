<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_auth();
verify_csrf_if_available();
$pdo = db();


$tournament_id = (int)($_POST['tournament_id'] ?? 0);
$name = trim((string)($_POST['team_name'] ?? ''));
$platform = (string)($_POST['platform'] ?? 'pc');
if ($tournament_id<=0 || $name==='') json_err('missing_fields');


$t = get_tournament($pdo,$tournament_id);
if (!$t) json_err('not_found',404);
if (!in_array($t['status'], ['open','running','locked'], true)) json_err('registration_closed', 409);


// optional: Max Teams prüfen
if (!empty($t['max_teams'])) {
$cnt = $pdo->prepare('SELECT COUNT(*) FROM tournament_teams WHERE tournament_id=?');
$cnt->execute([$tournament_id]);
if ((int)$cnt->fetchColumn() >= (int)$t['max_teams']) json_err('tournament_full', 409);
}


$pdo->beginTransaction();
$st = $pdo->prepare('INSERT INTO tournament_teams (tournament_id,name,captain_user_id,platform,status) VALUES (?,?,?,?,"confirmed")');
$st->execute([$tournament_id,$name,(int)$me['id'],$platform]);
$teamId = (int)$pdo->lastInsertId();


$st2 = $pdo->prepare('INSERT INTO tournament_team_members (tournament_team_id,user_id,role,accepted) VALUES (?,?,"captain",1)');
$st2->execute([$teamId,(int)$me['id']]);
$pdo->commit();


json_ok(['team_id'=>$teamId]);
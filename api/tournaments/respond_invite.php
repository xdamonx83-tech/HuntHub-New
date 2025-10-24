<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_auth();
verify_csrf_if_available();
$pdo = db();


$invite_id = (int)($_POST['invite_id'] ?? 0);
$action = (string)($_POST['action'] ?? ''); // 'accept' | 'decline'
if ($invite_id<=0 || !in_array($action,['accept','decline'],true)) json_err('bad_params');


$st = $pdo->prepare('SELECT i.*, t.tournament_id, tr.team_size FROM tournament_invites i JOIN tournament_teams t ON t.id=i.tournament_team_id JOIN tournaments tr ON tr.id=t.tournament_id WHERE i.id=?');
$st->execute([$invite_id]);
$inv = $st->fetch(PDO::FETCH_ASSOC);
if (!$inv || (int)$inv['invited_user_id'] !== (int)$me['id']) json_err('not_found',404);


if ($action==='decline') {
$pdo->prepare('UPDATE tournament_invites SET status="declined" WHERE id=?')->execute([$invite_id]);
json_ok();
}


// accept -> Slots prüfen
$cur = $pdo->prepare('SELECT COUNT(*) FROM tournament_team_members WHERE tournament_team_id=?');
$cur->execute([(int)$inv['tournament_team_id']]);
if ((int)$cur->fetchColumn() >= (int)$inv['team_size']) json_err('team_full',409);


$pdo->beginTransaction();
$pdo->prepare('UPDATE tournament_invites SET status="accepted" WHERE id=?')->execute([$invite_id]);
$pdo->prepare('INSERT IGNORE INTO tournament_team_members (tournament_team_id,user_id,role,accepted) VALUES (?,?,"member",1)')
->execute([(int)$inv['tournament_team_id'], (int)$me['id']]);
$pdo->commit();


json_ok();
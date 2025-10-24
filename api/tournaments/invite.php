<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_auth();
verify_csrf_if_available();
$pdo = db();


$team_id = (int)($_POST['team_id'] ?? 0);
$ids = $_POST['user_ids'] ?? [];
if ($team_id<=0 || !is_array($ids) || !$ids) json_err('missing_fields');


// Captain‑Check
$st = $pdo->prepare('SELECT t.tournament_id, t.captain_user_id, tr.team_size FROM tournament_teams t JOIN tournaments tr ON tr.id=t.tournament_id WHERE t.id=?');
$st->execute([$team_id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) json_err('team_not_found',404);
if ((int)$row['captain_user_id'] !== (int)$me['id']) json_err('not_captain',403);


// Teamgröße prüfen
$cur = $pdo->prepare('SELECT COUNT(*) FROM tournament_team_members WHERE tournament_team_id=?');
$cur->execute([$team_id]);
$slotsLeft = (int)$row['team_size'] - (int)$cur->fetchColumn();
if ($slotsLeft <= 0) json_err('team_full',409);


$ids = array_slice(array_unique(array_map('intval',$ids)), 0, $slotsLeft);
$code = function(){ return substr(strtoupper(bin2hex(random_bytes(6))),0,12); };


$ins = $pdo->prepare('INSERT INTO tournament_invites (tournament_team_id,invited_user_id,invited_by_user_id,code,status) VALUES (?,?,?,?,"pending")');


foreach ($ids as $uid) {
try {
$ins->execute([$team_id,$uid,(int)$me['id'],$code()]);
notify_safe($uid,'tournament_invite',[ 'team_id'=>$team_id ]);
} catch (Throwable $e) { /* duplicate invite -> ignore */ }
}
json_ok();
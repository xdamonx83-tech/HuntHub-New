<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_auth();
verify_csrf_if_available();
$pdo = db();


$tournament_id = (int)($_POST['tournament_id'] ?? 0);
$team_id = (int)($_POST['team_id'] ?? 0);
$kills = max(0, (int)($_POST['kills'] ?? 0));
$boss_kills = max(0, (int)($_POST['boss_kills'] ?? 0));
$tokens = max(0, min(2, (int)($_POST['tokens'] ?? 0)));
$gauntlet = !empty($_POST['gauntlet']) ? 1 : 0;
$deaths = max(0, (int)($_POST['deaths'] ?? 0));
if ($tournament_id<=0 || $team_id<=0) json_err('missing_fields');


if (!ensure_team_member($pdo,$team_id,(int)$me['id'])) json_err('not_team_member',403);


$t = get_tournament($pdo,$tournament_id);
if (!$t) json_err('not_found',404);
if (!can_submit_run($t)) json_err('tournament_closed',409);


$sc = json_decode($t['scoring_json'] ?? '[]', true) ?: [];
$raw = compute_points_arr(['kills'=>$kills,'boss_kills'=>$boss_kills,'tokens'=>$tokens,'gauntlet'=>$gauntlet,'deaths'=>$deaths], $sc);


// Screenshot ist Pflicht (kannst du optional machen)
if (empty($_FILES['screenshot']['tmp_name'])) json_err('screenshot_required',400);
$baseAbs = __DIR__ . '/../../uploads/tournaments/' . $tournament_id . '/runs/';
$baseRel = '/uploads/tournaments/' . $tournament_id . '/runs/';
$pathRel = store_upload_image_to($baseAbs, $baseRel, $_FILES['screenshot']);


$st = $pdo->prepare('INSERT INTO tournament_runs (tournament_id,tournament_team_id,match_at,kills,boss_kills,tokens,gauntlet,deaths,raw_points,screenshot_path,status) VALUES (?,?,?,?,?,?,?,?,?,? ,"submitted")');
$st->execute([$tournament_id,$team_id,null,$kills,$boss_kills,$tokens,$gauntlet,$deaths,$raw,$pathRel]);
$runId = (int)$pdo->lastInsertId();


// Admin benachrichtigen
notify_safe((int)$t['created_by'], 'tournament_run_submitted', ['tournament_id'=>$tournament_id,'team_id'=>$team_id,'run_id'=>$runId]);


json_ok(['run'=>['id'=>$runId,'raw_points'=>$raw,'status'=>'submitted','screenshot'=>$pathRel]]);
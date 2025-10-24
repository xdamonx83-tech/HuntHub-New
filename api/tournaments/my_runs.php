<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_auth();
$pdo = db();


$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if ($tournament_id<=0) json_err('bad_params');


$st = $pdo->prepare('SELECT r.* FROM tournament_runs r JOIN tournament_team_members m ON m.tournament_team_id=r.tournament_team_id WHERE r.tournament_id=? AND m.user_id=? ORDER BY r.created_at DESC');
$st->execute([$tournament_id, (int)$me['id']]);
json_ok(['items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
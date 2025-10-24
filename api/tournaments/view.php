<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) json_err('bad_id');


$t = get_tournament($pdo,$id);
if (!$t) json_err('not_found',404);


// Teams kurz anreißen
$teams = $pdo->prepare('SELECT id,name,platform,status,captain_user_id FROM tournament_teams WHERE tournament_id=? ORDER BY name');
$teams->execute([$id]);


json_ok(['tournament'=>$t,'teams'=>$teams->fetchAll(PDO::FETCH_ASSOC)]);
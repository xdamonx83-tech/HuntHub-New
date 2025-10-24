<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$pdo = db();
$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if ($tournament_id<=0) json_err('bad_params');


// Top‑N Läufe je Team summieren
$sql = <<<SQL
WITH ranked AS (
SELECT r.tournament_team_id, r.id AS run_id, r.raw_points,
ROW_NUMBER() OVER (PARTITION BY r.tournament_team_id ORDER BY r.raw_points DESC, r.id ASC) AS rn
FROM tournament_runs r
WHERE r.tournament_id = ? AND r.status = 'approved'
)
SELECT tt.id AS team_id, tt.name AS team_name, SUM(ranked.raw_points) AS total_points
FROM ranked
JOIN tournament_teams tt ON tt.id = ranked.tournament_team_id
JOIN tournaments t ON t.id = ?
WHERE ranked.rn <= t.best_runs
GROUP BY tt.id, tt.name
ORDER BY total_points DESC, team_name ASC
SQL;
$st = $pdo->prepare($sql);
$st->execute([$tournament_id,$tournament_id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
json_ok(['items'=>$rows]);
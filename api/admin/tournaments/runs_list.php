<?php
set_exception_handler(function(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()]); });


require_once __DIR__ . '/../../../auth/db.php';
require_once __DIR__ . '/../../../auth/guards.php';
require_once __DIR__ . '/../../../auth/csrf.php';
$cfg = require __DIR__ . '/../../../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');


$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_admin();


$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '[]', true) ?: [];
$token = $in['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (function_exists('verify_csrf') && !verify_csrf($pdo,(string)$token)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }


$statusFilter = (string)($in['status'] ?? 'pending'); // pending|approved|rejected|flagged|all
$limit = max(1, min(200, (int)($in['limit'] ?? 100)));
$offset= max(0, (int)($in['offset'] ?? 0));


// Spalten erkennen
$colsRuns = [];
foreach ($pdo->query('SHOW COLUMNS FROM tournament_runs', PDO::FETCH_ASSOC) as $c) { $colsRuns[$c['Field']] = $c; }


$pointsSel = isset($colsRuns['raw_points']) ? 'r.raw_points' : (isset($colsRuns['points_total']) ? 'r.points_total' : (isset($colsRuns['points']) ? 'r.points' : '0'));
$shotSel = isset($colsRuns['screenshot_rel']) ? 'r.screenshot_rel' : (isset($colsRuns['screenshot_path']) ? 'r.screenshot_path' : (isset($colsRuns['screenshot']) ? 'r.screenshot' : (isset($colsRuns['image_path'])?'r.image_path':'NULL')));
$createdSel= isset($colsRuns['created_at']) ? 'r.created_at' : (isset($colsRuns['created']) ? 'r.created' : (isset($colsRuns['created_on']) ? 'r.created_on' : 'NULL'));
$statusSel = isset($colsRuns['status']) ? 'r.status' : "'pending'";
$killsSel = isset($colsRuns['kills']) ? 'r.kills' : 'NULL';
$bossSel = isset($colsRuns['bosses']) ? 'r.bosses' : (isset($colsRuns['boss_kills'])? 'r.boss_kills' : 'NULL');
$tokenSel = isset($colsRuns['tokens']) ? 'r.tokens' : (isset($colsRuns['token'])? 'r.token' : 'NULL');
$gaunSel = isset($colsRuns['gauntlet']) ? 'r.gauntlet' : 'NULL';
$deathSel = isset($colsRuns['deaths']) ? 'r.deaths' : (isset($colsRuns['deads'])? 'r.deads' : 'NULL');


$where = '1=1';
$params = [];
if ($statusFilter !== 'all' && isset($colsRuns['status'])) {
// Numerisch oder ENUM/VARCHAR filtern
$type = strtolower((string)($colsRuns['status']['Type'] ?? ''));
if (str_contains($type,'int')) {
$map = ['pending'=>0,'approved'=>1,'rejected'=>2,'flagged'=>3];
$val = $map[$statusFilter] ?? 0;
$where .= ' AND r.status = ?'; $params[] = $val;
} else {
$where .= ' AND r.status = ?'; $params[] = $statusFilter;
}
}


$sql = "SELECT r.id, r.tournament_team_id, $pointsSel AS pts, $shotSel AS shot, $createdSel AS created_at, $statusSel AS status,
$killsSel AS kills, $bossSel AS bosses, $tokenSel AS tokens, $gaunSel AS gauntlet, $deathSel AS deaths,
tt.name AS team_name, t.name AS tournament_name
FROM tournament_runs r
LEFT JOIN tournament_teams tt ON tt.id = r.tournament_team_id
LEFT JOIN tournaments t ON t.id = r.tournament_id
WHERE $where
ORDER BY r.id DESC
LIMIT $limit OFFSET $offset";
$st = $pdo->prepare($sql); $st->execute($params);
$rows = [];
while($r = $st->fetch(PDO::FETCH_ASSOC)){
$shot = (string)($r['shot'] ?? '');
if ($shot!=='' && $shot[0] !== '/') $shot = '/'.$shot;
$rows[] = [
'id' => (int)$r['id'],
'team' => $r['team_name'] ?? '',
'tournament' => $r['tournament_name'] ?? '',
'points' => (int)($r['pts'] ?? 0),
'kills' => isset($r['kills']) ? (int)$r['kills'] : null,
'bosses'=> isset($r['bosses'])? (int)$r['bosses'] : null,
'tokens'=> isset($r['tokens'])? (int)$r['tokens'] : null,
'gauntlet'=> isset($r['gauntlet'])? (int)$r['gauntlet'] : null,
'deaths'=> isset($r['deaths'])? (int)$r['deaths'] : null,
'status'=> $r['status'] ?? 'pending',
'created_at'=> $r['created_at'] ?? '',
'screenshot_url'=> $shot ? ($APP_BASE.$shot) : null,
];
}


echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);


?>
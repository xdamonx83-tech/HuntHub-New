<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_admin_user();
verify_csrf_if_available();
$pdo = db();


$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) json_err('bad_id');


$fields = ['name','description','platform','team_size','format','rules_text','prizes_text','best_runs','max_teams','scoring_json','starts_at','ends_at'];
$set = [];$vals=[];
foreach ($fields as $f) {
if (array_key_exists($f, $_POST)) { $set[] = "$f = ?"; $vals[] = $_POST[$f]; }
}
if (!$set) json_err('no_changes');
$vals[] = $id;
$pdo->prepare('UPDATE tournaments SET '.implode(',', $set).' WHERE id=?')->execute($vals);
json_ok(['id'=>$id]);
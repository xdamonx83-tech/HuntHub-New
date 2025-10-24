<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_auth();
verify_csrf_if_available();
$pdo = db();


$tournament_id = (int)($_POST['tournament_id'] ?? 0);
$run_id = isset($_POST['run_id']) && $_POST['run_id'] !== '' ? (int)$_POST['run_id'] : null;
$type = (string)($_POST['type'] ?? 'score');
$message = trim((string)($_POST['message'] ?? ''));
if ($tournament_id<=0 || $message==='') json_err('missing_fields');


$evidence = null;
if (!empty($_FILES['evidence']['tmp_name'])) {
$baseAbs = __DIR__ . '/../../uploads/tournaments/' . $tournament_id . '/disputes/';
$baseRel = '/uploads/tournaments/' . $tournament_id . '/disputes/';
$evidence = store_upload_image_to($baseAbs, $baseRel, $_FILES['evidence']);
}


$st = $pdo->prepare('INSERT INTO tournament_disputes (tournament_id,run_id,created_by,type,message,evidence_path,status) VALUES (?,?,?,?,?,? ,"open")');
$st->execute([$tournament_id,$run_id,(int)$me['id'],$type,$message,$evidence]);
json_ok();
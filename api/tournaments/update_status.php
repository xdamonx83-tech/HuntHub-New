<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me = require_admin_user();
verify_csrf_if_available();
$pdo = db();


$id = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? 'draft');
if ($id<=0 || !in_array($status, ['draft','open','running','locked','scoring','finished'], true)) json_err('bad_params');
$pdo->prepare('UPDATE tournaments SET status=? WHERE id=?')->execute([$status,$id]);
json_ok(['id'=>$id,'status'=>$status]);
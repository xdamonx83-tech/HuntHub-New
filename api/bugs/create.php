<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$me = require_auth();
verify_csrf_if_available();
$pdo = db();

$title = trim($_POST['title'] ?? '');
$desc  = trim($_POST['description'] ?? '');
if ($title==='' || $desc==='') json_err('missing_fields');

$owner = bug_require_owner_col($pdo);

// Nur die existierenden Spalten setzen; Status/Prio haben Defaults
$sql = "INSERT INTO bugs (`$owner`, title, description) VALUES (?,?,?)";
$st  = $pdo->prepare($sql);
$st->execute([(int)$me['id'],$title,$desc]);

json_ok(['id'=>(int)$pdo->lastInsertId()]);

<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');


try {
$me = require_auth();
$pdo = db();


$need = [
'bugs' => ['id','title','description','status','priority','created_at','updated_at'],
'bug_comments' => ['id','bug_id','user_id','is_admin','message','created_at'],
'bug_attachments' => ['id','bug_id','user_id','kind','path','mime','size','created_at'],
];


$missing = [];
foreach ($need as $tbl => $cols) {
$st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
$st->execute([$tbl]);
if (!(int)$st->fetchColumn()) { $missing[$tbl] = 'TABLE MISSING'; continue; }


$st = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
$st->execute([$tbl]);
$have = array_map('strtolower', $st->fetchAll(PDO::FETCH_COLUMN, 0));
$miss = array_values(array_filter($cols, fn($c)=>!in_array(strtolower($c), $have, true)));
if ($miss) $missing[$tbl] = $miss;
}


// Spezial: Owner‑Spalte in bugs erkennen
$owner = bug_owner_col($pdo);
if ($owner === '') {
$missing['bugs_owner_column'] = 'missing (expected one of user_id/created_by/reporter_id/owner_id/author_id/uid)';
} else {
$missing['bugs_owner_column'] = $owner; // dokumentieren, welche Spalte genutzt wird
}


echo json_encode(['ok'=>true,'missing'=>$missing], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
http_response_code(500);
echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
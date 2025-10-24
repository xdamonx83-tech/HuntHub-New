<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');
set_exception_handler(function(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()]); });


require_once __DIR__ . '/../../../auth/db.php';
require_once __DIR__ . '/../../../auth/guards.php';
require_once __DIR__ . '/../../../auth/csrf.php';


$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); require_admin();
$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$token = $in['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (function_exists('verify_csrf') && !verify_csrf($pdo,(string)$token)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }


$id = (int)($in['id'] ?? 0);
$want = (string)($in['status'] ?? 'approved'); // approved|rejected|pending|flagged
$reason = trim((string)($in['reason'] ?? ''));
if ($id<=0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }


// Spalten prüfen
$cols=[]; foreach($pdo->query('SHOW COLUMNS FROM tournament_runs',PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=$c; }
if (!isset($cols['status'])) { echo json_encode(['ok'=>false,'error'=>'no_status_column']); exit; }


$type = strtolower((string)($cols['status']['Type'] ?? ''));
$val = $want;
if (str_contains($type,'int')){
$map=['pending'=>0,'approved'=>1,'rejected'=>2,'flagged'=>3];
$val = $map[$want] ?? 0;
}


$set = 'status = ?'; $params = [$val];
if (isset($cols['mod_comment']) && $reason!=='') { $set .= ', mod_comment = ?'; $params[] = $reason; }
$params[] = $id;
$pdo->prepare("UPDATE tournament_runs SET $set WHERE id=?")->execute($params);


echo json_encode(['ok'=>true,'id'=>$id,'status_set'=>$val]);
?>
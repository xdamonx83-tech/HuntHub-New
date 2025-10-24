<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$pdo = db();


$status = isset($_GET['status']) && $_GET['status'] !== '' ? (string)$_GET['status'] : null;
$platform = isset($_GET['platform']) && $_GET['platform'] !== '' ? (string)$_GET['platform'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(1, min(50, (int)($_GET['per_page'] ?? 12)));
$off = ($page-1)*$per_page;


$w = []; $v = [];
if ($status && in_array($status,['draft','open','running','locked','scoring','finished'],true)) { $w[]='status=?'; $v[]=$status; }
if ($platform && in_array($platform,['pc','xbox','ps','mixed'],true)) { $w[]='platform=?'; $v[]=$platform; }
$where = $w ? ('WHERE '.implode(' AND ',$w)) : '';


$total = (int)($pdo->query('SELECT COUNT(*) FROM tournaments '.$where)->execute($v) ? $pdo->query('SELECT FOUND_ROWS()') : 0);
$st = $pdo->prepare("SELECT id,slug,name,platform,team_size,format,status,starts_at,ends_at,best_runs FROM tournaments $where ORDER BY starts_at DESC LIMIT $per_page OFFSET $off");
$st->execute($v);
json_ok(['items'=>$st->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'per_page'=>$per_page]);
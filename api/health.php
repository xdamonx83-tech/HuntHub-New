<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/db.php';
$out = ['php'=>PHP_VERSION,'db'=>null,'users'=>null,'algos'=>password_algos()];
try { $pdo = db(); $out['db'] = 'ok'; } catch(Throwable $e){ $out['db'] = $e->getMessage(); }
if (!empty($pdo)) {
  try { $pdo->query("SELECT 1 FROM users LIMIT 1"); $out['users_table']='ok'; }
  catch(Throwable $e){ $out['users_table']=$e->getMessage(); }
}
echo json_encode($out);

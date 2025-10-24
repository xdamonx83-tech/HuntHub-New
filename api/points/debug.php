<?php
declare(strict_types=1);
header('Content-Type: application/json');

// Von /api/points/ zwei Ebenen hoch:
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../lib/points.php';

$me  = require_auth();
$pdo = db();

function dbg_tables(PDO $pdo): array {
  $out = [];
  try {
    $st = $pdo->query("SHOW TABLES");
    while ($r = $st->fetch(PDO::FETCH_NUM)) $out[] = $r[0];
  } catch (Throwable) {}
  return $out;
}
function dbg_cols(PDO $pdo, string $t): array {
  $out = [];
  try {
    $st = $pdo->query("SHOW COLUMNS FROM `$t`");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) $out[] = $r;
  } catch (Throwable) {}
  return $out;
}
function dbg_val(PDO $pdo, string $sql, array $args=[]){
  try{ $st=$pdo->prepare($sql); $st->execute($args); $v=$st->fetchColumn(); return ($v===false)?null:$v; }
  catch(Throwable $e){ return ['_err'=>$e->getMessage()]; }
}

$uid  = (int)$me['id'];
$tabs = dbg_tables($pdo);

$cand_direct = ['points','punkt','punkte','guthaben','guthabenstand','credits','credit','coins','coin','balance','saldo','xp','score'];
$cand_delta  = ['delta','diff','amount','value','points_delta','punkte_delta','change'];

$guesses = [];
foreach ($tabs as $t) {
  $cols = dbg_cols($pdo, $t);
  if (!$cols) continue;

  $names = array_map(fn($c)=>strtolower($c['Field']), $cols);
  $hasUserId = in_array('user_id', $names, true);
  $hasId     = in_array('id', $names, true);
  $isUsers   = ($t === 'users');

  // DIRECT: fester Kontostand
  foreach ($cols as $c) {
    $col = strtolower($c['Field']);
    if (!in_array($col, $cand_direct, true)) continue;

    if ($isUsers && $hasId) {
      $v = dbg_val($pdo, "SELECT `$col` FROM `users` WHERE id=?", [$uid]);
      if ($v !== null) $guesses[] = ['mode'=>'direct','table'=>$t,'column'=>$col,'where'=>'users.id','value'=>$v];
    } elseif ($hasUserId) {
      $v = dbg_val($pdo, "SELECT `$col` FROM `$t` WHERE user_id=? LIMIT 1", [$uid]);
      if ($v !== null) $guesses[] = ['mode'=>'direct','table'=>$t,'column'=>$col,'where'=>'user_id','value'=>$v];
    }
  }

  // SUM: Transaktionen
  if ($hasUserId) {
    foreach ($cols as $c) {
      $col = strtolower($c['Field']);
      if (!in_array($col, $cand_delta, true)) continue;
      $sum = dbg_val($pdo, "SELECT COALESCE(SUM(`$col`),0) FROM `$t` WHERE user_id=?", [$uid]);
      if ($sum !== null) $guesses[] = ['mode'=>'sum','table'=>$t,'column'=>$col,'where'=>'user_id','value'=>$sum];
    }
  }
}

echo json_encode([
  'ok'=>true,
  'user_id'=>$uid,
  'lib_balance'=>get_user_points($pdo, $uid),
  'guesses'=>$guesses,
  'paths'=>[
    'guards'=>realpath(__DIR__ . '/../../auth/guards.php'),
    'db'=>realpath(__DIR__ . '/../../auth/db.php'),
    'points'=>realpath(__DIR__ . '/../../lib/points.php'),
  ],
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

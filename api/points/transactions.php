<?php
declare(strict_types=1);
header('Content-Type: application/json');
ini_set('display_errors','0');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';

$me  = require_auth();
$pdo = db();

/* ---------------- helpers ---------------- */
function jerr(string $msg, int $code=400): never {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function table_exists(PDO $pdo, string $t): bool {
  try { return (bool)$pdo->query("SHOW TABLES LIKE ".$pdo->quote($t))->fetchColumn(); }
  catch (Throwable) { return false; }
}
function cols(PDO $pdo, string $t): array {
  try { return $pdo->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN, 0) ?: []; }
  catch (Throwable) { return []; }
}
function jdecode(?string $s): array {
  if (!$s) return [];
  $a = json_decode($s, true);
  return is_array($a) ? $a : [];
}

/* --------------- paging ------------------ */
$limit  = max(1, min(100, (int)($_GET['limit'] ?? 25)));
$cursor = isset($_GET['cursor']) ? (int)$_GET['cursor'] : null;

/* --------------- choose table ------------- */
$candidatesPref = [
  // Bevorzugt „Ledger“-Tabellen mit Ausgaben
  'points_transactions','transactions','user_points_log','points_log'
];
$tables = array_values(array_filter($candidatesPref, fn($t)=>table_exists($pdo,$t)));
if (!$tables) jerr('points_table_not_found', 500);

/* mapping builder for a table */
$buildMap = function(string $t) use ($pdo): ?array {
  $all = cols($pdo, $t);
  if (!$all) return null;

  $map = [
    'id'            => in_array('id',$all,true) ? 'id' :
                       (in_array('tx_id',$all,true) ? 'tx_id' : null),

    'user_id'       => in_array('user_id',$all,true) ? 'user_id' :
                       (in_array('uid',$all,true) ? 'uid' : null),

    'reason'        => in_array('reason',$all,true) ? 'reason' :
                       (in_array('type',$all,true) ? 'type' :
                       (in_array('action',$all,true) ? 'action' :
                       (in_array('event',$all,true) ? 'event' : null))),

    'delta'         => in_array('delta',$all,true) ? 'delta' :
                       (in_array('points',$all,true) ? 'points' :
                       (in_array('amount',$all,true) ? 'amount' :
                       (in_array('value',$all,true) ? 'value' :
                       (in_array('points_delta',$all,true) ? 'points_delta' :
                       (in_array('change',$all,true) ? 'change' :
                       (in_array('points_change',$all,true) ? 'points_change' : null)))))),

    'balance_after' => in_array('balance_after',$all,true) ? 'balance_after' :
                       (in_array('balance',$all,true) ? 'balance' :
                       (in_array('points_balance',$all,true) ? 'points_balance' :
                       (in_array('new_balance',$all,true) ? 'new_balance' : null))),

    'meta'          => in_array('meta',$all,true) ? 'meta' :
                       (in_array('details',$all,true) ? 'details' :
                       (in_array('data',$all,true) ? 'data' :
                       (in_array('extra',$all,true) ? 'extra' : null))),

    'created_at'    => in_array('created_at',$all,true) ? 'created_at' :
                       (in_array('created',$all,true) ? 'created' :
                       (in_array('ts',$all,true) ? 'ts' :
                       (in_array('timestamp',$all,true) ? 'timestamp' :
                       (in_array('time',$all,true) ? 'time' :
                       (in_array('date',$all,true) ? 'date' : null))))),
  ];
  if (!$map['id'] || !$map['user_id'] || !$map['created_at'] || !$map['delta']) return null;
  return $map + ['__table'=>$t];
};

/* wähle die erste Tabelle, die (a) brauchbares Mapping hat und (b) für den User existiert;
   wenn möglich eine, die auch negative deltas enthält */
$choice = null;
foreach ($tables as $t) {
  $m = $buildMap($t);
  if (!$m) continue;
  // Hat der User hier Einträge?
  $st = $pdo->prepare("SELECT {$m['delta']} AS d FROM `{$t}` WHERE `{$m['user_id']}`=? ORDER BY `{$m['id']}` DESC LIMIT 30");
  $st->execute([(int)$me['id']]);
  $sample = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
  if (!$sample) continue;
  $hasNegative = array_reduce($sample, fn($c,$v)=>$c || (int)$v < 0, false);
  $choice = $m;
  if ($hasNegative) break; // Jackpot – enthält Ausgaben
}
if (!$choice) jerr('no_rows_for_user', 200);

/* --------------- select rows ------------- */
$t = $choice['__table'];
$select = [];
$select[] = "`{$choice['id']}` AS id";
$select[] = "`{$choice['user_id']}` AS user_id";
$select[] = "`{$choice['delta']}` AS delta";
$select[] = $choice['reason']        ? "`{$choice['reason']}` AS reason"          : "NULL AS reason";
$select[] = $choice['balance_after'] ? "`{$choice['balance_after']}` AS balance_after" : "NULL AS balance_after";
$select[] = $choice['meta']          ? "`{$choice['meta']}` AS meta"              : "NULL AS meta";
$select[] = "`{$choice['created_at']}` AS created_at";
$selectSql = implode(', ',$select);

$params = [(int)$me['id']];
$where  = "WHERE `{$choice['user_id']}` = ?";
if ($cursor) { $where .= " AND `{$choice['id']}` < ?"; $params[] = $cursor; }

$sql = "SELECT {$selectSql}
        FROM `{$t}`
        {$where}
        ORDER BY `{$choice['id']}` DESC
        LIMIT {$limit}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* --------------- Balance berechnen (Fallback) ------------- */
$currentBalance = 0;
try {
  $stB = $pdo->prepare("SELECT points FROM user_points WHERE user_id=? LIMIT 1");
  $stB->execute([(int)$me['id']]);
  $currentBalance = (int)($stB->fetchColumn() ?: 0);
} catch (Throwable $e) { /* egal */ }

$needBackCalc = true;
foreach ($rows as $r) {
  if ($r['balance_after'] !== null && $r['balance_after'] !== '') { $needBackCalc = false; break; }
}
if ($needBackCalc) {
  // Vom aktuellen Stand rückwärts laufen:
  $bal = $currentBalance;
  foreach ($rows as &$r) {
    $r['balance_after'] = $bal;
    $bal -= (int)$r['delta']; // für die nächste (ältere) Zeile
  }
  unset($r);
}

/* --------------- Item-Titel für Käufe -------------------- */
$need = [];
foreach ($rows as $r) {
  $meta = jdecode($r['meta'] ?? '');
  if (($r['reason'] ?? '') === 'shop_purchase' && !empty($meta['item_id']) && empty($meta['item_title'])) {
    $need[(int)$meta['item_id']] = true;
  }
}
$titles = [];
if ($need) {
  try {
    $ids = array_map('intval', array_keys($need));
    $in  = implode(',', array_fill(0,count($ids),'?'));
    $st2 = $pdo->prepare("SELECT id, title FROM shop_items WHERE id IN ($in)");
    $st2->execute($ids);
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $it) {
      $titles[(int)$it['id']] = (string)$it['title'];
    }
  } catch (Throwable $e) {}
}

/* --------------- Ausgabe normalisieren --------------- */
$out = [];
foreach ($rows as $r) {
  $meta  = jdecode($r['meta'] ?? '');
  $label = null; $note = null;

  switch ($r['reason'] ?? '') {
    case 'shop_purchase':
      $label = 'Shop-Kauf';
      if (!empty($meta['item_title']))      $note = $meta['item_title'];
      elseif (!empty($meta['item_id']) && isset($titles[(int)$meta['item_id']])) $note = $titles[(int)$meta['item_id']];
      elseif (!empty($meta['item_id']))     $note = 'Artikel #'.$meta['item_id'];
      break;
    case 'daily_login':  $label = 'Täglicher Login-Bonus'; break;
    case 'referral':     $label = 'Referral'; break;
    case 'upload_avatar':$label = 'Avatar hochgeladen'; break;
    case 'upload_cover': $label = 'Cover hochgeladen'; break;
    case 'forum_post':   $label = 'Forenbeitrag'; break;
    case 'wall_post':    $label = 'Pinnwand-Beitrag'; break;
    case 'wall_comment': $label = 'Pinnwand-Kommentar'; break;
    case 'transfer_out': $label = 'Punkte gesendet';  $note = $meta['to_name']   ?? ($meta['to']   ?? null); break;
    case 'transfer_in':  $label = 'Punkte erhalten';  $note = $meta['from_name'] ?? ($meta['from'] ?? null); break;
  }

  $out[] = [
    'id'            => (int)$r['id'],
    'created_at'    => (string)$r['created_at'],
    'reason'        => (string)($r['reason'] ?? ''),
    'label'         => $label,
    'note'          => $note,
    'delta'         => (int)$r['delta'],
    'balance_after' => isset($r['balance_after']) ? (int)$r['balance_after'] : null,
  ];
}

/* --------------- Cursor ----------------- */
$next = (count($rows) === $limit) ? (int)$rows[count($rows)-1]['id'] : null;

echo json_encode(['ok'=>true,'rows'=>$out,'next_cursor'=>$next], JSON_UNESCAPED_UNICODE);

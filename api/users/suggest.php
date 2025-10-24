<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/db.php';
$pdo = db();

/** optional: eingeloggten User ermitteln (um sich selbst auszuschließen) */
$me = null;
if (is_file(__DIR__ . '/../../auth/guards.php')) {
  require_once __DIR__ . '/../../auth/guards.php';
  if (function_exists('optional_auth')) {
    try { $me = optional_auth(); } catch (Throwable $e) { $me = null; }
  }
}

$q     = trim((string)($_GET['q'] ?? ''));
$limit = max(1, min(15, (int)($_GET['limit'] ?? 7)));

if ($q === '') { echo json_encode(['ok'=>true,'users'=>[]]); exit; }

try {
  // vorhandene Spalten ermitteln
  $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
  $has  = fn(string $c) => in_array($c, $cols, true);

  // SELECT dynamisch bauen
  $select = ['id'];
  if     ($has('display_name')) $select[] = 'display_name';
  elseif ($has('username'))     $select[] = 'username AS display_name';
  else                          $select[] = "CONCAT('User #', id) AS display_name";

  if     ($has('slug'))         $select[] = 'slug';
  elseif ($has('username'))     $select[] = 'username AS slug';
  else                          $select[] = "NULL AS slug";

  if     ($has('avatar_path'))  $select[] = 'avatar_path';
  elseif ($has('avatar_url'))   $select[] = 'avatar_url AS avatar_path';
  elseif ($has('avatar'))       $select[] = 'avatar AS avatar_path';
  else                          $select[] = "NULL AS avatar_path";

  $select[] = $has('email')    ? 'email'    : "NULL AS email";
  $select[] = $has('ref_code') ? 'ref_code' : "NULL AS ref_code";

  // Suchfelder (nur vorhandene)
  $searchCols = [];
  if ($has('slug'))         $searchCols[] = 'slug';
  if ($has('display_name')) $searchCols[] = 'display_name';
  elseif ($has('username')) $searchCols[] = 'username';
  if ($has('email'))        $searchCols[] = 'email';
  if ($has('ref_code'))     $searchCols[] = 'ref_code';

  if (!$searchCols) { echo json_encode(['ok'=>true,'users'=>[]]); exit; }

  // WHERE mit positionsbasierten Platzhaltern
  $params   = [];
  $where    = [];

  // sich selbst ausschließen (zwei Platzhalter)
  $where[]  = "(? = 0 OR id <> ?)";
  $params[] = (int)($me['id'] ?? 0);
  $params[] = (int)($me['id'] ?? 0);

  foreach ($searchCols as $c) {
    $where[]   = "$c LIKE ?";
    $params[]  = '%'.$q.'%';
  }
  $whereSql = implode(' AND ', [
    $where[0], // self filter
    '(' . implode(' OR ', array_slice($where, 1)) . ')'
  ]);

  // ORDER BY: "Beginn-Treffer zuerst"
  $orderParts = [];
  foreach (['slug','display_name','username','email'] as $c) {
    if (in_array($c, $searchCols, true)) {
      $orderParts[] = "(CASE WHEN $c LIKE ? THEN 0 ELSE 1 END)";
      $params[]     = $q.'%';
    }
  }
  $orderName = $has('display_name') ? 'display_name' : ($has('username') ? 'username' : 'id');
  $orderSql  = ($orderParts ? implode(', ', $orderParts).', ' : '') . "$orderName ASC";

  // LIMIT direkt als Integer einsetzen (keine Bindings, vermeidet Treiber-Inkompatibilitäten)
  $limitInt = (int)$limit;

  $sql = "
    SELECT ".implode(',', $select)."
      FROM users
     WHERE $whereSql
     ORDER BY $orderSql
     LIMIT $limitInt
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);

  $out = [];
  while ($u = $st->fetch(PDO::FETCH_ASSOC)) {
    // E-Mail maskieren
    $email = (string)($u['email'] ?? '');
    if ($email !== '' && strpos($email,'@') !== false) {
      [$l,$d] = explode('@',$email,2);
      $email  = mb_substr($l,0,2).'…@'.$d;
    }
    $out[] = [
      'id'     => (int)$u['id'],
      'name'   => (string)$u['display_name'],
      'slug'   => (string)($u['slug'] ?? ''),
      'avatar' => (string)($u['avatar_path'] ?? ''),
      'email'  => $email,
      'ref'    => (string)($u['ref_code'] ?? '')
    ];
  }

  echo json_encode(['ok'=>true,'users'=>$out]);

} catch (Throwable $e) {
  error_log('users/suggest error: '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}

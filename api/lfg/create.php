<?php
declare(strict_types=1);

/**
 * /api/lfg/create.php – robuste JSON-API (kein Redirect, immer JSON)
 * - Nimmt die Felder aus dem Create-Form entgegen
 * - Auth ohne Redirect (optional_auth/current_user/Session)
 * - Baut das INSERT dynamisch nur über vorhandene Spalten in lfg_posts
 * - timeslots: akzeptiert JSON ODER beliebigen Text
 * - expires_at: akzeptiert datetime-local (YYYY-MM-DDTHH:MM) oder frei (wird via strtotime geparst)
 */

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
});
set_error_handler(function($severity,$message,$file,$line){
  throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../../auth/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---- Auth ohne Redirect ----
$me = null;
if (is_file(__DIR__ . '/../../auth/guards.php')) {
  require_once __DIR__ . '/../../auth/guards.php';
  if (function_exists('optional_auth')) {
    $me = optional_auth();
  } elseif (function_exists('current_user')) {
    $me = current_user();
  }
}
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!$me && isset($_SESSION['user']['id'])) { $me = ['id'=>(int)$_SESSION['user']['id']]; }
$user_id = (int)($me['id'] ?? 0);
if ($user_id <= 0) { echo json_encode(['ok'=>false, 'error'=>'unauthorized']); exit; }

// ---- Ping ----
if (isset($_GET['__ping'])) { echo json_encode(['ok'=>true,'pong'=>true]); exit; }

// ---- Tabelle vorhanden? ----
$st = $pdo->query("SHOW TABLES LIKE 'lfg_posts'");
if (!$st || !$st->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'missing_tables','missing'=>'lfg_posts']); exit; }

// ---- Spalten ermitteln ----
$columns = [];
$desc = $pdo->query("SHOW COLUMNS FROM `lfg_posts`");
while ($row = $desc->fetch(PDO::FETCH_ASSOC)) { $columns[$row['Field']] = true; }
$has = fn(string $c) => isset($columns[$c]);

// ---- Input einsammeln ----
$in = function(string $k, $def=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : (isset($_GET[$k])? trim((string)$_GET[$k]) : $def); };

$platform       = strtolower($in('platform',''));
$region         = strtolower($in('region',''));
$mode           = strtolower($in('mode',''));
$playstyle      = strtolower($in('playstyle',''));
$mmr            = $in('mmr','') === '' ? null : (int)$in('mmr','');
$kd             = $in('kd','')  === '' ? null : (float)$in('kd','');
$primary_weapon = $in('primary_weapon','');
$languages      = $in('languages','');
$looking_for    = strtolower($in('looking_for',''));
$headset_raw    = strtolower($in('headset',''));
$headset        = ($headset_raw!=='' ? (in_array($headset_raw,['1','true','ja','yes','on'],'true')?1:0) : null);
$notes          = $in('notes','');
$timeslots_raw  = $in('timeslots','');
$expires_raw    = $in('expires_at','');

// timeslots: JSON behalten, sonst Text
$timeslots = null;
if ($timeslots_raw !== '') {
  $ts = $timeslots_raw;
  // Versuche JSON zu normalisieren, wenn es nach JSON aussieht
  $looksJson = ($ts[0] ?? '') === '{' || ($ts[0] ?? '') === '[';
  if ($looksJson) {
    try {
      $decoded = json_decode($ts, true, 512, JSON_THROW_ON_ERROR);
      $timeslots = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
      // kein valides JSON -> als Text speichern
      $timeslots = $ts;
    }
  } else {
    $timeslots = $ts;
  }
}

// expires_at: viele Formate zulassen (datetime-local / deutsch / iso)
$expires_at = null;
if ($expires_raw !== '') {
  $r = $expires_raw;
  // normalize: 2025-09-03T20:00 -> space
  $r = str_replace('T',' ', $r);
  // deutsch 03.09.2025 20:00 -> 2025-09-03 20:00
  if (preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s+(\d{1,2}:\d{2})(?::\d{2})?)?$~', $r, $m)) {
    $r = sprintf('%04d-%02d-%02d %s', (int)$m[3], (int)$m[2], (int)$m[1], $m[4] ?? '00:00');
  }
  $ts = strtotime($r);
  if ($ts !== false) { $expires_at = date('Y-m-d H:i:s', $ts); }
}

// ---- Pflichtvalidierung minimal ----
if ($platform === '' || $region === '') {
  echo json_encode(['ok'=>false,'error'=>'validation','message'=>'platform und region sind erforderlich']);
  exit;
}

// ---- Insert-Daten vorbereiten (nur vorhandene Spalten) ----
$now = date('Y-m-d H:i:s');
$data = [
  'user_id'        => $user_id,
  'platform'       => $platform,
  'region'         => $region,
  'mode'           => $mode,
  'playstyle'      => $playstyle,
  'mmr'            => $mmr,
  'kd'             => $kd,
  'primary_weapon' => $primary_weapon,
  'languages'      => $languages,
  'looking_for'    => $looking_for,
  'headset'        => $headset,
  'notes'          => $notes,
  'timeslots'      => $timeslots,
  'expires_at'     => $expires_at,
  'visible'        => 1,
  'created_at'     => $now,
  'updated_at'     => $now,
];

$fields = [];
$marks  = [];
$params = [];
foreach ($data as $col => $val) {
  if ($has($col) && $val !== null) { // NULL-Felder optional weglassen, außer expires_at/timeslots dürfen NULL sein
    $fields[] = "`$col`";
    $marks[]  = ":$col";
    $params[":$col"] = $val;
  } elseif ($has($col) && in_array($col, ['expires_at','timeslots','mmr','kd','headset'], true)) {
    // Diese Spalten wollen wir auch als NULL explizit setzen, wenn vorhanden
    $fields[] = "`$col`";
    $marks[]  = 'NULL';
  }
}

if (!$fields) { echo json_encode(['ok'=>false,'error'=>'no_insertable_columns']); exit; }

$sql = 'INSERT INTO `lfg_posts` (' . implode(',', $fields) . ') VALUES (' . implode(',', $marks) . ')';
$st = $pdo->prepare($sql);
$st->execute($params);

$id = (int)$pdo->lastInsertId();

echo json_encode(['ok'=>true, 'id'=>$id], JSON_UNESCAPED_UNICODE);

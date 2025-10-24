<?php
// /api/tournaments/run_upload.php
// Upload-Handler für Turnier-Screenshots mit Metadatenprüfung + filemtime-Fallback

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');

set_error_handler(function($sev,$msg,$file,$line){ throw new ErrorException($msg,0,$sev,$file,$line); });
set_exception_handler(function(Throwable $e){
  http_response_code(500);
  error_log('[run_upload] '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());
  echo json_encode(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage(),'where'=>basename($e->getFile()).':'.$e->getLine()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
});

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
$cfg = require __DIR__ . '/../../auth/config.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---- User ----
if (function_exists('require_user'))      { $me = require_user(); }
elseif (function_exists('require_login')) { $me = require_login(); }
elseif (function_exists('require_auth'))  { $me = require_auth(); }
else                                      { $me = require_admin(); }
$uid = (int)($me['id'] ?? 0);

// ---- CSRF ----
$token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
if (function_exists('verify_csrf') && !verify_csrf($pdo,(string)$token)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'bad_csrf']); return;
}

$tid = (int)($_POST['tournament_id'] ?? 0);
if ($tid<=0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_tournament_id']); return; }

// ---- Turnier & Zeitfenster ----
$tzId = $cfg['timezone'] ?? 'Europe/Berlin';
$tz   = new DateTimeZone($tzId);
$stT = $pdo->prepare('SELECT id, starts_at, ends_at, scoring_json, '.(columnExists($pdo,'tournaments','status')?'status':'\'\' AS status').' FROM tournaments WHERE id=?');
$stT->execute([$tid]);
$T = $stT->fetch(PDO::FETCH_ASSOC);
if (!$T) { echo json_encode(['ok'=>false,'error'=>'tournament_not_found']); return; }

$now  = new DateTimeImmutable('now', $tz);
$grace= 300; // 5min
$startOk = true; $endOk = true;
$ss = (string)($T['starts_at'] ?? '');
$se = (string)($T['ends_at'] ?? '');
if ($ss!==''){ $s=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$ss,$tz) ?: new DateTimeImmutable($ss,$tz); $startOk = ($now->getTimestamp() >= ($s->getTimestamp()-$grace)); }
if ($se!==''){ $e=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$se,$tz) ?: new DateTimeImmutable($se,$tz); $endOk   = ($now->getTimestamp() <= ($e->getTimestamp()+$grace)); }
$windowOk = ($startOk && $endOk) || in_array((string)$T['status'],['open','running'],true);
if (!$windowOk) {
  echo json_encode(['ok'=>false,'error'=>'outside_time_window','detail'=>['now'=>$now->format('Y-m-d H:i:s'),'starts_at'=>$ss,'ends_at'=>$se,'status'=>$T['status']]]); return;
}

// ---- Team des Users ----
$stTeam = $pdo->prepare('SELECT tt.id FROM tournament_teams tt JOIN tournament_team_members m ON m.tournament_team_id=tt.id WHERE tt.tournament_id=? AND m.user_id=? LIMIT 1');
$stTeam->execute([$tid, $uid]);
$teamId = (int)($stTeam->fetchColumn() ?: 0);
if (!$teamId) { echo json_encode(['ok'=>false,'error'=>'no_team']); return; }

// ---- Eingaben & Punkte ----
$kills    = max(0,(int)($_POST['kills']    ?? 0));
$bosses   = max(0,(int)($_POST['bosses']   ?? 0));
$tokens   = max(0,(int)($_POST['tokens']   ?? 0));
$gauntlet = max(0,(int)($_POST['gauntlet'] ?? 0));
$deaths   = max(0,(int)($_POST['deaths']   ?? 0));
$sc = ['kill'=>1,'boss'=>3,'token'=>5,'gauntlet'=>10,'death'=>-5];
if (!empty($T['scoring_json'])) { $tmp=json_decode((string)$T['scoring_json'],true); if(is_array($tmp)) $sc=array_merge($sc,$tmp); }
$points = ($kills*$sc['kill'])+($bosses*$sc['boss'])+($tokens*$sc['token'])+($gauntlet*$sc['gauntlet'])+($deaths*$sc['death']);

// ---- Datei prüfen ----
if (empty($_FILES['shot']) || ($_FILES['shot']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { echo json_encode(['ok'=>false,'error'=>'missing_screenshot']); return; }
$f = $_FILES['shot'];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = (string)($finfo->file($f['tmp_name']) ?: '');
$allow = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];
$ext   = $allow[$mime] ?? null;
if (!$ext) { echo json_encode(['ok'=>false,'error'=>'bad_filetype','detail'=>$mime]); return; }

$maxBytes = 20 * 1024 * 1024;
if (($f['size'] ?? 0) < 1000 || ($f['size'] ?? 0) > $maxBytes) {
  echo json_encode(['ok'=>false,'error'=>'bad_size']); return;
}

// ---- Zielpfad
$root   = dirname(__DIR__, 2);
$relDir = '/uploads/tournaments/'.$tid.'/runs/';
$absDir = $root.$relDir; if (!is_dir($absDir)) @mkdir($absDir, 0775, true);
$fn     = $teamId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).$ext;
$abs    = $absDir.$fn; $rel = $relDir.$fn;

if (!move_uploaded_file($f['tmp_name'], $abs)) { echo json_encode(['ok'=>false,'error'=>'upload_failed']); return; }

// ---- META: Zeitstempel auslesen
[$metaDt, $metaSource] = extract_image_datetime_fast($abs, $mime);

// --- Fallback: wenn keine Metadaten gefunden -> filemtime() nutzen ---
if (!$metaDt) {
  $mt = @filemtime($abs);
  if ($mt) {
    $metaDt = (new DateTime('@'.$mt))->setTimezone(new DateTimeZone('UTC'));
    $metaSource = 'filemtime';
  }
}

// ---- Heuristik
$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$maxAgeHours       = 24;
$maxFutureSkewMin  = 5;

$isSuspicious = 0; $reasons = [];
$startsAt = !empty($T['starts_at']) ? new DateTimeImmutable((string)$T['starts_at'], new DateTimeZone('UTC')) : null;
$endsAt   = !empty($T['ends_at'])   ? new DateTimeImmutable((string)$T['ends_at'],   new DateTimeZone('UTC')) : null;

if ($metaDt instanceof DateTimeInterface) {
  $diffH = (int) floor(($nowUtc->getTimestamp() - $metaDt->getTimestamp()) / 3600);
  if ($diffH > $maxAgeHours) { $isSuspicious = 1; $reasons[] = "older_than_{$maxAgeHours}h"; }
  if ($metaDt->getTimestamp() - $nowUtc->getTimestamp() > ($maxFutureSkewMin * 60)) {
    $isSuspicious = 1; $reasons[] = 'future_time';
  }
  if ($startsAt && $metaDt < $startsAt) { $isSuspicious = 1; $reasons[] = 'before_tournament'; }
  if ($endsAt   && $metaDt > $endsAt)   { $isSuspicious = 1; $reasons[] = 'after_tournament'; }
} else {
  $isSuspicious = 1; $reasons[] = 'no_metadata';
}

// ---- Spalten autodetektion ----
$cols=[]; foreach($pdo->query('SHOW COLUMNS FROM tournament_runs',PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=$c; }

$colTournamentId = pickCol($cols, ['tournament_id','t_id']);
$colTeamId       = pickCol($cols, ['tournament_team_id','team_id']);
$pointsCol       = pickCol($cols, ['raw_points','points_total','points']);
$shotCol         = pickCol($cols, ['screenshot_rel','screenshot_path','screenshot','image_path']);
$uploaderCol     = pickCol($cols, ['uploader_user_id','uploader_id','user_id','created_by']);

$statusCol = isset($cols['status']) ? 'status' : null;
$statusVal = null;
if ($statusCol) {
  $type = strtolower((string)($cols['status']['Type'] ?? ''));
  $default = $cols['status']['Default'] ?? null;
  if (str_starts_with($type, 'enum(')) {
    preg_match_all("/'([^']*)'/", $type, $m);
    $allowed = $m[1] ?? [];
    if (in_array('pending', $allowed, true))      $statusVal = 'pending';
    elseif (in_array('new', $allowed, true))      $statusVal = 'new';
    elseif ($default !== null && $default !== '') $statusVal = (string)$default;
    elseif (!empty($allowed))                     $statusVal = (string)$allowed[0];
  } elseif (str_contains($type,'int')) {
    $statusVal = $default !== null && $default !== '' ? (int)$default : 0;
  } else {
    $statusVal = $default !== null ? (string)$default : 'pending';
  }
}

$killCol    = pickCol($cols, ['kills']);
$bossCol    = pickCol($cols, ['bosses','boss_kills']);
$tokenCol   = pickCol($cols, ['tokens','token']);
$gauntCol   = pickCol($cols, ['gauntlet']);
$deathCol   = pickCol($cols, ['deaths','deads']);

$exifDtCol   = pickCol($cols, ['exif_datetime']);
$exifSrcCol  = pickCol($cols, ['exif_source']);
$suspCol     = pickCol($cols, ['suspicious']);
$metaNoteCol = pickCol($cols, ['meta_note']);

if (!$colTournamentId || !$colTeamId) {
  echo json_encode(['ok'=>false,'error'=>'schema_unsupported','detail'=>['need'=>['tournament_id','tournament_team_id'],'have'=>array_keys($cols)]]); return;
}

$data = [ $colTournamentId=>$tid, $colTeamId=>$teamId ];
if ($killCol)  $data[$killCol]  = $kills;
if ($bossCol)  $data[$bossCol]  = $bosses;
if ($tokenCol) $data[$tokenCol] = $tokens;
if ($gauntCol) $data[$gauntCol] = $gauntlet;
if ($deathCol) $data[$deathCol] = $deaths;
if ($pointsCol)   $data[$pointsCol]   = $points;
if ($shotCol)     $data[$shotCol]     = $rel;
if ($statusCol && $statusVal !== null) $data[$statusCol] = $statusVal;
if ($uploaderCol) $data[$uploaderCol] = $uid;

if ($exifDtCol)   $data[$exifDtCol]   = $metaDt ? $metaDt->format('Y-m-d H:i:s') : null;
if ($exifSrcCol)  $data[$exifSrcCol]  = $metaSource ?: null;
if ($suspCol)     $data[$suspCol]     = (int)$isSuspicious;
if ($metaNoteCol) $data[$metaNoteCol] = $reasons ? implode(',', $reasons) : null;

$keys = array_keys($data);
$place= implode(',', array_fill(0, count($keys), '?'));
$sql  = 'INSERT INTO tournament_runs ('.implode(',', $keys).') VALUES ('.$place.')';
$stI  = $pdo->prepare($sql);
$stI->execute(array_values($data));
$id   = (int)$pdo->lastInsertId();

echo json_encode([
  'ok'=>true,
  'id'=>$id,
  'points'=>$points,
  'file'=>$rel,
  'status_set'=>$statusVal,
  'suspicious'=>(bool)$isSuspicious,
  'reason'=>$reasons,
]);

// ---------------- helpers ----------------

function columnExists(PDO $pdo, string $table, string $col): bool {
  foreach ($pdo->query('SHOW COLUMNS FROM `'.$table.'`', PDO::FETCH_ASSOC) as $c) {
    if (isset($c['Field']) && $c['Field'] === $col) return true;
  }
  return false;
}
function pickCol(array $cols, array $candidates): ?string {
  foreach ($candidates as $c) if (isset($cols[$c])) return $c;
  return null;
}

/** PNG tIME chunk parser */
function read_png_time(?string $path): ?DateTime {
  if (!$path || !is_file($path)) return null;
  $fp = @fopen($path, 'rb'); if (!$fp) return null;
  $sig = fread($fp, 8);
  if ($sig !== "\x89PNG\r\n\x1a\n") { fclose($fp); return null; }
  while (!feof($fp)) {
    $lenData = fread($fp, 4); if (strlen($lenData) < 4) break;
    $len = unpack('N', $lenData)[1];
    $type = fread($fp, 4);    if (strlen($type) < 4) break;
    if ($type === 'tIME') {
      $data = fread($fp, $len);
      if (strlen($data) === 7) {
        $Y = unpack('n', substr($data,0,2))[1];
        $m = ord($data[2]); $d = ord($data[3]);
        $H = ord($data[4]); $i = ord($data[5]); $s = ord($data[6]);
        try {
          return new DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $Y,$m,$d,$H,$i,$s), new DateTimeZone('UTC'));
        } catch (Throwable $e) {}
      }
      fclose($fp); return null;
    } else {
      fseek($fp, $len + 4, SEEK_CUR);
    }
  }
  fclose($fp);
  return null;
}

/** EXIF/XMP lesen + PNG tIME fallback */
function extract_image_datetime_fast(string $path, string $mime): array {
  $dt = null; $source = null;

  if ($mime === 'image/png') {
    $dt = read_png_time($path);
    if ($dt) return [$dt, 'png_tIME'];
  }

  if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
    try {
      $exif = @exif_read_data($path, null, true, false);
      if ($exif && is_array($exif)) {
        foreach (['EXIF'=>['DateTimeOriginal','CreateDate'], 'IFD0'=>['DateTime']] as $grp=>$keys) {
          foreach ($keys as $k) {
            if (!empty($exif[$grp][$k])) {
              $raw = (string)$exif[$grp][$k];
              $try = DateTime::createFromFormat('Y:m:d H:i:s', $raw, new DateTimeZone('UTC'))
                    ?: (date_create_immutable($raw, new DateTimeZone('UTC')) ?: null);
              if ($try instanceof DateTimeInterface) {
                $dt2 = $try instanceof DateTime ? $try : new DateTime($try->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
                return [$dt2, 'exif'];
              }
            }
          }
        }
      }
    } catch (Throwable $e) {}
  }

  return [$dt, $source];
}

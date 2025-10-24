<?php
// File: /api/tournaments/my_state.php
// Zweck: Team + eigene Runs zurückgeben, robust gegen Schema-Varianten

declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','1');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
$cfg = require __DIR__ . '/../../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // User ermitteln (kompatibel mit verschiedenen Guard-Setups)
  if (function_exists('require_user'))      { $me = require_user(); }
  elseif (function_exists('require_login')) { $me = require_login(); }
  elseif (function_exists('require_auth'))  { $me = require_auth(); }
  else                                      { $me = require_admin(); }
  $uid = (int)($me['id'] ?? 0);

  // CSRF prüfen
  $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
  if (function_exists('verify_csrf') && !verify_csrf($pdo,(string)$token)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_csrf']);
    return; }

  $tid = (int)($_POST['tournament_id'] ?? 0);
  if ($tid<=0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_tournament_id']); return; }

  // Team des Users im Turnier
  $st = $pdo->prepare('SELECT tt.* FROM tournament_teams tt JOIN tournament_team_members m ON m.tournament_team_id=tt.id WHERE tt.tournament_id=? AND m.user_id=? LIMIT 1');
  $st->execute([$tid, $uid]);
  $team = $st->fetch(PDO::FETCH_ASSOC) ?: null;

  $members = [];
  if ($team) {
    $st2 = $pdo->prepare('SELECT u.id, u.display_name FROM tournament_team_members m JOIN users u ON u.id=m.user_id WHERE m.tournament_team_id=? ORDER BY m.id ASC');
    $st2->execute([$team['id']]);
    while ($r = $st2->fetch(PDO::FETCH_ASSOC)) { $members[] = ['id'=>(int)$r['id'],'name'=>$r['display_name']]; }
  }

  // ===== Runs laden – Spalten autodetektion =====
  $cols = [];
  foreach ($pdo->query('SHOW COLUMNS FROM tournament_runs', PDO::FETCH_ASSOC) as $c) { $cols[$c['Field']] = true; }

  // Uploader-Spalte herausfinden (uploader_user_id | user_id | created_by | none)
  $uploaderCol = null;
  if (isset($cols['uploader_user_id']))      $uploaderCol = 'uploader_user_id';
  elseif (isset($cols['user_id']))           $uploaderCol = 'user_id';
  elseif (isset($cols['created_by']))        $uploaderCol = 'created_by';

  // Punkte-Spalte
  $pointsSel = isset($cols['raw_points']) ? 'raw_points' : (isset($cols['points']) ? 'points' : '0');
  // Screenshot-Spalte
  $shotSel   = isset($cols['screenshot_rel']) ? 'screenshot_rel' : (isset($cols['screenshot_path']) ? 'screenshot_path' : "''");
  // Created-Spalte (für Anzeige)
  $createdSel= isset($cols['created_at']) ? 'created_at' : (isset($cols['created']) ? 'created' : (isset($cols['created_on']) ? 'created_on' : 'NULL'));
  // Status-Spalte
  $statusSel = isset($cols['status']) ? 'status' : "'pending'";

  $runs = [];
  if ($team) {
    $sql = "SELECT id, $pointsSel AS pts, $statusSel AS status, $createdSel AS created_at, $shotSel AS shot FROM tournament_runs WHERE tournament_team_id=?";
    $params = [$team['id']];
    if ($uploaderCol) { $sql .= " AND $uploaderCol=?"; $params[] = $uid; }
    $sql .= ' ORDER BY id DESC LIMIT 30';

    $st3 = $pdo->prepare($sql);
    $st3->execute($params);
    while ($r = $st3->fetch(PDO::FETCH_ASSOC)) {
      $shot = (string)($r['shot'] ?? '');
      if ($shot !== '' && $shot[0] !== '/') $shot = '/' . $shot;
      $url = $shot !== '' ? ($APP_BASE . $shot) : null;
      $runs[] = [
        'id'            => (int)$r['id'],
        'points'        => (int)($r['pts'] ?? 0),
        'status'        => (string)($r['status'] ?? 'pending'),
        'created_at'    => (string)($r['created_at'] ?? ''),
        'screenshot_url'=> $url,
      ];
    }
  }

  echo json_encode([
    'ok'     => true,
    'team'   => $team ? ['id'=>(int)$team['id'],'name'=>$team['name'],'join_code'=>$team['join_code']??null] : null,
    'members'=> $members,
    'runs'   => $runs,
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()]);
}
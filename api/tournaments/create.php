<?php
declare(strict_types=1);

// Temporär Fehler sichtbar machen
error_reporting(E_ALL);
ini_set('display_errors','1');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

function json_ok(array $data = [], int $http = 200): void {
  http_response_code($http);
  echo json_encode(array_merge(['ok'=>true], $data), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function json_err(string $error, int $http = 400, array $extra = []): void {
  http_response_code($http);
  echo json_encode(array_merge(['ok'=>false,'error'=>$error], $extra), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

try { $pdo = db(); } catch (Throwable $e) { json_err('db_connect', 500, ['detail'=>$e->getMessage()]); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try { $me = require_admin(); } catch (Throwable $e) { json_err('auth_required', 401, ['detail'=>$e->getMessage()]); }

$token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
if (function_exists('verify_csrf') && !verify_csrf($pdo, (string)$token)) json_err('bad_csrf', 403);

$fetch = fn(string $k) => isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
$name       = $fetch('name');
$team_size  = (int)($_POST['team_size'] ?? 3);
$platform   = $fetch('platform') ?: 'mixed';
$format     = $fetch('format')   ?: 'points';
$starts_at  = $fetch('starts_at') ?: $fetch('starts_at_local');
$ends_at    = $fetch('ends_at')   ?: $fetch('ends_at_local');
$rules      = (string)($_POST['rules_text'] ?? '');
$prizes     = (string)($_POST['prizes_text'] ?? '');
$best_runs  = max(1, min(5, (int)($_POST['best_runs'] ?? 3)));
$max_teams  = isset($_POST['max_teams']) && $_POST['max_teams'] !== '' ? (int)$_POST['max_teams'] : null;
$scoring_in = (string)($_POST['scoring_json'] ?? '');

$norm = static function(string $s): string {
  if ($s === '') return '';
  return (strpos($s,'T') !== false) ? str_replace('T',' ',$s) . (strlen($s)===16?':00':'') : $s;
};
$starts_at = $norm($starts_at);
$ends_at   = $norm($ends_at);

if ($name === '' || $starts_at === '' || $ends_at === '') {
  json_err('missing_fields', 422, ['need'=>['name','starts_at','ends_at']]);
}

$slugify = function(string $s): string {
  $s = strtolower(trim($s));
  $s = preg_replace('~[^a-z0-9]+~u','-',$s);
  $s = trim($s,'-');
  return $s ?: 'tournament';
};
$slug = $slugify($name);

// vorhandene Spalten lesen
$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM tournaments', PDO::FETCH_ASSOC) as $row) {
  $cols[$row['Field']] = true;
}
$hasSlug = isset($cols['slug']);

if ($hasSlug) {
  $base = $slug; $i=1;
  while (true) {
    $stmt = $pdo->prepare('SELECT 1 FROM tournaments WHERE slug=? LIMIT 1');
    $stmt->execute([$slug]);
    if (!$stmt->fetchColumn()) break;
    $slug = $base . '-' . (++$i);
  }
}

$payload = [
  'slug'         => $slug,
  'name'         => $name,
  'description'  => null,
  'platform'     => $platform,
  'team_size'    => $team_size,
  'format'       => $format,
  'rules_text'   => $rules,
  'prizes_text'  => $prizes,
  'best_runs'    => $best_runs,
  'max_teams'    => $max_teams,
  'status'       => 'draft',
  'scoring_json' => ($scoring_in !== '' ? $scoring_in : json_encode(['kill'=>1,'boss'=>3,'token'=>5,'gauntlet'=>10,'death'=>-5], JSON_UNESCAPED_SLASHES)),
  'starts_at'    => $starts_at,
  'ends_at'      => $ends_at,
  'created_by'   => (int)($me['id'] ?? 0),
];

// nur Spalten übernehmen, die es wirklich gibt
$data = array_filter($payload, fn($v,$k)=>isset($cols[$k]), ARRAY_FILTER_USE_BOTH);
if (!isset($data['name']) || !isset($data['starts_at']) || !isset($data['ends_at'])) {
  json_err('table_missing_required_columns', 500, ['have'=>array_keys($cols)]);
}

$keys = array_keys($data);
$place = implode(',', array_fill(0, count($keys), '?'));
$sql = 'INSERT INTO tournaments ('.implode(',', $keys).') VALUES ('.$place.')';

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array_values($data));
  $id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
  json_err('db_error', 500, ['detail'=>$e->getMessage(),'sql'=>$sql,'keys'=>$keys]);
}

$upAbs = __DIR__ . '/../../uploads/tournaments/' . $id . '/runs/';
if (!is_dir($upAbs)) @mkdir($upAbs, 0775, true);

json_ok(['id'=>$id,'slug'=>$hasSlug ? $slug : null]);
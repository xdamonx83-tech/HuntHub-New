<?php
declare(strict_types=1);

/**
 * /api/lfg/list.php – HART & AUTARK
 * - Keine Theme-/Layout-Includes
 * - Kein _bootstrap.php (vermeidet versehentliche Ausgaben)
 * - Immer JSON (auch bei Fehlern)
 * - Eingebaute Diag: ?__ping=1
 */

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'server_error', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../../auth/db.php';

$pdo = db(); // PDO

// --- Quick diag
if (isset($_GET['__ping'])) {
    echo json_encode(['ok'=>true, 'pong'=>true, 'route'=>'/api/lfg/list.php']);
    exit;
}

// --- Hilfsfunktionen (lokal, ohne _util.php)
function jint($v, ?int $min=null, ?int $max=null): ?int {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    $i = (int)$v;
    if ($min !== null && $i < $min) $i = $min;
    if ($max !== null && $i > $max) $i = $max;
    return $i;
}
function jnum($v, ?float $min=null, ?float $max=null): ?float {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    $f = (float)$v;
    if ($min !== null && $f < $min) $f = $min;
    if ($max !== null && $f > $max) $f = $max;
    return $f;
}
function jin(string $v=null, array $allowed=[], string $default=''): string {
    if ($v === null || $v === '') return $default;
    return in_array($v, $allowed, true) ? $v : $default;
}

// --- Prüfe Tabellen-Existenz, um HTML-SQL-Fehler zu vermeiden
$need = ['lfg_posts','lfg_requests'];
foreach ($need as $tbl) {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl));
    if (!$st || !$st->fetchColumn()) {
        echo json_encode(['ok'=>false, 'error'=>'missing_tables', 'missing'=>$tbl]);
        exit;
    }
}

// --- Filter einlesen
$q          = trim((string)($_GET['q'] ?? ''));
$platform   = $_GET['platform']  ?? null;
$region     = $_GET['region']    ?? null;
$mode       = $_GET['mode']      ?? null;
$playstyle  = $_GET['playstyle'] ?? null;
$headset    = $_GET['headset']   ?? null;
$looking    = $_GET['looking_for'] ?? null;
$lang       = $_GET['lang']      ?? null;
$min_mmr    = jint($_GET['min_mmr'] ?? null, 0);
$max_mmr    = jint($_GET['max_mmr'] ?? null, 0);
$min_kd     = jnum($_GET['min_kd']  ?? null, 0.0);
$max_kd     = jnum($_GET['max_kd']  ?? null, 0.0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = min(50, max(5, (int)($_GET['per_page'] ?? 20)));
$offset     = ($page - 1) * $per_page;

// Erlaubte Sets
$platform = jin($platform,  ['pc','xbox','ps'], '');
$region   = jin($region,    ['eu','na','sa','asia','oce'], '');
$mode     = jin($mode,      ['bounty','clash','both'], '');
$playstyle= jin($playstyle, ['offensive','defensive','balanced'], '');
$looking  = jin($looking,   ['solo','duo','trio','any'], '');

$where  = ["p.visible=1", "(p.expires_at IS NULL OR p.expires_at > NOW())"];
$params = [];

if ($q !== '')         { $where[] = "(p.notes LIKE :q OR p.primary_weapon LIKE :q)"; $params[':q'] = '%'.$q.'%'; }
if ($platform !== '')   { $where[] = "p.platform = :platform";  $params[':platform'] = $platform; }
if ($region   !== '')   { $where[] = "p.region   = :region";    $params[':region']   = $region; }
if ($mode     !== '')   { $where[] = "p.mode     = :mode";      $params[':mode']     = $mode; }
if ($playstyle!== '')   { $where[] = "p.playstyle= :playstyle"; $params[':playstyle']= $playstyle; }
if ($headset !== null && $headset !== '') { $where[] = "p.headset = :headset"; $params[':headset'] = ($headset==='1'||$headset===1||$headset==='true')?1:0; }
if ($looking  !== '')   { $where[] = "p.looking_for = :looking"; $params[':looking'] = $looking; }
if ($lang && $lang!==''){ $where[] = "FIND_IN_SET(:lng, COALESCE(p.languages,'')) > 0"; $params[':lng'] = trim((string)$lang); }
if ($min_mmr !== null)  { $where[] = "p.mmr >= :min_mmr"; $params[':min_mmr'] = $min_mmr; }
if ($max_mmr && $max_mmr>0) { $where[] = "p.mmr <= :max_mmr"; $params[':max_mmr'] = $max_mmr; }
if ($min_kd  !== null)  { $where[] = "p.kd >= :min_kd";   $params[':min_kd']  = $min_kd; }
if ($max_kd && $max_kd>0) { $where[] = "p.kd <= :max_kd"; $params[':max_kd']  = $max_kd; }

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Count
$st = $pdo->prepare("SELECT COUNT(*) FROM lfg_posts p $whereSql");
$st->execute($params);
$total = (int)$st->fetchColumn();

// Daten
$sql = "SELECT p.*, u.id as u_id, u.display_name, u.avatar_path, u.slug
        FROM lfg_posts p
        JOIN users u ON u.id = p.user_id
        $whereSql
        ORDER BY p.updated_at DESC
        LIMIT :lim OFFSET :off";

$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':lim', $per_page, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();

$items = [];
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $user = [
        'id'           => (int)$r['u_id'],
        'display_name' => $r['display_name'] ?? ('User#'.$r['u_id']),
        'avatar_path'  => $r['avatar_path'] ?? null,
        'slug'         => $r['slug'] ?? null,
    ];
    unset($r['u_id'], $r['display_name'], $r['avatar_path'], $r['slug']);
    $items[] = ['post'=>$r, 'user'=>$user];
}

echo json_encode(['ok'=>true, 'items'=>$items, 'total'=>$total, 'page'=>$page, 'per_page'=>$per_page], JSON_UNESCAPED_UNICODE);

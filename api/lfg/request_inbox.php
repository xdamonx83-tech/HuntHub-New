<?php
declare(strict_types=1);

/**
 * /api/lfg/request_inbox.php
 * Liefert Anfragen an MICH (owner_id = me).
 * status: pending | accepted | declined | blocked | all
 * IMMER JSON, kein Redirect.
 */

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'server_error', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function($sev,$msg,$file,$line){
    throw new ErrorException($msg, 0, $sev, $file, $line);
});

require_once __DIR__ . '/../../auth/db.php';
$pdo = db();

/* ---- Auth ohne Redirect ---- */
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
if (!$me && isset($_SESSION['user']['id'])) {
    $me = ['id' => (int)$_SESSION['user']['id']];
}
$user_id = (int)($me['id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(['ok'=>false, 'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---- Ping ---- */
if (isset($_GET['__ping'])) { echo json_encode(['ok'=>true, 'pong'=>true]); exit; }

/* ---- Tabellen? ---- */
foreach (['lfg_requests','lfg_posts','users'] as $tbl) {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl));
    if (!$st || !$st->fetchColumn()) {
        echo json_encode(['ok'=>false, 'error'=>'missing_tables', 'missing'=>$tbl]); exit;
    }
}

/* ---- Filter & Pagination ---- */
$allowed = ['pending','accepted','declined','blocked','all'];
$status  = $_GET['status'] ?? 'pending';
if (!in_array($status, $allowed, true)) $status = 'pending';

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = min(100, max(10, (int)($_GET['per_page'] ?? 50)));
$offset   = ($page - 1) * $per_page;

$where = "r.owner_id = :me";
$params = [':me' => $user_id];
if ($status !== 'all') { $where .= " AND r.status = :st"; $params[':st'] = $status; }

/* ---- Count ---- */
$st = $pdo->prepare("SELECT COUNT(*) FROM lfg_requests r WHERE $where");
$st->execute($params);
$total = (int)$st->fetchColumn();

/* ---- Daten ---- */
$sql = "SELECT
          r.id, r.post_id, r.owner_id, r.requester_id, r.message, r.status, r.created_at,
          -- Requester (Absender)
          ur.id   AS req_id,
          ur.display_name AS req_display_name,
          ur.avatar_path  AS req_avatar_path,
          ur.slug         AS req_slug,
          -- Post (kurz)
          p.id AS p_id, p.platform, p.region, p.mode, p.playstyle
        FROM lfg_requests r
        JOIN users ur ON ur.id = r.requester_id
        JOIN lfg_posts p ON p.id = r.post_id
        WHERE $where
        ORDER BY r.created_at DESC
        LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':lim', $per_page, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();

$items = [];
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $items[] = [
        'request' => [
            'id'          => (int)$r['id'],
            'post_id'     => (int)$r['post_id'],
            'owner_id'    => (int)$r['owner_id'],
            'requester_id'=> (int)$r['requester_id'],
            'message'     => $r['message'],
            'status'      => $r['status'],
            'created_at'  => $r['created_at'],
        ],
        'requester' => [
            'id'           => (int)$r['req_id'],
            'display_name' => $r['req_display_name'] ?? ('User#'.$r['req_id']),
            'avatar_path'  => $r['req_avatar_path'] ?? null,
            'slug'         => $r['req_slug'] ?? null,
        ],
        'post' => [
            'id'        => (int)$r['p_id'],
            'platform'  => $r['platform'],
            'region'    => $r['region'],
            'mode'      => $r['mode'],
            'playstyle' => $r['playstyle'],
        ],
    ];
}

echo json_encode(['ok'=>true, 'items'=>$items, 'total'=>$total, 'page'=>$page, 'per_page'=>$per_page], JSON_UNESCAPED_UNICODE);

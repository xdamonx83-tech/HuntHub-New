<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__, 3); // -> Projektwurzel
require_once $ROOT . '/auth/db.php';
require_once $ROOT . '/auth/guards.php';
require_once $ROOT . '/auth/csrf.php';

try {
  $pdo = db();
  require_admin();

  // CSRF prüfen (falls deine csrf.php verify_csrf bereitstellt)
  $in   = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
  $csrf = $_SERVER['HTTP_X_CSRF'] ?? ($in['csrf'] ?? '');
  if (function_exists('verify_csrf') && !verify_csrf($pdo, (string)$csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']); exit;
  }

  // Helpers
  $qAll = static function(PDO $pdo, string $sql, array $p = []) {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  };
  $tblExists = static function(PDO $pdo, string $name): bool {
    try { return (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote($name))->fetchColumn(); }
    catch (\Throwable $e) { return false; }
  };
  $cols = [];
  foreach ($qAll($pdo, 'SHOW COLUMNS FROM `reels`') as $c) $cols[$c['Field']] = true;
  $pick = static function(array $cols, array $cands, string $fallback='NULL'): string {
    foreach ($cands as $c) if (isset($cols[$c])) return "r.`$c`";
    return $fallback;
  };

  $limit  = max(1, min(500, (int)($in['limit']  ?? 200)));
  $offset = max(0,            (int)($in['offset'] ?? 0));
  $q      = trim((string)($in['q'] ?? ''));
  $status = trim((string)($in['status'] ?? ''));
  $userId = (int)($in['user_id'] ?? 0);

  $videoSel  = $pick($cols, ['video_rel','video_path','src','video','file_rel','file_path','url'], "NULL");
  $posterSel = $pick($cols, ['poster_rel','poster_path','poster','cover_rel','cover_path','cover'], "NULL");
  $descSel   = $pick($cols, ['description','caption','text','body'], "''");
  $tagsSel   = $pick($cols, ['hashtags_cache','hashtags','tags'], "''");
  $createdSel= $pick($cols, ['created_at','created','created_on','ts'], "NOW()");
  $statusSel = $pick($cols, ['status'], "'visible'");
  $userSel   = $pick($cols, ['user_id','author_id'], "0");

  $hasUsers = $tblExists($pdo, 'users');
  $likesTbl    = $tblExists($pdo, 'reel_likes')     ? 'reel_likes'     : ($tblExists($pdo,'reels_likes')    ? 'reels_likes'    : '');
  $commentsTbl = $tblExists($pdo, 'reel_comments')  ? 'reel_comments'  : ($tblExists($pdo,'reels_comments') ? 'reels_comments' : '');
  $reportsTbl  = $tblExists($pdo, 'reports') ? 'reports' : '';

  $likesSQL    = $likesTbl    ? "(SELECT COUNT(*) FROM `$likesTbl`    x WHERE x.reel_id = r.id)" : "0";
  $commentsSQL = $commentsTbl ? "(SELECT COUNT(*) FROM `$commentsTbl` x WHERE x.reel_id = r.id)" : "0";
  $reportsSQL  = $reportsTbl  ? "(SELECT COUNT(*) FROM `$reportsTbl`  rp WHERE (rp.type='reel' OR rp.entity_type='reel') AND rp.entity_id = r.id)" : "0";

  $where = ['1=1']; $args = [];
  if ($status !== '') { $where[] = "$statusSel = :st"; $args[':st'] = $status; }
  if ($userId > 0)    { $where[] = "$userSel = :uid";  $args[':uid']= $userId; }

  if ($q !== '') {
    if (preg_match('/^#(.+)/u', $q, $m))      { $where[] = "$tagsSel LIKE :tag"; $args[':tag'] = '%#'.$m[1].'%'; }
    elseif ($hasUsers && preg_match('/^@(.+)/u', $q, $m)) { $where[] = "u.display_name LIKE :un"; $args[':un'] = '%'.$m[1].'%'; }
    elseif (ctype_digit($q))                  { $where[] = "r.id = :rid";        $args[':rid'] = (int)$q; }
    else                                     { $where[] = "($descSel LIKE :qq OR $tagsSel LIKE :qq)"; $args[':qq'] = '%'.$q.'%'; }
  }

  $selUser = $hasUsers ? "u.display_name AS username" : "NULL AS username";
  $sql = "SELECT r.id, $userSel AS user_id, $selUser,
                 $createdSel AS created_at, $statusSel AS status,
                 $videoSel   AS video_path, $posterSel AS poster_path,
                 $descSel    AS description, $tagsSel  AS hashtags_cache,
                 $likesSQL   AS likes_count, $commentsSQL AS comments_count, $reportsSQL AS reports_count
          FROM `reels` r
          ".($hasUsers ? "LEFT JOIN `users` u ON u.id = $userSel" : "")."
          WHERE ".implode(' AND ', $where)."
          ORDER BY r.id DESC
          LIMIT :lim OFFSET :off";

  $st = $pdo->prepare($sql);
  foreach ($args as $k=>$v) $st->bindValue($k,$v);
  $st->bindValue(':lim',$limit,PDO::PARAM_INT);
  $st->bindValue(':off',$offset,PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $cfg  = require $ROOT . '/auth/config.php';
  $base = rtrim((string)($cfg['app_base'] ?? ''), '/');
  $toUrl = static function(?string $rel) use ($base){
    if (!$rel) return null;
    if (preg_match('~^https?://~i', $rel)) return $rel;
    return $base . '/' . ltrim($rel,'/');
  };

  $out = array_map(function($r) use ($toUrl){
    return [
      'id'             => (int)$r['id'],
      'user_id'        => (int)$r['user_id'],
      'username'       => $r['username'] ?? null,
      'created_at'     => $r['created_at'],
      'status'         => $r['status'],
      'likes_count'    => (int)$r['likes_count'],
      'comments_count' => (int)$r['comments_count'],
      'reports_count'  => (int)$r['reports_count'],
      'description'    => (string)$r['description'],
      'hashtags_cache' => (string)$r['hashtags_cache'],
      'src'            => $toUrl($r['video_path']  ?? null),
      'poster'         => $toUrl($r['poster_path'] ?? null),
    ];
  }, $rows);

  echo json_encode(['ok'=>true,'items'=>$out], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','hint'=>$e->getMessage()]);
}

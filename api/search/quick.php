<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out(int $status, array $data): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// --- Helpers (ohne Platzhalter in SHOW ...) ---
function tableExists(PDO $pdo, string $table): bool {
  $sql = "SHOW TABLES LIKE " . $pdo->quote($table);
  $res = $pdo->query($sql);
  return $res && $res->fetchColumn() !== false;
}
function colExists(PDO $pdo, string $table, string $col): bool {
  $sql = "SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($col);
  $res = $pdo->query($sql);
  return $res && $res->fetchColumn() !== false;
}

$diag = [];

try {
  // Bootstrap/DB
  $boot = __DIR__ . '/../../auth/bootstrap.php';
  if (is_file($boot)) { require_once $boot; $diag[]='bootstrap'; }
  else {
    $cfg = require __DIR__ . '/../../auth/config.php';
    require_once __DIR__ . '/../../auth/db.php';
    require_once __DIR__ . '/../../auth/guards.php';
    $diag[]='config+db+guards';
  }
  $pdo = function_exists('hh_db') ? hh_db() : db();
  $diag[] = function_exists('hh_db') ? 'hh_db()' : 'db()';

  // Inputs
  $q       = trim((string)($_GET['q'] ?? ''));
  $limitM  = max(1, min(10, (int)($_GET['limit_members'] ?? 6)));
  $limitT  = max(1, min(10, (int)($_GET['limit_threads'] ?? 6)));
  $len     = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
  if ($q === '' || $len < 2) out(200, ['ok'=>true,'items'=>['members'=>[],'threads'=>[]],'diag'=>$diag]);

  // LIKE escapen
  $esc  = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q);
  $like = '%'.$esc.'%';

  $APP_BASE = isset($APP_BASE) ? (string)$APP_BASE : '';
  $ph = $APP_BASE . '/assets/images/avatars/placeholder.png';

  // ---- Members ----
  $sqlM = "
    SELECT id, display_name, slug, avatar_path
    FROM users
    WHERE display_name LIKE :m1 ESCAPE '\\\\' OR slug LIKE :m2 ESCAPE '\\\\'
    ORDER BY display_name ASC
    LIMIT $limitM
  ";
  $stM = $pdo->prepare($sqlM);
  $stM->bindValue(':m1', $like, PDO::PARAM_STR);
  $stM->bindValue(':m2', $like, PDO::PARAM_STR);
  $stM->execute();
  $members = [];
  foreach ($stM->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
    $id = (int)($r['id'] ?? 0); if ($id<=0) continue;
    $members[] = [
      'id'=>$id,
      'name'=>(string)($r['display_name'] ?? ''),
      'avatar'=>(string)($r['avatar_path'] ?? '') ?: $ph,
      'url'=>$APP_BASE . '/user.php?id=' . $id,
      'type'=>'member'
    ];
  }

  // ---- Threads ----
  $threadTable = 'threads';
  if (!tableExists($pdo, $threadTable)) {
    foreach (['topics','forum_threads'] as $cand) { if (tableExists($pdo,$cand)) { $threadTable=$cand; break; } }
  }
  $titleCol = null;
  foreach (['title','subject','name'] as $cand) { if (colExists($pdo,$threadTable,$cand)) { $titleCol=$cand; break; } }
  if (!$titleCol) { out(200, ['ok'=>true,'items'=>['members'=>$members,'threads'=>[]],'diag'=>$diag]); }

  $orderCol = 'id';
  foreach (['updated_at','last_post_at','created_at'] as $cand) { if (colExists($pdo,$threadTable,$cand)) { $orderCol=$cand; break; } }

  $sqlT = "
    SELECT id, `$titleCol` AS title
    FROM `$threadTable`
    WHERE `$titleCol` LIKE :t1 ESCAPE '\\\\'
    ORDER BY `$orderCol` DESC
    LIMIT $limitT
  ";
  $stT = $pdo->prepare($sqlT);
  $stT->bindValue(':t1', $like, PDO::PARAM_STR);
  $stT->execute();
  $threads = [];
  foreach ($stT->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
    $id = (int)($r['id'] ?? 0); if ($id<=0) continue;
    $threads[] = [
      'id'=>$id,
      'title'=>(string)($r['title'] ?? ''),
      'url'=>$APP_BASE . '/forum/thread.php?t=' . $id,
      'type'=>'thread'
    ];
  }

  out(200, ['ok'=>true, 'items'=>['members'=>$members, 'threads'=>$threads], 'diag'=>$diag]);

} catch (Throwable $e) {
  out(500, ['ok'=>false,'error'=>'exception','msg'=>$e->getMessage(),'file'=>$e->getFile().':'.$e->getLine(),'diag'=>$diag]);
}

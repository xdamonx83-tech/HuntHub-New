<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out(int $status, array $data): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$diag = [];

try {
  // Bootstrap / Fallback
  $boot = __DIR__ . '/../../auth/bootstrap.php';
  if (is_file($boot)) { require_once $boot; $diag[]='bootstrap'; }
  else {
    $cfg = require __DIR__ . '/../../auth/config.php';
    require_once __DIR__ . '/../../auth/db.php';
    require_once __DIR__ . '/../../auth/guards.php';
    $diag[]='config+db+guards';
  }

  // DB
  if (function_exists('hh_db')) { $pdo = hh_db(); $diag[]='hh_db()'; }
  else                          { $pdo = db();    $diag[]='db()';    }

  // Inputs
  $qRaw  = (string)($_GET['q'] ?? '');
  $q     = trim($qRaw);
  $limit = max(1, min(20, (int)($_GET['limit'] ?? 8)));

  if ($q === '' || mb_strlen($q) < 2) {
    out(200, ['ok'=>true,'items'=>[],'diag'=>$diag]);
  }

  // LIKE-Safe
  $esc  = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q);
  $like = '%'.$esc.'%';

  // Wichtig: zwei Platzhalter + LIMIT inline (kein :lim!)
  $sql = "
    SELECT id, display_name, slug, avatar_path
    FROM users
    WHERE display_name LIKE :q1 ESCAPE '\\\\'
       OR slug         LIKE :q2 ESCAPE '\\\\'
    ORDER BY display_name ASC
    LIMIT $limit
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':q1', $like, PDO::PARAM_STR);
  $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // URLs
  $APP_BASE = isset($APP_BASE) ? (string)$APP_BASE : '';
  $ph = $APP_BASE . '/assets/images/avatars/placeholder.png';

  $items = [];
  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) continue;
    $items[] = [
      'id'     => $id,
      'name'   => (string)($r['display_name'] ?? ''),
      'slug'   => (string)($r['slug'] ?? ''),
      'avatar' => (string)($r['avatar_path'] ?? '') ?: $ph,
      'url'    => $APP_BASE . '/user.php?id=' . $id,
    ];
  }

  out(200, ['ok'=>true,'items'=>$items,'diag'=>$diag]);

} catch (Throwable $e) {
  out(500, [
    'ok'    => false,
    'error' => 'exception',
    'msg'   => $e->getMessage(),
    'file'  => $e->getFile().':'.$e->getLine(),
    'diag'  => $diag
  ]);
}

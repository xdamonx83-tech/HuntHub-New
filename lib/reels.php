<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';

function reels_db(): PDO { return db(); }
function reels_now(): string { return date('Y-m-d H:i:s'); }

/* Basis-SELECT mit deinem Schema */
function reels_user_sql_base(): string {
  return 'SELECT r.*,
                 COALESCE(u.display_name, CONCAT("u", r.user_id)) AS username,
                 COALESCE(u.avatar_url, u.avatar_path) AS avatar
          FROM reels r
          LEFT JOIN users u ON u.id = r.user_id';
}

if (!function_exists('reels_ensure_dirs')) {
  function reels_ensure_dirs(): void {
    $dirs = [
      __DIR__ . '/../uploads/reels',
      __DIR__ . '/../uploads/reels_src',
      __DIR__ . '/../uploads/reels_posters',
    ];
    foreach ($dirs as $d) if (!is_dir($d)) @mkdir($d, 0775, true);
  }
}

if (!function_exists('reels_parse_hashtags')) {
  /** Simple Hashtag‑Parser */
  function reels_parse_hashtags(string $text): array {
    preg_match_all('/(^|\s)#([\p{L}0-9_\.\-]{2,80})/u', $text, $m);
    $tags = array_map(fn($t) => mb_strtolower($t,'UTF-8'), $m[2] ?? []);
    $tags = array_values(array_unique($tags));
    return $tags;
  }
}

if (!function_exists('reels_insert')) {
  function reels_insert(int $userId, string $src, ?string $poster, ?string $desc, ?int $durationMs, array $filters): int {
    $pdo = reels_db();
    $stmt = $pdo->prepare('INSERT INTO reels (user_id, src, poster, description, duration_ms, filters_json, hashtags_cache, created_at) VALUES (?,?,?,?,?,?,?,?)');
    $tags = reels_parse_hashtags($desc ?? '');
    $stmt->execute([$userId, $src, $poster, $desc, $durationMs, json_encode($filters, JSON_UNESCAPED_UNICODE), $tags ? ('#'.implode(' #',$tags)) : null, reels_now()]);
    $reelId = (int)$pdo->lastInsertId();
    if ($tags) reels_upsert_hashtags($reelId, $tags);
    return $reelId;
  }
}

if (!function_exists('reels_upsert_hashtags')) {
  function reels_upsert_hashtags(int $reelId, array $tags): void {
    $pdo = reels_db();
    $pdo->beginTransaction();
    try {
      $sel = $pdo->prepare('SELECT id FROM reel_hashtags WHERE tag=?');
      $ins = $pdo->prepare('INSERT INTO reel_hashtags(tag) VALUES (?)');
      $map = $pdo->prepare('INSERT IGNORE INTO reel_hashtag_map(reel_id, tag_id) VALUES (?,?)');
      foreach ($tags as $t) {
        $sel->execute([$t]);
        $tagId = (int)($sel->fetchColumn() ?: 0);
        if (!$tagId) { $ins->execute([$t]); $tagId = (int)$pdo->lastInsertId(); }
        if ($tagId) $map->execute([$reelId, $tagId]);
      }
      $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); }
  }
}

function reels_get(int $id): ?array {
  $pdo = reels_db();
  $sql = reels_user_sql_base().' WHERE r.id=? AND r.is_deleted=0';
  try {
    $st = $pdo->prepare($sql); $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      if (empty($row['username'])) $row['username'] = 'u'.$row['user_id'];
      return $row;
    }
  } catch (PDOException $e) {
    // Fallback ohne JOIN (zur Not)
    $st = $pdo->prepare('SELECT r.* FROM reels r WHERE r.id=? AND r.is_deleted=0');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) { $row['username'] = 'u'.$row['user_id']; $row['avatar'] = null; return $row; }
  }
  return null;
}

function reels_feed(?int $viewerId, ?int $afterId, int $limit=10): array {
  $pdo = reels_db();
  $params = [];
  $where = ' WHERE r.is_deleted=0';
  if ($afterId) { $where .= ' AND r.id < ?'; $params[] = $afterId; }
  $sql = reels_user_sql_base() . $where . ' ORDER BY r.id DESC LIMIT ' . (int)$limit;

  $rows = [];
  try {
    $st = $pdo->prepare($sql); $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (PDOException $e) {
    $st = $pdo->prepare('SELECT r.* FROM reels r'. $where . ' ORDER BY r.id DESC LIMIT ' . (int)$limit);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) { $r['username'] = 'u'.$r['user_id']; $r['avatar'] = null; }
  }

  if ($viewerId && $rows) {
    $ids = array_map(fn($x)=>(int)$x['id'], $rows);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $lk = $pdo->prepare("SELECT reel_id FROM reel_likes WHERE user_id=? AND reel_id IN ($in)");
    $lk->execute(array_merge([$viewerId], $ids));
    $liked = array_flip(array_map('intval', $lk->fetchAll(PDO::FETCH_COLUMN)));
    foreach ($rows as &$r) { $r['liked'] = isset($liked[(int)$r['id']]); }
  }
  return $rows;
}

if (!function_exists('reels_like_toggle')) {
  function reels_like_toggle(int $userId, int $reelId): array {
    $pdo = reels_db();
    $pdo->beginTransaction();
    try {
      $sel = $pdo->prepare('SELECT 1 FROM reel_likes WHERE user_id=? AND reel_id=?');
      $sel->execute([$userId, $reelId]);
      $liked = (bool)$sel->fetchColumn();

      if ($liked) {
        $pdo->prepare('DELETE FROM reel_likes WHERE user_id=? AND reel_id=?')->execute([$userId,$reelId]);
        $pdo->prepare('UPDATE reels SET likes_count=GREATEST(likes_count-1,0) WHERE id=?')->execute([$reelId]);
        $liked = false;
      } else {
        $pdo->prepare('INSERT IGNORE INTO reel_likes(user_id,reel_id,created_at) VALUES (?,?,?)')->execute([$userId,$reelId,reels_now()]);
        $pdo->prepare('UPDATE reels SET likes_count=likes_count+1 WHERE id=?')->execute([$reelId]);
        $liked = true;
      }
      $cnt = (int)$pdo->query('SELECT likes_count FROM reels WHERE id='.(int)$reelId)->fetchColumn();
      $pdo->commit();
      return ['liked'=>$liked,'count'=>$cnt];
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  }
}

if (!function_exists('reels_comment_create')) {
  function reels_comment_create(int $userId, int $reelId, string $body): int {
    $pdo = reels_db();
    $stmt = $pdo->prepare('INSERT INTO reel_comments (reel_id,user_id,body,created_at) VALUES (?,?,?,?)');
    $stmt->execute([$reelId,$userId,trim($body),reels_now()]);
    $pdo->prepare('UPDATE reels SET comments_count=comments_count+1 WHERE id=?')->execute([$reelId]);
    return (int)$pdo->lastInsertId();
  }
}

function reels_comments_fetch(int $reelId, int $limit=50, ?int $afterId=null): array {
  $pdo = reels_db();
  $params = [$reelId];
  $sql = 'SELECT c.*,
                 COALESCE(u.display_name, CONCAT("u", c.user_id)) AS username,
                 COALESCE(u.avatar_url, u.avatar_path) AS avatar
          FROM reel_comments c
          LEFT JOIN users u ON u.id = c.user_id
          WHERE c.reel_id = ?';
  if ($afterId) { $sql .= ' AND c.id < ?'; $params[] = $afterId; }
  $sql .= ' ORDER BY c.id DESC LIMIT ' . (int)$limit;

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (PDOException $e) {
    // Fallback ohne JOIN, damit es nie 500 gibt
    $stmt = $pdo->prepare('SELECT * FROM reel_comments WHERE reel_id=?'
                          . ($afterId ? ' AND id < ?' : '')
                          . ' ORDER BY id DESC LIMIT ' . (int)$limit);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) { $r['username'] = 'u'.$r['user_id']; $r['avatar'] = null; }
    return $rows;
  }
}
<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/guards.php';
if (basename($_SERVER['SCRIPT_FILENAME']) !== 'wallguest.php') {
    $me = require_auth();
} else {
    $me = optional_auth();
}

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../lib/wall.php';
require_once __DIR__ . '/../../lib/wall_edits.php'; // ← neu

try {
  $db = db();
  $post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
  if ($post_id <= 0) throw new InvalidArgumentException('invalid post_id');

  $comments = wall_fetch_comments($db, $post_id);

  // --- PATCH: alle IDs einsammeln, Edits in einem Query bestimmen, Flag setzen
  $ids = [];
  $collect = function(array $list) use (&$collect, &$ids) {
    foreach ($list as $c) {
      if (!empty($c['id'])) $ids[] = (int)$c['id'];
      if (!empty($c['children']) && is_array($c['children'])) $collect($c['children']);
    }
  };
  $collect($comments);
  $ids = array_values(array_unique($ids));

  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT DISTINCT comment_id FROM wall_comment_edits WHERE comment_id IN ($in)");
    $st->execute($ids);
    $editedSet = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));

    $apply = function(array &$list) use (&$apply, $editedSet) {
      foreach ($list as &$c) {
        $cid = (int)($c['id'] ?? 0);
        $c['has_edits'] = isset($editedSet[$cid]); // ← wichtig fürs Badge
        if (!empty($c['children']) && is_array($c['children'])) $apply($c['children']);
      }
    };
    $apply($comments);
  }

  echo json_encode(['ok' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}

<?php
// /api/wall/comments.php
// GET: thread_id (int), optional: limit (1..100, default 20)
// Response: { ok:true, items:[{ id, created_at, body_html, author:{ id, username, avatar } }], count:int }

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';

function jexit(array $data): void { echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

try {
  $me = optional_auth(); // Kommentare lesen darf i. d. R. jeder eingeloggte Nutzer; passe bei Bedarf an

  $tid = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
  if ($tid <= 0) jexit(['ok'=>false,'error'=>'Missing thread_id']);

  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
  if ($limit < 1) $limit = 1; if ($limit > 100) $limit = 100;

  $pdo = db();

  // Thread prüfen + Sichtbarkeit
  $stmt = $pdo->prepare('SELECT id, author_id, wall_owner_id, visibility FROM threads WHERE id = :id LIMIT 1');
  $stmt->execute([':id'=>$tid]);
  $thread = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$thread) jexit(['ok'=>false,'error'=>'Thread not found']);

  if (function_exists('can_view_thread_wall') && !can_view_thread_wall($me, $thread)) {
    jexit(['ok'=>false,'error'=>'Not allowed']);
  }

  // Alle Kommentare außer dem ersten Post
  // (erster Post = kleinstes ID in diesem Thread)
  $sql = 'SELECT p.id, p.author_id, p.content AS body_html, p.created_at,
                 u.username, u.avatar
          FROM posts p
          JOIN users u ON u.id = p.author_id
          WHERE p.thread_id = :tid
            AND p.id <> (SELECT MIN(id) FROM posts WHERE thread_id = :tid)
          ORDER BY p.id ASC
          LIMIT ' . (int)$limit;

  $q = $pdo->prepare($sql);
  $q->execute([':tid'=>$tid]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $items = [];
  foreach ($rows as $r) {
    $items[] = [
      'id' => (int)$r['id'],
      'created_at' => $r['created_at'],
      'body_html' => (string)$r['body_html'],
      'author' => [
        'id' => (int)$r['author_id'],
        'username' => (string)($r['username'] ?? ''),
        'avatar' => (string)($r['avatar'] ?? '')
      ]
    ];
  }

  jexit(['ok'=>true, 'items'=>$items, 'count'=>count($items)]);

} catch (Throwable $e) {
  jexit(['ok'=>false,'error'=>'Server error']);
}
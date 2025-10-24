<?php
declare(strict_types=1);
// returns {ok, likes, comments, liked}

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../auth/guards.php';
$me = optional_auth(); // falls vorhanden

$postId = (int)($_GET['post_id'] ?? 0);
$out = ['ok'=>false, 'likes'=>0, 'comments'=>0, 'liked'=>false];

try{
  // Passe Tabellen/Spalten an DEINE Struktur an!
  // Beispiele:
  // likes:   post_likes(post_id, user_id)
  // comments:comments(post_id, deleted_at IS NULL)
  $q = $pdo->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id=?');
  $q->execute([$postId]); $out['likes'] = (int)$q->fetchColumn();

  $q = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id=? AND deleted_at IS NULL');
  $q->execute([$postId]); $out['comments'] = (int)$q->fetchColumn();

  if (!empty($me['id'])) {
    $q = $pdo->prepare('SELECT 1 FROM post_likes WHERE post_id=? AND user_id=? LIMIT 1');
    $q->execute([$postId, $me['id']]); $out['liked'] = (bool)$q->fetchColumn();
  }
  $out['ok'] = true;
} catch(Throwable $e){
  http_response_code(200);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);

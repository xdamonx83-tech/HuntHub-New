<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

/* ---- ID robust einlesen ---- */
$in = in_json();
$tid = 0;
if (isset($in['id']))         $tid = (int)$in['id'];
elseif (isset($in['thread_id'])) $tid = (int)$in['thread_id'];
elseif (isset($_POST['id']))     $tid = (int)$_POST['id'];
elseif (isset($_POST['thread_id'])) $tid = (int)$_POST['thread_id'];
elseif (isset($_GET['id']))       $tid = (int)$_GET['id'];
elseif (isset($_GET['thread_id']))$tid = (int)$_GET['thread_id'];

if ($tid <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

/* ---- Hilfsfunktionen ---- */
$hasTable = function(PDO $pdo, string $t): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $st->execute([$t]); return (bool)$st->fetchColumn();
};
$hasCol = function(PDO $pdo, string $t, string $c): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
};

try {
  // Thread existiert?
  $chk = $pdo->prepare("SELECT 1 FROM threads WHERE id = ? LIMIT 1");
  $chk->execute([$tid]);
  if (!$chk->fetch()) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'thread_not_found']); exit; }

  $pdo->beginTransaction();

  $postsExist = $hasTable($pdo,'posts') && $hasCol($pdo,'posts','thread_id');

  // post_likes zu Posts dieses Threads
  if ($hasTable($pdo,'post_likes') && $postsExist && $hasCol($pdo,'post_likes','post_id')) {
    $pdo->prepare("DELETE pl FROM post_likes pl JOIN posts p ON p.id=pl.post_id WHERE p.thread_id=:tid")
        ->execute([':tid'=>$tid]);
  }

  // notifications zu Posts dieses Threads
  if ($hasTable($pdo,'notifications')) {
    if ($postsExist && $hasCol($pdo,'notifications','post_id')) {
      $pdo->prepare("DELETE n FROM notifications n JOIN posts p ON p.id=n.post_id WHERE p.thread_id=:tid")
          ->execute([':tid'=>$tid]);
    }
    if ($hasCol($pdo,'notifications','thread_id')) {
      $pdo->prepare("DELETE FROM notifications WHERE thread_id=:tid")->execute([':tid'=>$tid]);
    }
  }

  // Posts löschen
  if ($postsExist) {
    $pdo->prepare("DELETE FROM posts WHERE thread_id=:tid")->execute([':tid'=>$tid]);
  }

  // thread_likes (falls vorhanden)
  if ($hasTable($pdo,'thread_likes') && $hasCol($pdo,'thread_likes','thread_id')) {
    $pdo->prepare("DELETE FROM thread_likes WHERE thread_id=:tid")->execute([':tid'=>$tid]);
  }

  // Thread selbst
  $pdo->prepare("DELETE FROM threads WHERE id=:tid")->execute([':tid'=>$tid]);

  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

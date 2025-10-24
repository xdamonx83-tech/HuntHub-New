<?php
declare(strict_types=1);

// --- Robust JSON-only bootstrap ---
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors','0');
set_error_handler(function($no,$str,$file,$line){ throw new ErrorException($str,0,$no,$file,$line); });

// --- App bootstrap ---
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';

// CSRF helper
function wall_check_csrf(string $t): bool {
  $t = (string)$t;
  foreach (['verify_csrf','csrf_verify','csrf_check','validate_csrf'] as $fn) {
    if (function_exists($fn)) return (bool) $fn($t);
  }
  if (function_exists('csrf_token')) {
    try { return hash_equals((string)csrf_token(), $t); } catch (Throwable $e) {}
  }
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  foreach (['csrf','_csrf','csrf_token','token'] as $k) {
    if (isset($_SESSION[$k])) return hash_equals((string)$_SESSION[$k], $t);
  }
  return false;
}

// Body normalizer fallback
function wall_normalize_body(string $txt): string {
  if (function_exists('normalize_forum_body')) return (string)normalize_forum_body($txt);
  if (function_exists('forum_normalize_body'))  return (string)forum_normalize_body($txt);
  return nl2br(htmlspecialchars($txt, ENT_QUOTES, 'UTF-8'));
}

try {
  require_auth();
  $me = current_user();

  $csrf = (string)($_POST['csrf'] ?? '');
  if (!wall_check_csrf($csrf)) throw new RuntimeException('Invalid CSRF token');

  $visibility = $_POST['visibility'] ?? 'public';
  if (!in_array($visibility, ['public','friends','private'], true)) $visibility = 'public';

  $ownerId = (int)($_POST['owner_id'] ?? $me['id']);
  if (function_exists('can_post_on_wall') && !can_post_on_wall($me, $ownerId)) {
    throw new RuntimeException('Not allowed to post on this wall');
  }

  $message = trim((string)($_POST['message'] ?? ''));
  if ($message === '') throw new RuntimeException('Empty message');

  $db = db();
  $db->beginTransaction();

  // Board-Id robust bestimmen (nutze vorhandenes Board, z.B. slug='wall', sonst 1. Board)
  $boardId = (int)($db->query("SELECT id FROM boards WHERE slug='wall' LIMIT 1")->fetchColumn() ?: 0);
  if ($boardId <= 0) {
    $boardId = (int)$db->query("SELECT id FROM boards ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($boardId <= 0) throw new RuntimeException('No board available');
  }

  // Thread anlegen
  $stmt = $db->prepare(
    'INSERT INTO threads (board_id, author_id, title, created_at, updated_at, wall_owner_id, visibility, posts_count, last_post_at)
     VALUES (:board, :author, :title, NOW(), NOW(), :owner, :vis, 0, NULL)'
  );
  $stmt->execute([
    ':board'  => $boardId,
    ':author' => (int)$me['id'],
    ':title'  => '',
    ':owner'  => $ownerId,
    ':vis'    => $visibility,
  ]);
  $threadId = (int)$db->lastInsertId();

  // Ersten Post
  $content = wall_normalize_body($message);
  $ins = $db->prepare('INSERT INTO posts (thread_id, author_id, content, created_at, updated_at)
                       VALUES (:tid, :uid, :content, NOW(), NOW())');
  $ins->execute([
    ':tid'     => $threadId,
    ':uid'     => (int)$me['id'],
    ':content' => $content,
  ]);

  // Thread-Zähler
  $db->prepare('UPDATE threads SET posts_count = posts_count + 1, last_post_at = NOW() WHERE id = :id')
     ->execute([':id' => $threadId]);

  $db->commit();

  ob_clean();
  echo json_encode(['ok' => true, 'thread_id' => $threadId], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
  http_response_code(500);
  ob_clean();
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

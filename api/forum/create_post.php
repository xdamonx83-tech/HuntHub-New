<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../forum/lib_forum_text.php';
require_once __DIR__ . '/../../forum/lib_link_preview.php';
// GAMIFY: ohne WebSocket – liefert freigeschaltete Erfolge zurück
require_once __DIR__ . '/../gamification/lib.php';
require_once __DIR__ . '/../../lib/gamification_helper.php';
require_once __DIR__ . '/../../lib/points.php'; // ✅ Punkte-System einbinden

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
  exit;
}

$pdo  = db();
$user = require_auth();

$cfg  = require __DIR__ . '/../../auth/config.php';
$csrf = $_POST['csrf']
     ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));

if (!check_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '', $csrf)) {
  http_response_code(419);
  echo json_encode(['ok'=>false,'error'=>'csrf_failed']);
  exit;
}

/* ------------ Eingaben ------------ */
$thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;

// Frontend liefert i.d.R. bereits fertiges HTML in "content" (siehe thread.php)
$content = trim((string)($_POST['content'] ?? ''));

// Legacy-Fallback: content_plain + content_html zusammenbauen, falls "content" leer ist
if ($content === '') {
  $plain = trim((string)($_POST['content_plain'] ?? ''));
  $extra = trim((string)($_POST['content_html'] ?? ''));
  if ($plain !== '') {
    $esc = htmlspecialchars($plain, ENT_QUOTES, 'UTF-8');
    $blocks = preg_split('/\n{2,}/', $esc) ?: [];
    $content = '<p>'.implode('</p><p>', array_map(fn($p)=>str_replace("\n", '<br>', $p), $blocks)).'</p>';
  }
  if ($extra !== '') {
    $content = $content ? ($content."\n".$extra) : $extra;
  }
}

// Bild aus Editor (Base64 Data-URL)
$editedImageDataUrl = (string)($_POST['edited_image'] ?? '');

/* ------------ Thread prüfen ------------ */
$st = $pdo->prepare("SELECT board_id, is_locked FROM threads WHERE id = ? AND deleted_at IS NULL");
$st->execute([$thread_id]);
$thread = $st->fetch(PDO::FETCH_ASSOC);

if (!$thread) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'thread_not_found']); exit; }
if ((int)$thread['is_locked'] === 1) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'thread_locked']); exit; }

/* ------------ Upload-Helfer ------------ */
$DOC = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$UPLOAD_DIR = $DOC . '/uploads/forum';
$PUBLIC_BASE = '/uploads/forum'; // öffentlich erreichbar

if (!is_dir($UPLOAD_DIR)) {
  @mkdir($UPLOAD_DIR, 0777, true);
}

function save_base64_image(string $dataUrl, string $destDir): array {
  if (!preg_match('~^data:(image/(png|jpe?g|gif|webp));base64,~i', $dataUrl, $m)) {
    return [false, 'unsupported_dataurl', null];
  }
  $mime = strtolower($m[1]);
  $ext  = $m[2] === 'jpeg' ? 'jpg' : ($m[2] === 'jpg' ? 'jpg' : $m[2]);
  $bin  = base64_decode(preg_replace('~^data:[^,]+,~', '', $dataUrl), true);
  if ($bin === false) return [false, 'decode_failed', null];

  $name = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $path = rtrim($destDir,'/') . '/' . $name;
  if (file_put_contents($path, $bin) === false) {
    return [false, 'write_failed', null];
  }
  return [true, null, $name];
}

function save_uploaded_file(array $file, string $destDir): array {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return [false, 'no_file', null];
  }
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $file['tmp_name']) ?: '';
  finfo_close($finfo);
  if (!preg_match('~^image/(png|jpe?g|gif|webp)$~i', $mime, $m)) {
    return [false, 'unsupported_mime', null];
  }
  $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
  $name = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = rtrim($destDir,'/') . '/' . $name;
  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    return [false, 'move_failed', null];
  }
  return [true, null, $name];
}

/* ------------ optional: Bild speichern ------------ */
$imageUrl = null;

if ($editedImageDataUrl !== '') {
  [$ok, $err, $fn] = save_base64_image($editedImageDataUrl, $UPLOAD_DIR);
  if ($ok && $fn) {
    $imageUrl = $PUBLIC_BASE . '/' . $fn;
    $imgHtml = '<figure class="image"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" loading="lazy" alt=""></figure>';
    $content = $content ? ($content . "\n" . $imgHtml) : $imgHtml;
  }
}

if ($imageUrl === null && !empty($_FILES['file']) && is_array($_FILES['file'])) {
  [$ok, $err, $fn] = save_uploaded_file($_FILES['file'], $UPLOAD_DIR);
  if ($ok && $fn) {
    $imageUrl = $PUBLIC_BASE . '/' . $fn;
    $imgHtml = '<figure class="image"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" loading="lazy" alt=""></figure>';
    $content = $content ? ($content . "\n" . $imgHtml) : $imgHtml;
  }
}

/* ------------ Validierung ------------ */
if ($thread_id <= 0 || $content === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'invalid_input']);
  exit;
}

/* ------------ Mentions vorbereiten ------------ */
function normalize_name(string $s): string {
  $s = preg_replace('/\s+/u', '', $s ?? '');
  return mb_strtolower($s, 'UTF-8');
}

$mentionIds = [];
$mentionsRaw = $_POST['mentions'] ?? '[]';
$mentionsArr = json_decode((string)$mentionsRaw, true);
if (is_array($mentionsArr)) {
  foreach ($mentionsArr as $m) {
    $id = (int)($m['id'] ?? 0);
    if ($id > 0 && $id !== (int)$user['id']) $mentionIds[$id] = true;
  }
}
if (!$mentionIds) {
  if (preg_match_all('/@([^\s<>&]{3,40})/u', $content, $m)) {
    $tokens = array_unique(array_map('normalize_name', $m[1]));
    if ($tokens) {
      $ph  = implode(',', array_fill(0, count($tokens), '?'));
      $sql = "SELECT id, display_name FROM users
              WHERE REPLACE(LOWER(display_name), ' ', '') IN ($ph)";
      $st = $pdo->prepare($sql);
      $st->execute($tokens);
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $uid = (int)$u['id'];
        if ($uid !== (int)$user['id']) $mentionIds[$uid] = true;
      }
    }
  }
}
$mentionIds = array_map('intval', array_keys($mentionIds));

/* ------------ Links automatisch klickbar machen ------------ */
function linkify_plain_urls(string $html): string {
  return preg_replace_callback(
    '~(?<!["\'>])(https?://[^\s"<]+)~i',
    fn($m) => '<a href="'.htmlspecialchars($m[1]).'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($m[1]).'</a>',
    $html
  );
}
$content = linkify_plain_urls($content);

/* ------------ DB-Transaktion ------------ */
$pdo->beginTransaction();
try {
  $ins = $pdo->prepare(
    "INSERT INTO posts (thread_id, author_id, content, created_at)
     VALUES (?, ?, ?, NOW())"
  );
  $ins->execute([$thread_id, (int)$user['id'], $content]);
  $newPostId = (int)$pdo->lastInsertId();

  try { lp_store_previews($pdo, $newPostId, $content); } catch (Throwable $e) {}

  $pdo->prepare("UPDATE threads SET posts_count = posts_count + 1, last_post_at = NOW() WHERE id = ?")->execute([$thread_id]);
  $pdo->prepare("UPDATE boards  SET posts_count = posts_count + 1, last_post_at = NOW() WHERE id = ?")->execute([(int)$thread['board_id']]);

  // Mentions
  $savedNoti = 0;
  if ($mentionIds) {
    $ph  = implode(',', array_fill(0, count($mentionIds), '?'));
    $val = $pdo->prepare("SELECT id FROM users WHERE id IN ($ph)");
    $val->execute($mentionIds);
    $valid = array_map('intval', $val->fetchAll(PDO::FETCH_COLUMN));
    if ($valid) {
      $insN = $pdo->prepare(
        "INSERT INTO notifications (user_id, actor_id, type, thread_id, post_id)
         VALUES (?, ?, 'mention_post', ?, ?)"
      );
      foreach ($valid as $uid) {
        if ($uid === (int)$user['id']) continue;
        try { $insN->execute([$uid, (int)$user['id'], $thread_id, $newPostId]); $savedNoti++; } catch (Throwable $e) {}
      }
    }
  }

  // Thread-Starter benachrichtigen
  try {
    $st0 = $pdo->prepare("SELECT author_id FROM posts WHERE thread_id = ? ORDER BY id ASC LIMIT 1");
    $st0->execute([$thread_id]);
    $starterId = (int)$st0->fetchColumn();
    if ($starterId && $starterId !== (int)$user['id']) {
      $insT = $pdo->prepare(
        "INSERT INTO notifications (user_id, actor_id, type, thread_id, post_id)
         VALUES (?, ?, 'comment_thread', ?, ?)"
      );
      $insT->execute([$starterId, (int)$user['id'], $thread_id, $newPostId]);
    }
  } catch (Throwable $e) {}

  $pdo->commit();

  // ✅ Punkte gutschreiben für Forum-Kommentar/Post
  $POINTS = get_points_mapping();
  award_points($pdo, (int)$user['id'], 'forum_comment', $POINTS['forum_comment'] ?? 3);

  // GAMIFY
  $achNow = [];
  try {
    awardXP($pdo, (int)$user['id'], 'new_post');
  } catch (Throwable $e) {
    error_log("Gamification failed after commit: " . $e->getMessage());
    $achNow = [];
  }

  $rendered = forum_render_text($content);

  echo json_encode([
    'ok'                     => true,
    'post_id'                => $newPostId,
    'image_url'              => $imageUrl,
    'mentions_saved'         => $savedNoti,
    'rendered_html'          => $rendered,
    'achievements_unlocked'  => $achNow,
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

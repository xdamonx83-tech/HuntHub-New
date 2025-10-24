<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../forum/lib_forum_text.php';
require_once __DIR__ . '/../../forum/lib_link_preview.php';
require_once __DIR__ . '/../../lib/gamification.php';
require_once __DIR__ . '/../../lib/gamification_helper.php';
require_once __DIR__ . '/../../lib/points.php'; // ✅ Punkte-System einbinden

// Nur POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
  exit;
}

$pdo   = db();
$user  = require_auth();
$cfg   = require __DIR__ . '/../../auth/config.php';

// CSRF prüfen
$session = $_COOKIE[$cfg['cookies']['session_name']] ?? '';
$csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
if (!check_csrf($pdo, $session, $csrf)) {
  http_response_code(419);
  echo json_encode(['ok'=>false,'error'=>'csrf_failed']);
  exit;
}

/* -------------------------------------------------------------
 * Hilfsfunktionen
 * ------------------------------------------------------------- */
function slugify(string $s): string {
  $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  $t = strtolower($t ?: $s);
  $t = preg_replace('/[^a-z0-9]+/','-',$t);
  $t = trim($t, '-');
  return $t !== '' ? $t : 'thread';
}

function normalize_name(string $s): string {
  $s = preg_replace('/\s+/u', '', $s ?? '');
  return mb_strtolower($s, 'UTF-8');
}

// Upload-Helfer wie in create_post.php
$DOC = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$UPLOAD_DIR  = $DOC . '/uploads/forum';
$PUBLIC_BASE = '/uploads/forum';
if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0777, true); }

function save_base64_image(string $dataUrl, string $destDir): array {
  if (!preg_match('~^data:(image/(png|jpe?g|gif|webp));base64,~i', $dataUrl, $m)) {
    return [false, 'unsupported_dataurl', null];
  }
  $mime = strtolower($m[1]);
  $ext  = $m[2] === 'jpeg' ? 'jpg' : ($m[2] === 'jpg' ? 'jpg' : $m[2]);
  $bin  = base64_decode(preg_replace('~^data:[^,]+,~', '', $dataUrl), true);
  if ($bin === false) return [false, 'decode_failed', null];

  $name = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = rtrim($destDir,'/') . '/' . $name;
  if (file_put_contents($dest, $bin) === false) return [false, 'write_failed', null];
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

/* -------------------------------------------------------------
 * Eingaben
 * ------------------------------------------------------------- */
$board_id = (int)($_POST['board_id'] ?? 0);
$title    = trim((string)($_POST['title'] ?? ''));
$content  = trim((string)($_POST['content'] ?? ''));

// Fallback für Content
if ($content === '') {
  $plain = trim((string)($_POST['content_plain'] ?? ''));
  $extra = trim((string)($_POST['content_html'] ?? ''));
  if ($plain !== '') {
    $esc    = htmlspecialchars($plain, ENT_QUOTES, 'UTF-8');
    $blocks = preg_split('/\n{2,}/', $esc) ?: [];
    $content = '<p>'.implode('</p><p>', array_map(fn($p)=>str_replace("\n", '<br>', $p), $blocks)).'</p>';
  }
  if ($extra !== '') {
    $content = $content ? ($content."\n".$extra) : $extra;
  }
}

// Bilder
$editedImageDataUrl = (string)($_POST['edited_image'] ?? '');
if ($editedImageDataUrl !== '') {
  [$ok, $err, $fn] = save_base64_image($editedImageDataUrl, $UPLOAD_DIR);
  if ($ok && $fn) {
    $imageUrl = $PUBLIC_BASE . '/' . $fn;
    $imgHtml  = '<figure class="image"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" loading="lazy" alt=""></figure>';
    $content  = $content ? ($content . "\n" . $imgHtml) : $imgHtml;
  }
} elseif (!empty($_FILES['file']) && is_array($_FILES['file'])) {
  [$ok, $err, $fn] = save_uploaded_file($_FILES['file'], $UPLOAD_DIR);
  if ($ok && $fn) {
    $imageUrl = $PUBLIC_BASE . '/' . $fn;
    $imgHtml  = '<figure class="image"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" loading="lazy" alt=""></figure>';
    $content  = $content ? ($content . "\n" . $imgHtml) : $imgHtml;
  }
}

// Validierung
if ($board_id <= 0 || $title === '' || $content === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'invalid_input']);
  exit;
}

// Mentions sammeln
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
      $sql = "SELECT id FROM users WHERE REPLACE(LOWER(display_name), ' ', '') IN ($ph)";
      $st  = $pdo->prepare($sql);
      $st->execute($tokens);
      foreach ($st->fetchAll(PDO::FETCH_COLUMN, 0) as $uid) {
        $uid = (int)$uid;
        if ($uid !== (int)$user['id']) $mentionIds[$uid] = true;
      }
    }
  }
}
$mentionIds = array_map('intval', array_keys($mentionIds));

// Slug generieren
$baseSlug = slugify($title);
$slug = $baseSlug;
for ($i=2; $i<9999; $i++) {
  $chk = $pdo->prepare('SELECT 1 FROM threads WHERE slug = ? LIMIT 1');
  $chk->execute([$slug]);
  if (!$chk->fetchColumn()) break;
  $slug = $baseSlug.'-'.$i;
}

$pdo->beginTransaction();
try {
  // Board checken
  $bst = $pdo->prepare('SELECT id FROM boards WHERE id = ?');
  $bst->execute([$board_id]);
  if (!$bst->fetchColumn()) {
    throw new RuntimeException('board_not_found');
  }

  // Thread anlegen
  $tst = $pdo->prepare('INSERT INTO threads (board_id, author_id, title, slug, posts_count, last_post_at, created_at) VALUES (?,?,?,?,0,NOW(),NOW())');
  $tst->execute([$board_id, (int)$user['id'], $title, $slug]);
  $thread_id = (int)$pdo->lastInsertId();

  // OP-Post anlegen
  $pst = $pdo->prepare('INSERT INTO posts (thread_id, author_id, content, created_at) VALUES (?,?,?,NOW())');
  $pst->execute([$thread_id, (int)$user['id'], $content]);
  $post_id = (int)$pdo->lastInsertId();

  // Zähler aktualisieren
  $pdo->prepare('UPDATE threads SET posts_count = posts_count + 1 WHERE id = ?')->execute([$thread_id]);
  $pdo->prepare('UPDATE boards  SET threads_count = threads_count + 1, posts_count = posts_count + 1, last_post_at = NOW() WHERE id = ?')->execute([$board_id]);

  // Link-Previews
  try { lp_store_previews($pdo, $post_id, $content); } catch (Throwable $e) {}

  // Mentions
  if ($mentionIds) {
    $insN = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, thread_id, post_id) VALUES (?, ?, 'mention_post', ?, ?)");
    foreach ($mentionIds as $uid) {
      if ($uid === (int)$user['id']) continue;
      try { $insN->execute([$uid, (int)$user['id'], $thread_id, $post_id]); } catch (Throwable $e) {}
    }
  }

  $pdo->commit();

  // ✅ Punkte gutschreiben für Forum-Thread
  $POINTS = get_points_mapping();
  award_points($pdo, (int)$user['id'], 'forum_post', $POINTS['forum_post'] ?? 7);

  // GAMIFY
  $achNow = [];
  try {
    gamify_bump($pdo, (int)$user['id'], 'threads_count', 1);
    $achNow = gamify_check($pdo, (int)$user['id'], 'thread_created');
    update_quest_progress($pdo, (int)$user['id'], 'thread_created');
  } catch (Throwable $e) { $achNow = []; }

  echo json_encode([
    'ok'        => true,
    'thread_id' => $thread_id,
    'post_id'   => $post_id,
    'slug'      => $slug,
    'achievements_unlocked' => $achNow,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

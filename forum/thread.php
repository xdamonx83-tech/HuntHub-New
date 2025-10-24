<?php
declare(strict_types=1);

if (!isset($L) || !is_array($L)) {
    $lang = $_GET['lang']          // URL hat Vorrang (falls vorhanden)
         ?? ($_COOKIE['lang'] ?? '')
         ?: (function_exists('detect_lang') ? detect_lang() : 'de');

    $lang = in_array($lang, ['de','en'], true) ? $lang : 'de';

    $file = __DIR__ . "/../lang/{$lang}.php";
    if (!is_file($file)) $file = __DIR__ . '/../lang/de.php';

    /** @var array $L */
    $L = require $file;
}
$__lang = $lang ?? (function_exists('detect_lang') ? detect_lang() : 'de');
$__comments = $L['comments'] ?? ($__lang === 'en' ? 'Comments' : 'Kommentare');
$__like     = $L['like']     ?? ($__lang === 'en' ? 'Like'      : 'Gefällt mir');
$__share    = $L['share']    ?? ($__lang === 'en' ? 'Share'     : 'Teilen');
$__liked_by    = $L['liked_by']    ?? ($__lang === 'en' ? 'Liked by'     : 'Gefällt');
$__show_more    = $L['show_more']    ?? ($__lang === 'en' ? 'Show more'     : 'Mehr anzeigen');
$__show_less    = $L['show_less']    ?? ($__lang === 'en' ? 'Show less'     : 'Weniger anzeigen');
$__comment_write    = $L['comment_write']    ?? ($__lang === 'en' ? 'Write comment...'     : 'Kommentar schreiben...');
$__video_trimm   = $L['video_trimm']    ?? ($__lang === 'en' ? 'Cut video'     : 'Video zuschneiden');
$__close    = $L['close']    ?? ($__lang === 'en' ? 'Close'     : 'Schließen');
$__start    = $L['start']    ?? ($__lang === 'en' ? 'Start'     : 'Start');
$__end    = $L['end']    ?? ($__lang === 'en' ? 'End'     : 'Ende');
$__lenght    = $L['lenght']    ?? ($__lang === 'en' ? 'Lenght'     : 'Länge');
$__duration    = $L['duration']    ?? ($__lang === 'en' ? 'Duration'     : 'Gesamtlänge');
$__working    = $L['working']    ?? ($__lang === 'en' ? 'Working...'     : 'Wird bearbeitet...');
$__video_upload    = $L['video_upload']    ?? ($__lang === 'en' ? 'Upload video'     : 'Video hochladen');
$__confirm    = $L['confirm']    ?? ($__lang === 'en' ? 'Confirm'     : 'Bestätigen');
$__really   = $L['really']    ?? ($__lang === 'en' ? 'Are you sure?'     : 'Bist du sicher?');
$__cancel    = $L['cancel']    ?? ($__lang === 'en' ? 'Cancel'     : 'Abbrechen');
$__delete    = $L['delete']    ?? ($__lang === 'en' ? 'Delete'     : 'Löschen');
$__comment_edit    = $L['comment_edit']    ?? ($__lang === 'en' ? 'Edit comment'     : 'Kommentar bearbeiten');
$__save    = $L['save']    ?? ($__lang === 'en' ? 'Save'     : 'Speichern');
$__edit    = $L['edit']    ?? ($__lang === 'en' ? 'Edit'     : 'Editieren');
// wenn kein id/slug vorhanden, aber nur Sprache gesetzt wurde → zurück zur letzten URL
if (!isset($_GET['id']) && !isset($_GET['t']) && !isset($_GET['slug'])) {
  // versuche Referer zu nehmen und nur lang zu ersetzen
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  if ($ref) {
    $u = parse_url($ref);
    parse_str($u['query'] ?? '', $qs);
    if (!empty($qs['id']) || !empty($qs['t']) || !empty($qs['slug'])) {
      $qs['lang'] = $lang;
      $target = ($u['path'] ?? '/').'?'.http_build_query($qs);
      header('Location: '.$target, true, 302);
      exit;
    }
  }
  // sonst normale 400/404
}
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../forum/lib_forum_text.php';
require_once __DIR__ . '/../forum/lib_link_preview.php';
session_set_cookie_params([
  'path'     => '/',      // Cookie überall gültig
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

$pdo = db();
$me  = function_exists('optional_auth') ? optional_auth() : null;

$cfg       = require __DIR__ . '/../auth/config.php';
$APP_BASE  = rtrim($cfg['app_base'] ?? '', '/');

// Avatar‑Fallback (entscheidend, damit Bilder nicht hart brechen)
$avatarFallback = ($APP_BASE ?: '') . '/assets/images/avatars/placeholder.png';

$csrf = issue_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '');

$threadId = isset($_GET['t']) ? (int)$_GET['t'] : 0;
$cursorId = isset($_GET['after']) ? (int)$_GET['after'] : 0;
$limit    = 30;

$uid = (int)($me['id'] ?? 0);

/** ---- Helper: Top-Liker (max $limit) holen ---- */
function fetchTopLikers(PDO $pdo, string $type, int $id, int $limit = 5): array {
  if ($type === 'thread') {
    $sql = "SELECT u.id,
                   COALESCE(u.display_name,'Gelöschter Benutzer') AS display_name,
                   u.avatar_path
            FROM thread_likes tl
            LEFT JOIN users u ON u.id = tl.user_id
            WHERE tl.thread_id = ?
            ORDER BY tl.created_at DESC
            LIMIT {$limit}";
  } else {
    $sql = "SELECT u.id,
                   COALESCE(u.display_name,'Gelöschter Benutzer') AS display_name,
                   u.avatar_path
            FROM post_likes pl
            LEFT JOIN users u ON u.id = pl.user_id
            WHERE pl.post_id = ?
            ORDER BY pl.created_at DESC
            LIMIT {$limit}";
  }
  $st = $pdo->prepare($sql);
  $st->execute([$id]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* ---------- Thread inkl. like-Infos laden ---------- */
$st = $pdo->prepare("
SELECT
  t.id,
  t.title,
  t.slug,
  t.is_locked,
  t.is_pinned,  
  t.is_locked,
  t.posts_count,
  t.likes_count,
  t.board_id AS thread_board_id,   -- <- aus threads
  CASE WHEN tl.user_id IS NULL THEN 0 ELSE 1 END AS liked_by_me,
  b.id  AS board_id,               -- <- aus boards (gleich wie oben)
  b.name AS board_name

  FROM threads t
  JOIN boards b ON b.id = t.board_id
  LEFT JOIN thread_likes tl
         ON tl.thread_id = t.id
        AND tl.user_id   = :uid
  WHERE t.id = :tid AND t.deleted_at IS NULL
  LIMIT 1
");
$st->execute([':uid'=>$uid, ':tid'=>$threadId]);
$thread = $st->fetch(PDO::FETCH_ASSOC);
$boardId = (int)($thread['board_id'] ?? $thread['thread_board_id'] ?? 0);
if (!$thread) {
  http_response_code(404);
  echo 'Thread nicht gefunden';
  exit;
}

// Thread-Top-Liker
$threadTop    = fetchTopLikers($pdo, 'thread', (int)$thread['id'], 5);
$threadOthers = max(0, (int)$thread['likes_count'] - count($threadTop));

/* ---------- Posts inkl. like-Infos laden ---------- */
$params = [
  ':tid' => $threadId,
  ':uid' => $uid
];
$cond = '';
if ($cursorId > 0) {
  $cond = " AND p.id > :after ";
  $params[':after'] = $cursorId;
}

$ps = $pdo->prepare("
  SELECT
    p.id,
    p.author_id,
    p.content,
    p.created_at,
    p.edited_at,
    p.likes_count,
    CASE WHEN pl.user_id IS NULL THEN 0 ELSE 1 END AS liked_by_me,
    COALESCE(u.display_name, 'Gelöschter Benutzer') AS display_name,
    u.avatar_path
  FROM posts p
  LEFT JOIN users u ON u.id = p.author_id
  LEFT JOIN post_likes pl
         ON pl.post_id = p.id
        AND pl.user_id = :uid
  WHERE p.thread_id = :tid
    AND p.deleted_at IS NULL
    $cond
  ORDER BY p.id ASC
  LIMIT $limit");
$ps->execute($params);
$posts = $ps->fetchAll(PDO::FETCH_ASSOC);
// OP + Kommentare aufteilen
$op = $posts[0] ?? null;
$comments = $op ? array_slice($posts, 1) : [];

// Für die OP-Avatare in der Like-Leiste (wir verwenden Post-Likes der OP)
$opTop    = $op ? fetchTopLikers($pdo, 'post', (int)$op['id'], 5) : [];
$opOthers = $op ? max(0, (int)$op['likes_count'] - count($opTop)) : 0;

// "View more" Logik: zuerst 2 Kommentare zeigen, Rest per Toggle
$initialShown = min(6, count($comments));
$hasHidden    = count($comments) > $initialShown;

$title = htmlspecialchars($thread['title']);

?>
<?php ob_start(); ?>

<?php
// … deine require_…
// einfache, sichere Autolink-Funktion für bereits gerendertes HTML
function autolink_html(string $html): string {
  return preg_replace_callback(
    '~(?<![="\'>])(https?://[^\s<]+)~i',
    function ($m) {
      $u = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
      return '<a href="'.$u.'" target="_blank" rel="noopener nofollow ugc">'.$u.'</a>';
    },
    $html
  );
}
?>
<style>

  .like-btn[aria-pressed="true"] .icon { filter: drop-shadow(0 0 4px #f29620); }
  .like-btn.is-liked .icon { color:#f29620 }

  /* Likes-Modal */
  .likes-modal.hidden { display:none }
  .likes-modal{position:fixed;inset:0;z-index:9999}
  .likes-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.6)}
  .likes-card{
    position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
    width:min(560px,92vw);max-height:70vh;overflow:auto;
    background:#0b0b0b;border:1px solid rgba(255,255,255,.12);border-radius:14px
  }
  .likes-hd{display:flex;justify-content:space-between;align-items:center;
    padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.08)}
  .likes-body{padding:10px 12px}
  .likes-row{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px}
  .likes-row:hover{background:#141414}
  .likes-row img{width:36px;height:36px;border-radius:999px;object-fit:cover;border:1px solid rgba(255,255,255,.1)}
  .likes-close{background:transparent;border:0;color:#fff;font-size:18px;cursor:pointer}

  /* Inline-Likes */
  .likes-inline{ display:flex;align-items:center;gap:12px;margin-top:12px; }
  .likes-avatars{display:flex;align-items:center}
  .likes-avatars img{
    width:36px;height:36px;object-fit:cover;border-radius:9999px;
    border:2px solid #fff;margin-left:-10px;box-shadow:0 0 0 1px rgba(255,255,255,.05);cursor:pointer;
  }
  .likes-avatars img:first-child{margin-left:0}
  .likes-text{ background:none;border:0;cursor:pointer;padding:0;margin:0;font:inherit;color:#9aa5b1; }
  .likes-text .name, .likes-text .others{color:#e6eef5}

  .post-content video, .post-content img { max-width:100%; height:auto; display:block; border-radius:8px; }
  .post-content figure { margin: .75rem 0; }
  .post-content figcaption { font-size:.875rem; opacity:.8; }

  /* Confirm-Modal */
  .confirm-modal.hidden { display:none }
  .confirm-modal { position:fixed; inset:0; z-index:10000; }
  .confirm-backdrop { position:absolute; inset:0; background:rgba(0,0,0,.6); }
  .confirm-card {
    position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    width:min(420px,92vw);
    background:#0b0b0b; border:1px solid rgba(255,255,255,.12); border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,.5);
  }
  .confirm-hd { padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08); color:#fff; font-weight:600; }
  .confirm-bd { padding:16px; color:#d1d5db; }
  .confirm-ft { padding:12px 16px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid rgba(255,255,255,.08); }
  .cm-btn { padding:.5rem .9rem; border:1px solid rgba(255,255,255,.15); border-radius:10px; background:#1b1b1b; color:#fff; cursor:pointer; }
  .cm-btn:hover { filter:brightness(1.1); }
  .cm-btn-danger { background:#8b1d1d; border-color:#a12727; }
  .cm-btn-primary { background:#1f4f99; border-color:#2b63bf; }

  /* Edit-Modal */
  .edit-modal.hidden { display:none }
  .edit-modal { position:fixed; inset:0; z-index:10010; }
  .edit-backdrop { position:absolute; inset:0; background:rgba(0,0,0,.6); }
  .edit-card {
    position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    width:min(820px,96vw); max-height:84vh; display:flex; flex-direction:column;
    background:#0b0b0b; border:1px solid rgba(255,255,255,.12); border-radius:14px;
  }
  .edit-hd { padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08); color:#fff; font-weight:600; }
  .edit-bd { padding:12px 16px; }
  .edit-bd textarea { width:100%; min-height:260px; background:#131313; color:#e5e7eb; border:1px solid rgba(255,255,255,.12); border-radius:10px; padding:10px; }
  .edit-ft { padding:12px 16px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid rgba(255,255,255,.08); }
  .btn { padding:.5rem .9rem; border:1px solid rgba(255,255,255,.15); border-radius:10px; background:#1b1b1b; color:#fff; cursor:pointer; }
  .btn:hover { filter:brightness(1.1); }

  .btn-danger { background:#8b1d1d; border-color:#a12727; }
  .btn[disabled] { opacity:.6; cursor:not-allowed; }

  /* --- Video-Trim Modal (UI) --- */
  .vt-mask{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;z-index:9999}
  .vt-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:20px}
  .vt-card{background:#0b0b0b;border:1px solid rgba(255,255,255,.1);border-radius:16px;max-width:900px;width:100%}
  .vt-hd{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.08);color:#fff}
  .vt-bd{padding:14px 16px;color:#e5e7eb}
  .vt-ft{padding:12px 16px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid rgba(255,255,255,.08)}
  .vt-meta{display:flex;justify-content:space-between;align-items:center;margin-top:8px;font-size:.95rem;color:#cbd5e1}
  .vt-range-wrap{position:relative;height:44px;margin-top:10px}
  .vt-track{position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);
    height:8px;border-radius:999px;background:#222;border:1px solid rgba(255,255,255,.08)}
  .vt-highlight{position:absolute;top:0;bottom:0;background:#f29620;border-radius:999px}
  input.vt-range{position:absolute;left:0;right:0;top:0;width:100%;height:44px;background:none;margin:0;pointer-events:none}
  input.vt-range::-webkit-slider-thumb{ -webkit-appearance:none; width:18px;height:18px;border-radius:50%; background:#fff;border:2px solid #f29620;box-shadow:0 0 0 2px #0b0b0b; pointer-events:auto; cursor:pointer}
  input.vt-range::-moz-range-thumb{ width:18px;height:18px;border-radius:50%; background:#fff;border:2px solid #f29620; box-shadow:0 0 0 2px #0b0b0b; pointer-events:auto; cursor:pointer}
  input.vt-range::-ms-thumb{ width:18px;height:18px;border-radius:50%; background:#fff;border:2px solid #f29620; box-shadow:0 0 0 2px #0b0b0b; pointer-events:auto; cursor:pointer}
  input.vt-range::-webkit-slider-runnable-track{ background:transparent; }
  input.vt-range::-moz-range-track{ background:transparent; }
  input.vt-range::-ms-track{ background:transparent; border-color:transparent; color:transparent; }
  .vt-times{display:flex;justify-content:space-between;gap:10px;margin-top:6px;font-family:ui-monospace, SFMono-Regular, Menlo, monospace;color:#cbd5e1}
  .vt-len{margin-top:6px;color:#cbd5e1}

  /* Progress */
  .vt-prog{width:100%;margin-top:10px}
  .vt-prog.hidden{display:none}
  .vt-prog-text{font-size:.95rem;color:#cbd5e1;margin-bottom:6px}
  .vt-prog-bar{height:10px;background:#222;border-radius:999px;overflow:hidden;border:1px solid rgba(255,255,255,.08)}
  .vt-prog-bar>div{height:100%;width:0%;background:#f29620;transition:width .2s ease}
  .vt-prog-bar>div.indet{position:relative;width:30%;animation:vtIndet 1.2s linear infinite}
  @keyframes vtIndet{0%{margin-left:-30%}100%{margin-left:100%}}
@keyframes commentGlow {
  0%   { box-shadow: 0 0 0 rgba(242,150,32,0); }
  25%  { box-shadow: 0 0 24px rgba(242,150,32,.65), inset 0 0 6px rgba(242,150,32,.55); }
  60%  { box-shadow: 0 0 18px rgba(242,150,32,.40), inset 0 0 4px rgba(242,150,32,.35); }
  100% { box-shadow: 0 0 0 rgba(242,150,32,0); }
}

.comment-highlight {
  position: relative;
  border-radius: 12px;           /* deckt deine Bubbles ab */
  animation: commentGlow 2.2s ease-out;
}

@media (prefers-reduced-motion: reduce){
  .comment-highlight { animation: none; box-shadow: 0 0 14px rgba(242,150,32,.5); }
}
.link-card{
  display:block; border:1px solid rgba(255,255,255,.08); border-radius:12px;
  overflow:hidden; background:#0f1113; text-decoration:none; color:#e5e7eb;
  transition:transform .08s ease, box-shadow .12s ease, border-color .12s ease;
  margin-top:10px;
}
.link-card:hover{
  border-color:rgba(255,255,255,.18);
  transform:translateY(-1px);
  box-shadow:0 6px 18px rgba(0,0,0,.35);
}
.link-card-img{
  width:100%; aspect-ratio: 16/9; background-size:cover; background-position:center;
}
.link-card-body{ padding:12px 14px; }
.link-card-domain{ font-size:.85rem; color:#9aa5b1; margin-bottom:4px }
.link-card-title{ font-weight:600; line-height:1.25; margin-bottom:4px; color:#fff }
.link-card-desc{ color:#cbd5e1; font-size:.95rem; line-height:1.35; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden }
/* 1) Flex-Item darf schrumpfen */
.post-content{
  min-width: 0;          /* wichtig bei Flex */
  flex: 1 1 auto;        /* darf Platz beanspruchen und schrumpfen */
  max-width: 100%;
  overflow-wrap: anywhere;  /* bricht sehr lange URLs/Wörter */
  word-break: break-word;   /* Fallback */
}

/* 2) Linkkarte strikt im Container halten */
.post-content .link-card{
  display:block;
  width:100%;
  max-width:100%;
  box-sizing:border-box;
}

/* 3) Optional: Schatten/Überstand innerhalb der Sprechblase kappen */
.bg-glass-5{ overflow: hidden; }   /* falls du den Card-Schatten nicht hinaus ragen lassen willst */

</style>
<input type="hidden" id="boardId" value="<?= $boardId ?>">
<main>
            <section class="pt-30p">
                <div class="section-pt">
                    <div
                        class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
                        <div class="container">
                            <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
                                <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                                    <h2 class="heading-2 text-w-neutral-1 mb-3">
                              
										<h2 class="heading-2 text-w-neutral-1 mb-3 flex items-center gap-2">
  <?= htmlspecialchars($thread['title']) ?>
  <?php if (!empty((int)$thread['is_pinned'])): ?>
    <span class="flag flag-pin"><i class="ti ti-pin"></i> Gepinnt</span>
  <?php endif; ?>
  <?php if (!empty((int)$thread['is_locked'])): ?>
    <span class="flag flag-locked"><i class="ti ti-lock"></i> Geschlossen</span>
  <?php endif; ?>
</h2>
                                    </h2>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="/" class="breadcrumb-link">
                                                Home
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
										<li class="breadcrumb-item">
                                            <a href="/forum/boards.php" class="breadcrumb-link">
                                                Forum
                                            </a>
                                        </li>
										 <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
										<li class="breadcrumb-item">
                                            <a href="/forum/board.php?b=<?= $boardId ?>" class="breadcrumb-link">
                                                <?= htmlspecialchars($thread['board_name'] ?? 'Board') ?>
                                            </a>
                                        </li>
                                 
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-current"><?= htmlspecialchars($thread['title']) ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="overlay-11"></div>
                    </div>
                </div>
            </section>
  <section class="section-pb pt-60p overflow-visible">
    <div class="container">
      <div class="grid grid-cols-12 gap-30p">
        <div class="4xl:col-start-2 4xl:col-end-8 xxl:col-span-7 col-span-12">
          <div class="grid grid-cols-1 gap-30p">

            <!-- Thread-Header -->
     

            <!-- Posts -->
            <!-- Posts -->
<div class="grid grid-cols-1 gap-30p *:bg-b-neutral-3 *:rounded-12 *:px-40p *:py-32p">

  <?php if ($op): ?>
  <!-- ==== OP-Card (Thema) ==== -->
  <div data-aos="fade-up" x-data="{ commentsShow: false }">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <img class="avatar size-60p" src="<?= htmlspecialchars($op['avatar_path'] ?: $avatarFallback) ?>" alt="user" />
        <div>
          <a href="<?= $APP_BASE ?>/user.php?id=<?= (int)$op['author_id'] ?>"
             class="text-xl-medium text-w-neutral-1 link-1 line-clamp-1 mb-1">
            <?= htmlspecialchars($op['display_name']) ?>
          </a>
          <span class="text-s-medium text-w-neutral-4">
            <?= htmlspecialchars($op['created_at']) ?>
          </span>
        </div>
      </div>
      <div x-data="dropdown" class="dropdown">
        <button @click="toggle()" class="dropdown-toggle w-fit text-white icon-32" aria-label="Menu">
          <i class="ti ti-dots"></i>
        </button>
        <div x-show="isOpen" @click.away="close()" class="dropdown-content">
   
          <?php
          $canManageOp = $me && (in_array($me['role'] ?? 'user', ['administrator','moderator'], true)
                        || (int)$me['id'] === (int)$op['author_id']);
          if ($canManageOp): ?>
            <button type="button" @click="close()" class="dropdown-item comment-edit" data-comment-id="<?= (int)$op['id'] ?>"><?= htmlspecialchars($__edit, ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" @click="close()" class="dropdown-item comment-delete" data-comment-id="<?= (int)$op['id'] ?>"><?= htmlspecialchars($__delete, ENT_QUOTES, 'UTF-8') ?></button>
          <?php else: ?>
            <button @click="close()" class="dropdown-item">Report</button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- OP Inhalt -->
    <div class="py-20p">
  <div class="text-sm text-w-neutral-4 post-content" id="content-<?= (int)$op['id'] ?>">
  <?= autolink_html(forum_render_text($op['content'])) ?>
<?php foreach (lp_get_previews($pdo, (int)$op['id']) as $pv): ?>
  <a class="link-card" href="<?= htmlspecialchars($pv['url']) ?>" target="_blank" rel="noopener">
    <?php if (!empty($pv['image_url'])): ?>
      <div class="link-card-img" style="background-image:url('<?= htmlspecialchars($pv['image_url']) ?>')"></div>
    <?php endif; ?>
    <div class="link-card-body">
      <div class="link-card-domain"><?= htmlspecialchars(parse_url($pv['url'], PHP_URL_HOST) ?? '') ?></div>
      <div class="link-card-title"><?= htmlspecialchars($pv['title'] ?: $pv['url']) ?></div>
      <?php if (!empty($pv['description'])): ?>
        <div class="link-card-desc"><?= htmlspecialchars($pv['description']) ?></div>
      <?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
</div>


    </div>
    <script type="application/json" id="comment-raw-<?= (int)$op['id'] ?>">
      <?= json_encode(['content' => (string)$op['content']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
    </script>

    <!-- OP Aktionen -->
    <div class="flex items-center justify-between flex-wrap gap-24p mb-20p">
      <div class="flex items-center gap-32p">
        <button type="button"
                class="flex items-center gap-2 text-base text-w-neutral-1 like-btn <?= !empty($op['liked_by_me']) ? 'is-liked' : '' ?>"
                data-type="post" data-id="<?= (int)$op['id'] ?>" aria-pressed="<?= !empty($op['liked_by_me']) ? 'true' : 'false' ?>">
          <i class="ti ti-heart icon-24 <?= !empty($op['liked_by_me']) ? 'text-danger' : 'text-w-neutral-4' ?>"></i>
          <?= htmlspecialchars($__like, ENT_QUOTES, 'UTF-8') ?>
          <span class="like-count ml-1"><?= (int)$op['likes_count'] ?></span>
        </button>
        <button type="button" @click="commentsShow = !commentsShow" class="flex items-center gap-2 text-base text-w-neutral-1">
          <i class="ti ti-message icon-24 text-w-neutral-4"></i>
          <?= htmlspecialchars($__comments, ENT_QUOTES, 'UTF-8') ?>
        </button>
      </div>
      <button type="button" class="flex items-center gap-2 text-base text-w-neutral-1">
        <i class="ti ti-share-3 icon-24 text-w-neutral-4"></i>
        <?= htmlspecialchars($__share, ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>

    <!-- OP Likes (Avatare + Text) -->
    <div class="flex items-center flex-wrap gap-3 md:gap-[18px] mb-20p">
      <div class="flex items-center *:avatar *:size-8 *:border *:border-white *:-ml-3 ml-3">
        <?php foreach ($opTop as $u): ?>
          <img src="<?= htmlspecialchars($u['avatar_path'] ?: $avatarFallback) ?>" alt="<?= htmlspecialchars($u['display_name']) ?>"
               class="likes-open" data-type="post" data-id="<?= (int)$op['id'] ?>" />
        <?php endforeach; ?>
      </div>
      <?php if ((int)$op['likes_count'] > 0): ?>
        <button type="button" class="text-sm text-w-neutral-4 likes-open" data-type="post" data-id="<?= (int)$op['id'] ?>">
          <?= htmlspecialchars($__liked_by, ENT_QUOTES, 'UTF-8') ?> <span class="span text-w-neutral-1"><?= htmlspecialchars($opTop[0]['display_name'] ?? 'User') ?></span>
          <?php if ($opOthers > 0): ?>
            <span class="span text-w-neutral-1">und</span> <?= (int)$opOthers ?> anderen
          <?php endif; ?>
        </button>
      <?php endif; ?>
    </div>

    <!-- Kommentare (Bubbles unter der OP-Card) -->
    <div class="pt-20p border-t border-shap">
      <div class="grid grid-cols-1 gap-20p mb-20p">
        <?php for ($i = 0; $i < $initialShown; $i++):
          $c = $comments[$i] ?? null; if (!$c) break;
          $cAvatar = $c['avatar_path'] ?: $avatarFallback;
        ?>
        <!-- kommentar <?= $i+1 ?> -->
        <div class="flex items-start gap-3" id="comment-<?= (int)$c['id'] ?>">
          <img class="avatar size-48p" src="<?= htmlspecialchars($cAvatar) ?>" alt="user" />
          <div style="width:90%">
            <div class="bg-glass-5 px-3 py-2 rounded-12">
              <p class="flex gap-2">
  <a href="<?= $APP_BASE ?>/user.php?id=<?= (int)$c['author_id'] ?>"
     class="text-m-medium text-w-neutral-1 link-1 line-clamp-1 mb-2">
     <?= htmlspecialchars($c['display_name']) ?>
  </a>
  <span class="text-s-medium text-w-neutral-4" style="font-size:10px;"> <?= htmlspecialchars($c['created_at']) ?></span>
</p>
              <div class="flex items-end max-sm:flex-wrap gap-3">
    <div class="text-sm text-w-neutral-3 post-content" id="content-<?= (int)$c['id'] ?>">
	<?= forum_render_text($c['content']) ?>
<?php foreach (lp_get_previews($pdo, (int)$c['id']) as $pv): ?>
  <a class="link-card" href="<?= htmlspecialchars($pv['url']) ?>" target="_blank" rel="noopener">
    <?php if ($pv['image_url']): ?><div class="link-card-img" style="background-image:url('<?= htmlspecialchars($pv['image_url']) ?>')"></div><?php endif; ?>
    <div class="link-card-body">
      <div class="link-card-domain"><?= htmlspecialchars(parse_url($pv['url'], PHP_URL_HOST) ?? '') ?></div>
      <div class="link-card-title"><?= htmlspecialchars($pv['title'] ?: $pv['url']) ?></div>
      <?php if ($pv['description']): ?><div class="link-card-desc"><?= htmlspecialchars($pv['description']) ?></div><?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
</div>

            
			  
              </div>
			    <script type="application/json" id="comment-raw-<?= (int)$c['id'] ?>">
    <?= json_encode(
      ['content' => (string)$c['content']],
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>
  </script>
            </div>
            <div class="flex items-center gap-16p">
    
              <div class="flex-y gap-1">
                <button type="button"
                      class="shrink-0 flex items-end gap-2 icon-20 text-w-neutral-4 like-btn <?= !empty($c['liked_by_me']) ? 'is-liked' : '' ?>"
                      data-type="post" data-id="<?= (int)$c['id'] ?>" aria-pressed="<?= !empty($c['liked_by_me']) ? 'true' : 'false' ?>">
                <i class="ti ti-heart icon-20 <?= !empty($c['liked_by_me']) ? 'text-danger' : 'text-w-neutral-4' ?>"></i>
                <span class="text-sm text-w-neutral-1 like-count"><?= (int)$c['likes_count'] ?></span>
				
              </button>
			  <button>
			  <button type="button"
        class="comment-mention"
        title="Autor erwähnen"
        data-user-id="<?= (int)$c['author_id'] ?>"
        data-user-name="<?= htmlspecialchars($c['display_name'], ENT_QUOTES) ?>">
  <i class="ti ti-share-3"></i>
</button>
			  </button>
			    <button type="button" class="comment-edit" data-comment-id="<?= (int)$c['id'] ?>">
    <i class="ti ti-edit"></i>
  </button>
           <?php $canDel = $me && ( (int)$me['id']===(int)$c['author_id'] || in_array($me['role']??'user',['administrator','moderator'],true) ); ?>
<?php if ($canDel): ?>
  <button type="button" class="comment-delete" data-comment-id="<?= (int)$c['id'] ?>" title="Kommentar löschen">
    <i class="ti ti-trash"></i>
  </button>
<?php endif; ?>    
              </div>
            </div>
          </div>
        </div>
        <?php endfor; ?>
      </div>

      <!-- versteckte Kommentare (x-collapse) -->
      <div x-show="commentsShow" x-collapse class="grid grid-cols-1 gap-20p">
        <?php for ($i = $initialShown; $i < count($comments); $i++):
          $c = $comments[$i];
          $cAvatar = $c['avatar_path'] ?: $avatarFallback;
        ?>
        <div class="flex items-start gap-3" id="comment-<?= (int)$c['id'] ?>">
          <img class="avatar size-48p" src="<?= htmlspecialchars($cAvatar) ?>" alt="user" />
          <div style="width:90%">
            <div class="bg-glass-5 px-3 py-2 rounded-12">
              <p class="flex gap-2">
  <a href="<?= $APP_BASE ?>/user.php?id=<?= (int)$c['author_id'] ?>"
     class="text-m-medium text-w-neutral-1 link-1 line-clamp-1 mb-2">
     <?= htmlspecialchars($c['display_name']) ?>
  </a>
  <span class="text-s-medium text-w-neutral-4" style="font-size:10px;"> <?= htmlspecialchars($c['created_at']) ?></span>
</p>
              <div class="flex items-end max-sm:flex-wrap gap-3">
                <div class="text-sm text-w-neutral-3 post-content" id="content-<?= (int)$c['id'] ?>">
				<?= forum_render_text($c['content']) ?>
             <?php foreach (lp_get_previews($pdo, (int)$c['id']) as $pv): ?>
  <a class="link-card" href="<?= htmlspecialchars($pv['url']) ?>" target="_blank" rel="noopener">
    <?php if ($pv['image_url']): ?><div class="link-card-img" style="background-image:url('<?= htmlspecialchars($pv['image_url']) ?>')"></div><?php endif; ?>
    <div class="link-card-body">
      <div class="link-card-domain"><?= htmlspecialchars(parse_url($pv['url'], PHP_URL_HOST) ?? '') ?></div>
      <div class="link-card-title"><?= htmlspecialchars($pv['title'] ?: $pv['url']) ?></div>
      <?php if ($pv['description']): ?><div class="link-card-desc"><?= htmlspecialchars($pv['description']) ?></div><?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
                </div>


              </div>
			    <script type="application/json" id="comment-raw-<?= (int)$c['id'] ?>">
    <?= json_encode(
      ['content' => (string)$c['content']],
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>
  </script>
            </div>
            <div class="flex items-center gap-16p">
    
              <div class="flex-y gap-1">
              <button type="button"
                      class="shrink-0 flex items-end gap-2 icon-20 text-w-neutral-4 like-btn <?= !empty($c['liked_by_me']) ? 'is-liked' : '' ?>"
                      data-type="post" data-id="<?= (int)$c['id'] ?>" aria-pressed="<?= !empty($c['liked_by_me']) ? 'true' : 'false' ?>">
                <i class="ti ti-heart icon-20 <?= !empty($c['liked_by_me']) ? 'text-danger' : 'text-w-neutral-4' ?>"></i>
                <span class="text-sm text-w-neutral-1 like-count"><?= (int)$c['likes_count'] ?></span>
				
              </button>
			  <button>
			  <button type="button"
        class="comment-mention"
        title="Autor erwähnen"
        data-user-id="<?= (int)$c['author_id'] ?>"
        data-user-name="<?= htmlspecialchars($c['display_name'], ENT_QUOTES) ?>">
  <i class="ti ti-share-3"></i>
</button>
			  </button>
			  <button type="button" class="comment-edit" data-comment-id="<?= (int)$c['id'] ?>">
    <i class="ti ti-edit"></i>
  </button>
               <?php $canDel = $me && ( (int)$me['id']===(int)$c['author_id'] || in_array($me['role']??'user',['administrator','moderator'],true) ); ?>
<?php if ($canDel): ?>
  <button type="button" class="comment-delete" data-comment-id="<?= (int)$c['id'] ?>" title="Kommentar löschen">
    <i class="ti ti-trash"></i>
  </button>
<?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endfor; ?>
      </div>

      <div class="mt-20p">
        <?php if ($hasHidden): ?>
<button
  id="commentsToggle"
  @click="commentsShow = !commentsShow"
  type="button"
  class="text-m-medium text-w-neutral-1 mb-16p block"
  x-text="commentsShow ? 'Weniger anzeigen' : 'Mehr anzeigen'">
</button>
        <?php endif; ?>

        <!-- kleine „Pill“-Eingabe wie im Screenshot (optional zusätzlich zu deinem großen Formular unten) -->
        <?php if ($me && !$thread['is_locked']): ?>
<form id="replyPill"
      action="<?= $APP_BASE ?>/api/forum/create_post.php"
      method="post" enctype="multipart/form-data"
      class="flex items-center justify-between gap-24p bg-b-neutral-2 rounded-full py-16p px-32p">

  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="thread_id" value="<?= (int)$thread['id'] ?>">
  <input type="hidden" name="mentions" id="replyMentions" value="[]">
  <input type="hidden" name="content_html" id="replyHtml" value="">

  <!-- Plain-Text Eingabe bleibt -->
  <input id="replyText" name="content_plain"
         class="w-full bg-transparent text-sm text-white placeholder:text-w-neutral-1"
         type="text" placeholder="<?= htmlspecialchars($__comment_write, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required>

  <!-- Tool-Leiste: hier wird das Popup angedockt -->
  <div id="replyToolbar" class="relative flex-y gap-3 icon-24 text-w-neutral-4">
    <button type="button" id="emojiBtn" aria-label="Emoji">
      <i class="ti ti-mood-smile-beam"></i>
    </button>

    <!-- Emoji-Popup (standardmäßig versteckt) -->
    <div id="emojiPopup" class="emoji-popup" hidden aria-hidden="true" role="dialog" aria-label="Emojis">
      <div class="emoji-box">
        <div class="emoji-head">
          <span class="emoji-title">Emojis</span>
          <button type="button" class="emoji-close" aria-label="Schließen">×</button>
        </div>
        <div class="emoji-grid" role="listbox" aria-label="Emoji-Liste">
          <!-- kompakte Auswahl; problemlos erweiterbar -->
          <button type="button" class="emo" data-emo="😀">😀</button>
          <button type="button" class="emo" data-emo="😃">😃</button>
          <button type="button" class="emo" data-emo="😄">😄</button>
          <button type="button" class="emo" data-emo="😁">😁</button>
          <button type="button" class="emo" data-emo="😆">😆</button>
          <button type="button" class="emo" data-emo="😅">😅</button>
          <button type="button" class="emo" data-emo="😂">😂</button>
          <button type="button" class="emo" data-emo="🤣">🤣</button>

          <button type="button" class="emo" data-emo="😊">😊</button>
          <button type="button" class="emo" data-emo="🙂">🙂</button>
          <button type="button" class="emo" data-emo="😉">😉</button>
          <button type="button" class="emo" data-emo="😍">😍</button>
          <button type="button" class="emo" data-emo="😘">😘</button>
          <button type="button" class="emo" data-emo="😜">😜</button>
          <button type="button" class="emo" data-emo="😎">😎</button>
          <button type="button" class="emo" data-emo="🤔">🤔</button>

          <button type="button" class="emo" data-emo="😴">😴</button>
          <button type="button" class="emo" data-emo="😇">😇</button>
          <button type="button" class="emo" data-emo="😢">😢</button>
          <button type="button" class="emo" data-emo="😭">😭</button>
          <button type="button" class="emo" data-emo="😡">😡</button>
          <button type="button" class="emo" data-emo="🤯">🤯</button>
          <button type="button" class="emo" data-emo="😱">😱</button>
          <button type="button" class="emo" data-emo="🤗">🤗</button>

          <button type="button" class="emo" data-emo="👍">👍</button>
          <button type="button" class="emo" data-emo="👎">👎</button>
          <button type="button" class="emo" data-emo="👏">👏</button>
          <button type="button" class="emo" data-emo="🙏">🙏</button>
          <button type="button" class="emo" data-emo="🙌">🙌</button>
          <button type="button" class="emo" data-emo="💪">💪</button>
          <button type="button" class="emo" data-emo="🔥">🔥</button>
          <button type="button" class="emo" data-emo="💯">💯</button>

          <button type="button" class="emo" data-emo="🎉">🎉</button>
          <button type="button" class="emo" data-emo="✨">✨</button>
          <button type="button" class="emo" data-emo="🌟">🌟</button>
          <button type="button" class="emo" data-emo="💡">💡</button>
          <button type="button" class="emo" data-emo="❤️">❤️</button>
          <button type="button" class="emo" data-emo="💚">💚</button>
          <button type="button" class="emo" data-emo="💙">💙</button>
          <button type="button" class="emo" data-emo="💛">💛</button>
        </div>
      </div>
    </div>

    <label title="Bild anhängen"><i class="ti ti-photo"></i>
      <input type="file" id="replyFile" name="file" class="hidden" accept="image/*">
    </label>
<input type="hidden" name="edited_image" id="editedImage">
<input type="file" id="replyVideo" name="video" class="hidden" accept="video/*">
    <button type="button" id="btnVideoTrim"><i class="ti ti-video"></i></button>

    <button type="submit" id="sendBtn" aria-label="Senden">
      <i class="ti ti-arrow-up-right"></i>
    </button>
  </div>
</form>
<script defer src="/assets/js/image-editor.js"></script>

<script defer src="/assets/js/emoji-popup.js"></script>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>


            <!-- Antworten -->
            <?php if ($me && !$thread['is_locked']): ?>


              <!-- Button zum Öffnen -->
              

              <!-- Modal -->
              <div id="vtModal" class="vt-mask" aria-hidden="true">
                <div class="vt-wrap">
                  <div class="vt-card">
                    <div class="vt-hd">
                      <strong><?= htmlspecialchars($__video_trimm, ENT_QUOTES, 'UTF-8') ?></strong>
                      <button type="button" id="vtClose" class="btn"><?= htmlspecialchars($__close, ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                    <div class="vt-bd">
                      <input type="file" id="vtFile" accept="video/mp4,video/quicktime,video/webm,video/x-matroska">
                      <video id="vtPreview" controls style="width:100%;max-height:50vh;margin-top:10px;background:#111"></video>

                      <!-- Doppelschieberegler -->
                      <div class="vt-range-wrap">
                        <div class="vt-track"><div id="vtHighlight" class="vt-highlight" style="left:0%;right:0%"></div></div>
                        <input id="vtStartRange" class="vt-range" type="range" min="0" max="0" step="0.1" value="0">
                        <input id="vtEndRange"   class="vt-range" type="range" min="0" max="0" step="0.1" value="0">
                      </div>
                      <div class="vt-times">
                        <span><?= htmlspecialchars($__start, ENT_QUOTES, 'UTF-8') ?>: <strong id="vtStartLabel">00:00</strong></span>
                        <span><?= htmlspecialchars($__end, ENT_QUOTES, 'UTF-8') ?>: <strong id="vtEndLabel">00:00</strong></span>
                      </div>
                      <div class="vt-len"><?= htmlspecialchars($__lenght, ENT_QUOTES, 'UTF-8') ?>: <strong id="vtLen">0:00</strong></div>
                      <div class="vt-meta">
                        <span id="vtDurLabel"><?= htmlspecialchars($__duration, ENT_QUOTES, 'UTF-8') ?>: 0:00</span>
                      </div>

                      <!-- Fortschritt -->
                      <div id="vtProgress" class="vt-prog hidden" aria-live="polite">
                        <div id="vtProgText" class="vt-prog-text"><?= htmlspecialchars($__working, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="vt-prog-bar"><div id="vtProgBar"></div></div>
                      </div>
                    </div>
                    <div class="vt-ft">
                      <button type="button" id="vtUpload" class="btn btn-primary"><?= htmlspecialchars($__video_upload, ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                  </div>
                </div>
              </div>
			  <script>
(() => {
  const textInput   = document.getElementById('replyText');
  const mentionsInp = document.getElementById('replyMentions');

  function getMentions() {
    try { return JSON.parse(mentionsInp.value || '[]'); } catch { return []; }
  }
  function setMentions(arr) {
    // Duplikate entfernen
    const seen = new Set(); const clean = [];
    for (const m of arr) {
      const id = Number(m?.id||0);
      if (!id || seen.has(id)) continue;
      seen.add(id); clean.push({id, name: String(m.name||'')});
    }
    mentionsInp.value = JSON.stringify(clean);
  }
  function insertAtCursor(el, text) {
    el.focus();
    if (typeof el.selectionStart === 'number') {
      const s = el.selectionStart, e = el.selectionEnd;
      el.value = el.value.slice(0,s) + text + el.value.slice(e);
      el.selectionStart = el.selectionEnd = s + text.length;
    } else {
      el.value += text;
    }
  }

  // Klick auf Pfeil -> @Name in Eingabe + Mentions-Array pflegen
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.comment-mention');
    if (!btn || !textInput || !mentionsInp) return;

    const uid  = Number(btn.dataset.userId || 0);
    const name = (btn.dataset.userName || '').trim();
    if (!uid || !name) return;

    // @Name vorbereiten
    const token = '@' + name.replace(/\s+/g, '');
    // Nur einfügen, wenn noch nicht im Text
    if (!textInput.value.includes(token)) {
      const prefix = textInput.value && !/\s$/.test(textInput.value) ? ' ' : '';
      insertAtCursor(textInput, prefix + token + ' ');
    }

    const list = getMentions();
    if (!list.some(m => Number(m.id) === uid)) {
      list.push({ id: uid, name: name });
      setMentions(list);
    }
  });

  // Beim Absenden Mentions mitsenden (FormData enthält hidden field automatisch)
  // -> Dein vorhandener Submit-Handler bleibt unverändert.
})();
</script>

<script>
(() => {
  const form      = document.getElementById('replyPill');
  if (!form) return;

  const textInput = document.getElementById('replyText');
  const htmlStore = document.getElementById('replyHtml');
  const fileInput = document.getElementById('replyFile');
  const sendBtn   = document.getElementById('sendBtn');

  // Plaintext -> simples HTML (Absätze + <br>)
  function plainToHtml(text) {
    if (!text) return '';
    const div = document.createElement('div'); div.textContent = text;
    const esc = div.innerHTML;
    const blocks = esc.split(/\n{2,}/).map(p => p.replace(/\n/g, '<br>'));
    return '<p>' + blocks.join('</p><p>') + '</p>';
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const raw      = (textInput.value || '').trim();
    const extraHTML= (htmlStore.value || '').trim(); // hier liegen z.B. <figure>…</figure>
    if (!raw && !extraHTML && !(fileInput?.files?.length)) return;

    const fd = new FormData(form);
    // Nicht als content_plain senden
    fd.delete('content_plain');
    // Zusammenfügen: erst Text->HTML, dann Snippets anhängen
    const html = (raw ? plainToHtml(raw) : '') + (extraHTML ? '\n' + extraHTML : '');
    fd.set('content', html);

    try {
      sendBtn.disabled = true;
      const res = await fetch(form.action, {
        method: 'POST', body: fd, credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const out = await res.json().catch(()=>({}));
      if (!res.ok || out.ok === false) throw new Error(out.error || 'Fehler');
      form.reset();
      htmlStore.value = ''; // HTML-Store leeren
      location.reload();    // simpel & robust
    } catch (err) {
      alert(err.message || 'Kommentar konnte nicht gesendet werden.');
    } finally {
      sendBtn.disabled = false;
    }
  });

  document.getElementById('emojiBtn')?.addEventListener('click', () => {
    textInput.value += (textInput.value ? ' ' : '') + '🙂';
    textInput.focus();
  });
})();
</script>

              <script>
              (() => {
                const API  = '/api/video/upload.php';

                // CSRF JIT holen: zuerst Hidden-Feld im Formular, dann <meta>
                function getCSRF() {
                  return (
                    document.querySelector('#replyForm input[name="csrf"]')?.value ||
                    document.querySelector('meta[name="csrf"]')?.content ||
                    ''
                  );
                }

                const openBtn = document.getElementById('btnVideoTrim');
                const modal   = document.getElementById('vtModal');
                const closeBtn= document.getElementById('vtClose');
                const fileIn  = document.getElementById('vtFile');
                const vid     = document.getElementById('vtPreview');

                const rStart  = document.getElementById('vtStartRange');
                const rEnd    = document.getElementById('vtEndRange');
                const hl      = document.getElementById('vtHighlight');

                const labStart= document.getElementById('vtStartLabel');
                const labEnd  = document.getElementById('vtEndLabel');
                const labLen  = document.getElementById('vtLen');
                const labDur  = document.getElementById('vtDurLabel');
                const upBtn   = document.getElementById('vtUpload');

                // Progress UI
                const progBox  = document.getElementById('vtProgress');
                const progText = document.getElementById('vtProgText');
                const progBar  = document.getElementById('vtProgBar');

                const STEP = 0.1; // Sekundenauflösung
                let dur = 0;
                let active = null; // 'start'|'end' während Drag

                // Progress Helpers
                function showProg(text){ progBox.classList.remove('hidden'); setProg(0, text || ''); }
                function hideProg(){ progBox.classList.add('hidden'); setProg(0, ''); }
                function setProg(pct, text){
                  if (typeof text === 'string') progText.textContent = text;
                  if (pct == null) { progBar.classList.add('indet'); return; }
                  progBar.classList.remove('indet');
                  const v = Math.max(0, Math.min(100, Math.round(pct)));
                  progBar.style.width = v + '%';
                }
                function makeJobId(len=16){
                  const u8 = new Uint8Array(len/2); crypto.getRandomValues(u8);
                  return Array.from(u8, b => b.toString(16).padStart(2,'0')).join('');
                }

                /* Utils */
                const clamp = (x, a, b) => Math.min(b, Math.max(a, x));
                function fmt(t){
                  t = Math.max(0, t|0); // ganze Sekunden für Anzeige
                  const m = Math.floor(t/60), s = t%60;
                  return String(m).padStart(1,'0') + ':' + String(s).padStart(2,'0');
                }
                function render(){
                  const s = parseFloat(rStart.value);
                  const e = parseFloat(rEnd.value);
                  labStart.textContent = fmt(s);
                  labEnd.textContent   = fmt(e);
                  labLen.textContent   = fmt(Math.max(0, e - s));
                  labDur.textContent   = 'Gesamtdauer: ' + fmt(dur);

                  // Highlight-Balken (links=Start, rechts=Rest ab Ende)
                  const leftPct  = dur > 0 ? (s/dur)*100 : 0;
                  const rightPct = dur > 0 ? (100 - (e/dur)*100) : 0;
                  hl.style.left  = leftPct + '%';
                  hl.style.right = rightPct + '%';
                }

                function open(){ modal.style.display='block'; document.body.style.overflow='hidden'; }
                function close(){ modal.style.display='none'; document.body.style.overflow=''; reset(); }
                function reset(){
                  hideProg();
                  fileIn.value=''; vid.src=''; active=null;
                  rStart.value='0'; rEnd.value='0'; dur=0;
                  render();
                }

                openBtn?.addEventListener('click', open);
                closeBtn?.addEventListener('click', close);
                modal?.addEventListener('click', (e)=>{ if(e.target === modal) close(); });

                // Datei gewählt → Vorschau + Regler initialisieren
                fileIn?.addEventListener('change', () => {
                  const f = fileIn.files?.[0];
                  if (!f) return;
                  const url = URL.createObjectURL(f);
                  vid.src = url;
                  vid.onloadedmetadata = () => {
                    dur = isFinite(vid.duration) ? vid.duration : 0;
                    rStart.min = rEnd.min = 0;
                    rStart.max = rEnd.max = dur.toFixed(1);
                    rStart.step = rEnd.step = STEP;
                    rStart.value = '0';
                    rEnd.value   = dur.toFixed(1);
                    render();
                  };
                });

                // Drag-Logik: Start/End-Konsistenz sicherstellen
                function onStartInput(){
                  let s = parseFloat(rStart.value);
                  let e = parseFloat(rEnd.value);
                  s = clamp(s, 0, e - STEP);
                  rStart.value = s.toFixed(1);
                  render();
                  active === 'start' && (vid.currentTime = s);
                }
                function onEndInput(){
                  let s = parseFloat(rStart.value);
                  let e = parseFloat(rEnd.value);
                  e = clamp(e, s + STEP, dur);
                  rEnd.value = e.toFixed(1);
                  render();
                  active === 'end' && (vid.currentTime = e);
                }

                rStart.addEventListener('input', onStartInput);
                rEnd.addEventListener('input', onEndInput);

                rStart.addEventListener('pointerdown', ()=> active='start');
                rEnd  .addEventListener('pointerdown', ()=> active='end');
                window.addEventListener('pointerup',   ()=> active=null);

                // Klick in die Leiste (optional): bewegt den näheren Griff
                hl.parentElement.addEventListener('click', (e) => {
                  const rect = hl.parentElement.getBoundingClientRect();
                  const ratio = clamp((e.clientX - rect.left)/rect.width, 0, 1);
                  const t = ratio * dur;
                  const ds = Math.abs(t - parseFloat(rStart.value));
                  const de = Math.abs(t - parseFloat(rEnd.value));
                  if (ds <= de) { rStart.value = clamp(t, 0, parseFloat(rEnd.value)-STEP).toFixed(1); onStartInput(); }
                  else          { rEnd.value   = clamp(t, parseFloat(rStart.value)+STEP, dur).toFixed(1); onEndInput(); }
                  vid.currentTime = t;
                });

                // Upload (XHR + optionales Polling auf progress.php)
                upBtn?.addEventListener('click', async () => {
                  const f = fileIn.files?.[0];
                  if (!f) { alert('Bitte ein Video auswählen.'); return; }
                  const start = parseFloat(rStart.value);
                  const end   = parseFloat(rEnd.value);
                  if (!(end > start)) { alert('Ende muss größer als Start sein.'); return; }

                  const csrf = getCSRF();
                  if (!csrf) { alert('CSRF-Token fehlt. Seite neu laden.'); return; }

                  const jobId = makeJobId(16);
                  const clipDur = end - start;

                  const fd = new FormData();
                  fd.append('video', f);
                  fd.append('start', String(start));
                  fd.append('end',   String(end));
                  fd.append('csrf',  csrf);
                  fd.append('job',   jobId);

                  upBtn.disabled = true;
                  upBtn.textContent = 'Lade hoch…';
                  showProg('Upload…');

                  let pollTimer = null;
                  const startPolling = () => {
                    setProg(50, 'Verarbeitung…');
                    const url = `/api/video/progress.php?job=${encodeURIComponent(jobId)}&dur=${encodeURIComponent(clipDur)}`;
                    pollTimer = setInterval(async () => {
                      try {
                        const r = await fetch(url, {cache:'no-store'});
                        if (!r.ok) { setProg(null, 'Verarbeitung…'); return; }
                        const j = await r.json().catch(()=>null);
                        if (!j || j.ok !== true) { setProg(null, 'Verarbeitung…'); return; }
                        const p = typeof j.percent === 'number' ? j.percent : null;
                        if (p == null) { setProg(null, 'Verarbeitung…'); return; }
                        setProg(50 + Math.min(49, p*0.5), `Verarbeitung… ${Math.round(p)}%`);
                        if (j.state === 'end') { clearInterval(pollTimer); pollTimer = null; }
                      } catch {
                        setProg(null, 'Verarbeitung…');
                      }
                    }, 800);
                  };

                  const xhr = new XMLHttpRequest();
                  xhr.open('POST', API, true);
                  xhr.responseType = 'json';
                  xhr.withCredentials = true;
                  xhr.setRequestHeader('Accept','application/json');
                  xhr.setRequestHeader('X-CSRF', csrf);

                  xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable && e.total) {
                      const pct = (e.loaded / e.total) * 50; // 0–50%
                      setProg(pct, `Upload… ${Math.round(pct*2)}%`);
                    }
                  };
                  xhr.upload.onload = () => {
                    upBtn.textContent = 'Schneide…';
                    // Polling versuchen – wenn progress.php fehlt, bleibt die Anzeige indeterminiert
                    startPolling();
                  };
                  xhr.onerror = () => {
                    upBtn.disabled = false;
                    upBtn.textContent = 'Clip hochladen';
                    hideProg();
                    alert('Netzwerkfehler beim Upload.');
                  };
                  xhr.onreadystatechange = () => {
                    if (xhr.readyState !== 4) return;
                    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }

                    const res = xhr;
                    const out = res.response ?? (() => { try { return JSON.parse(res.responseText); } catch { return null; } })();

                    if (res.status !== 200 || !out || out.ok !== true) {
                      const msg = (out && (out.message || out.error)) || `HTTP ${res.status}`;
                      upBtn.disabled = false;
                      upBtn.textContent = 'Clip hochladen';
                      setProg(0, 'Fehler');
                      alert('Upload fehlgeschlagen: ' + msg);
                      return;
                    }

                    setProg(100, 'Fertig');
                    upBtn.textContent = 'Einfügen…';

                    const html = `
                      <figure class="video">
                        <video controls preload="metadata" ${out.poster ? `poster="${out.poster}"` : ''}>
                          <source src="${out.video}" type="video/mp4">
                        </video>
                      </figure>
                    `;
       // neu:
const store = document.getElementById('replyHtml');
if (store) {
  store.value += (store.value ? '\n' : '') + html + '\n';
}
// optional: optisches Feedback
const txt = document.getElementById('replyText');
if (txt && !txt.value) txt.placeholder = 'Video angehängt ✓  – hier Text schreiben…';


                    upBtn.disabled = false;
                    upBtn.textContent = 'Clip hochladen';
                    hideProg();
                    close();
                  };

                  xhr.send(fd);
                });

              })();
              </script>

              <script>
                (() => {
                  const form = document.getElementById('replyForm');
                  if (!form) return;
                  form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const fd = new FormData(form);
                    try {
                      const res = await fetch(form.action, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include',
                        headers: { 'Accept': 'application/json' }
                      });
                      const out = await res.json();
                      if (!res.ok || !out?.ok) throw new Error(out?.error || 'Fehler');
                      form.reset();
                      location.reload();
                    } catch (err) {
                      alert(err.message || 'Fehler beim Antworten');
                    }
                  });
                })();
              </script>
            <?php elseif ($thread['is_locked']): ?>
              <div class="text-w-neutral-3 mt-4">Thread ist gesperrt.</div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- CSRF für Like-/Delete-/Edit-API -->
<meta name="csrf" content="<?= htmlspecialchars($csrf) ?>">

<!-- Likes Modal -->
<div id="likesModal" class="likes-modal hidden">
  <div class="likes-backdrop"></div>
  <div class="likes-card">
    <div class="likes-hd">
      <strong>Likes</strong>
      <button class="likes-close" aria-label="Schließen">✕</button>
    </div>
    <div class="likes-body" id="likesList"></div>
  </div>
</div>

<!-- Confirm-Modal -->
<div id="confirmModal" class="confirm-modal hidden" aria-modal="true" role="dialog" aria-labelledby="cm-title">
  <div class="confirm-backdrop" data-cm-close="1"></div>
  <div class="confirm-card">
    <div class="confirm-hd" id="cm-title"><?= htmlspecialchars($__confirm, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="confirm-bd" id="cm-message"><?= htmlspecialchars($__really, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="confirm-ft">
      <button type="button" class="cm-btn" data-cm-cancel="1"><?= htmlspecialchars($__cancel, ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="cm-btn cm-btn-danger" data-cm-ok="1"><?= htmlspecialchars($__delete, ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</div>

<!-- Edit-Modal -->
<div id="editModal" class="edit-modal hidden" aria-modal="true" role="dialog" aria-labelledby="em-title">
  <div class="edit-backdrop" data-em-close="1"></div>
  <div class="edit-card">
    <div class="edit-hd" id="em-title"><?= htmlspecialchars($__comment_edit, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="edit-bd">
      <textarea id="editTextarea" placeholder="Text bearbeiten …"></textarea>
    </div>
    <div class="edit-ft">
      <button type="button" class="btn" data-em-cancel="1"><?= htmlspecialchars($__cancel, ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="btn btn-primary" data-em-save="1"><?= htmlspecialchars($__save, ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</div>

<script>
(() => {
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const API_TOGGLE  = '<?= rtrim($APP_BASE, "/") ?>/api/forum/toggle_like.php';
  const API_LIST    = '<?= rtrim($APP_BASE, "/") ?>/api/forum/list_likes.php';
  const API_DELETE  = '<?= rtrim($APP_BASE, "/") ?>/api/forum/comment_delete.php';
  const API_UPDATE  = '<?= rtrim($APP_BASE, "/") ?>/api/forum/comment_update.php';
  const AVA_FALLBACK = '<?= $avatarFallback ?>';
  const PROFILE = id => '<?= $APP_BASE ?>/user.php?id=' + id;

  // ===== HTML↔Plain-Konverter für Editor =====
  function htmlToPlain(html) {
    if (!html) return '';
    html = String(html)
      .replace(/<\s*br\s*\/?>/gi, '\n')
      .replace(/<\/\s*(p|div|h[1-6]|blockquote|li)\s*>/gi, '\n')
      .replace(/<\s*li\s*>/gi, '• ');
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    let text = tmp.textContent || '';
    text = text.replace(/\r\n/g, '\n').replace(/\n{3,}/g, '\n\n');
    return text.trim();
  }
  function plainToHtml(text) {
    if (!text) return '';
    const div = document.createElement('div'); // zum Escapen
    div.textContent = text;
    const esc = div.innerHTML;
    const parts = esc.split(/\n{2,}/).map(p => p.replace(/\n/g, '<br>'));
    return '<p>' + parts.join('</p><p>') + '</p>';
  }

  function updateBtn(btn, liked, count) {
    btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
    const c = btn.querySelector('.like-count');
    if (c) c.textContent = String(count);
    btn.classList.toggle('is-liked', !!liked);
  }

  // Toggle Like (Thread + Post)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.like-btn');
    if (!btn) return;

    if (e.target.closest('.comment-delete') || e.target.closest('.comment-edit')) return;

    e.preventDefault();

    const id   = Number(btn.dataset.id || 0);
    const type = btn.dataset.type;
    if (!id || !type) return;

    const pressed = btn.getAttribute('aria-pressed') === 'true';
    const countEl = btn.querySelector('.like-count');
    const oldCnt  = countEl ? Number(countEl.textContent || 0) : 0;
    updateBtn(btn, !pressed, Math.max(0, oldCnt + (pressed ? -1 : +1)));

    try {
      const fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('type', type);
      fd.append('id',   String(id));

      const res = await fetch(API_TOGGLE, {
        method: 'POST',
        body: fd,
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const out = await res.json();
      if (!res.ok || !out?.ok) throw new Error(out?.error || 'Fehler');

      updateBtn(btn, !!out.liked, out.likes ?? 0);
    } catch (err) {
      updateBtn(btn, pressed, oldCnt);
      if ((err.message || '').includes('unauthorized')) {
        alert('Bitte zuerst einloggen.');
      } else {
        console.error(err);
        alert('Like konnte nicht gespeichert werden.');
      }
    }
  });

  // Likes-Modal
  const MOD   = document.getElementById('likesModal');
  const LIST  = document.getElementById('likesList');
  const CLOSE = MOD.querySelector('.likes-close');

  function openLikes(){ MOD.classList.remove('hidden'); document.body.style.overflow='hidden'; }
  function closeLikes(){ MOD.classList.add('hidden'); document.body.style.overflow=''; LIST.innerHTML=''; }

  CLOSE.addEventListener('click', closeLikes);
  MOD.querySelector('.likes-backdrop').addEventListener('click', closeLikes);

  document.addEventListener('click', async (e) => {
    const trg = e.target.closest('.likes-open');
    if (!trg) return;

    const type = trg.dataset.type;
    const id   = Number(trg.dataset.id || 0);
    if (!type || !id) return;

    try {
      LIST.innerHTML = '<div class="p-3 text-w-neutral-3">Lade Likes…</div>';
      openLikes();
      const res = await fetch(`${API_LIST}?type=${encodeURIComponent(type)}&id=${id}`, {
        method: 'GET',
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const out = await res.json();
      if (!res.ok || !out?.ok) throw new Error(out?.error || 'Fehler');

      if (!out.users?.length) {
        LIST.innerHTML = '<div class="p-3 text-w-neutral-3">Noch niemand</div>';
        return;
      }

      LIST.innerHTML = out.users.map(u => {
        const ava = u.avatar_path || AVA_FALLBACK;
        const name = u.display_name || 'User';
        return `
          <a class="likes-row" href="${PROFILE(u.id)}">
            <img src="${ava}" alt="${name}">
            <div class="text-w-neutral-1">${name}</div>
          </a>
        `;
      }).join('');
    } catch (err) {
      LIST.innerHTML = '<div class="p-3 text-red-400">Fehler beim Laden.</div>';
      console.error(err);
    }
  });

  // === Confirm-Dialog ===
  const CM = document.getElementById('confirmModal');
  const CM_MSG = document.getElementById('cm-message');
  const CM_OK = CM.querySelector('[data-cm-ok]');
  const CM_CANCEL = CM.querySelector('[data-cm-cancel]');
  const CM_BACKDROP = CM.querySelector('[data-cm-close]');

  function confirmModal(message, { okText='OK', cancelText='<?= htmlspecialchars($__cancel, ENT_QUOTES, 'UTF-8') ?>', danger=true, title='<?= htmlspecialchars($__confirm, ENT_QUOTES, 'UTF-8') ?>' } = {}) {
    return new Promise(resolve => {
      document.getElementById('cm-title').textContent = title;
      CM_MSG.textContent = message;
      CM_OK.textContent = okText;
      CM_CANCEL.textContent = cancelText;

      CM_OK.classList.toggle('cm-btn-danger', !!danger);
      CM_OK.classList.toggle('cm-btn-primary', !danger);

      CM.classList.remove('hidden'); document.body.style.overflow = 'hidden';

      const onOk = () => cleanup(true);
      const onCancel = () => cleanup(false);
      const onKey = (e) => { if (e.key === 'Escape') cleanup(false); };

      function cleanup(val){
        CM.classList.add('hidden'); document.body.style.overflow = '';
        CM_OK.removeEventListener('click', onOk);
        CM_CANCEL.removeEventListener('click', onCancel);
        CM_BACKDROP.removeEventListener('click', onCancel);
        document.removeEventListener('keydown', onKey);
        resolve(val);
      }

      CM_OK.addEventListener('click', onOk);
      CM_CANCEL.addEventListener('click', onCancel);
      CM_BACKDROP.addEventListener('click', onCancel);
      document.addEventListener('keydown', onKey);
      setTimeout(() => CM_OK.focus(), 0);
    });
  }

  // === Edit-Dialog ===
  const EM = document.getElementById('editModal');
  const EM_TA = document.getElementById('editTextarea');
  const EM_SAVE = EM.querySelector('[data-em-save]');
  const EM_CANCEL = EM.querySelector('[data-em-cancel]');
  const EM_BACKDROP = EM.querySelector('[data-em-close]');
  let EM_CURRENT_ID = null;

  function openEdit(id, initialText){
    EM_CURRENT_ID = id;
    EM_TA.value = initialText || '';
    EM.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => EM_TA.focus(), 0);
  }
  function closeEdit(){
    EM_CURRENT_ID = null;
    EM.classList.add('hidden');
    document.body.style.overflow = '';
    EM_TA.value = '';
    EM_SAVE.disabled = false;
  }
  EM_CANCEL.addEventListener('click', closeEdit);
  EM_BACKDROP.addEventListener('click', closeEdit);
  document.addEventListener('keydown', (e) => {
    if (!EM.classList.contains('hidden') && e.key === 'Escape') closeEdit();
  });

  // Öffnen „Bearbeiten“
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.comment-edit');
    if (!btn) return;
    const id = btn.getAttribute('data-comment-id');
    if (!id) return;

    const raw = document.getElementById('comment-raw-' + id);
    let text = '';
    if (raw) {
      try { text = JSON.parse(raw.textContent || '{}').content || ''; } catch(_) {}
    }
    // HTML aus DB → Plain für Textarea
    openEdit(id, htmlToPlain(text));
  });

  // Speichern „Bearbeiten“
  EM_SAVE.addEventListener('click', async () => {
    const id = EM_CURRENT_ID;
    if (!id) return;

    EM_SAVE.disabled = true;

    // Plaintext aus Textarea → HTML für API
    const plain   = EM_TA.value;
    const content = plainToHtml(plain);

    try {
      const res = await fetch(API_UPDATE, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'Accept': 'application/json',
          'X-CSRF': CSRF
        },
        body: new URLSearchParams({ comment_id: id, content: content })
      });
      const out = await res.json().catch(() => ({}));
      if (!res.ok || out.ok === false) {
        EM_SAVE.disabled = false;
        alert(out.error || 'Speichern fehlgeschlagen.');
        return;
      }

      // Inhalt ersetzen (servergerendert bevorzugt)
      const box = document.getElementById('content-' + id);
      if (box) {
        if (typeof out.rendered_html === 'string') {
          box.innerHTML = out.rendered_html;
        } else {
          box.innerHTML = content;
        }
      }
      // Meta „bearbeitet“ aktualisieren
      if (out.edited_at_display) {
        const meta = document.getElementById('meta-' + id);
        if (meta) {
          const created = meta.textContent.split('·')[0].trim();
          meta.textContent = created + ' · bearbeitet ' + out.edited_at_display;
        }
      }
      // Roh-JSON aktualisieren (HTML speichern)
      const raw = document.getElementById('comment-raw-' + id);
      if (raw) {
        raw.textContent = JSON.stringify({ content: content });
      }

      closeEdit();
    } catch (err) {
      console.error(err);
      EM_SAVE.disabled = false;
      alert('Netzwerkfehler beim Speichern.');
    }
  });

  // Kommentare löschen (mit Confirm-Modal)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.comment-delete');
    if (!btn) return;

    const id = btn.getAttribute('data-comment-id');
    if (!id) return;

    const ok = await confirmModal('<?= htmlspecialchars($__really, ENT_QUOTES, 'UTF-8') ?>', { okText:'<?= htmlspecialchars($__delete, ENT_QUOTES, 'UTF-8') ?>', danger:true });
    if (!ok) return;

    try {
      const res = await fetch(API_DELETE, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'Accept': 'application/json',
          'X-CSRF': CSRF
        },
        body: new URLSearchParams({ comment_id: id })
      });
      const out = await res.json().catch(() => ({}));
      if (!res.ok || out.ok === false) {
        alert(out.error || 'Löschen fehlgeschlagen.');
        return;
      }
      const row = document.getElementById('comment-' + id);
      if (row) row.remove();
    } catch (err) {
      console.error(err);
      alert('Netzwerkfehler beim Löschen.');
    }
  });
})();
</script>
<script>
(() => {
  const TOGGLE = document.getElementById('commentsToggle');

  function isVisible(el) {
    return !!(el && el.offsetParent !== null);
  }

  function waitForVisible(el, timeout = 3000) {
    return new Promise(resolve => {
      const start = performance.now();
      (function tick(){
        if (isVisible(el) || performance.now() - start > timeout) return resolve();
        requestAnimationFrame(tick);
      })();
    });
  }

  async function focusComment(hash) {
    if (!hash || !hash.startsWith('#comment-')) return;
    const el = document.querySelector(hash);
    if (!el) return;

    // Wenn der Kommentar versteckt ist: aufklappen (Button klicken)
    if (!isVisible(el) && TOGGLE) {
      TOGGLE.click();
      // kurze Wartezeit für x-collapse Animation
      await waitForVisible(el, 3000);
    }

    // Scrollen & hervorheben
// Scrollen & hervorheben
try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
catch { el.scrollIntoView(); }

// NEU: Glow auf die Bubble legen (falls vorhanden), sonst auf den gesamten Kommentar
const bubble = el.querySelector('.bg-glass-5') || el;
bubble.classList.add('comment-highlight');
setTimeout(() => bubble.classList.remove('comment-highlight'), 2200);
  }

  function handleHash() {
    focusComment(location.hash);
  }

  // Beim initialen Laden
  document.addEventListener('DOMContentLoaded', handleHash);
  // Wenn im Verlauf ein anderer #comment-… angewählt wird
  window.addEventListener('hashchange', handleHash);
})();
document.querySelectorAll('.post-content video, .hh-msg video')
  .forEach(v => v.classList.add('js-plyr'));
  
</script>
<script src="/theme/js/lightbox.js" defer></script>


<?php
$content = ob_get_clean();

$pageTitle = h($thread['title'] ?? 'Thread') . " | Hunthub Forum";
$pageDesc  = mb_substr(strip_tags((string)($op['content'] ?? '')), 0, 160);
$pageImage = first_post_image((string)($op['content'] ?? ''), $APP_BASE."/assets/images/og-default.jpg");

// JSON-LD DiscussionForumPosting
$schemaJson = hh_schema_forum_thread([
    'id'         => $thread['id'] ?? 0,
    'title'      => $thread['title'] ?? 'Thread',
    'slug'       => $thread['slug'] ?? '',
    'created_at' => $thread['created_at'] ?? '',
    'updated_at' => $thread['updated_at'] ?? '',
], [
    'author_name' => $op['author_name'] ?? ($op['author'] ?? 'User'),
    'content'     => $op['content'] ?? '',
    'image'       => first_post_image((string)($op['content'] ?? '')),
], $APP_BASE);

render_theme_page($content, $pageTitle, $pageDesc, $pageImage, $schemaJson);

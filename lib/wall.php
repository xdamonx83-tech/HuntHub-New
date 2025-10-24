<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/guards.php';
if (basename($_SERVER['SCRIPT_FILENAME']) !== 'wallguest.php') {
    $me = require_auth();
} else {
    $me = optional_auth();
}
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/wall_likes.php';
require_once __DIR__ . '/wall_edits.php';
require_once __DIR__ . '/wall_media.php';

/**
 * Karten-Render inkl. Attachments (Wrapper)
 */
if (!function_exists('wall_render_post_with_media')) {
  function wall_render_post_with_media(array $post): string {
    // Medien werden bereits IM Body von wall_render_post() ausgegeben.
    // Wrapper bleibt für Backwards-Kompatibilität, hängt aber nichts mehr an.
    return wall_render_post($post);
  }
}


/** DB handle */
function wall_db(): PDO { return db(); }

/** Helfer nur definieren, wenn sie im Projekt nicht schon existieren */
if (!function_exists('esc')) {
  function esc($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('now')) {
  function now(): string { return date('Y-m-d H:i:s'); }
}

/* -----------------------------------------------------------
 *  USERS: dynamische Feldzuordnung (username / slug / avatar)
 * ---------------------------------------------------------*/

function wall_user_field_map(PDO $db): array {
  $cols = [];
  try {
    $rs = $db->query('SHOW COLUMNS FROM `users`');
    if ($rs) while ($r = $rs->fetch(PDO::FETCH_ASSOC)) $cols[strtolower($r['Field'])] = true;
  } catch (Throwable $e) {}

  $pick = function(array $cands) use ($cols) {
    foreach ($cands as $c) if (isset($cols[strtolower($c)])) return $c;
    return null;
  };

  return [
    'username' => $pick(['username','user_name','name','display_name','fullname','login','user','nick','nickname','email']),
    'slug'     => $pick(['slug','user_slug','handle','vanity','nickname','nick']),
    'avatar'   => $pick(['avatar','avatar_url','photo','picture','profile_image','image','avatar_path','profile_photo_url']),
  ];
}

/** SELECT-Teil für users */
function wall_user_select_sql(PDO $db): string {
  $cols = [];
  $rs = $db->query('SHOW COLUMNS FROM users');
  if ($rs) while ($r = $rs->fetch(PDO::FETCH_ASSOC)) $cols[strtolower($r['Field'])] = true;

  $pick = function(array $cands) use ($cols) {
    foreach ($cands as $c) if (isset($cols[strtolower($c)])) return $c;
    return null;
  };

  $usernameCol = $pick(['username','user_name','name','display_name','fullname','login','user','nick','nickname','email']);
  $slugCol     = $pick(['slug','user_slug','handle','vanity','nickname','nick']);

  $hasAvatarUrl  = isset($cols['avatar_url']);
  $hasAvatarPath = isset($cols['avatar_path']);
  if ($hasAvatarUrl && $hasAvatarPath) {
    $avatarExpr = "COALESCE(NULLIF(u.`avatar_url`, ''), NULLIF(u.`avatar_path`, '')) AS avatar";
  } else {
    $avatarCol = $pick(['avatar','avatar_url','photo','picture','profile_image','image','avatar_path','profile_photo_url']);
    $avatarExpr = $avatarCol ? "u.`{$avatarCol}` AS avatar" : "NULL AS avatar";
  }

  $parts = [];
  $parts[] = $usernameCol ? "u.`{$usernameCol}` AS username" : "NULL AS username";
  $parts[] = $slugCol     ? "u.`{$slugCol}` AS slug"         : "NULL AS slug";
  $parts[] = $avatarExpr;
  return implode(', ', $parts);
}

/* ----------------------------
 *  Validierung & Utilities
 * --------------------------*/

function wall_validate_content(?string $plain, ?string $html): bool {
  return (trim((string)$plain) !== '' || trim((string)$html) !== '');
}

function wall_avatar_url(?string $avatar): string {
  $a = trim((string)$avatar);
  if ($a === '') return '/assets/images/avatar-default.png';
  if (preg_match('~^https?://~i', $a)) return $a;       // absolut
  if (isset($a[0]) && $a[0] === '/') return $a;         // root-relativ
  if (stripos($a, 'uploads/avatars/') === 0) return '/' . $a;
  return '/uploads/avatars/' . ltrim($a, '/');          // nur Dateiname
}

/* ----------------------------
 *  Datenoperationen
 * --------------------------*/

function wall_insert_post(int $user_id, ?string $plain, ?string $html): int {
  $db = wall_db();
  $st = $db->prepare('INSERT INTO wall_posts (user_id, content_plain, content_html, created_at) VALUES (?,?,?,NOW())');
  $st->execute([$user_id, $plain, $html]);
  return (int)$db->lastInsertId();
}

function wall_get_post(PDO $db, int $post_id): ?array {
  $sel = wall_user_select_sql($db);
  $sql = "SELECT p.*, $sel
            FROM wall_posts p
            JOIN users u ON u.id = p.user_id
           WHERE p.id = ? AND p.deleted_at IS NULL
           LIMIT 1";
  $q = $db->prepare($sql);
  $q->execute([$post_id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function wall_fetch_feed(PDO $db, ?int $after_id = null, int $limit = 10): array {
  $sel   = wall_user_select_sql($db);
  $limit = max(1, min(50, (int)$limit));

  if ($after_id) {
    $st = $db->prepare('SELECT created_at, id FROM wall_posts WHERE id = ? LIMIT 1');
    $st->execute([$after_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [];

    $sql = "SELECT p.*, $sel
              FROM wall_posts p
              JOIN users u ON u.id = p.user_id
             WHERE p.deleted_at IS NULL
               AND (
                 p.created_at < ?
                 OR (p.created_at = ? AND p.id < ?)
               )
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT $limit";
    $q = $db->prepare($sql);
    $q->execute([$row['created_at'], $row['created_at'], (int)$row['id']]);
  } else {
    $sql = "SELECT p.*, $sel
              FROM wall_posts p
              JOIN users u ON u.id = p.user_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT $limit";
    $q = $db->query($sql);
  }
  return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function wall_insert_comment(int $user_id, int $post_id, ?int $parent_comment_id, ?string $plain, ?string $html): int {
  $db = wall_db();

  $chk = $db->prepare('SELECT id FROM wall_posts WHERE id=? AND deleted_at IS NULL');
  $chk->execute([$post_id]);
  if (!$chk->fetch()) throw new RuntimeException('post_not_found');

  if ($parent_comment_id !== null) {
    $pc = $db->prepare('SELECT id, parent_comment_id, post_id FROM wall_comments WHERE id=? AND deleted_at IS NULL');
    $pc->execute([$parent_comment_id]);
    $prow = $pc->fetch(PDO::FETCH_ASSOC);
    if (!$prow) throw new RuntimeException('parent_not_found');
    if (!empty($prow['parent_comment_id'])) throw new RuntimeException('depth_exceeded');
    if ((int)$prow['post_id'] !== $post_id) throw new RuntimeException('cross_post_parent');
  }

  $st = $db->prepare('INSERT INTO wall_comments (post_id, parent_comment_id, user_id, content_plain, content_html, created_at)
                      VALUES (?,?,?,?,?,NOW())');
  $st->execute([$post_id, $parent_comment_id, $user_id, $plain, $html]);
  return (int)$db->lastInsertId();
}

function wall_fetch_comments(PDO $db, int $post_id): array {
  require_once __DIR__ . '/wall_media.php'; // für comment_media_list/render

  $sel = wall_user_select_sql($db);
  $q = $db->prepare(
    "SELECT c.*, u.id AS user_id, $sel,
            EXISTS(SELECT 1 FROM wall_comment_edits e WHERE e.comment_id = c.id) AS has_edits,
            c.under_review_at,
            c.content_deleted_at
       FROM wall_comments c
       JOIN users u ON u.id = c.user_id
      WHERE c.post_id = ? AND c.deleted_at IS NULL
      ORDER BY c.id DESC"
  );
  $q->execute([$post_id]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;

  // Karte der Comments für das spätere Verschachteln
  $map = [];

  foreach ($rows as $c) {
    $id = (int)$c['id'];

    // Likes
    $c['like_button_html']  = wall_like_button_html($db, 'comment', $id, $uid);
    $c['like_summary_html'] = wall_like_summary_html($db, 'comment', $id);

    // Medien
    $hasMedia = !empty(comment_media_list($id)); // scannt /uploads/comments/c{id}
    $c['media_html'] = $hasMedia ? comment_render_attachments_html($id) : '';

    // Under review Flag (bool fürs Frontend)
    $c['under_review'] = !empty($c['under_review_at']);

    // Deleted-Content nur, wenn wirklich gar kein Inhalt (weder Text/HTML noch Medien)
    $noText = (trim((string)($c['content_plain'] ?? '')) === '') &&
              (trim((string)($c['content_html'] ?? '')) === '');
    $c['deleted_content'] = !empty($c['content_deleted_at']) || ($noText && !$hasMedia);

    // Kinder-Array vorbereiten
    $c['children'] = [];

    $map[$id] = $c;
  }

  // Verschachteln (max. 1 Ebene)
  $roots = [];
  foreach ($map as $id => &$c) {
    if (!empty($c['parent_comment_id'])) {
      $pid = (int)$c['parent_comment_id'];
      if (isset($map[$pid])) {
        $map[$pid]['children'][] = &$c;
      }
    } else {
      $roots[] = &$c;
    }
  }
  unset($c);

  return $roots;
}


/* ----------------------------
 *  Render
 * --------------------------*/
function wall_comment_count(PDO $db, int $post_id): int {
  $st = $db->prepare('SELECT COUNT(*) FROM wall_comments WHERE post_id = ? AND deleted_at IS NULL');
  $st->execute([$post_id]);
  return (int)$st->fetchColumn();
}

/** Baut die Post-Card (app_base() wird intern geholt) */
function wall_render_post(array $post): string {
  // APP_BASE ermitteln
  $APP_BASE = '';
  if (function_exists('app_base')) {
    $APP_BASE = (string)app_base();
  } else {
    $cfgFile = __DIR__ . '/../auth/config.php';
    $cfg = file_exists($cfgFile) ? (require $cfgFile) : [];
    $APP_BASE = rtrim((string)($cfg['app_base'] ?? ''), '/');
  }

  $id   = (int)($post['id'] ?? 0);
  $name = (string)($post['username'] ?? ('User #'.(int)($post['user_id'] ?? 0)));
  $slug = (string)($post['slug'] ?? '');
  $av   = wall_avatar_url($post['avatar'] ?? '');
  $dt   = (string)($post['created_at'] ?? '');
  

  $plain = (string)($post['content_plain'] ?? '');
  if ($plain === "\u{200B}") $plain = ''; // Zero-Width-Space ausblenden
  $plain = trim($plain);
  $plainHtml = $plain !== '' ? nl2br(esc($plain)) : '';

  $media = trim((string)($post['content_html'] ?? ''));
  $mediaHtml = $media !== '' ? $media : '';

  $uid  = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
  $db   = wall_db();
  $editedBadge = wall_render_edited_badge($db, 'post', $id);
$commentCount = wall_comment_count($db, $id);
  $counts = wall_reaction_count($db,'post',$id);
  arsort($counts);
  $icons = [
    'like'=>'/assets/images/like.webp',
    'love'=>'/assets/images/love.webp',
    'haha'=>'/assets/images/haha.webp',
    'wow'=>'/assets/images/wow.webp',
    'sad'=>'/assets/images/sad.webp',
    'angry'=>'/assets/images/angry.webp'
  ];
  $total = array_sum($counts);

  $underReview = !empty($post['under_review_at']);
  $viewerId = (int)($_SESSION['user']['id'] ?? 0);
  $isAdmin  = (string)($_SESSION['user']['role'] ?? '') === 'admin' || (string)($_SESSION['user']['role'] ?? '') === 'administrator';
  $isOwner  = $viewerId && $viewerId === (int)($post['user_id'] ?? 0);

  if ($underReview && !$isAdmin && !$isOwner) {
    return '<article class="hh-card" data-post-id="'.(int)$post['id'].'"><div class="py-20p text-center opacity-80">Beitrag wird geprüft.</div></article>';
  }

  ob_start();
  ?>
  <?php
$__me    = $GLOBALS['me'] ?? [];
$uid     = (int)($__me['id'] ?? 0);
$postUid = (int)($post['user_id'] ?? 0);

$role     = strtolower((string)($__me['role'] ?? ''));
$isAdmin  = ($role === 'administrator' || $role === 'admin' || !empty($__me['is_admin'])
             || (isset($__me['roles']) && is_array($__me['roles']) && in_array('admin', $__me['roles'], true)));

$isOwner = $uid && ($uid === $postUid);
?>
<style id="hh-hide-inline-comments">
  /* Kommentare im Feed nie anzeigen */
  #feed .hh-comments{ display:none !important; }
</style>

  <article class="hh-card hh-post" data-post-id="<?= $id ?>">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <img class="avatar size-60p" src="<?= esc($av) ?>" alt="user" />
        <div>
          <a href="<?= esc($APP_BASE) ?>/user.php?id=<?= esc($slug) ?>"
             class="text-xl-medium text-w-neutral-1 link-1 line-clamp-1 mb-1">
             <?= esc($name) ?>
          </a>
    <a class="text-s-medium text-w-neutral-4 hover:underline"
   href="<?= esc($APP_BASE) ?>/p/<?= (int)$id ?><?= $slug ? '-' . esc($slug) : '' ?>">
  <?= esc($dt) ?>
</a>

          <?php if (!empty($editedBadge)): ?>
            <div class="hh-post-meta text-xs opacity-70 mt-1 flex items-center gap-2">
              <?= $editedBadge ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div x-data="dropdown" class="dropdown">
        <button @click="toggle()" class="dropdown-toggle w-fit text-white icon-32">
          <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-dots"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /></svg>
        </button>
        <div x-show="isOpen" @click.away="close()" class="dropdown-content">
          <?php if ($isOwner || $isAdmin): ?>
          <button type="button" class="dropdown-item hh-btn-edit-post" data-post-id="<?= (int)$post['id'] ?>">Bearbeiten</button>
          <button type="button" class="dropdown-item text-red-400 hh-btn-delete-post" data-post-id="<?= (int)$post['id'] ?>">Löschen</button>
          <?php endif; ?>
          <button type="button" class="dropdown-item" data-report-post data-post-id="<?= (int)$post['id'] ?>">Melden</button>
        </div>
      </div>
    </div>

    <!-- Post Body -->
    <div class="py-20p">
      <div class="hh-post-bd hh-post-content">
        <?= $plainHtml ? "<p class=\"hh-text\">$plainHtml</p>" : "" ?>
        <?= $mediaHtml ? "<div class=\"hh-media\">$mediaHtml</div>" : "" ?>
		  <?php
    // ⬇️ NEU: Hochgeladene Bilder/Videos (WEBP/GIF) direkt im Body anzeigen
    echo wall_render_post_attachments_html((int)$post['id']);
  ?>
  
  
  
  <div id="hh-media-viewer" aria-hidden="true">
  <div class="hhmv-backdrop"></div>
  <div class="hhmv-panel">
    <img id="hh-media-viewer-img" alt="">
    <button type="button" class="hhmv-close" aria-label="Schließen">✕</button>
  </div>
</div>

  
  
      </div>
    </div>

    <!-- Footer -->
    <div x-data="{ commentsShow: false }" @click.outside="commentsShow = false">
	    <?php if ($total > 0): ?>
<a href="#"
   class="btn-likers flex items-center ml-3 gap-2"
   data-entity-type="post"
   data-entity-id="<?= $id ?>">
  <span class="flex -space-x-3">
    <?php foreach(array_keys($counts) as $r): ?>
      <img class="inline-block h-4 w-4 rounded-full ring-2 ring-white"
           src="<?= $icons[$r] ?>" alt="<?= $r ?>" />
    <?php endforeach; ?>
  </span>
  <span class="text-sm text-w-neutral-1 whitespace-nowrap"><?= $total ?></span>
</a>

      <?php endif; ?>
      <div class="flex items-center justify-between flex-wrap gap-24p mb-20p">
	  
	  
	  
        <div class="flex items-center gap-32p">
          <?= wall_like_button_html($db,'post',$id, (int)($_SESSION['user']['id'] ?? 0)) ?>
<button
  type="button"

  class="flex items-center gap-2 text-base text-w-neutral-1 hh-open-comments"
  data-open-comments
  data-post-id="<?= $id ?>"
>
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M1.68687 7.06852C2.31315 4.39862 4.39783 2.31394 7.06773 1.68767C8.9959 1.23538 11.0026 1.23538 12.9307 1.68767C15.6006 2.31394 17.6853 4.39862 18.3116 7.06852C18.7639 8.99669 18.7639 11.0034 18.3116 12.9315C17.6853 15.6014 15.6006 17.6861 12.9307 18.3124C11.0026 18.7647 8.9959 18.7647 7.06773 18.3124C4.39783 17.6861 2.31315 15.6014 1.68687 12.9315C1.23458 11.0034 1.23458 8.99669 1.68687 7.06852Z" stroke="currentColor" stroke-width="1"></path>
<path d="M7 7H11M7 13H10M7 10H13" class="icon_main" stroke-width="1" stroke-linecap="round"></path>
</svg>
</svg>
  <?= ($commentCount === 1) ? '1 Kommentar' : ($commentCount . ' Kommentare') ?> 
</button>

        </div>

      </div>

  

      <!-- Comments -->
      <section class="hh-comments"
               data-endpoint="<?= esc($APP_BASE) ?>/api/wall/comments.php?post_id=<?= $id ?>">
        <div class="mt-20p">
          <div class="hh-comment-form hh-composer flex items-center justify-between gap-24p rounded-full py-16p px-32p"
               data-endpoint="<?= esc($APP_BASE) ?>/api/wall/comment_create.php"
               data-post-id="<?= $id ?>">
            <textarea class="hh-input w-full resize-none overflow-hidden rounded-12 px-3 py-2 bg-glass-5 text-sm text-w-neutral-1 placeholder:text-w-neutral-3"
                      rows="1"
                      placeholder="Antwort schreiben…"
                      maxlength="1000"
                      oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
            <div class="flex-y gap-3 icon-24 text-w-neutral-4">
              <button type="button" class="hh-emoji-btn hh-emoji-cmt" data-emoji-placement="top">
			<svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" class="feather feather-emoticons">
  <!-- Gesichtskreis + Mund: weiß -->
  <g fill="none" stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="11"></circle>
    <path d="M7.6 14.8c1.2 1.7 3 2.2 4.4 2.2s3.2-.5 4.4-2.2"></path>
  </g>
  <!-- Augen: rot -->
  <rect x="8" y="9" width="2" height="2" rx="0.4" fill="#e74c3c"></rect>
  <rect x="14" y="9" width="2" height="2" rx="0.4" fill="#e74c3c"></rect>
</svg></button>
              <label>
			 <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="feather feather-image"><rect x="11" y="5" width="4" height="4" rx="2" class="icon_main" stroke-width="1"></rect><path d="M2.71809 15.2014L4.45698 13.4625C6.08199 11.8375 8.71665 11.8375 10.3417 13.4625L12.0805 15.2014M12.0805 15.2014L12.7849 14.497C14.0825 13.1994 16.2143 13.2961 17.3891 14.7059L17.802 15.2014M12.0805 15.2014L14.6812 17.802M1.35288 13.0496C0.882374 11.0437 0.882374 8.95626 1.35288 6.95043C2.00437 4.17301 4.17301 2.00437 6.95043 1.35288C8.95626 0.882375 11.0437 0.882374 13.0496 1.35288C15.827 2.00437 17.9956 4.17301 18.6471 6.95044C19.1176 8.95626 19.1176 11.0437 18.6471 13.0496C17.9956 15.827 15.827 17.9956 13.0496 18.6471C11.0437 19.1176 8.95626 19.1176 6.95044 18.6471C4.17301 17.9956 2.00437 15.827 1.35288 13.0496Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg><input type="file" class="hh-file-image" accept="image/*" multiple hidden></label>

              <div class="hh-previews"></div>
              <input type="hidden" name="content_html" value="">
              <input type="file" class="hh-file-video" accept="video/*" hidden>
            </div>
            <button class="hh-btn" type="button" data-action="submit">Senden</button>
          </div>
        </div>
        <div class="hh-comment-list"></div>
      </section>
    </div>
  </article>


<!-- Kommentare-Modal -->
<div id="hh-comments-modal" class="hhc hidden" aria-hidden="true" style="position:fixed;inset:0;z-index:9999">
  <div class="hhc__backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(2px)"></div>
  <div class="hhc__dialog"
       style="position:relative;z-index:1;margin:4vh auto;width:min(1000px,95vw);height:88vh;
              background:rgba(16,16,18,.95);border:1px solid rgba(255,255,255,.08);
              border-radius:16px;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,.5)">
    <div class="hhc__header"
         style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;
                border-bottom:1px solid rgba(255,255,255,.08);position:sticky;top:0;background:inherit;z-index:2">
      <h3 style="margin:0;font-size:18px;font-weight:600">Kommentare</h3>
      <button type="button" class="hhc__close"
              style="width:34px;height:34px;border-radius:999px;background:transparent;color:#fff;border:1px solid rgba(255,255,255,.2)">✕</button>
    </div>
    <div class="hhc__body" style="flex:1;overflow:auto;padding:14px">
      <div class="hhc__content"></div>
    </div>
  </div>
</div>

  <?php
  return trim((string)ob_get_clean());
}

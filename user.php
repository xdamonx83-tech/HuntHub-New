<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__.  '/lib/appearance.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$pdo = db();

/* Projekt liegt im Webroot */
$cfg = require __DIR__ . '/auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/'); // Root => ''

// +++ NEU: Helfer-Funktion, um Spalten-Existenz zu prüfen (aus gamification.php) +++
$colExists = function(string $t, string $c) use ($pdo): bool {
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `$t` LIKE ".$pdo->quote($c));
        if ($st === false) return false;
        return $st->fetchColumn() !== false;
    }
    catch (Throwable) { return false; }
};

/* eingeloggter Nutzer (kann null sein) + CSRF */
$me = function_exists('optional_auth') ? optional_auth() : null;
$sessionName = $cfg['cookies']['session_name'] ?? '';
$csrf = issue_csrf($pdo, $_COOKIE[$sessionName] ?? '');

/* Input */
$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

if ($id <= 0 && $slug === '') {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

/* User laden */
// +++ NEU: Spaltennamen für Level dynamisch ermitteln +++
$levelCol = $colExists('users','level') ? 'level' : ($colExists('users','lvl') ? 'lvl' : null);

// +++ NEU: SELECT-Teil der Abfrage dynamisch bauen +++
$select = "id, display_name, slug, role, bio,
           avatar_path, cover_path,
           cover_x, cover_y, cover_scale,
           created_at";
if ($levelCol) {
    $select .= ", `$levelCol` AS level"; // Als 'level' für den Code vereinheitlichen
}
// Social-Spalten sicher hinzufügen (falls Spalte fehlt -> NULL aliasen, damit Key existiert)
$select .=
  ", " . ($colExists('users','social_twitch')    ? "social_twitch"    : "NULL AS social_twitch") .
  ", " . ($colExists('users','social_tiktok')    ? "social_tiktok"    : "NULL AS social_tiktok") .
  ", " . ($colExists('users','social_youtube')   ? "social_youtube"   : "NULL AS social_youtube") .
  ", " . ($colExists('users','social_instagram') ? "social_instagram" : "NULL AS social_instagram") .
  ", " . ($colExists('users','social_twitter')   ? "social_twitter"   : "NULL AS social_twitter") .
  ", " . ($colExists('users','social_facebook')  ? "social_facebook"  : "NULL AS social_facebook") .
  ", " . ($colExists('users','is_twitch_streamer') ? "is_twitch_streamer" : "0 AS is_twitch_streamer");

$sql = "
    SELECT $select
    FROM users
    WHERE " . ($slug !== '' ? "slug = ?" : "id = ?") . "
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$slug !== '' ? $slug : $id]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo 'Profil nicht gefunden.';
    exit;
}

/* Assets (Root-kompatibel) */
$avatar = $user['avatar_path'] ?: ($APP_BASE . '/assets/images/avatars/placeholder.png');
$cover  = $user['cover_path']  ?: ($APP_BASE . '/assets/images/cover-placeholder.jpg');

/* Cover-Parameter (für dein JS) */
$coverU   = isset($user['cover_x']) ? (float)$user['cover_x'] : null;
$coverV   = isset($user['cover_y']) ? (float)$user['cover_y'] : null;
$coverRel = isset($user['cover_scale']) ? (float)$user['cover_scale'] : null;

$memberSince = date('d.m.Y', strtotime((string)$user['created_at']));
$ap   = build_user_appearance($pdo, $user);
/* Threads des Users */
$th = $pdo->prepare("
    SELECT
        t.id,
        t.title,
        t.slug,
        t.created_at,
        t.posts_count,
        t.likes_count,
        (
            SELECT p.content
            FROM posts p
            WHERE p.thread_id = t.id
            ORDER BY p.id ASC
            LIMIT 1
        ) AS first_content
    FROM threads t
    WHERE t.author_id = ?
    ORDER BY t.created_at DESC
    LIMIT 20
");
$th->execute([(int)$user['id']]);
$userThreads = $th->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* Helper */
function twitch_handle(?string $val): ?string {
  $v = trim((string)$val);
  if ($v === '') return null;
  if (($v[0] ?? '') === '@') $v = substr($v, 1);
  return trim($v, "/ \t\r\n") ?: null;
}
$TW_HANDLE = twitch_handle($user['social_twitch'] ?? null);
$TW_PARENT = htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'hunthub.online', ENT_QUOTES);
function thread_preview_from_html(?string $html, int $max = 280): string {
    $txt = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$html)));
    return (mb_strlen($txt, 'UTF-8') > $max)
        ? (mb_substr($txt, 0, $max - 1, 'UTF-8') . '…')
        : $txt;
}
/* === Freunde des Profil-Inhabers laden === */
$friendsLimit = 9; // Sidebar: 3 x 3

// Versuche die Helper-Funktion aus lib/friends.php zu nutzen …
$friendRows = [];
try {
    require_once __DIR__ . '/lib/friends.php'; // enthält list_friends()
    if (function_exists('list_friends')) {
        $friendRows = list_friends($pdo, (int)$user['id'], $friendsLimit, 0);
    }
} catch (Throwable $e) {
    // ignoriere – wir nutzen den SQL-Fallback unten
}

// Fallback (falls lib/friends.php nicht vorhanden ist)
if (!$friendRows) {
    $sql = "
        SELECT u.id, u.display_name, u.slug, u.avatar_path, f.updated_at AS friends_since
        FROM friendships f
        JOIN users u
            ON (u.id = CASE WHEN f.requester_id = ? THEN f.addressee_id ELSE f.requester_id END)
        WHERE (f.requester_id = ? OR f.addressee_id = ?)
            AND f.status = 'accepted'
        ORDER BY f.updated_at DESC
        LIMIT ?
    ";
    $stFr = $pdo->prepare($sql);
    $stFr->execute([(int)$user['id'], (int)$user['id'], (int)$user['id'], $friendsLimit]);
    $friendRows = $stFr->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$requestedId = (int)$user['id'];                   // ← immer die echte Profil-ID verwenden
$tab = $_GET['tab'] ?? 'posts';
$validTabs = ['posts','bewertungen','achv'];
if (!in_array($tab, $validTabs, true)) $tab = 'posts';

$pdo = db();

/* --- Helpers --------------------------------------------------------- */
function table_cols(PDO $pdo, string $table): array {
  try { return $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN, 0) ?: []; }
  catch (\Throwable $e) { return []; }
}
function has_table(PDO $pdo, string $table): bool {
  try { $list = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN, 0); return in_array($table,$list,true); }
  catch (\Throwable $e) { return false; }
}

/* --- Profil laden (dynamisch nur vorhandene Spalten) ----------------- */
$uCols = table_cols($pdo, 'users');
$select = ['id'];
if (in_array('display_name',$uCols,true)) $select[] = 'display_name';
if (in_array('username',    $uCols,true)) $select[] = 'username';
if (in_array('avatar_url',  $uCols,true)) $select[] = 'avatar_url';
elseif (in_array('avatar',  $uCols,true)) $select[] = 'avatar AS avatar_url';
if (in_array('created_at',  $uCols,true)) $select[] = 'created_at';
else $select[] = 'NOW() AS created_at';

$st = $pdo->prepare("SELECT ".implode(',', $select)." FROM `users` WHERE id=? LIMIT 1");
$st->execute([$requestedId]);
$profile = $st->fetch();
if (!$profile) { http_response_code(404); exit('User not found'); }

/* --- Daten für Posts (robust, schema-agnostisch) --------------------- */
$errPosts = null; $posts = [];
if ($tab === 'posts') {
  try {
    // Kandidatentabellen für Threads/Beiträge
    $table = null;
    foreach (['threads','posts','user_posts'] as $try) {
      if (has_table($pdo, $try)) { $table = $try; break; }
    }
    if ($table) {
      $tcols = table_cols($pdo, $table);
      $id   = in_array('id',$tcols,true) ? 'id' : (in_array('post_id',$tcols,true)?'post_id':'id');
      $uid  = in_array('user_id',$tcols,true) ? 'user_id' : (in_array('author_id',$tcols,true)?'author_id':'user_id');
      $tit  = in_array('title',$tcols,true) ? 'title' : (in_array('subject',$tcols,true)?'subject':null);
      $body = in_array('body',$tcols,true) ? 'body'  : (in_array('content',$tcols,true)?'content':(in_array('text',$tcols,true)?'text':null));
      $dt   = in_array('created_at',$tcols,true)?'created_at':(in_array('created',$tcols,true)?'created':(in_array('posted_at',$tcols,true)?'posted_at':null));

      $sel = ["t.`$id` AS id"];
      $sel[] = $tit  ? "t.`$tit`  AS title"      : "NULL AS title";
      $sel[] = $body ? "t.`$body` AS body"       : "NULL AS body";
      $sel[] = $dt   ? "t.`$dt`   AS created_at" : "NOW() AS created_at";
      // Replies/Likes optional (Tabellen evtl. nicht vorhanden)
      $sel[] = has_table($pdo,'comments') ? "(SELECT COUNT(*) FROM comments c WHERE c.thread_id = t.`$id`) AS replies" : "0 AS replies";
      $sel[] = has_table($pdo,'likes')    ? "(SELECT COUNT(*) FROM likes    l WHERE l.thread_id    = t.`$id`) AS likes"   : "0 AS likes";

      $order = $dt ? "t.`$dt` DESC" : "t.`$id` DESC";
      $sql = "SELECT ".implode(',', $sel)." FROM `$table` t WHERE t.`$uid`=? ORDER BY $order LIMIT 50";
      $st = $pdo->prepare($sql);
      $st->execute([$requestedId]);
      $posts = $st->fetchAll() ?: [];
    } else {
      $errPosts = 'Keine Tabelle threads/posts/user_posts gefunden.';
    }
  } catch (\Throwable $e) {
    $errPosts = 'Beiträge konnten nicht geladen werden: '.$e->getMessage();
  }
}

/* --- Partial rendern ------------------------------------------------- */
$partialsDir = __DIR__ . '/partials';
$map = [
  'posts'   => $partialsDir.'/profile.posts.php',
  'bewertungen' => $partialsDir.'/profile.bewertungen.php', // (optional)
  'achv'    => $partialsDir.'/profile.achv.php',    // (optional)
];
$partial = is_file($map[$tab] ?? '') ? $map[$tab] : $map['posts'];

ob_start();
$__tab     = $tab;
$__user    = $profile;
$__posts   = $posts;
$__errTab  = $tab === 'posts' ? $errPosts : null;
include $partial;
$partialHtml = ob_get_clean();

/* --- Partial-Modus ausliefern? -------------------------------------- */
$isPartial = (isset($_GET['partial']) && $_GET['partial'] === '1')
          || (isset($_SERVER['HTTP_X_PARTIAL']) && $_SERVER['HTTP_X_PARTIAL'] === '1');

if ($isPartial) {
  header('Content-Type: text/html; charset=utf-8');
  echo $partialHtml; exit;
}
// Anzahl Freunde (für die Überschrift/Badge)
$stCnt = $pdo->prepare("
    SELECT COUNT(*) FROM friendships f
    WHERE (f.requester_id = ? OR f.addressee_id = ?) AND f.status = 'accepted'
");
$stCnt->execute([(int)$user['id'], (int)$user['id']]);
$friendCount = (int)$stCnt->fetchColumn();
$socials = [
  'twitch'    => $user['social_twitch']    ?? null,
  'tiktok'    => $user['social_tiktok']    ?? null,
  'youtube'   => $user['social_youtube']   ?? null,
  'instagram' => $user['social_instagram'] ?? null,
  'twitter'   => $user['social_twitter']   ?? null,
  'facebook'  => $user['social_facebook']  ?? null,
  
];

$icons = [
  'twitch'    => '<i class="ti ti-brand-twitch"></i>',
  'tiktok'    => '<i class="ti ti-brand-tiktok"></i>',
  'youtube'   => '<i class="ti ti-brand-youtube"></i>',
  'instagram' => '<i class="ti ti-brand-instagram"></i>',
  'twitter'   => '<i class="ti ti-brand-twitter"></i>',
  'facebook'  => '<i class="ti ti-brand-facebook"></i>',
];

function social_url_view(string $platform, ?string $val): ?string {
  $v = trim((string)$val);
  if ($v === '') return null;

  // führendes @ sicher entfernen
  if (($v[0] ?? '') === '@') $v = substr($v, 1);
  $v = trim($v, "/ \t\r\n");

  switch ($platform) {
    case 'twitch':    return "https://twitch.tv/" . $v;
    case 'tiktok':    return "https://www.tiktok.com/@" . $v;
    case 'instagram': return "https://instagram.com/" . $v;
    case 'twitter':   return "https://twitter.com/" . $v; // X
    case 'facebook':
      // komplette URL erlauben (z. B. profile.php?id=...)
      return preg_match('~^https?://~i', (string)$val) ? (string)$val : "https://facebook.com/" . $v;
    case 'youtube':
      // akzeptiere: @handle | c/Name | channel/UC... | user/Name
      if (preg_match('~^(c|user|channel)/~i', $v)) return "https://www.youtube.com/" . $v;
      return "https://www.youtube.com/@" . $v;
    default: return null;
  }
}

ob_start(); ?>
<main>
<style>


/* Username */
.user-name.is-vip i.vip { margin-left:.35rem; }

/* Avatar-Wrapper + Overlay-Rahmen */
.avatar-wrap { position:relative; display:inline-block; }
.avatar-wrap .avatar { display:block; border-radius:50%; }
.avatar-wrap.has-frame .frame-overlay {
  position:absolute; inset:0; pointer-events:none;
}

/* Beispiel: Rahmen über Klassen (wenn du css_class in shop_items nutzt) */
.frame-gold .avatar { box-shadow:0 0 0 3px #d4af37; }
.frame-neon  .avatar { box-shadow:0 0 0 3px #39ff14; }

/* Badges-Leiste */
.badges { display:flex; gap:6px; flex-wrap:wrap; }
.badge {
  display:inline-flex; align-items:center; gap:6px;
  padding:4px 8px; border-radius:999px; font-size:.78rem;
  background:#1f2937; color:#cbd5e1; border:1px solid #374151;
}
.badge img { width:16px; height:16px; display:block; }


/* Beispiel-Farbklassen */
/* Basis: realistisch wirkende Gold-Füllung */
.user-name.name--gold{
  /* Fallback-Farbe, falls background-clip nicht unterstützt wird */
  color:#d4af37 !important;

  /* „echtes“ Gold per Verlauf – als Textfüllung */
  background-image:
    /* Glanzstreifen, der später für den Shine animiert wird */
    linear-gradient(130deg, rgba(255,255,255,.22), rgba(255,255,255,0) 32%),
    /* Gold-Verlauf */
    linear-gradient(180deg,
      #fff7c8 0%, #f6e08a 12%, #e1b530 26%, #b27a1a 38%,
      #ffd66a 52%, #b77b17 66%, #dfb23a 82%, #fff1b0 100%);
  -webkit-background-clip:text; background-clip:text;
  color:transparent !important;

  /* etwas Relief + Lesbarkeit auf dunklem BG */
  text-shadow:
    0 1px 0 rgba(255,255,255,.25),
    0 2px 4px rgba(0,0,0,.35),
    0 0 12px rgba(214,167,36,.35);
}

/* Optional: animierter „Shine“, der über das Gold wandert */
.user-name.name--gold-shine{
  background-size: 220% 100%, 100% 100%;
  animation: hh-gold-shine 2.8s linear infinite;
}
@keyframes hh-gold-shine{
  from { background-position: -220% 0, 0 0; }
  to   { background-position:  220% 0, 0 0; }
}

/* Optional: dezent funkelnde „Glitter“-Punkte (ohne Bilder) */
.user-name.name--gold-glitter{
  position:relative;
}
.user-name.name--gold-glitter::after{
  content:"";
  position:absolute; inset:0;
  /* kleine helle Punkte per conic/radial mix */
  background:
    radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,.9), transparent 60%),
    radial-gradient(1.5px 1.5px at 65% 55%, rgba(255,255,255,.8), transparent 60%),
    radial-gradient(1.8px 1.8px at 35% 75%, rgba(255,255,255,.85), transparent 60%),
    conic-gradient(from 0deg, rgba(255,255,255,.25), transparent 25%, transparent 100%);
  mix-blend-mode: screen;
  pointer-events:none;
  animation: hh-gold-glitter 4s linear infinite;
  /* Clip auf Text */
  -webkit-mask: linear-gradient(#000 0 0) text;
          mask: linear-gradient(#000 0 0) text;
}
@keyframes hh-gold-glitter{
  from { transform: translate3d(0,0,0); }
  to   { transform: translate3d(8%,0,0); }
}

/* Respektiere reduzierte Bewegungen */
@media (prefers-reduced-motion: reduce){
  .user-name.name--gold-shine,
  .user-name.name--gold-glitter::after{ animation: none; }
}




/* nicer modal */
.hh-modal{position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center}
.hh-modal__overlay{position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(2px);animation:hh-fade .12s ease}
.hh-modal__panel{position:relative;width:min(92vw,520px);background:#1a1c1f;border:1px solid #2c2f33;border-radius:14px;box-shadow:0 20px 80px rgba(0,0,0,.55);padding:22px 22px}
.hh-modal__title{font-weight:800;font-size:22px;line-height:1.2;margin:0 0 6px;color:#fff}
.hh-modal__msg{color:#aeb3b7;margin:6px 0 18px;font-size:15px}
.hh-modal__actions{display:flex;justify-content:flex-end;gap:10px}
.hh-close{position:absolute;top:10px;right:10px;border:0;background:transparent;color:#cbd0d6;cursor:pointer}
.hh-close:hover{color:#fff}
@keyframes hh-fade{from{opacity:0}to{opacity:1}}
@keyframes hh-pop{from{opacity:0;transform:translateY(6px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.hh-pop{animation:hh-pop .16s cubic-bezier(.2,.8,.2,1)}
/* falls dein Theme kein btn-danger hat */
.btn-danger{background:#dc2626;color:#fff}
.btn-danger:hover{filter:brightness(1.05)}
/* Profil-Badge neben dem Usernamen */
.profile-badge{
  display:inline-flex;
  align-items:center;
  gap:.4rem;



  line-height:1;

  white-space:nowrap;
}
.heading-3 .profile-badge{ font-size:.6em; } /* skaliert mit der Überschrift */

.profile-badge__icon{
  width:1.1em;
  height:1.1em;
  display:inline-block;
}

.profile-badge__text{
  font-weight:600;
  letter-spacing:.02em;
  opacity:.95;
}

/* Optional: Farben je nach Label */
.profile-badge[aria-label*="ADMIN"]{
  color:#f59e0b; /* amber-500 */
}
.profile-badge[aria-label*="MOD"]{
  color:#60a5fa; /* blue-400 */
}
.profile-badge[aria-label*="PRO"],
.profile-badge[aria-label*="VIP"]{
  color:#34d399; /* emerald-400 */
}
/* Avatar-Wrapper */
/* Basis */
/* Basis */
.avatar-wrap{ position:relative; display:inline-block;
  --ring-scale: 1.0;     /* größer/kleiner machen */
  --ring-thickness: 5%;  /* Dicke des Rings */
  --ring-blur: 12px;      /* Glow */
  --ring-speed: 10s;       /* Geschwindigkeit */
}
.avatar-wrap .avatar{ display:block; border-radius:50%; }

/* Animierter Frame */
.avatar-wrap.frame--neon-rotor::before,
.avatar-wrap.frame--neon-rotor::after{
  content:"";
  position:absolute; left:50%; top:50%;
  width:calc(100% * var(--ring-scale));
  height:calc(100% * var(--ring-scale));
  transform:translate(-50%,-50%);
  border-radius:50%;
  pointer-events:none; z-index:2; will-change:transform;

  /* Nur Rand sichtbar (Loch in der Mitte) */
  mask: radial-gradient(farthest-side,
        transparent calc(100% - var(--ring-thickness)),
        #000 calc(100% - var(--ring-thickness) + 0.5px));
  -webkit-mask: radial-gradient(farthest-side,
        transparent calc(100% - var(--ring-thickness)),
        #000 calc(100% - var(--ring-thickness) + 0.5px));
}

/* untere Glow-Schicht */
.avatar-wrap.frame--neon-rotor::before{
  background: conic-gradient(
    from 0deg,
    #45f3ff, #45f3ff 25%,
    #ff2770 50%,
    #45f3ff 75%,
    #45f3ff
  );
  filter: blur(var(--ring-blur));
  animation: ring-spin var(--ring-speed) linear infinite;
}

/* obere, schärfere Schicht – gegenläufig für Dynamik */
.avatar-wrap.frame--neon-rotor::after{
  background: conic-gradient(
    from 180deg,
    #8be9ff, #8be9ff 25%,
    #ff63a0 50%,
    #8be9ff 75%,
    #8be9ff
  );
  animation: ring-spin var(--ring-speed) linear infinite reverse;
}

/* WICHTIG: transform vollständig in den Keyframes angeben */
@keyframes ring-spin{
  from { transform: translate(-50%,-50%) rotate(0deg); }
  to   { transform: translate(-50%,-50%) rotate(360deg); }
}

/* Optional: auf Handy minimal größer */
@media (max-width:640px){
  .avatar-wrap.frame--neon-rotor{ --ring-scale: 1.0; }
}


</style>

<section class="section-pt overflow-visible">
    <div class="container">
        <div class="relative">
            <div class="glitch-effect">
                <div class="glitch-thumb" style="height: 472px;">
                    <img class="w-full xl:h-[490px] h-[400px] hover:scale-110 object-cover" style="height:auto;"
                         src="<?= htmlspecialchars($cover) ?>" alt="image">
                </div>
                <div class="glitch-thumb" style="height: 472px;">
                    <img class="w-full xl:h-[490px] lg:h-[400px] md:h-[340px] sm:h-[300px] h-[240px] hover:scale-110 object-cover"
                         style="height:auto;" src="<?= htmlspecialchars($cover) ?>" alt="image">
                </div>
            </div>
        </div>

<script>
function applyPanNormalized(imgEl, frameEl, relScale, u, v) {
    if (!imgEl || !frameEl) return;
    const probe = new Image();
    probe.onload = () => {
        const natW = probe.naturalWidth, natH = probe.naturalHeight;
        const fw = frameEl.clientWidth, fh = frameEl.clientHeight;
        const fit = Math.max(fw / natW, fh / natH);
        const s   = (relScale ?? 1) * fit;
        const imgW = natW * s, imgH = natH * s;
        const overflowX = Math.max(0, imgW - fw);
        const overflowY = Math.max(0, imgH - fh);
        const uu = (u == null) ? 0.5 : Math.min(1, Math.max(0, u));
        const vv = (v == null) ? 0.5 : Math.min(1, Math.max(0, v));
        const x = overflowX > 0 ? -overflowX * uu : (fw - imgW) / 2;
        const y = overflowY > 0 ? -overflowY * vv : (fh - imgH) / 2;
        imgEl.style.transformOrigin = '0 0';
        imgEl.style.transform = `translate(${x}px, ${y}px) scale(${s})`;
    };
    probe.src = imgEl.currentSrc || imgEl.src;
}
(function initCoverFromSaved(){
    const frame = document.getElementById('coverFrame');
    const img   = document.getElementById('coverImg');
    if (!frame || !img) return;
    const saved = {
        u:   <?= json_encode($coverU) ?>,
        v:   <?= json_encode($coverV) ?>,
        srl: <?= json_encode($coverRel) ?>
    };
    const apply = () => applyPanNormalized(img, frame, saved.srl, saved.u, saved.v);
    window.addEventListener('load', apply);
    window.addEventListener('resize', () => {
        clearTimeout(window.__coverTimer);
        window.__coverTimer = setTimeout(apply, 100);
    });
})();
</script>


        <div class="grid grid-cols-12 grid-mobile gap-30p bg-b-neutral-3 rounded-12 pb-30p">
            <div class="4xl:col-start-2 4xl:col-end-12 col-span-12 max-4xl:px-48p">
                <div class="relative flex 3xl:items-end max-3xl:items-center 3xl:justify-between max-3xl:flex-col gap-30p 3xl:mt-[70px] xl:-mt-52 lg:-mt-44 md:-mt-36 sm:-mt-30 -mt-20 4xl:mb-[70px] mb-60p">

                    <div class="3xl:order-1 order-2 flex text-center divide-x divide-shap">
                        <div class="pr-6">
                            <h2 class="heading-40 text-w-neutral-1 mb-1"><?= (int)$friendCount ?></h2>
                            <span class="text-m-medium text-w-neutral-4/70" data-i18n="friends"><?php echo $L['friends']; ?></span>
                        </div>
                        <div class="px-24p">
                            <h2 class="heading-40 text-w-neutral-1 mb-1">140</h2>
                            <span class="text-m-medium text-w-neutral-4/70" data-i18n="wins"><?php echo $L['wins']; ?></span>
                        </div>
                        <div class="pl-6">
                            <h2 class="heading-40 text-w-neutral-1 mb-1" data-i18n="level">   <?php if (isset($user['level'])): ?>
                                    <?= (int)$user['level'] ?>
                                <?php endif; ?></h2>
                            <span class="text-m-medium text-w-neutral-4/70" data-i18n="level"><?php echo $L['level']; ?></span>
                        </div>
                    </div>

                    <div class="3xl:order-2 order-1 3xl:absolute 3xl:bottom-0 3xl:left-1/2 3xl:-translate-x-1/2 max-3xl:flex-col-c z-[4]">
  <?php
$frameClass  = trim((string)($ap['frame_class'] ?? ''));   // kommt aus build_user_appearance()
$frameStyle  = trim((string)($ap['frame_style'] ?? ''));   // optional
$wrapClass = 'avatar-wrap' . (!empty($ap['frame_class']) ? ' ' . $ap['frame_class'] : '');
$wrapStyle = !empty($ap['frame_style']) ? ' style="'.htmlspecialchars($ap['frame_style']).'"' : '';
    $nameClass     = (string)($ap['name_class'] ?? 'user-name');
    $nameStyle     = (string)($ap['name_style'] ?? '');
  ?>

  <!-- Avatar + Rahmen -->
<?php $overlay = (string)($ap['frame_overlay_html'] ?? ''); ?>


<div class="<?= htmlspecialchars($wrapClass) ?>"<?= $wrapStyle ?>>
  <img class="avatar xl:size-60 lg:size-52 md:size-44 sm:size-40 size-28 border border-secondary"
       src="<?= htmlspecialchars($avatar) ?>" alt="profile">
  <?= $ap['frame_overlay_html'] ?? '' ?>
</div>
<!-- debug frame_class: <?= htmlspecialchars($ap['frame_class'] ?? '') ?> -->





  <div class="text-center mt-30p">
    <h3 class="heading-3 text-w-neutral-1 mb-3 text-split-top <?= htmlspecialchars($nameClass) ?>"
        style="<?= htmlspecialchars($nameStyle ? $nameStyle.' !important' : '') ?>">
      <?= htmlspecialchars($user['display_name'] ?? $user['slug'] ?? 'User') ?>

      <?php if ((int)($user['is_twitch_streamer'] ?? 0) === 1): ?>
        <a class="profile-badge twitch-badge" target="_blank" rel="noopener noreferrer" title="Twitch-Streamer">
          <svg class="profile-badge__icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <path d="M4 2L2 6v16h6v2h4l2-2h4l6-6V2H4zm16 10l-3 3h-5l-2 2v-2H6V4h14v8zM14 6h2v5h-2V6zm-5 0h2v5H9V6z" fill="currentColor"/>
          </svg>
        </a>
      <?php endif; ?>
    </h3>

    <p class="text-m-medium text-w-neutral-4">
      <?= $user['bio'] ? nl2br(htmlspecialchars($user['bio'])) : '<span class="text-white/50">Keine Bio vorhanden.</span>' ?>
    </p>

    <div class="hhr-summary profile-header"></div>
    <button id="hhr-help" class="hhr-help" type="button" title="Was ist das HHR?">
      <i class="ti ti-help-circle" aria-hidden="true"></i><span class="sr-only">Hilfe</span>
    </button>
  </div>
</div>


  <div class="order-3 flex items-center gap-3">
  <?php
  $icons = [
    'facebook'  => 'ti ti-brand-facebook',
    'twitch'    => 'ti ti-brand-twitch',
    'instagram' => 'ti ti-brand-instagram',
    'twitter'   => 'ti ti-brand-twitter',
    'tiktok'    => 'ti ti-brand-tiktok',
    'youtube'   => 'ti ti-brand-youtube',
  ];
  $order = ['facebook','twitch','instagram','twitter','tiktok','youtube']; // gewünschte Reihenfolge

  foreach ($order as $p) {
    $handle = (string)($user['social_'.$p] ?? '');
    $url = social_url_view($p, $handle);
    if (!$url) continue;
    $icon = $icons[$p];
    ?>
      <a href="<?= htmlspecialchars($url, ENT_QUOTES) ?>"
         class="btn-socal-primary"
         target="_blank" rel="noopener nofollow"
         title="<?= htmlspecialchars(ucfirst($p).($handle ? ': @'.ltrim($handle,'@/') : ''), ENT_QUOTES) ?>">
        <i class="<?= $icon ?>"></i>
      </a>
    <?php
  }
  ?>
</div>
<script>
(function(){
  const btn = document.getElementById('hhr-help');
  if (!btn) return;

  // nutzt deine vorhandene openModal/HHR_openModal Funktion – sonst baut es kurz selbst ein Modal
  function useModal(html){
    const make = window.openModal || window.HHR_openModal || function(innerHTML){
      const wrap=document.createElement('div');
      wrap.setAttribute('role','dialog'); wrap.setAttribute('aria-modal','true');
      wrap.style.cssText='position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center';
      wrap.innerHTML=`
        <div style="position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(2px)"></div>
        <div class="hh-modal__panel" style="position:relative;width:min(92vw,700px);background:#1a1c1f;border:1px solid #2c2f33;border-radius:14px;box-shadow:0 20px 80px rgba(0,0,0,.55);padding:22px">
          <button type="button" aria-label="Schließen" style="position:absolute;top:10px;right:10px;background:transparent;border:0;color:#cbd0d6;font-size:18px;cursor:pointer">✕</button>
          ${innerHTML}
        </div>`;
      document.body.appendChild(wrap);
      const panel=wrap.children[1];
      const close=()=>wrap.remove();
      panel.querySelector('[aria-label="Schließen"]').addEventListener('click',close);
      wrap.firstElementChild.addEventListener('click',close);
      document.addEventListener('keydown',function onEsc(e){if(e.key==='Escape'){close();document.removeEventListener('keydown',onEsc);}});
      return {panel, close};
    };
    return make(html);
  }

  btn.addEventListener('click', () => {
    const html = `
      <h3 style="color:#fff;margin:0 0 12px;font-weight:800;font-size:22px;">HHR – HuntHub Ranking</h3>
      <div style="color:#cbd5e1;line-height:1.65">
        <p>Das <strong>HuntHub Ranking (HHR)</strong> ist ein 6-Sterne-System, mit dem Spieler ihr Miteinander nach einer Runde einschätzen. Es bewertet <em>Verhalten</em> und <em>Teamplay</em> – nicht Skill/Stats.</p>

        <h4 style="margin:14px 0 6px;color:#fff;font-weight:700;">So wird bewertet</h4>
        <ul style="margin:0 0 8px 18px">
          <li><strong>Spielweise:</strong> fair, teamorientiert, respektvoll?</li>
          <li><strong>Freundlichkeit:</strong> Umgangston in Voice/Chat?</li>
          <li><strong>Hilfsbereitschaft:</strong> Callouts, Revives, Support?</li>
        </ul>
        <p>Je Kategorie vergibst du <strong>1–6 Sterne</strong> und kannst optional einen Kommentar (max. 800 Zeichen) hinzufügen.</p>

        <h4 style="margin:14px 0 6px;color:#fff;font-weight:700;">Der HHR-Score</h4>
        <ul style="margin:0 0 8px 18px">
          <li>Ø = (Spielweise + Freundlichkeit + Hilfsbereitschaft) ÷ 3</li>
          <li>Der exakte Ø (z. B. <strong>5,33 / 6,0</strong>) wird angezeigt.</li>
          <li>Für die Sternanzeige wird zusätzlich auf <strong>1–6 Sterne</strong> gerundet.</li>
        </ul>

        <h4 style="margin:14px 0 6px;color:#fff;font-weight:700;">Sichtbarkeit</h4>
        <ul style="margin:0 0 8px 18px">
          <li>Unter dem Avatar: Ø-Wert + Anzahl der Bewertungen.</li>
          <li>Im Tab <strong>„Bewertungen“</strong>: alle Einzelbewertungen mit Sterne-Split, Kommentar und Zeitstempel.</li>
        </ul>

        <h4 style="margin:14px 0 6px;color:#fff;font-weight:700;">Wer darf bewerten?</h4>
        <ul style="margin:0 0 8px 18px">
          <li>Nur eingeloggt und <strong>nicht</strong> das eigene Profil.</li>
          <li>Pro Person <strong>eine</strong> Bewertung – du kannst sie jederzeit <strong>aktualisieren</strong> (sie überschreibt die alte).</li>
        </ul>

        <h4 style="margin:14px 0 6px;color:#fff;font-weight:700;">So gibst du eine Bewertung ab</h4>
        <ol style="margin:0 0 8px 18px">
          <li>Im Profil auf <strong>„Bewertung abgeben“</strong> klicken.</li>
          <li>Sterne setzen, optional kommentieren.</li>
          <li><strong>Speichern</strong> – der Ø-Wert und die Liste aktualisieren sich sofort (kein Reload).</li>
        </ol>

        <h4 style="margin:14px 0 6px;color:#fff;font-weight:700;">Fairness & Hinweise</h4>
        <ul style="margin:0 0 0 18px">
          <li>Bewerte ehrlich und sachlich – das hilft der Community.</li>
          <li>Missbrauch/Beleidigungen bitte melden.</li>
        </ul>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:16px">
        <button class="btn btn-primary rounded-12" data-close>Verstanden</button>
      </div>`;
    const m = useModal(html);
    m.panel.querySelector('[data-close]')?.addEventListener('click', m.close);
  });
})();
</script>


                    <div class="order-4 3xl:absolute 3xl:right-0 3xl:-top-25 flex items-center justify-center flex-wrap gap-20p z-[3]">
                        <button
                            id="friendAction"
                            type="button"
                            class="btn btn-lg btn-primary rounded-12"
                            data-csrf="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>"
                            data-other="<?= (int)$user['id'] ?>"
                            data-me="<?= (int)($me['id'] ?? 0) ?>"
                        data-i18n="friendadd">
                            <?php echo $L['friendadd']; ?>
                            <i class="ti ti-plus"></i>
                        </button>
<a href="#" data-open-chat="<?= (int)$user['id'] ?>" class="btn btn-lg btn-neutral-2 rounded-12"
   data-name="<?= htmlspecialchars($user['display_name']) ?>"
   data-avatar="<?= htmlspecialchars($user['avatar_path'] ?: ($APP_BASE.'/assets/images/avatars/placeholder.png')) ?>" data-i18n="message">
    <?php echo $L['message']; ?>
</a>
                        
                    </div>

                </div>

                <div>
                    <div class="flex items-center max-sm:items-start justify-between max-sm:flex-col gap-32p ">
					<?php $base = '/user.php?id='.(int)$profile['id']; ?>
                        <div class="tab-navbar flex items-center flex-wrap gap-x-32p gap-y-24p sm:text-xl text-lg *:font-borda font-medium text-w-neutral-1 whitespace-nowrap">
                            <a href="<?= $base ?>&tab=posts"   class="<?= $tab==='posts'?'is-active':'' ?>" data-i18n="posts"><?php echo $L['posts']; ?></a>
                             <a href="<?= $base ?>&tab=bewertungen" class="<?= $tab==='bewertungen'?'is-active':'' ?>"><?php echo $L['bewert']; ?></a> 
                            <a href="<?= $base ?>&tab=achv"    class="<?= $tab==='achv'?'is-active':'' ?>" data-i18n="arch"><?php echo $L['arch']; ?></a>
                        </div>

             
                    </div>
                </div>

            </div>
        </div>

    </div>
</section>

<section class="section-pb pt-60p overflow-visible">
    <div class="container">
        <div class="grid grid-cols-12 gap-30p">
            <div class="4xl:col-start-2 4xl:col-end-8 xxl:col-span-7 col-span-12">
                <div class="grid grid-cols-1 gap-30p">

                    <div>
                        <div class="grid grid-cols-1 gap-30p *:bg-b-neutral-3 *:rounded-12 *:px-40p *:py-32p"
						id="profile-content" data-user-id="<?= (int)$profile['id'] ?>">


 <?= $partialHtml ?>


                        </div>

                        <div class="flex-c mt-48p">
                            <button type="button" class="btn btn-lg btn-neutral-3 rounded-12" data-i18n="loadmore"><?php echo $L['loadmore']; ?></button>
                        </div>
                    </div>

                </div>
            </div>

            <div class="4xl:col-start-8 4xl:col-end-12 xxl:col-span-5 col-span-12 relative xxl:block hidden">
                <div class="xxl:sticky xxl:top-30">
                    <div class="grid grid-cols-1 gap-30p *:bg-b-neutral-3 *:rounded-12 *:py-32p *:px-40p xxl:max-h-screen xxl:overflow-y-auto scrollbar-sm">

                        <div>
    <div class="flex items-center justify-between gap-24p">
        <div>
            <h3 class="heading-3 text-w-neutral-1 mb-1" data-i18n="friends">
                <?php echo $L['friends']; ?>
            </h3>
     
        </div>

<script>
(function(){
    const API = '/api/gamification/user_progress.php';

    function esc(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}

    function render(items){
        const grid = document.getElementById('achievementsGrid');
        if (!grid) return;
        grid.innerHTML = '';

        // Optional: gruppiert nach rule_stat (Beiträge/Kommentare etc.)
        const groups = {};
        for (const a of items) {
            const k = a.rule_stat || 'all';
            (groups[k] ||= []).push(a);
        }

        for (const [group, arr] of Object.entries(groups)) {
            // Subheadline (optional, aus rule_stat abgeleitet)
       

            for (const a of arr) {
                const pct = Math.max(0, Math.min(100, a.percent|0));
                const card = document.createElement('div');
                card.className = 'bg-b-neutral-4 py-32p px-40p flex-col-c text-center rounded-12' + (a.unlocked ? ' achv-card--done' : '');
                card.innerHTML = `
                                     
                                   
										${a.icon ? `<img class="size-140p rounded-full mb-16p" src="${esc(a.icon)}" alt="">` : ''}
                                    <a href="game-details.html"
                                        class="heading-4 text-w-neutral-1 link-1 line-clamp-1 mb-3">
                                        ${esc(a.title || 'Erfolg')}
                                    </a>
                                    <span class="text-m-medium text-primary mb-16p">
                                        ${a.current} von ${a.threshold}
                                    </span>
                                    <div x-data="progressBar(0, 45)" x-init="init()" class="overflow-x-hidden w-full">
                                        <div class="flex items-center w-full">
                                            <div class="w-3.5 h-5 bg-primary"></div>
                                            <div x-intersect.once="$dispatch('start-progress')"
                                                class="relative w-full h-2.5 bg-w-neutral-3">
                                                <span :style="'width:' + ${pct} + '%'" class="progressbar-1 h-full">
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                    ${a.unlocked ? `<div class="achv-badge">Freigeschaltet</div>
					` : ``}
                `;
                grid.appendChild(card);
            }
        }
    }

    function load(){ 
        fetch(API, { credentials: 'include' })
            .then(r => r.json()).then(j => { if (j.ok) render(j.items); })
            .catch(()=>{});
    }

    // initial laden
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else { load(); }

    // Optional: live aktualisieren, wenn dein WS ein notify sendet
    if (window.socket && typeof window.socket.on === 'function') {
        window.socket.on('notify', (n) => {
            if (n && (n.type === 'achievement_unlocked' || n.refresh)) {
                load();
            }
        });
    }
})();
</script>

    </div>

    <?php if (!empty($friendRows)): ?>
        <div class="grid grid-cols-3 gap-x-20p gap-y-30p my-20p">
            <?php foreach ($friendRows as $fr): 
                $fid   = (int)$fr['id'];
                $fname = (string)($fr['display_name'] ?? '');
                $fslug = (string)($fr['slug'] ?? '');
                $fava  = $fr['avatar_path'] ?: ($APP_BASE . '/assets/images/avatars/placeholder.png');
                $url   = $APP_BASE . '/user.php?id=' . $fid . '&slug=' . urlencode($fslug);
            ?>
                <div><a href="<?= htmlspecialchars($url) ?>">
                    <img class="w-full sm:h-[136px] h-24 object-cover aspect-square rounded-12 mb-3"
                         src="<?= htmlspecialchars($fava) ?>" alt="<?= htmlspecialchars($fname) ?>" /></a>
                    <a href="<?= htmlspecialchars($url) ?>"
                       class="text-m-semi-bold text-w-neutral-1 link-1 line-clamp-1">
                        <?= htmlspecialchars($fname) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="px-16p py-20p my-12p rounded-12 bg-b-neutral-2 text-w-neutral-4 text-sm" data-i18n="nofriends">
            <?php echo $L['nofriends']; ?>
        </div>
    <?php endif; ?>

    </div>




   <?php if ($TW_HANDLE): ?>
  <div>
    <div class="flex-y justify-between gap-24p">
      <h3 class="heading-3 text-w-neutral-1">
        Livestream
        <span class="h5 span text-primary">@<?= htmlspecialchars($TW_HANDLE) ?></span>
      </h3>
    </div>

    <div class="grid grid-cols-1 gap-20p mt-20p">
      <div class="*:w-full *:h-[246px] rounded-12 overflow-hidden">
        <div class="plyr__video-embed player relative">

          <!-- Platzhalter (16:9) -->
          <div id="tw-wrapper" class="relative w-full overflow-hidden rounded-12" style="aspect-ratio:16/9;">
            <button id="tw-load"
                    class="absolute inset-0 flex items-center justify-center gap-3 bg-black/60 text-white rounded-12"
                    style="backdrop-filter: blur(2px);">
              <i class="ti ti-brand-twitch text-2xl"></i>
              <span>Stream laden</span>
            </button>
          </div>

          <p class="mt-2 text-xs text-w-neutral-4">
            Hinweis: Beim Laden des Streams werden Daten an Twitch übertragen.
          </p>
        </div>

        <script>
        (function(){
          const btn = document.getElementById('tw-load');
          const wrap = document.getElementById('tw-wrapper');
          if (!btn || !wrap) return;
          btn.addEventListener('click', () => {
            const iframe = document.createElement('iframe');
            iframe.allowFullscreen = true;
            iframe.setAttribute('scrolling','no');
            iframe.setAttribute('frameborder','0');
            iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;';
            iframe.src = "https://player.twitch.tv/?channel=<?= rawurlencode($TW_HANDLE) ?>&parent=<?= $TW_PARENT ?>&muted=true&autoplay=true";
            wrap.appendChild(iframe);
            btn.remove();
          });
        })();
        </script>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php if (!$TW_HANDLE && ($me['id'] ?? 0) === (int)$user['id']): ?>
<div class="bg-b-neutral-3 rounded-12 py-24p px-32p">
  <div class="text-w-neutral-1 mb-2">
    <?= htmlspecialchars($L['twitch_not_connected'] ?? 'Twitch nicht verbunden') ?>
  </div>
  <div class="text-w-neutral-4 text-sm mb-3">
    <?= htmlspecialchars($L['twitch_hint'] ?? 'Füge deinen Twitch-Namen in den Profileinstellungen hinzu, um deinen Stream hier zu zeigen.') ?>
  </div>
  <a href="<?= $APP_BASE ?>/profile.php" class="btn btn-sm btn-primary rounded-10">
    <i class="ti ti-brand-twitch mr-1"></i>
    <?= htmlspecialchars($L['twitch_connect'] ?? 'Twitch verknüpfen') ?>
  </a>
</div>

<?php endif; ?>

                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

</main>

<script>
(function(){
    const btn = document.getElementById('friendAction');
    if (!btn) return;

    const BASE = "<?= $APP_BASE ?>";
    const API  = (p) => `${BASE}/api/friends/${p}`;

    function getCSRF(){
        return btn.dataset.csrf || (document.querySelector('meta[name="csrf"]')?.content || '');
    }

    // robuster Übersetzungs‑Helper
    const t = (k,f)=> (window.L && Object.prototype.hasOwnProperty.call(window.L,k)) ? window.L[k] : f;

    // sicher parsen (leere Strings -> 0)
    const other = Number.parseInt(btn.dataset.other ?? '0', 10) || 0;
    const me    = Number.parseInt(btn.dataset.me    ?? '0', 10) || 0;

    if (!other) { btn.disabled = true; btn.textContent = t('unknown_user','Unbekannter Nutzer'); return; }
    if (!me)    { btn.disabled = true; btn.textContent = t('first_login','Erst einloggen'); return; }
    if (me === other) { btn.disabled = true; btn.textContent = t('your_profile','Dein Profil'); return; }

    function label(status){
        switch(status){
            case 'friends':           return t('remove_friend','Freund entfernen');
            case 'pending_outgoing':  return t('request_sent','Anfrage gesendet');
            case 'pending_incoming':  return t('accept_request','Anfrage annehmen?');
            default:                  return t('add_friend','Freund hinzufügen');
        }
    }
    function stylize(status){
        btn.classList.remove('btn-primary','btn-neutral-2','btn-danger');
        if (status === 'friends')               btn.classList.add('btn-danger');
        else if (status === 'pending_outgoing') btn.classList.add('btn-neutral-2');
        else                                    btn.classList.add('btn-primary');
    }
    async function setStatus(s){
        btn.dataset.status = s;
        btn.textContent    = label(s);
        stylize(s);
    }

    async function api(url, opts){
        const r  = await fetch(url, {
            redirect:'follow',
            headers:{ 'Accept':'application/json' },
            credentials:'same-origin',
            ...opts
        });
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const text = await r.text();
            throw new Error(`${t('no_json','Kein JSON')} (HTTP ${r.status}). ${text.slice(0,180)}`);
        }
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || `HTTP ${r.status}`);
        return j;
    }
    async function getStatus(){
        const j = await api(API('status.php?user_id=' + other));
        return j.status || 'none';
    }

    function showModal({title='', message='', confirmText='OK', cancelText=null, danger=false}) {
        return new Promise(resolve=>{
            const z = 2147483647;
            const wrap = document.createElement('div');
            wrap.setAttribute('role','dialog');
            wrap.setAttribute('aria-modal','true');
            wrap.style.cssText = `position:fixed;inset:0;z-index:${z};display:flex;align-items:center;justify-content:center`;
            wrap.innerHTML = `
                <div style="position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(2px)"></div>
                <div style="position:relative;width:min(92vw,520px);background:#1a1c1f;border:1px solid #2c2f33;border-radius:14px;box-shadow:0 20px 80px rgba(0,0,0,.55);padding:22px 22px;transform:translateY(6px) scale(.97);opacity:0;transition:.16s cubic-bezier(.2,.8,.2,1)">
                    <button aria-label="${t('close','Schließen')}" style="position:absolute;top:10px;right:10px;background:transparent;border:0;color:#cbd0d6;font-size:18px;cursor:pointer">✕</button>
                    <h3 style="margin:0 0 6px;font-weight:800;font-size:22px;line-height:1.2;color:#fff">${title}</h3>
                    <div style="color:#aeb3b7;margin:6px 0 18px;font-size:15px">${message}</div>
                    <div style="display:flex;justify-content:flex-end;gap:10px">
                        ${cancelText ? `<button data-cancel class="btn btn-neutral-2 rounded-10">`+cancelText+`</button>` : ''}
                        <button data-ok class="btn ${danger?'btn-danger':'btn-primary'} rounded-10">`+confirmText+`</button>
                    </div>
                </div>`;
            document.body.appendChild(wrap);
            const panel  = wrap.children[1];
            requestAnimationFrame(()=>{ panel.style.transform='translateY(0) scale(1)'; panel.style.opacity='1'; });

            const btnOk  = panel.querySelector('[data-ok]');
            const btnCan = panel.querySelector('[data-cancel]');
            const btnX   = panel.querySelector('button[aria-label="'+t('close','Schließen')+'"]');

            const focusables = [btnCan, btnOk, btnX].filter(Boolean);
            (focusables[0] || btnOk).focus();

            const onKey = (e)=>{
                if (e.key === 'Escape' && btnCan) close(false);
                if (e.key === 'Tab') {
                    const idx = focusables.indexOf(document.activeElement);
                    if (e.shiftKey && idx === 0) { e.preventDefault(); focusables[focusables.length-1].focus(); }
                    else if (!e.shiftKey && idx === focusables.length-1) { e.preventDefault(); focusables[0].focus(); }
                }
            };
            const close = (val)=>{
                document.removeEventListener('keydown', onKey);
                wrap.remove();
                resolve(val);
            };
            document.addEventListener('keydown', onKey);
            btnOk.addEventListener('click', ()=>close(true));
            btnX.addEventListener('click', ()=>close(false));
            if (btnCan) btnCan.addEventListener('click', ()=>close(false));
            wrap.firstElementChild.addEventListener('click', ()=>{ if (btnCan) close(false); });
        });
    }
    const confirmDialog = (msg)=>showModal({title:t('confirm','Bestätigen'), message:msg, confirmText:t('yes','Ja'), cancelText:t('cancel','Abbrechen'), danger:true});
    const errorDialog   = (msg)=>showModal({title:t('error','Fehler'), message:msg, confirmText:t('close','Schließen'), danger:false});

    async function init(){
        try { await setStatus(await getStatus()); }
        catch(e){ console.error(e); btn.disabled = true; btn.textContent = t('error','Fehler'); }
    }

    btn.addEventListener('click', async ()=>{
        const s = btn.dataset.status || 'none';
        try {
            btn.disabled = true;

            if (s === 'none') {
                const fd = new FormData();
                fd.append('csrf', getCSRF());
                fd.append('user_id', String(other));
                const j = await api(API('send_request.php'), { method:'POST', body: fd });
                await setStatus(j.status);

            } else if (s === 'pending_incoming') {
                const pending = await api(API('pending.php'));
                const req = (pending.incoming || []).find(x => Number(x.id) === other);
                if (!req) { await errorDialog(t('request_not_found','Anfrage nicht gefunden.')); return; }
                const fd = new FormData();
                fd.append('csrf', getCSRF());
                fd.append('request_id', String(req.request_id));
                fd.append('action','accept');
                const j2 = await api(API('respond_request.php'), { method:'POST', body: fd });
                await setStatus(j2.status);

            } else if (s === 'pending_outgoing') {
                const ok = await confirmDialog(t('withdraw_request','Eigene Freundschaftsanfrage zurückziehen?'));
                if (!ok) return;
                const fd = new FormData();
                fd.append('csrf', getCSRF());
                fd.append('user_id', String(other));
                await api(API('cancel_request.php'), { method:'POST', body: fd });
                await setStatus('none');

            } else if (s === 'friends') {
                const ok = await confirmDialog(t('remove_friend_confirm','Freund wirklich entfernen?'));
                if (!ok) return;
                const fd = new FormData();
                fd.append('csrf', getCSRF());
                fd.append('user_id', String(other));
                await api(API('unfriend.php'), { method:'POST', body: fd });
                await setStatus('none');
            }

        } catch (e) {
            console.error(e);
            await errorDialog(t('action_failed','Aktion fehlgeschlagen: ')+ e.message);
        } finally {
            btn.disabled = false;
        }
    });

    init();
})();
</script>


<script>
    const API_BASE = "<?= rtrim($cfg['app_base'],'/') ?>/api";

    async function apiLogin(email, password){
        const res = await fetch('/api/auth/login.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'Accept':'application/json'},
            body: JSON.stringify({ email, password }),
            credentials: 'include'
        });
        return res.json();
    }

    async function apiLogout(){
        await fetch('/api/auth/logout.php', { method:'POST', credentials:'include' });
    }
</script>

<script>
  // AJAX Tabs
  (() => {
    const content = document.getElementById('profile-content');
    const nav = document.querySelector('.profile-tabs');
    if (!content || !nav) return;

    const showLoading = () => content.classList.add('is-loading');
    const hideLoading = () => content.classList.remove('is-loading');

    async function loadTab(url, push = true) {
      try {
        showLoading();
        const u = new URL(url, location.origin);
        u.searchParams.set('partial','1');
        const res = await fetch(u.toString(), { headers: { 'X-Partial': '1' }, credentials:'include' });
        const html = await res.text();
        content.innerHTML = html;
        if (push) history.pushState({ url }, '', url);
      } catch(e) {
        console.error(e);
        content.innerHTML = '<div class="note-error">Konnte Inhalt nicht laden.</div>';
      } finally {
        hideLoading();
      }
    }

    nav.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-profile-tab]');
      if (!a) return;
      e.preventDefault();
      nav.querySelectorAll('a[data-profile-tab]').forEach(el=>el.classList.remove('is-active'));
      a.classList.add('is-active');
      loadTab(a.href, true);
    });

    window.addEventListener('popstate', (e) => {
      const url = (e.state && e.state.url) ? e.state.url : location.href;
      const current = new URL(url, location.origin).searchParams.get('tab') || 'posts';
      nav.querySelectorAll('a[data-profile-tab]').forEach(el => {
        el.classList.toggle('is-active', el.dataset.profileTab === current);
      });
      loadTab(url, false);
    });
  })();
  
  </script>
<script src="/theme/js/ratings.js?v=20250829d" defer></script>
  <link rel="stylesheet" href="/assets/styles/app.css?v=hhr-stars-1">
<script>
window.addEventListener('DOMContentLoaded', function(){
if (window.HHR) HHR.init(<?= (int)$user['id'] ?>, <?= ($me && (int)$me['id'] !== (int)$user['id']) ? 'true' : 'false' ?>);
});
</script>
<?php
$content = ob_get_clean();

$pageTitle = htmlspecialchars($user['display_name'] ?? 'Profil') . " | Hunthub";
$pageDesc  = mb_substr(strip_tags((string)($user['about_me'] ?? 'Profilseite des Users')), 0, 160);

// Avatar oder Fallback
$avatarPath = $user['avatar_path'] ?? '';
if ($avatarPath) {
    $pageImage = (preg_match('#^https?://#', $avatarPath))
        ? $avatarPath
        : $APP_BASE . $avatarPath;
} else {
    $pageImage = $APP_BASE . "/assets/images/og-profile.jpg";
}

render_theme_page($content, $pageTitle, $pageDesc, $pageImage);

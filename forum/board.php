<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_once __DIR__ . '/../lib/layout.php';

// Sprache + i18n laden
$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
// Lokale Kurzform, damit keine "Undefined variable $L" Notices entstehen
$L = $GLOBALS['L'];

// kleiner HTML‑Escaper (NULL‑safe)
function esc($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// Query‑Redirect für Suche
$q = trim($_GET['q'] ?? $_GET['search'] ?? '');
if ($q !== '') {
  header('Location: /forum/search.php?q=' . urlencode($q));
  exit;
}

$pdo = db();
$me  = function_exists('optional_auth') ? optional_auth() : null;

$cfg       = require __DIR__ . '/../auth/config.php';
$APP_BASE  = rtrim((string)($cfg['app_base'] ?? ''), '/');
$csrf      = issue_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '');

// Avatar‑Fallback (verhindert Warnings + kaputte <img>)
$avatarFallback = ($APP_BASE ?: '') . '/assets/images/avatars/placeholder.png';

$boardId = isset($_GET['b']) ? (int)$_GET['b'] : 0;
$cursor  = isset($_GET['cursor']) ? (string)$_GET['cursor'] : null;

$limit = 20;
$limitPlusOne = $limit + 1;

/* ---------- Board ---------- */
$bst = $pdo->prepare("SELECT id, name, slug, description FROM boards WHERE id = ?");
$bst->execute([$boardId]);
$board = $bst->fetch(PDO::FETCH_ASSOC);
if (!$board) {
  http_response_code(404);
  echo "Board nicht gefunden";
  exit;
}

/* ---------- Cursor‑Bedingung ---------- */
$cond   = '';
$params = [$boardId];
if ($cursor) {
  $parts = explode('|', $cursor, 2);
  if (count($parts) === 2) {
    $cond     = "AND (t.last_post_at < ? OR (t.last_post_at = ? AND t.id < ?))";
    $params[] = $parts[0];
    $params[] = $parts[0];
    $params[] = (int)$parts[1];
  }
}

/* ---------- Threads inkl. letztem Antwortenden ---------- */
/* nutzt posts(thread_id,id)-Index optimal */
$sql = "
SELECT
  t.id,
  t.title,
  t.slug,
  t.is_locked,
  t.is_pinned,
  t.posts_count,
  t.last_post_at,

  -- Ersteller (Fallback, falls noch keine Antwort existiert)
  ua.display_name AS author_name,
  ua.avatar_path  AS author_avatar,
  ua.id           AS author_id,

  -- Letzter Post & dessen Autor
  ul.display_name AS last_user_name,
  ul.avatar_path  AS last_user_avatar,
  ul.id           AS last_user_id

FROM threads t
LEFT JOIN users ua ON ua.id = t.author_id     -- LEFT JOIN, falls Autor anonymisiert/gelöscht
LEFT JOIN (
  SELECT thread_id, MAX(id) AS last_post_id
  FROM posts
  WHERE deleted_at IS NULL
  GROUP BY thread_id
) lp          ON lp.thread_id = t.id
LEFT JOIN posts  pl ON pl.id = lp.last_post_id
LEFT JOIN users  ul ON ul.id = pl.author_id

WHERE t.board_id = ? AND t.deleted_at IS NULL
$cond
ORDER BY t.is_pinned DESC, t.last_post_at DESC, t.id DESC
LIMIT $limitPlusOne";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Pagination‑Cursor ---------- */
$nextCursor = null;
if (count($rows) > $limit) {
  $last = array_pop($rows);
  $nextCursor = ($last['last_post_at'] ?? '') . '|' . $last['id'];
}

$title = 'Board – ' . ($board['name'] ?? '');

ob_start();
?>

        <!-- main start -->
        <main>
            <section class="pt-30p">
                <div class="section-pt">
                    <div
                        class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
                        <div class="container">
                            <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
                                <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                                    <h2 class="heading-2 text-w-neutral-1 mb-3">
                                        <?= esc($board['name'] ?? '') ?>
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
                                            <span class="breadcrumb-current"><?= esc($board['name'] ?? '') ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="overlay-11"></div>
                    </div>
                </div>
            </section>
            <!-- teams section start -->
            <section class="section-pb pt-30">
                <div class="container">
               
                    <div class="flex items-center justify-between flex-wrap gap-24p mb-30p">


                        <form class="flex items-center sm:flex-row flex-col gap-28p shrink-0 sm:w-fit w-full">
                            <div
                                class="sm:w-[230px] w-full shrink-0 px-16p py-3 flex items-center justify-between sm:gap-3 gap-2 rounded-12 border border-shap">
                                <input autocomplete="off" class="bg-transparent text-w-neutral-1 w-full" type="text"
                                    name="search" id="search" placeholder="Search...">
                                <button type="submit" class="flex-c icon-24 text-w-neutral-4">
                                    <i class="ti ti-search"></i>
                                </button>
                            </div>
								</form>
                    <?php if ($me): ?>
        <button id="openThreadModal" class="btn btn-md btn-primary rounded-12" >
       <?= esc($L['thread_create'] ?? 'Thread erstellen') ?>
        </button>
      <?php endif; ?>
                        

	  <?php if ($me): ?>
  <!-- Modal: Thread erstellen -->
  <style>
    .modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.65);display:none;z-index:9999}
    .modal-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:20px}
    .modal-card{background:#0b0b0b;border:1px solid rgba(255,255,255,.1);border-radius:16px;max-width:720px;width:100%}
    .modal-hd{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.08)}
    .modal-bd{padding:18px}
    .btnx{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 14px;border:1px solid rgba(255,255,255,.15);background:#1b1b1b;color:#fff}
    .btnx.primary{background:#f29620;color:#111;border-color:#f29620}
  </style>

  <div id="threadModal" class="modal-mask" aria-hidden="true">
    <div class="modal-wrap" role="dialog" aria-modal="true" aria-labelledby="threadModalTitle">
      <div class="modal-card">
<div class="modal-hd">
  <strong id="threadModalTitle"><?= esc(t('thread_new')) ?></strong>
  <button type="button" class="btnx" id="closeThreadModal"><?= esc(t('close')) ?></button>
</div>
<div class="modal-bd">
<form id="newThreadForm"
      action="<?= esc($APP_BASE) ?>/api/forum/create_thread.php"
      method="post" enctype="multipart/form-data"
      class="bg-b-neutral-3 rounded-12 p-16">
  <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
  <input type="hidden" name="board_id" value="<?= (int)$board['id'] ?>">

  <label class="label label-lg mb-2"><?= esc(t('title')) ?></label>
  <input class="box-input-3 mb-3" type="text" name="title" id="threadTitle"
         placeholder="<?= esc(t('title')) ?>" required maxlength="200">

  <label class="label label-lg mb-2"><?= esc(t('first_post')) ?></label>

  <!-- Hidden Stores wie beim Kommentar-Form -->
  <input type="hidden" name="mentions" id="replyMentions" value="[]">
  <input type="hidden" name="content_html" id="replyHtml" value="">
  <input type="hidden" name="edited_image" id="editedImage" value="">

  <!-- Plain-Text Eingabe: selbe ID wie beim Kommentar-Form, damit VT-/Emoji-Logik greift -->
  <textarea id="replyText" name="content_plain"
            class="box-input-3 h-[140px] mb-4"
            placeholder="<?= esc(t('first_post')) ?>" required></textarea>
<div id="attachPreview" class="mt-2 hidden">
  <div class="text-sm text-w-neutral-4 mb-2">
    <?= esc($L['attachments'] ?? 'Angehängte Medien') ?>
  </div>
  <div id="attachList" class="flex items-center gap-8p flex-wrap"></div>
</div>
  <!-- Toolbar: Emoji + Bild + Video-Trim (identisch zu thread.php) -->
  <div class="flex items-center gap-3 text-w-neutral-4 mb-4">
   <!--  <button type="button" id="emojiBtn" aria-label="Emoji" class="icon-24">
      <i class="ti ti-mood-smile-beam"></i>
    </button> -->

    <!-- Emoji-Popup -->
    <div id="emojiPopup" class="emoji-popup" hidden aria-hidden="true" role="dialog" aria-label="Emojis">
      <div class="emoji-box">
        <div class="emoji-head">
          <span class="emoji-title">Emojis</span>
          <button type="button" class="emoji-close" aria-label="<?= esc(t('close')) ?>">×</button>
        </div>
        <div class="emoji-grid" role="listbox" aria-label="Emoji-Liste">
          <?php // kompakte Auswahl – wie in thread.php ?>
          <button type="button" class="emo" data-emo="😀">😀</button>
          <button type="button" class="emo" data-emo="😃">😃</button>
          <button type="button" class="emo" data-emo="😄">😄</button>
          <button type="button" class="emo" data-emo="😁">😁</button>
          <button type="button" class="emo" data-emo="😂">😂</button>
          <button type="button" class="emo" data-emo="🤣">🤣</button>
          <button type="button" class="emo" data-emo="😊">😊</button>
          <button type="button" class="emo" data-emo="😍">😍</button>
          <button type="button" class="emo" data-emo="🔥">🔥</button>
          <button type="button" class="emo" data-emo="💡">💡</button>
          <button type="button" class="emo" data-emo="❤️">❤️</button>
          <button type="button" class="emo" data-emo="💚">💚</button>
          <button type="button" class="emo" data-emo="💙">💙</button>
          <button type="button" class="emo" data-emo="💛">💛</button>
        </div>
      </div>
    </div>

    <!-- Bild-Upload (öffnet Bild-Editor via assets/js/image-editor.js) -->
    <label title="<?= esc(t('image_attach') ?? 'Bild anhängen') ?>" class="icon-24 cursor-pointer">
      <i class="ti ti-photo"></i>
      <input type="file" id="replyFile" name="file" class="hidden" accept="image/*">
    </label>

    <!-- Video-Trim öffnen -->
    <button type="button" id="btnVideoTrim" class="icon-24" aria-label="<?= esc($L['video_trimm'] ?? 'Thread erstellen') ?>">
      <i class="ti ti-video"></i>
    </button>

    <!-- Absenden -->
    <div class="ml-auto">
      <button type="button" class="btnx" id="cancelThreadModal"><?= esc(t('cancel')) ?></button>
      <button class="btnx primary" id="sendBtn" type="submit"><?= esc(t('create')) ?></button>
    </div>
  </div>
</form>

<!-- Benötigte Skripte wie beim Kommentar-Form -->
<script defer src="/assets/js/image-editor.js"></script>
<script defer src="/assets/js/emoji-popup.js"></script>
</div>

        </div>
      </div>
    </div>
  </div>
<!-- VT-Modal: Video zuschneiden -->
<style id="vtModalStyles">
  /* Vollbild-Overlay, immer über normalen Modals */
  #vtModal.vt-mask { position: fixed; inset: 0; z-index: 2147483647; background: rgba(0,0,0,.6); display: none; }
  #vtModal .vt-wrap { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 24px; }
  #vtModal .vt-card { background: #151515; color: #fff; border-radius: 12px; width: min(920px, 96vw); max-height: 92vh; overflow: auto; box-shadow: 0 24px 80px rgba(0,0,0,.6); }
  #vtModal .vt-hd, #vtModal .vt-ft { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid rgba(255,255,255,.06); }
  #vtModal .vt-ft { border-top: 1px solid rgba(255,255,255,.06); border-bottom: 0; }
  #vtModal .vt-bd { padding: 16px; }
  #vtModal .vt-range-wrap { position: relative; margin-top: 12px; }
  #vtModal .vt-track { height: 6px; background: #333; border-radius: 4px; position: relative; }
  #vtModal .vt-highlight { position: absolute; top: 0; bottom: 0; left: 0; right: 0; background: #777; border-radius: 4px; }
  #vtModal .vt-range { position: absolute; left: 0; right: 0; width: 100%; -webkit-appearance: none; background: transparent; height: 16px; }
  #vtModal .vt-prog.hidden { display: none; }
  #vtModal .vt-prog-bar { height: 6px; background: #333; border-radius: 4px; overflow: hidden; }
  #vtModal #vtProgBar { height: 6px; width: 0; background: #bbb; }
  /* Track/Highlight sollen keine Pointer-Events fressen */
#vtModal .vt-track,
#vtModal .vt-highlight { pointer-events: none; }

/* Beide Range-Inputs liegen übereinander – Start muss oben sein */
#vtStartRange { z-index: 3; position: absolute; left: 0; right: 0; width: 100%; }
#vtEndRange   { z-index: 2; position: absolute; left: 0; right: 0; width: 100%; }

/* Nur die Slider-Daumen sollen Events bekommen, nicht die komplette Spur */
#vtModal .vt-range { pointer-events: none; } /* Spur ignoriert Klicks */
#vtModal .vt-range::-webkit-slider-thumb { pointer-events: auto; } /* Chrome/Safari */
#vtModal .vt-range::-moz-range-thumb    { pointer-events: auto; } /* Firefox */
#vtModal .vt-range::-ms-thumb           { pointer-events: auto; } /* Legacy Edge/IE */
#attachPreview .thumb{
  width:160px; height:90px; border-radius:8px;
  overflow:hidden; background:#111;
  border:1px solid rgba(255,255,255,.08);
}
#attachPreview .thumb video,
#attachPreview .thumb img{
  width:100%; height:100%; object-fit:cover; display:block;
}
</style>



<!-- VT-Modal: Video zuschneiden -->
<div id="vtModal" class="vt-mask" aria-hidden="true" style="display:none">
  <div class="vt-wrap">
    <div class="vt-card">
      <div class="vt-hd">
        <strong><?= esc(t('video_trimm')) ?></strong>
        <button type="button" id="vtClose" aria-label="<?= esc(t('close')) ?>" class="btnx"><?= esc(t('close')) ?></button>
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
          <span><?= esc(t('start')) ?>: <strong id="vtStartLabel">00:00</strong></span>
          <span><?= esc(t('end')) ?>: <strong id="vtEndLabel">00:00</strong></span>
        </div>
        <div class="vt-len"><?= esc(t('lenght') ?? 'Länge') ?>: <strong id="vtLen">0:00</strong></div>
        <div class="vt-meta">
          <span id="vtDurLabel"><?= esc(t('duration')) ?>: 0:00</span>
        </div>

        <!-- Fortschritt -->
        <div id="vtProgress" class="vt-prog hidden" aria-live="polite">
          <div id="vtProgText" class="vt-prog-text"><?= esc(t('working') ?? 'Wird bearbeitet...') ?></div>
          <div class="vt-prog-bar"><div id="vtProgBar"></div></div>
        </div>
      </div>
      <div class="vt-ft">
        <button type="button" id="vtUpload" class="btn btn-primary"><?= esc(t('video_upload')) ?></button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const API  = '/api/video/upload.php';

  // CSRF zuerst aus dem Formular, sonst aus <meta>
  function getCSRF() {
    return (
      document.querySelector('#newThreadForm input[name="csrf"]')?.value ||
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

  const STEP = 0.1;
  let dur = 0, active = null;

  function fmt(t){
    t = Math.max(0, Math.floor(Number(t)||0));
    const m = Math.floor(t/60), s = t%60;
    return `${m}:${String(s).padStart(2,'0')}`;
  }
  function setProg(pct, text){
    if (text) progText.textContent = text;
    if (pct == null){ progBox.classList.add('hidden'); return; }
    progBox.classList.remove('hidden');
    progBar.style.width = (pct+"%");
  }
  function hideProg(){ setProg(null); }

  function render(){
    const s = parseFloat(rStart.value)||0;
    const e = parseFloat(rEnd.value)||0;
    labStart.textContent = fmt(s);
    labEnd.textContent   = fmt(e);
    labLen.textContent   = fmt(Math.max(0, e - s));
    labDur.textContent   = '<?= esc(t('duration')) ?>: ' + fmt(dur);
    const leftPct  = dur>0 ? (s/dur*100) : 0;
    const rightPct = dur>0 ? (100 - (e/dur*100)) : 100;
    hl.style.left  = leftPct + '%';
    hl.style.right = rightPct + '%';
  }
// während des Ziehens an eine Zeit springen (mit sanftem Throttle)
let _seekRAF = null;
function previewAt(t) {
  if (!vid) return;
  t = Math.max(0, Math.min(dur || 0, Number(t) || 0));

  // beim Ziehen pausieren, damit der Frame sofort stehen bleibt
  if (!vid.paused) vid.pause();

  if (_seekRAF) cancelAnimationFrame(_seekRAF);
  _seekRAF = requestAnimationFrame(() => {
    if ('fastSeek' in vid) { try { vid.fastSeek(t); return; } catch {}
    }
    vid.currentTime = t;
  });
}

  function open(){ modal.style.display='block'; document.body.style.overflow='hidden'; }
  function close(){ modal.style.display='none'; document.body.style.overflow=''; reset(); }
  function reset(){ hideProg(); fileIn.value=''; vid.src=''; active=null; rStart.value='0'; rEnd.value='0'; dur=0; render(); }

  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  modal?.addEventListener('click', (e)=>{ if(e.target === modal) close(); });

  // Datei gewählt → Vorschau + Regler init
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
rStart?.addEventListener('input', () => {
  if (+rStart.value > +rEnd.value) rEnd.value = rStart.value;
  render();
  previewAt(rStart.value);   // <<< live in die Vorschau springen
});

rEnd  ?.addEventListener('input', () => {
  if (+rEnd.value < +rStart.value) rStart.value = rEnd.value;
  render();
  previewAt(rEnd.value);     // <<< live in die Vorschau springen
});


  // Upload + Server-Trim
  upBtn?.addEventListener('click', async () => {
    const f = fileIn.files?.[0];
    if (!f) { alert('Bitte ein Video auswählen.'); return; }
    const start = parseFloat(rStart.value);
    const end   = parseFloat(rEnd.value);
    if (!(end > start)) { alert('Ende muss größer als Start sein.'); return; }

    const csrf = getCSRF();
    if (!csrf) { alert('CSRF-Token fehlt. Seite neu laden.'); return; }

    const makeJobId = (len)=>Array.from(crypto.getRandomValues(new Uint8Array(len))).map(b=>('0'+(b%36).toString(36)).slice(-1)).join('');
    const jobId  = makeJobId(16);
    const clipDur= end - start;

    const fd = new FormData();
    fd.append('video', f);
    fd.append('start', String(start));
    fd.append('end',   String(end));
    fd.append('csrf',  csrf);
    fd.append('job',   jobId);

    upBtn.disabled = true;
    const oldLabel = upBtn.textContent;
    upBtn.textContent = '<?= esc(t('working') ?? 'Wird bearbeitet...') ?>';
    setProg(10, 'Upload…');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', API, true);
    xhr.upload.onprogress = (e)=>{
      if (e.lengthComputable) setProg(Math.min(90, Math.round(e.loaded/e.total*100)), 'Upload…');
    };
    xhr.onreadystatechange = () => {
      if (xhr.readyState !== 4) return;
      try {
        const out = JSON.parse(xhr.responseText||'{}');
        if (xhr.status < 200 || xhr.status >= 300 || out.ok === false) throw new Error(out.error||'Fehler');

        // Fortschritt weiter pollen
        setProg(50, 'Verarbeitung…');
       const url = `/api/video/progress.php?job=${encodeURIComponent(jobId)}&dur=${encodeURIComponent(clipDur)}`;
const poll = setInterval(async () => {
  try {
    const r = await fetch(url, { cache: 'no-store' });
    if (!r.ok) return;
    const st = await r.json().catch(() => ({}));
    if (!st || !st.ok) return;
    if (st.status === 'processing') return;

    clearInterval(poll);

    // <<< NEU: sichere URLs aus progress ODER upload nehmen
    const videoUrl  = (st && st.video)  || (out && out.video)  || '';
    const posterUrl = (st && st.poster) || (out && out.poster) || '';

    if (!videoUrl) {
      alert('Video-URL fehlt (Upload ok, aber keine URL geliefert). Bitte erneut versuchen.');
      upBtn.disabled = false;
      upBtn.textContent = oldLabel;
      hideProg();
      return;
    }

    const html = `
<figure class="video">
  <video controls preload="metadata" ${posterUrl ? `poster="${posterUrl}"` : ''}>
    <source src="${videoUrl}" type="video/mp4">
  </video>
</figure>`.trim();
// kleine Vorschau unter der Textarea
const attachBox  = document.getElementById('attachPreview');
const attachList = document.getElementById('attachList');
if (attachBox && attachList) {
  attachBox.classList.remove('hidden');
  const el = document.createElement('div');
  el.className = 'thumb';
  el.innerHTML = `
    <video muted playsinline preload="metadata"
      ${posterUrl ? `poster="${posterUrl}"` : ''} src="${videoUrl}">
    </video>`;
  attachList.appendChild(el);
}

    // In dein Hidden-Feld (name="content_html") schreiben – genau wie bisher
    const store = document.getElementById('replyHtml');
    if (store) store.value += (store.value ? '\n' : '') + html + '\n';

    const txt = document.getElementById('replyText');
    if (txt && !txt.value) txt.placeholder = 'Video angehängt ✓ – hier Text schreiben…';

    upBtn.disabled = false;
    upBtn.textContent = oldLabel;
    hideProg();
    close();
  } catch {}
}, 800);

      } catch (e) {
        alert((e && e.message) ? e.message : 'Upload fehlgeschlagen');
        upBtn.disabled = false;
        upBtn.textContent = oldLabel;
        hideProg();
      }
    };
    xhr.send(fd);
  });
})();
(() => {
  const imgInput    = document.getElementById('replyFile');   // <input type="file" accept="image/*">
  const editedInput = document.getElementById('editedImage'); // Hidden, vom Image-Editor
  const replyHtml   = document.getElementById('replyHtml');   // Hidden, sammelt HTML
  const box         = document.getElementById('attachPreview');
  const list        = document.getElementById('attachList');
  const seen        = new Set(); // für Deduplizierung

  function showBox(){ if (box) box.classList.remove('hidden'); }
  function norm(src){ return String(src||'').replace(location.origin, ''); }

  // Ersetzt vorhandenen IMG-Thumb (temp -> final) oder legt einen neuen an
  function setImgThumb(src, {temp=false} = {}) {
    if (!list || !src) return;
    showBox();
    src = norm(src);

    // wenn exakt gleich schon vorhanden -> nichts tun
    if (seen.has(src)) return;

    // zuerst temporären Thumb (vom File-Blob) aktualisieren …
    let el = list.querySelector('.thumb[data-kind="img"][data-temp="1"]');

    // … sonst letzten Image-Thumb nehmen …
    if (!el) el = [...list.querySelectorAll('.thumb[data-kind="img"]')].pop();

    // … oder neuen erstellen
    if (!el) {
      el = document.createElement('div');
      el.className = 'thumb';
      el.dataset.kind = 'img';
      list.appendChild(el);
    }

    el.dataset.temp = temp ? '1' : '0';
    el.innerHTML = `<img src="${src}" alt="">`;
    seen.add(src);

    // übrige temporäre Duplikate entfernen
    list.querySelectorAll('.thumb[data-kind="img"][data-temp="1"]').forEach(n => {
      if (n !== el) n.remove();
    });
  }

// NEU: Data-URL nutzen (CSP-freundlich)
imgInput?.addEventListener('change', () => {
  const f = imgInput.files?.[0];
  if (!f) return;
  const fr = new FileReader();
  fr.onload = () => setImgThumb(fr.result, { temp:true }); // fr.result = data:image/...;base64,…
  fr.readAsDataURL(f);
});


  // 2) Finale Vorschau, wenn der Editor die bearbeitete URL liefert
  editedInput?.addEventListener('change', () => {
    let v = (editedInput.value || '').trim();
    if (!v) return;
    try { const o = JSON.parse(v); if (o && o.url) v = o.url; } catch {}
    setImgThumb(v, { temp:false });
  });

  // 3) Fallback: falls der Editor direkt <img src="..."> in replyHtml schreibt
  if (replyHtml) {
    let prev = replyHtml.value;
    setInterval(() => {
      if (replyHtml.value === prev) return;
      prev = replyHtml.value;
      const m = replyHtml.value.match(/<img[^>]+src=["']([^"']+)["']/i);
      if (m && m[1]) setImgThumb(m[1], { temp:false });
    }, 800);
  }
})();

</script>


<!-- VIDEO END -->


  <script>
  (() => {
    const openBtn  = document.getElementById('openThreadModal');
    const modal    = document.getElementById('threadModal');
    const closeBtn = document.getElementById('closeThreadModal');
    const cancelBtn= document.getElementById('cancelThreadModal');
    const form     = document.getElementById('newThreadForm');
    const titleEl  = document.getElementById('threadTitle');

    function openModal(){ modal.style.display='block'; setTimeout(()=>titleEl?.focus(),30); }
    function closeModal(){ modal.style.display='none'; }

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e)=>{ if(e.target===modal) closeModal(); });
    window.addEventListener('keydown',(e)=>{ if(e.key==='Escape' && modal.style.display==='block') closeModal(); });

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          body: fd,
          credentials: 'include',
          headers: { 'Accept': 'application/json' }
        });
        const out = await res.json().catch(()=>({}));
        if (!res.ok || !out?.ok) throw new Error(out?.error || 'Fehler');

        const base = '<?= esc($APP_BASE) ?>';
        const slug = out.slug ? '&slug=' + encodeURIComponent(out.slug) : '';
        window.location.href = `${base}/forum/thread.php?t=${out.thread_id}${slug}`;
      } catch (err) {
        alert(err.message || 'Fehler beim Erstellen des Threads');
      }
    });
  })();
  </script>
  <?php endif; ?>
                    </div>
                    <div class="grid 4xl:grid-cols-4 xxl:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-30p">


        <?php foreach ($rows as $t): ?>
                <?php
                  // Fallback auf Ersteller, wenn noch niemand geantwortet hat
                  $lastName   = $t['last_user_name']   ?: ($t['author_name']   ?: 'Gelöschter Benutzer');
                  $lastAvatar = $t['last_user_avatar'] ?: ($t['author_avatar'] ?: $avatarFallback);
                  $lastUserId = $t['last_user_id']     ?: (int)($t['author_id'] ?? 0);

                  $threadUrl  = $APP_BASE . '/forum/thread.php?t=' . (int)$t['id']
                              . (!empty($t['slug']) ? '&slug=' . urlencode($t['slug']) : '');
                  $authorUrl  = $APP_BASE . '/user.php?id=' . (int)($t['author_id'] ?? 0);
                  $lastUrl    = $APP_BASE . '/user.php?id=' . (int)$lastUserId;

                  $authorAvatar = !empty($t['author_avatar']) ? $t['author_avatar'] : $avatarFallback;
                ?>


                        <!-- Card -->
                        <div class="bg-b-neutral-3 rounded-12 p-32p border border-transparent hover:border-accent-7 group transition-1"
                            data-aos="zoom-in">
                            <div class="flex items-start justify-between gap-24p mb-24p">
                                <div class="flex-y flex-wrap gap-3">
                                    <img class="avatar size-60p" src="<?= esc($authorAvatar) ?>" onerror="this.onerror=null;this.src='<?= esc($avatarFallback) ?>'" alt="<?= esc($t['author_name'] ?? 'Gelöschter Benutzer') ?>" />
                                    <div>
                                        <a href="<?= esc($threadUrl) ?>"
                                            class="text-xl-medium text-w-neutral-1 link-1"><?= esc($t['title'] ?? 'Ohne Titel') ?></a>
                                        <span class="text-m-medium text-w-neutral-3"><?= esc($t['author_name'] ?? 'Gelöschter Benutzer') ?></span>
                                    </div>
                                </div>
                          
                            </div>
                            <div class="flex-y flex-wrap gap-20p whitespace-nowrap mb-32p">
               
                                <div>
                                    <span class="text-m-medium text-w-neutral-4 mb-1"><?= esc($L['posts'] ?? 'Beiträge') ?></span>
                                    <div class="text-l-medium text-w-neutral-1"><?= (int)($t['posts_count'] ?? 0) ?></div>
                                </div>
                                <div>
                                    <span class="text-m-medium text-w-neutral-4 mb-1"><?= esc($L['date'] ?? 'Datum') ?></span>
                                    <span class="text-l-medium text-w-neutral-1"><?php
                                      $lp = (string)($t['last_post_at'] ?? '');
                                      echo $lp !== '' ? esc(date('d.m.Y - H:i', @strtotime($lp))) : '';
                                    ?></span>
                                </div>
                            </div>
                            <div class="flex-y flex-wrap justify-between gap-24p pt-32p border-t border-t-shap">
                                <div
                                    class="flex items-center *:size-40p *:shrink-0 *:size-40p *:border *:border-white *:-ml-3 ml-3">
                                    <img class="avatar" src="<?= esc($lastAvatar) ?>" alt="user" onerror="this.onerror=null;this.src='<?= esc($avatarFallback) ?>'" />
                               
                                    <span
                                        class="flex-c rounded-full bg-[#333333] text-s-medium text-w-neutral-1">+<?= (int)($t['posts_count'] ?? 0) ?></span>
                                </div>
                                <a href="<?= esc($threadUrl) ?>"
                                    class="btn px-16p py-2 btn-outline-secondary group-hover:bg-secondary group-hover:text-b-neutral-4">
                                    <?= esc($L['go_to_thread'] ?? 'Zum Thread') ?>
                                </a>
                            </div>
                        </div>
 <?php endforeach; ?>

                    </div>
               <?php if ($nextCursor): ?>
                <div class="p-16 text-center">
                  <a class="btn btn-sm btn-secondary rounded-12"
                     href="?b=<?= (int)$board['id'] ?>&cursor=<?= urlencode($nextCursor) ?>">
                    <?= esc($L['load_more_threads'] ?? 'Weitere Threads laden') ?>
                  </a>
                </div>
              <?php endif; ?>
                </div>
            </section>
            <!-- teams section end -->

        </main>
        <!-- main end -->

        <!-- footer start -->
<?php
$content = ob_get_clean();

$pageTitle = h($board['name'] ?? 'Board') . " | Hunthub Forum";
$pageDesc  = "Diskussionen im Board „" . h($board['name'] ?? 'Board') . "“";
$pageImage = $APP_BASE . "/assets/images/og-board.jpg";

// JSON-LD CollectionPage
$schemaJson = hh_schema_collection(
    (string)($board['name'] ?? 'Board'),
    $APP_BASE,
    '/forum/board.php?b='.(int)($board['id'] ?? 0),
    $pageDesc
);

render_theme_page($content, $pageTitle, $pageDesc, $pageImage, $schemaJson);

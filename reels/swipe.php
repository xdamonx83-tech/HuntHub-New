<?php
declare(strict_types=1);
session_start();

/* === CSRF laden (wie auf der Wall) ===================================== */
$csrf = '';
$csrfPath = $_SERVER['DOCUMENT_ROOT'] . '/auth/csrf.php';
if (is_file($csrfPath)) {
  require_once $csrfPath;
  if (function_exists('csrf_token')) $csrf = (string)csrf_token();
}
if ($csrf === '') {
  $csrf = (string)($_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? $_SESSION['x_csrf'] ?? '');
}

/* === Reels-Quelle ======================================================= */
$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$videosDir  = $publicRoot . '/uploads/reels/videos';
$postersDir = $publicRoot . '/uploads/reels/posters';
$mapFile    = $publicRoot . '/uploads/reels/map.json';

$items = [];
if (is_file($mapFile)) {
  $items = json_decode((string)file_get_contents($mapFile), true) ?: [];
} else {
  if (is_dir($videosDir)) {
    foreach (glob($videosDir . '/*.mp4') as $abs) {
      $name = basename($abs, '.mp4');
      $items[] = [
        'id'       => $name,
        'post_id'  => 0,
        'video'    => '/uploads/reels/videos/' . $name . '.mp4',
        'poster'   => is_file($postersDir . '/' . $name . '.jpg') ? '/uploads/reels/posters/' . $name . '.jpg' : '',
        'duration' => null,
      ];
    }
    usort($items, fn($a,$b)=> strcmp($b['id'],$a['id']));
  }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Reels</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSRF -->
  <meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <style>
    html,body{margin:0;height:100%;background:#000;color:#fff}
    .swiper{height:100vh}
    .swiper-slide{display:flex;align-items:center;justify-content:center;position:relative;background:#000}
    video.reel{width:100%;height:100%;object-fit:cover;background:#000}
    .hud{position:absolute;right:10px;bottom:20%;display:flex;flex-direction:column;gap:10px;z-index:5}
    .hud button{width:46px;height:46px;border-radius:50%;border:0;background:rgba(0,0,0,.45);color:#fff;font-size:18px;cursor:pointer}
    .hud button[disabled]{opacity:.45;cursor:not-allowed}
    .btn-like.active{background:rgba(220,38,38,.75)}
    .tip{position:absolute;bottom:16px;left:0;right:0;text-align:center;font:14px/1.2 system-ui;color:#eee;opacity:.75}

    /* Leichtes Comments-Modal */
    .rcm-wrap{position:fixed;inset:0;z-index:2147483600;display:none}
    .rcm-wrap[open]{display:block}
    .rcm-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(2px)}
    .rcm-dialog{
      position:relative;margin:4vh auto;height:min(88vh,100vh);width:min(1000px,95vw);
      display:grid;grid-template-rows:auto 1fr auto;border-radius:16px;
      background:#0f1012;border:1px solid rgba(255,255,255,.08);box-shadow:0 20px 50px rgba(0,0,0,.5);
    }
    .rcm-header,.rcm-footer{padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.08)}
    .rcm-footer{border-top:1px solid rgba(255,255,255,.08);border-bottom:0}
    .rcm-body{overflow:auto;-webkit-overflow-scrolling:touch;padding:10px 14px}
    .rcm-close{position:absolute;top:10px;right:10px;width:34px;height:34px;border-radius:999px;border:0;background:transparent;color:#fff;font-size:18px;cursor:pointer}
    .rcm-input{width:100%;min-height:44px;color:#fff;background:#15161a;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:10px}
    .rcm-send{margin-left:8px;border:0;background:#2563eb;color:#fff;padding:10px 14px;border-radius:10px;cursor:pointer}
    @media (max-width: 768px){
      .rcm-dialog{width:100vw;height:100vh;margin:0;border-radius:0;border-left:0;border-right:0}
    }
  </style>
</head>
<body>

<div class="swiper">
  <div class="swiper-wrapper">
    <?php foreach ($items as $it): 
      $pid = (int)($it['post_id'] ?? 0);
      $disabled = $pid ? '' : 'disabled';
    ?>
    <div class="swiper-slide" id="r-<?= htmlspecialchars($it['id']) ?>" data-post-id="<?= $pid ?>">
      <video class="reel"
             src="<?= htmlspecialchars($it['video']) ?>"
             <?= $it['poster'] ? 'poster="'.htmlspecialchars($it['poster']).'"' : '' ?>
             playsinline muted preload="metadata"></video>

      <div class="hud">
        <button class="btn-sound"   title="Ton ein/aus">🔇</button>
        <button class="btn-like"    title="Gefällt mir" data-post="<?= $pid ?>" <?= $disabled ?>>❤</button>
        <button class="btn-comment" title="Kommentare"  data-post="<?= $pid ?>" <?= $disabled ?>>💬</button>
      </div>

      <?php if (!$pid): ?>
        <div class="tip">Dieses Reel ist (noch) nicht mit einem Wall-Post verknüpft.</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Leichtes Comments-Modal -->
<div id="rcm" class="rcm-wrap" aria-hidden="true">
  <div class="rcm-backdrop"></div>
  <div class="rcm-dialog">
    <button class="rcm-close" aria-label="Schließen">✕</button>
    <div class="rcm-header"><strong>Kommentare</strong></div>
    <div class="rcm-body" id="rcm-body">
      <!-- Kommentare werden hier geladen -->
    </div>
    <div class="rcm-footer">
      <div style="display:flex;gap:8px;align-items:center">
        <textarea id="rcm-text" class="rcm-input" rows="2" placeholder="Antwort schreiben…"></textarea>
        <button id="rcm-send" class="rcm-send">Senden</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
/* ===== CSRF ============================================================= */
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

/* ===== Swiper & Video Steuerung ======================================== */
let globalMuted = true;
const sw = new Swiper('.swiper', {
  direction: 'vertical',
  slidesPerView: 1,
  mousewheel: true,
  on: {
    init: () => playActive(true),
    slideChangeTransitionEnd: () => playActive(false),
  }
});

function allVideos(){ return Array.from(document.querySelectorAll('video.reel')); }
function activeSlide(){ return document.querySelector('.swiper-slide-active'); }
function activeVideo(){ return activeSlide()?.querySelector('video.reel') || null; }
function soundBtn(slide){ return slide?.querySelector('.btn-sound'); }

function playActive(firstInit){
  allVideos().forEach(v => { try{ v.pause(); }catch(_){ } });
  const slide = activeSlide();
  const vid = activeVideo();
  if (!vid) return;
  vid.muted = globalMuted;
  vid.removeAttribute('controls');
  if (firstInit) { try{ vid.load(); }catch(_){} }
  const btn = soundBtn(slide);
  if (btn) btn.textContent = globalMuted ? '🔇' : '🔊';
  const p = vid.play();
  if (p && p.catch) p.catch(()=>{ /* Interaktion nötig */ });
}

/* Tap-to-Play/Pause */
document.addEventListener('click', (e)=>{
  const vid = e.target.closest('video.reel');
  if (vid) {
    if (vid.paused) vid.play().catch(()=>{});
    else vid.pause();
  }
});

/* Sound-Toggle */
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.btn-sound');
  if (!btn) return;
  globalMuted = !globalMuted;
  const vid = activeVideo();
  if (vid) { vid.muted = globalMuted; vid.play().catch(()=>{}); }
  btn.textContent = globalMuted ? '🔇' : '🔊';
});

/* ===== Likes ============================================================ */
async function toggleLike(postId, btn){
  if (!postId) return;
  try{
    const fd = new FormData();
    // kompatibel zu verschiedenen Backends
    fd.set('id', postId);
    fd.set('post_id', postId);
    if (CSRF) fd.set('csrf', CSRF);

    const res = await fetch('/api/wall/like_toggle.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        ...(CSRF ? {'X-CSRF': CSRF, 'X-CSRF-Token': CSRF} : {})
      }
    });
    const j = await res.json().catch(()=>null);
    if (j && j.ok === false && /csrf/i.test(j.error||'')) {
      alert('Sicherheits-Token ist abgelaufen. Bitte Seite neu laden.');
      return;
    }
    // UI toggeln (Server zählt ohnehin richtig)
    btn.classList.toggle('active');
  }catch(err){
    console.error('like failed:', err);
  }
}
document.addEventListener('click', (e)=>{
  const lbtn = e.target.closest('.btn-like');
  if (!lbtn || lbtn.disabled) return;
  toggleLike(+lbtn.dataset.post || 0, lbtn);
});

/* ===== Leichtes Kommentar-Modal ======================================== */
const RCM = document.getElementById('rcm');
const RCM_BODY = document.getElementById('rcm-body');
const RCM_TEXT = document.getElementById('rcm-text');
const RCM_SEND = document.getElementById('rcm-send');
let RCM_POST_ID = 0;

function rcmOpen(){ RCM.setAttribute('open',''); RCM.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
function rcmClose(){ RCM.removeAttribute('open'); RCM.setAttribute('aria-hidden','true'); document.body.style.overflow=''; RCM_BODY.innerHTML=''; RCM_TEXT.value=''; RCM_POST_ID=0; }

RCM.querySelector('.rcm-backdrop').addEventListener('click', rcmClose);
RCM.querySelector('.rcm-close').addEventListener('click', rcmClose);
document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape' && RCM.hasAttribute('open')) rcmClose(); });

async function fetchComments(postId){
  // Wir versuchen mehrere Endpunkte; erstes 200 gewinnt.
  const endpoints = [
    `/api/wall/comments.php?post_id=${encodeURIComponent(postId)}`,
    `/api/wall/comments_list.php?post_id=${encodeURIComponent(postId)}`,
  ];
  for (const url of endpoints){
    try{
      const res = await fetch(url, { credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json,text/html'} });
      if (!res.ok) continue;
      const ct = res.headers.get('content-type') || '';
      if (ct.includes('application/json')) {
        const j = await res.json();
        return {type:'json', data:j};
      } else {
        const html = await res.text();
        return {type:'html', data:html};
      }
    }catch(_){}
  }
  throw new Error('no_endpoint');
}

function renderJsonComments(j){
  const arr = Array.isArray(j?.comments) ? j.comments : [];
  if (!arr.length) return '<div style="opacity:.7">Noch keine Kommentare.</div>';
  return arr.map(c=>{
    const name = (c.username || c.user?.username || 'User');
    const txt  = (c.content_plain || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    const media= c.content_html ? `<div class="mt-1">${c.content_html}</div>` : '';
    return `<div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06)">
      <div style="font-weight:600">${name}</div>
      <div style="opacity:.9">${txt}</div>${media}
    </div>`;
  }).join('');
}

async function openComments(postId){
  RCM_POST_ID = postId;
  rcmOpen();
  RCM_BODY.innerHTML = '<div style="opacity:.7">Lade…</div>';
  try{
    const res = await fetchComments(postId);
    if (res.type === 'html') {
      RCM_BODY.innerHTML = res.data;
    } else {
      RCM_BODY.innerHTML = renderJsonComments(res.data);
    }
  }catch(e){
    console.error(e);
    RCM_BODY.innerHTML = '<div style="color:#f87171">Kommentare konnten nicht geladen werden.</div>';
  }
}

RCM_SEND.addEventListener('click', async ()=>{
  const text = (RCM_TEXT.value || '').trim();
  if (!RCM_POST_ID || !text) { RCM_TEXT.focus(); return; }
  try{
    const fd = new FormData();
    fd.set('post_id', RCM_POST_ID);
    fd.set('text', text);
    if (CSRF) fd.set('csrf', CSRF);
    const res = await fetch('/api/wall/comment_create.php', {
      method:'POST',
      body: fd,
      credentials:'same-origin',
      headers:{ 'X-Requested-With':'XMLHttpRequest', ...(CSRF ? {'X-CSRF':CSRF} : {}) }
    });
    const j = await res.json().catch(()=>null);
    if (!res.ok || (j && j.ok===false)) {
      if (j && /csrf/i.test(j.error||'')) alert('Sicherheits-Token ist abgelaufen. Bitte Seite neu laden.');
      else alert('Kommentar konnte nicht gesendet werden.');
      return;
    }
    RCM_TEXT.value = '';
    // nach Senden neu laden
    openComments(RCM_POST_ID);
  }catch(err){
    console.error(err);
    alert('Kommentar konnte nicht gesendet werden.');
  }
});

document.addEventListener('click', (e)=>{
  const cbtn = e.target.closest('.btn-comment');
  if (!cbtn || cbtn.disabled) return;
  const pid = +cbtn.dataset.post;
  if (pid) openComments(pid);
});

/* Direkt zu #r-<id> springen */
if (location.hash.startsWith('#r-')) {
  const id = location.hash.slice(3);
  const idx = Array.from(document.querySelectorAll('.swiper-slide'))
    .findIndex(s => s.id === 'r-'+id);
  if (idx >= 0) sw.slideTo(idx, 0);
}
</script>
</body>
</html>

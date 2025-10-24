<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/layout.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);

/* Mini-Translator */
if (!function_exists('t')) {
    function t(string $key, array $vars = []): string {
        $L = $GLOBALS['L'] ?? [];
        $s = $L[$key] ?? $key;
        if ($vars) {
            $repl = [];
            foreach ($vars as $k => $v) $repl['{' . $k . '}'] = (string)$v;
            return strtr($s, $repl);
        }
        return $s;
    }
}

$me  = require_auth();
$pdo = db();

require_once __DIR__ . '/lib/points.php';
$__ssr_balance = get_user_points($pdo, (int)$me['id']);

$cfg        = require __DIR__ . '/auth/config.php';
$APP_BASE   = rtrim($cfg['app_base'] ?? '/', '/');          // für interne Pfade/Assets
$APP_PUBLIC = rtrim($cfg['app_base_public'] ?? '', '/');    // für öffentliche Links/API-Aufrufe
$csrf       = issue_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '');

/* Fallback-Grafiken */
$coverFallback  = $APP_BASE . '/assets/images/cover-placeholder.jpg';
$avatarFallback = $APP_BASE . '/assets/images/avatars/placeholder.png';

/* SAFE: undefined keys abfangen */
$coverSrc  = (string)($me['cover_path']  ?? '');
$avatarSrc = (string)($me['avatar_path'] ?? '');
$coverSrc  = $coverSrc  !== '' ? $coverSrc  : $coverFallback;
$avatarSrc = $avatarSrc !== '' ? $avatarSrc : $avatarFallback;

$coverX     = isset($me['cover_x'])     ? (float)$me['cover_x']     : null;
$coverY     = isset($me['cover_y'])     ? (float)$me['cover_y']     : null;
$coverScale = isset($me['cover_scale']) ? (float)$me['cover_scale'] : null;

$title = t('profile.edit_title');

/* REF-LINK: konsequent ref_code (Fallback erzeugen, falls leer) */
$myRef = (string)($me['ref_code'] ?? '');
if ($myRef === '' && isset($me['id'])) {
    $myRef = 'REF' . str_pad((string)$me['id'], 6, '0', STR_PAD_LEFT);
}
$refLink = $APP_PUBLIC . '/register.php?ref=' . urlencode($myRef);

ob_start();
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf) ?>">
<script>window.APP_BASE = <?= json_encode($APP_PUBLIC) ?>;</script>

<main>
  <section class="section-py">
    <div class="container pt-30p">

      <!-- Cover -->
      <div class="relative rounded-32 overflow-hidden">
        <div id="coverFrame"
             class="relative w-full xl:h-[472px] lg:h-[400px] md:h-[340px] sm:h-[300px] h-[240px] bg-black/30 overflow-hidden rounded-32">
          <img id="coverImg"
               src="<?= htmlspecialchars($coverSrc) ?>"
               alt="<?= htmlspecialchars(t('profile.cover_alt')) ?>"
               class="absolute top-0 left-0 will-change-transform select-none pointer-events-none"
               style="transform-origin: 0 0;">
          <label for="coverInput"
                 class="cursor-pointer absolute xl:top-[30px] md:top-5 top-4 xl:right-[30px] md:right-5 right-4 z-[5]">
            <span class="flex-c size-60p rounded-full bg-b-neutral-3 text-w-neutral-1 icon-32">
              <i class="ti ti-camera"></i>
            </span>
          </label>
          <input type="file" id="coverInput" accept="image/png,image/gif,image/jpeg,image/webp" class="hidden">
        </div>
      </div>

      <!-- Avatar + Shop-Bar -->
      <div class="relative flex 3xl:items-end max-3xl:items-center 3xl:justify-between max-3xl:flex-col gap-30p
                  3xl:mt-[90px] xl:-mt-52 lg:-mt-44 md:-mt-36 sm:-mt-30 -mt-20 4xl:mb-[70px] mb-60p">
        <div class="3xl:absolute 3xl:bottom-0 3xl:left-1/2 3xl:-translate-x-1/2 max-3xl:flex-col-c z-[4]">
          <img id="avatarImg"
               class="avatar xl:size-60 lg:size-52 md:size-44 sm:size-40 size-28 border-2 border-secondary rounded-full object-cover"
               src="<?= htmlspecialchars($avatarSrc) ?>" alt="<?= htmlspecialchars(t('profile.avatar_alt')) ?>">
          <label for="avatarInput"
                 class="cursor-pointer absolute lg:-bottom-6 md:-bottom-5 -bottom-4 left-1/2 -translate-x-1/2">
            <span class="flex-c size-60p rounded-full bg-primary text-b-neutral-4 icon-32">
              <i class="ti ti-camera"></i>
            </span>
          </label>
          <input type="file" id="avatarInput" accept="image/png,image/gif,image/jpeg,image/webp" class="hidden">
        </div>

        <!-- Shop quick actions (rechts) -->
      
      </div>

      <!-- Formular -->
      <div class="grid grid-cols-12 gap-30p">
        <div class="xxl:col-start-3 xxl:col-end-11 col-span-12 ">
          <div class="bg-b-neutral-3 rounded-12 p-40p">
            <h4 class="heading-4 text-w-neutral-1 mb-60p" data-i18n="general"><?= htmlspecialchars($L['general']) ?></h4>

            <!-- feste absolute URL, unabhängig von app_base -->
            <form id="profileForm" action="/api/auth/update_profile.php" method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

              <div class="grid grid-cols-8 gap-30p">
                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3" data-i18n="username"><?= htmlspecialchars($L['username']) ?></label>
                  <input type="text" name="display_name" value="<?= htmlspecialchars((string)$me['display_name']) ?>" required class="box-input-3" />
                </div>





        <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3">Punktekonto</label>
                  <div class="flex gap-2">
                    <input type="text" id="pointsBadge" value="<?= (int)$__ssr_balance ?>" required class="box-input-3 flex-1" readonly />
                    <button type="button" id="openShopBtn" class="btn btn-md btn-secondary rounded-12">Guthabenshop</button>
                  </div>
                  <small class="text-gray-400">Gib deine verdienten Punkte aus</small>
                </div>









                <div class="col-span-8">
                  <label class="label label-lg mb-3" data-i18n="email"><?= htmlspecialchars($L['email']) ?></label>
                  <input type="email" name="email" value="<?= htmlspecialchars((string)$me['email']) ?>" class="box-input-3" />
                </div>

                <div class="col-span-8">
                  <label class="label label-lg mb-3" data-i18n="about"><?= htmlspecialchars($L['about']) ?></label>
                  <textarea class="box-input-3 h-[142px]" name="bio" rows="4"><?= htmlspecialchars((string)($me['bio'] ?? '')) ?></textarea>
                </div>

                <div class="col-span-8">
                  <label class="label label-lg mb-3" data-i18n="location"><?= htmlspecialchars($L['location']) ?></label>
                  <input type="text" name="location" class="box-input-3" />
                </div>

                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3" data-i18n="slug"><?= htmlspecialchars($L['slug']) ?></label>
                  <input type="text" value="https://htda.de/u/<?= htmlspecialchars((string)$me['slug']) ?>" class="box-input-3" readonly />
                </div>

                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3">Einladungslink</label>
                  <div class="flex gap-2">
                    <input id="refLinkInput" type="text" value="<?= htmlspecialchars($refLink) ?>" class="box-input-3 flex-1" readonly />
                    <button type="button" id="copyRefBtn" class="btn btn-md btn-secondary rounded-12">Kopieren</button>
                  </div>
                  <small class="text-gray-400">Teile diesen Link, um Freunde einzuladen</small>
                </div>
              </div>

              <div class="grid grid-cols-8 gap-30p">
                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3"><?= htmlspecialchars(t('profile.social.twitch.label')) ?></label>
                  <input name="twitch" type="text" class="box-input-3" placeholder="<?= htmlspecialchars(t('profile.social.twitch.ph')) ?>"
                         value="<?= htmlspecialchars((string)($me['social_twitch'] ?? ''), ENT_QUOTES) ?>" />
                </div>
                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3"><?= htmlspecialchars(t('profile.social.tiktok.label')) ?></label>
                  <input name="tiktok" type="text" class="box-input-3" placeholder="<?= htmlspecialchars(t('profile.social.tiktok.ph')) ?>"
                         value="<?= htmlspecialchars((string)($me['social_tiktok'] ?? ''), ENT_QUOTES) ?>" />
                </div>
                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3"><?= htmlspecialchars(t('profile.social.youtube.label')) ?></label>
                  <input name="youtube" type="text" class="box-input-3" placeholder="<?= htmlspecialchars(t('profile.social.youtube.ph')) ?>"
                         value="<?= htmlspecialchars((string)($me['social_youtube'] ?? ''), ENT_QUOTES) ?>" />
                </div>
                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3"><?= htmlspecialchars(t('profile.social.instagram.label')) ?></label>
                  <input name="instagram" type="text" class="box-input-3" placeholder="<?= htmlspecialchars(t('profile.social.instagram.ph')) ?>"
                         value="<?= htmlspecialchars((string)($me['social_instagram'] ?? ''), ENT_QUOTES) ?>" />
                </div>
                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3"><?= htmlspecialchars(t('profile.social.twitter_x.label')) ?></label>
                  <input name="twitter" type="text" class="box-input-3" placeholder="<?= htmlspecialchars(t('profile.social.twitter_x.ph')) ?>"
                         value="<?= htmlspecialchars((string)($me['social_twitter'] ?? ''), ENT_QUOTES) ?>" />
                </div>
                <div class="sm:col-span-4 col-span-8">
                  <label class="label label-lg mb-3"><?= htmlspecialchars(t('profile.social.facebook.label')) ?></label>
                  <input name="facebook" type="text" class="box-input-3" placeholder="<?= htmlspecialchars(t('profile.social.facebook.ph')) ?>"
                         value="<?= htmlspecialchars((string)($me['social_facebook'] ?? ''), ENT_QUOTES) ?>" />
                </div>
              </div>

              <div class="flex items-center md:justify-end justify-center">
                <button class="btn btn-md btn-primary rounded-12 mt-60p" data-i18n="save"><?= htmlspecialchars($L['save']) ?></button>
              </div>
            </form>
<!-- Save Modal -->
<div id="saveModal" aria-hidden="true">
  <div class="card">✅ Profil gespeichert</div>
</div>

            <form action="<?= $APP_BASE ?>/api/privacy/export.php" method="post">
              <button class="btn btn-md btn-secondary rounded-12 mt-60p"><?= htmlspecialchars(t('privacy.download_my_data')) ?></button>
            </form>

<style>
  /* Basale Resets */
  [hidden]{display:none!important}

  /* Delete-Modal */
  #deleteAccountModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);align-items:center;justify-content:center;z-index:1000;}
  #deleteAccountModal[aria-hidden="true"]{display:none!important;visibility:hidden!important;}
  #deleteAccountModal.is-open{display:flex!important;}
  .modal-dialog{width:92%;max-width:460px;background:#111827;color:#e5e7eb;border:1px solid #374151;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.6);}
  .modal-header{padding:14px 16px;border-bottom:1px solid #1f2937;display:flex;align-items:center;justify-content:space-between;}
  .modal-title{margin:0;font-size:1rem;font-weight:700;color:#fca5a5;}
  .modal-close{appearance:none;background:none;border:none;color:#9ca3af;font-size:20px;line-height:1;cursor:pointer;}
  .modal-body{padding:16px;display:grid;gap:10px;}
  .modal-footer{padding:14px 16px;border-top:1px solid #1f2937;display:flex;gap:10px;justify-content:flex-end;}
  .btn-secondary{background:#374151;color:#e5e7eb;border:none;padding:10px 14px;border-radius:10px;cursor:pointer;}
  .btn-secondary[disabled]{opacity:.6;cursor:not-allowed;}
  .btn-danger-outline{background:transparent;border:1px solid #ef4444;color:#fca5a5;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer;}
  .btn-danger-outline[disabled]{opacity:.5;cursor:not-allowed;}
  .field{display:grid;gap:6px;}
  .field input[type="text"]{background:#0b1220;border:1px solid #374151;color:#e5e7eb;border-radius:10px;padding:10px 12px;outline:none;}
  .hint{color:#9ca3af;font-size:.85rem;}
  .error{color:#fca5a5;font-size:.85rem;}
  .danger-zone{border:1px solid #ef4444;background:#1f2937;padding:16px;border-radius:12px;}
  .btn-danger{background:#ef4444;color:#111827;border:none;padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer;}

  /* Cover-Editor */
  .modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;z-index:9999;}
  .modal-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:20px;}
  .modal-card{background:#0b0b0b;border:1px solid rgba(255,255,255,.1);border-radius:16px;max-width:980px;width:100%;}
  .modal-hd{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.08);}
  .modal-bd{padding:18px;}
  .frame{position:relative;overflow:hidden;background:#111;border-radius:12px;}
  .editor-img{position:absolute;top:0;left:0;will-change:transform;user-select:none;cursor:grab;transform-origin:0 0;}
  .editor-img.dragging{cursor:grabbing;}
  .controls{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:14px;}
  input[type="range"]{width:220px;}
  .btnx{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 14px;border:1px solid rgba(255,255,255,.15);background:#1b1b1b;color:#fff}
  .btnx.primary{background:#f29620;color:#111;border-color:#f29620}

  /* ===== SHOP MODAL (kategorisiert) ===== */
  #shopModal{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;z-index:1000;}
  #shopModal.is-open{display:flex;align-items:center;justify-content:center;}
  #shopModal .card{background:#0b0b0b;width:min(1040px,96vw);max-height:80vh;border-radius:16px;border:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;overflow:hidden;}
  #shopModal .hd{padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;justify-content:space-between;align-items:center;}
  #shopModal .bd{padding:0;overflow:auto;}

  /* Kategorie-Navigation */
  .cats-nav{position:sticky;top:0;z-index:2;background:#0b0b0b;border-bottom:1px solid rgba(255,255,255,.08);padding:10px 18px;display:flex;gap:8px;flex-wrap:wrap}
  .cats-nav a{display:inline-block;padding:6px 10px;border:1px solid rgba(255,255,255,.12);border-radius:999px;color:#e5e7eb;text-decoration:none;font-size:.95rem}
  .cats-nav a:hover{background:#151515}

  /* Kategorien als Sektionen (untereinander; jede Sektion ein Grid) */
  .shop-section{padding:16px 18px 8px}
  .shop-section h5{margin:0 0 10px;color:#fff;font-weight:700;font-size:1.05rem;opacity:.95}
  .shop-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px}

  /* Karten */
  .shop-card{background:#111;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px}
  .shop-thumb{aspect-ratio:1/1;background:#0f0f0f;border-radius:8px;overflow:hidden}
  .shop-thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .shop-title{font-weight:600;color:#e5e7eb;min-height:2.2em}
  .shop-desc{font-size:.9rem;color:#9ca3af;min-height:1.2em}
  .shop-price{color:#fbbf24;font-weight:700}
  .shop-buy-btn{background:#f29620;color:#111;border:none;border-radius:10px;padding:8px 10px;cursor:pointer}
  .shop-buy-btn[disabled]{opacity:.6;cursor:not-allowed}
  .shop-pill{display:inline-block;background:#374151;color:#e5e7eb;border-radius:999px;padding:4px 8px;font-size:.85rem}
  /* ===== SAVE-MODAL ===== */
#saveModal {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}
#saveModal.is-open {
  display: flex;
}
#saveModal .card {
  background: #111827;
  color: #e5e7eb;
  padding: 20px 26px;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0,0,0,.6);
  font-size: 1rem;
  font-weight: 600;
  animation: fadeInUp .25s ease-out;
}
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

</style>

            <!-- Danger-Zone -->
            <div class="danger-zone">
              <h3><?= htmlspecialchars(t('profile.danger.title')) ?></h3>
              <p><?= t('profile.danger.desc') ?></p>
              <button id="btnDeleteAccount" class="btn-danger" type="button"><?= htmlspecialchars(t('profile.danger.open_modal')) ?></button>
            </div>

            <!-- Modal (Account löschen) -->
            <div id="deleteAccountModal" class="modal-backdrop" hidden aria-hidden="true">
              <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="delTitle">
                <div class="modal-header">
                  <h4 id="delTitle" class="modal-title"><?= htmlspecialchars(t('profile.modal.title')) ?></h4>
                  <button class="modal-close" type="button" aria-label="<?= htmlspecialchars(t('profile.modal.close')) ?>">×</button>
                </div>
                <div class="modal-body">
                  <p><?= t('profile.modal.body') ?></p>
                  <div class="field">
                    <label for="delConfirmInput"><?= htmlspecialchars(t('profile.modal.label')) ?></label>
                    <input id="delConfirmInput" type="text" inputmode="latin" autocomplete="off" placeholder="<?= htmlspecialchars(t('profile.modal.placeholder')) ?>">
                    <span class="hint"><?= htmlspecialchars(t('profile.modal.hint')) ?></span>
                    <span class="error" id="delError" hidden></span>
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn-secondary" type="button" data-action="cancel"><?= htmlspecialchars(t('profile.modal.cancel')) ?></button>
                  <button id="delConfirmBtn" class="btn-danger-outline" type="button" disabled><?= htmlspecialchars(t('profile.modal.confirm')) ?></button>
                </div>
              </div>
            </div>

            <!-- ===== SHOP MODAL ===== -->
            <div id="shopModal" aria-hidden="true">
              <div class="card">
                <div class="hd">
                  <h4 class="shop-title">Guthabenshop</h4>
                  <button class="modal-close shop-close" type="button" aria-label="Schließen">×</button>
                </div>
                <div class="bd">
                  <div id="shopCats" class="cats-nav"></div>
                  <div id="shopItems"></div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </section>
</main>

<!-- Overlay/Editor (Cover) -->
<div id="coverModal" class="modal-mask">
  <div class="modal-wrap">
    <div class="modal-card">
      <div class="modal-hd">
        <strong><?= htmlspecialchars(t('profile.cover_editor.title')) ?></strong>
        <button type="button" class="btnx" id="coverCancel"><?= htmlspecialchars(t('profile.cover_editor.cancel')) ?></button>
      </div>
      <div class="modal-bd">
        <div id="editFrame" class="frame" style="width:100%;height:472px;max-height:60vh;">
          <img id="editImg" class="editor-img" src="" alt="">
        </div>
        <div class="controls">
          <label><?= htmlspecialchars(t('profile.cover_editor.zoom')) ?></label>
          <input id="zoom" type="range" min="0.5" max="3" step="0.01" value="1">
          <button class="btnx" id="centerBtn" type="button"><?= htmlspecialchars(t('profile.cover_editor.center')) ?></button>
          <button class="btnx primary" id="saveCover" type="button"><?= htmlspecialchars(t('profile.cover_editor.save')) ?></button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* ===== Account löschen ===== */
(function(){
  const APP_BASE = window.APP_BASE || '';
  const btnOpen  = document.getElementById('btnDeleteAccount');
  const modal    = document.getElementById('deleteAccountModal');
  const dlg      = modal?.querySelector('.modal-dialog');
  const btnClose = modal?.querySelector('.modal-close');
  const btnCancel= modal?.querySelector('[data-action="cancel"]');
  const btnOk    = document.getElementById('delConfirmBtn');
  const input    = document.getElementById('delConfirmInput');
  const errorEl  = document.getElementById('delError');

  if (!btnOpen || !modal || !dlg || !btnClose || !btnCancel || !btnOk || !input) return;

  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden','true');
  modal.hidden = true;

  let lastFocus = null;

  function openModal(){
    lastFocus = document.activeElement;
    modal.hidden = false;
    modal.setAttribute('aria-hidden','false');
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    input.value = '';
    btnOk.disabled = true;
    errorEl.hidden = true;
    setTimeout(()=> input.focus(), 0);
  }
  function closeModal(){
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  input.addEventListener('input', ()=>{ btnOk.disabled = (input.value.trim() !== 'DELETE'); });

  modal.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
    if (e.key !== 'Tab') return;
    const focusables = dlg.querySelectorAll('button,[href],input,textarea,select,[tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    const first = focusables[0], last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  async function doDelete(){
    if (btnOk.disabled) return;
    btnOk.disabled = true; btnOk.textContent = <?= json_encode(t('profile.modal.deleting')) ?>; errorEl.hidden = true; errorEl.textContent='';
    try {
      const res = await fetch(APP_BASE + '/api/account/delete.php', {
        method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ confirm: 'DELETE' })
      });
      const data = await res.json().catch(()=>({}));
      if (res.ok && data.ok) { closeModal(); location.href = APP_BASE + '/'; return; }
      throw new Error(data.error || ('HTTP '+res.status));
    } catch (err) {
      errorEl.textContent = <?= json_encode(t('common.error')) ?> + ': ' + (err && err.message ? err.message : err);
      errorEl.hidden = false;
      btnOk.disabled = false; btnOk.textContent = <?= json_encode(t('profile.modal.confirm')) ?>;
    }
  }

  btnOpen.addEventListener('click', openModal);
  btnClose.addEventListener('click', closeModal);
  btnCancel.addEventListener('click', closeModal);
  btnOk.addEventListener('click', doDelete);
  modal.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });
})();
</script>

<script>
/* ===== Cover-Editor ===== */
(() => {
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const API  = document.getElementById('profileForm')?.action || '/api/auth/update_profile.php';

  async function postJson(url, formData) {
    const res = await fetch(url, {
      method: 'POST',
      body: formData,
      credentials: 'include',
      headers: { 'Accept': 'application/json' }
    });
    const txt = await res.text();
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) throw new Error(<?= json_encode(t('common.server_not_json')) ?>);
    const json = JSON.parse(txt);
    if (!res.ok || json?.ok === false) throw new Error(json?.error || <?= json_encode(t('common.error')) ?>);
    return json;
  }

  // Avatar Upload
  document.getElementById('avatarInput')?.addEventListener('change', async (e) => {
    const file = e.target.files?.[0]; if (!file) return;
    const img = document.getElementById('avatarImg');
    const old = img.src, url = URL.createObjectURL(file); img.src = url;
    const fd = new FormData(); fd.append('avatar', file); fd.append('csrf', CSRF);
    try { const out = await postJson(API, fd); if (out.avatar) img.src = out.avatar; }
    catch (err) { alert(err.message); img.src = old; }
    finally { URL.revokeObjectURL(url); e.target.value=''; }
  });

  /* Editor-Steuerung (gekürzt, funktionsgleich) */
  const coverInput = document.getElementById('coverInput');
  const modal      = document.getElementById('coverModal');
  const editFrame  = document.getElementById('editFrame');
  const editImg    = document.getElementById('editImg');
  const zoomEl     = document.getElementById('zoom');
  const centerBtn  = document.getElementById('centerBtn');
  const saveBtn    = document.getElementById('saveCover');
  const liveCover  = document.getElementById('coverImg');
  const liveFrame  = document.getElementById('coverFrame');

  let natW=0,natH=0,posX=0,posY=0,scale=1,drag=false,sx=0,sy=0,spx=0,spy=0;

  function setTransform(){ editImg.style.transform = `translate(${posX}px, ${posY}px) scale(${scale})`; }
  function clamp(fw,fh,iw,ih){ const minX=Math.min(0,fw-iw), minY=Math.min(0,fh-ih); posX=Math.min(0,Math.max(minX,posX)); posY=Math.min(0,Math.max(minY,posY)); }
  function centerFit(){
    const fw = editFrame.clientWidth, fh = editFrame.clientHeight;
    const fit = Math.max(fw/natW, fh/natH);
    scale = Math.max(0.5, Math.min(3, fit));
    const iw=natW*scale, ih=natH*scale;
    posX=(fw-iw)/2; posY=(fh-ih)/2; clamp(fw,fh,iw,ih); setTransform(); zoomEl.value=String(scale);
  }
  function openEditor(url){ editImg.onload=()=>{ natW=editImg.naturalWidth; natH=editImg.naturalHeight; centerFit(); }; editImg.src=url; modal.style.display='block'; }
  function closeEditor(){ modal.style.display='none'; editImg.src=''; }

  editImg.addEventListener('mousedown', e=>{ drag=true; editImg.classList.add('dragging'); sx=e.clientX; sy=e.clientY; spx=posX; spy=posY; e.preventDefault(); });
  window.addEventListener('mousemove', e=>{ if(!drag) return; posX=spx+(e.clientX-sx); posY=spy+(e.clientY-sy); clamp(editFrame.clientWidth, editFrame.clientHeight, natW*scale, natH*scale); setTransform(); });
  window.addEventListener('mouseup',   ()=>{ drag=false; editImg.classList.remove('dragging'); });

  zoomEl.addEventListener('input', ()=>{
    const fw=editFrame.clientWidth, fh=editFrame.clientHeight;
    const cfx=(fw/2-posX)/scale, cfy=(fh/2-posY)/scale;
    const ns=parseFloat(zoomEl.value);
    posX = fw/2 - cfx*ns; posY = fh/2 - cfy*ns; scale=ns;
    clamp(fw,fh,natW*scale,natH*scale); setTransform();
  });

  document.getElementById('coverCancel')?.addEventListener('click', closeEditor);
  coverInput?.addEventListener('change', e=>{ const f=e.target.files?.[0]; if(!f) return; openEditor(URL.createObjectURL(f)); });

  function applyPanNormalized(imgEl, frameEl, relScale, u, v){
    if(!imgEl||!frameEl) return;
    const probe=new Image();
    probe.onload=()=>{
      const nw=probe.naturalWidth, nh=probe.naturalHeight, fw=frameEl.clientWidth, fh=frameEl.clientHeight;
      const fit=Math.max(fw/nw, fh/nh), s=(relScale??1)*fit;
      const iw=nw*s, ih=nh*s, ox=Math.max(0, iw-fw), oy=Math.max(0, ih-fh);
      const uu=(u==null)?0.5:Math.min(1,Math.max(0,u));
      const vv=(v==null)?0.5:Math.min(1,Math.max(0,v));
      const x = ox>0 ? -ox*uu : (fw-iw)/2;
      const y = oy>0 ? -oy*vv : (fh-ih)/2;
      imgEl.style.transformOrigin='0 0';
      imgEl.style.transform=`translate(${x}px, ${y}px) scale(${s})`;
    };
    probe.src = imgEl.currentSrc || imgEl.src;
  }
  window.applyPanNormalized = applyPanNormalized;

  saveBtn.addEventListener('click', async ()=>{
    const frameW=editFrame.clientWidth, frameH=editFrame.clientHeight;
    const fit=Math.max(frameW/natW, frameH/natH); const relScale=scale/fit;
    const iw=natW*scale, ih=natH*scale, ox=Math.max(0, iw-frameW), oy=Math.max(0, ih-frameH);
    let u = ox>0 ? (-posX)/ox : 0.5; let v = oy>0 ? (-posY)/oy : 0.5;
    u=Math.min(1,Math.max(0,u)); v=Math.min(1,Math.max(0,v));

    const fd = new FormData();
    const file = document.getElementById('coverInput').files?.[0];
    if (file) fd.append('cover', file);
    fd.append('cover_x', String(u));
    fd.append('cover_y', String(v));
    fd.append('cover_scale', String(relScale));
    fd.append('csrf', CSRF);
    try{
      const out = await postJson('/api/auth/update_profile.php', fd);
      if (out.cover) document.getElementById('coverImg').src = out.cover;
      const rx=('cover_x' in out)?out.cover_x:u, ry=('cover_y' in out)?out.cover_y:v, rs=('cover_scale' in out)?out.cover_scale:relScale;
      applyPanNormalized(document.getElementById('coverImg'), document.getElementById('coverFrame'), rs, rx, ry);
      closeEditor();
    }catch{ alert(<?= json_encode(t('profile.cover_editor.save_error')) ?>); }
  });

  // Initiales Anwenden
  window.addEventListener('load', ()=>{
    applyPanNormalized(document.getElementById('coverImg'), document.getElementById('coverFrame'),
      <?= json_encode($coverScale) ?>, <?= json_encode($coverX) ?>, <?= json_encode($coverY) ?>);
  });
})();
</script>

<script>
/* ===== SHOP – Kategorien, Render & Aktionen ===== */
(function(){
  const APP_BASE = window.APP_BASE || '';
  const openBtn  = document.getElementById('openShopBtn');
  const modal    = document.getElementById('shopModal');
  const closeBtn = modal?.querySelector('.shop-close');
  const pointsEl = document.querySelector('[data-points-badge]');
  const refInput = document.getElementById('refLinkInput');
  const copyBtn  = document.getElementById('copyRefBtn');

  const listEl   = document.getElementById('shopItems');
  const navEl    = document.getElementById('shopCats');

  const ORDER = ['avatar','cover','frame','badge','vip','status','color','module','other'];
  const LABEL = {
    avatar:'Avatare', cover:'Cover', frame:'Rahmen', badge:'Badges',
    vip:'VIP', status:'Status', color:'Name-Farbe', module:'Module', other:'Sonstiges'
  };

  function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" }[m]));}
  function imgSrc(it){
    if (it.image_url) return it.image_url;
    if (it.image)     return (APP_BASE||'') + (String(it.image).startsWith('/')? it.image : '/' + it.image);
    return (APP_BASE||'') + '/assets/images/placeholder-item.png';
  }

  function renderItem(it){
    const price  = parseInt(it.price||0,10);
    const owned  = !!(it.owned || it.is_owned);
    const active = !!(it.active || it.is_active);
    return `
      <div class="shop-card">
        <div class="shop-thumb"><img src="${imgSrc(it)}" alt=""></div>
        <div class="shop-title">${esc(it.title||'Ohne Titel')}</div>
        ${it.description? `<div class="shop-desc">${esc(it.description)}</div>`:''}
        <div class="shop-price">${price} Punkte</div>
        ${owned
          ? (active ? `<span class="shop-pill">Aktiv</span>` : `<button class="shop-buy-btn js-activate" data-id="${it.id}">Aktivieren</button>`)
          : `<button class="shop-buy-btn js-buy" data-id="${it.id}" data-price="${price}">Kaufen</button>`
        }
        ${owned && !active ? `<span class="shop-pill">Gekauft</span>`:''}
      </div>`;
  }

  function groupByType(items){
    const groups = Object.fromEntries(ORDER.map(k=>[k,[]]));
    for (const it of (items||[])) {
      const t = String(it.type||'other').toLowerCase();
      (groups[ORDER.includes(t)?t:'other']).push(it);
    }
    return groups;
  }

  function renderNav(groups){
    const links = ORDER.filter(k=>groups[k].length).map(k=>`<a href="#cat-${k}">${LABEL[k]}</a>`).join('');
    navEl.innerHTML = links || '<span class="opacity-70">Keine Artikel</span>';
  }
  function renderSections(groups){
    const html = ORDER.filter(k=>groups[k].length).map(k=>`
      <section class="shop-section" id="cat-${k}">
        <h5>${LABEL[k]}</h5>
        <div class="shop-grid">
          ${groups[k].map(renderItem).join('')}
        </div>
      </section>
    `).join('');
    listEl.innerHTML = html || '<div class="p-6 opacity-70">Keine Shop-Artikel.</div>';
  }

  async function fetchItems(){
    listEl.innerHTML = '<div class="p-6 opacity-70">Lade Shop…</div>';
    try{
      const r = await fetch((APP_BASE||'') + '/api/shop/list.php', {credentials:'include'});
      const j = await r.json();
      if (!j || j.ok === false) throw new Error(j?.error || 'server_error');
      const items  = j.items || j.data || [];
      const groups = groupByType(items);
      renderNav(groups);
      renderSections(groups);
    }catch(e){
      navEl.innerHTML = '';
      listEl.innerHTML = '<div class="p-6 text-red-400">Fehler beim Laden: '+esc(e.message||e)+'</div>';
    }
  }

  async function refreshPoints(){
    try{
      const r = await fetch((APP_BASE||'') + '/api/points/balance.php', {credentials:'include'});
      const j = await r.json();
      if (j && j.ok && pointsEl) pointsEl.textContent = j.balance ?? '0';
    }catch(_){}
  }

  async function buy(itemId, btn){
    btn.disabled = true;
    try{
      const form = new URLSearchParams({ item_id:String(itemId) });
      const r = await fetch((APP_BASE||'') + '/api/shop/buy.php', { method:'POST', body: form, credentials:'include' });
      const j = await r.json();
      if (!r.ok || j.ok===false) throw new Error(j?.error||'server_error');
      await refreshPoints();
      await fetchItems();
    }catch(e){ alert('Kauf fehlgeschlagen: ' + (e?.message || e)); }
    finally{ btn.disabled = false; }
  }

  async function activate(itemId, btn){
    btn.disabled = true;
    try{
      const form = new URLSearchParams({ item_id:String(itemId) });
      const r = await fetch((APP_BASE||'') + '/api/shop/activate.php', { method:'POST', body: form, credentials:'include' });
      const j = await r.json();
      if (!r.ok || j.ok===false) throw new Error(j?.error||'server_error');
      await fetchItems();
    }catch(e){ alert('Aktivieren fehlgeschlagen: ' + (e?.message || e)); }
    finally{ btn.disabled = false; }
  }

  // Delegation auf Items-Container
  listEl?.addEventListener('click', (e)=>{
    const buyBtn = e.target.closest('.js-buy');
    const actBtn = e.target.closest('.js-activate');
    if (buyBtn) buy(Number(buyBtn.dataset.id||0), buyBtn);
    if (actBtn) activate(Number(actBtn.dataset.id||0), actBtn);
  });

  function openModal(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; fetchItems(); }
  function closeModal(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }

  openBtn?.addEventListener('click', openModal);
  closeBtn?.addEventListener('click', closeModal);
  window.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && modal.classList.contains('is-open')) closeModal(); });

  // Punkte beim Laden aktualisieren (falls Session bereits verändert)
  refreshPoints();

  // Referral kopieren
  copyBtn?.addEventListener('click', async ()=>{
    try{ await navigator.clipboard.writeText(refInput.value); copyBtn.textContent='Kopiert!'; }
    catch(_){ refInput.select(); document.execCommand('copy'); copyBtn.textContent='Kopiert!'; }
    setTimeout(()=> copyBtn.textContent='Kopieren', 1200);
  });
})();
</script>
<script>
(() => {
  const form = document.getElementById('profileForm');
  if (!form) return;

  const saveModal = document.getElementById('saveModal');

  function showSaveModal(msg = 'Profil gespeichert') {
    if (!saveModal) return;
    saveModal.querySelector('.card').textContent = msg;
    saveModal.classList.add('is-open');
    setTimeout(() => {
      saveModal.classList.remove('is-open');
    }, 3000);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(form);
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) btn.disabled = true;

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        body: fd,
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const out = await res.json();

      if (!res.ok || out.ok === false) throw new Error(out.error || 'Fehler beim Speichern');

      showSaveModal('✅ Profil gespeichert');
    } catch (err) {
      showSaveModal('❌ Fehler: ' + (err?.message || err));
    } finally {
      if (btn) btn.disabled = false;
    }
  });
})();


</script>

<?php
$content = ob_get_clean();
render_theme_page($content, $title);

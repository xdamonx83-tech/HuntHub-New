<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth/guards.php';

$me = function_exists('optional_auth') ? optional_auth() : current_user();

$displayName = $me['display_name'] ?? 'Gast';
$avatar      = !empty($me['avatar_path']) ? $me['avatar_path'] : '/assets/images/avatars/placeholder.png';

?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<script>window.APP_BASE = "<?= htmlspecialchars($APP_BASE ?? '', ENT_QUOTES) ?>";</script>

<meta name="csrf" content="<?= htmlspecialchars($csrf) ?>">
<style>
/* kleine Hilfsstyles für Felder im Dropdown */
.dropdown .form-row { display:flex; gap:.5rem; }
.dropdown .field {
  width:100%; background:#141414; border:1px solid rgba(255,255,255,.12);
  color:#e5e7eb; border-radius:10px; padding:.55rem .7rem;
}
.dropdown .msg { font-size:.9rem; padding:.25rem .5rem; }
.dropdown .msg.ok { color:#86efac; }
.dropdown .msg.err{ color:#fca5a5; }
.dropdown .hr { height:1px; background:rgba(255,255,255,.08); margin:.5rem 0; }
.hh-dd{position:absolute;right:0;top:48px;width:min(420px,92vw);
  background:#1a1c1f;border:1px solid #2c2f33;border-radius:14px;
  box-shadow:0 20px 60px rgba(0,0,0,.55);overflow:hidden;z-index:9999}
.hh-dd.show{animation:hh-pop .14s cubic-bezier(.2,.8,.2,1)}
@keyframes hh-pop{from{opacity:0;transform:translateY(-6px) scale(.98)}
                  to{opacity:1;transform:translateY(0) scale(1)}}
.hh-dd__header{position:sticky;top:0;display:flex;align-items:center;
  justify-content:space-between;padding:12px 16px;background:
  linear-gradient(180deg,#202327 0%,#1a1c1f 100%);border-bottom:1px solid #2c2f33}
.hh-dd__title{font-weight:800;color:#fff;font-size:18px}
.hh-dd__count{background:#2c2f33;color:#cbd0d6;border-radius:999px;
  padding:2px 8px;font-size:12px}
.hh-dd__list{max-height:60vh;overflow:auto}
.hh-dd__item{display:flex;gap:12px;padding:12px 16px;align-items:center;
  transition:background .15s}
.hh-dd__item:hover{background:rgba(255,255,255,.03)}
.hh-dd__avatar{width:42px;height:42px;border-radius:10px;object-fit:cover;border:1px solid #394049}
.hh-dd__body{min-width:0}
.hh-dd__name{color:#fff;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hh-dd__sub{color:#9aa0a6;font-size:13px}
.hh-dd__actions{margin-left:auto;display:flex;gap:8px}
.hh-accept{background:#16a34a;color:#fff;border-radius:10px;padding:8px 10px}
.hh-accept:hover{filter:brightness(1.05)}
.hh-decline{background:#2a2d31;color:#cdd1d5;border-radius:10px;padding:8px 10px}
.hh-decline:hover{filter:brightness(1.05)}
.hh-dd__empty{display:flex;flex-direction:column;align-items:center;gap:6px;
  padding:28px;color:#9aa0a6;font-size:14px}
.hh-dd__empty i{font-size:22px;color:#7a828a}
.hh-spinner{width:18px;height:18px;border:2px solid #4b5563;border-top-color:#fff;border-radius:50%;animation:hh-spin .6s linear infinite}
@keyframes hh-spin{to{transform:rotate(360deg)}}


/* === Notifications (Header) === */
.noti-badge{
  position:absolute; top:-2px; right:-2px; min-width:18px; height:18px;
  padding:0 4px; border-radius:9999px; background:#d9480f; color:#fff;
  font-size:12px; line-height:18px; text-align:center; box-shadow:0 0 0 2px rgba(0,0,0,.4)
}
.noti-menu{
  position:absolute; right:0; top:calc(100% + 10px); z-index:10000;
  width:min(420px, 92vw); background:#0b0b0b; border:1px solid rgba(255,255,255,.12);
  border-radius:14px; box-shadow:0 12px 30px rgba(0,0,0,.4); overflow:hidden
}
.noti-menu.hidden{ display:none }
.noti-hd, .noti-ft{
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 12px; background:#0f0f0f; border-bottom:1px solid rgba(255,255,255,.08)
}
.noti-ft{ border-top:1px solid rgba(255,255,255,.08); border-bottom:none }
.noti-list{ max-height:60vh; overflow:auto; background:#0b0b0b }
.noti-empty{ padding:14px; color:#9aa5b1; }
.noti-item{
  display:flex; gap:12px; align-items:flex-start; padding:12px;
  border-bottom:1px solid rgba(255,255,255,.06)
}
.noti-item:last-child{ border-bottom:none }
.noti-ava{
  width:40px; height:40px; border-radius:9999px; object-fit:cover;
  border:1px solid rgba(255,255,255,.1)
}
.noti-body{ flex:1; min-width:0 }
.noti-title{
  color:#e5e7eb; font-size:14px; line-height:1.35;
}
.noti-text{ color:#9aa5b1; font-size:12px; margin-top:2px }
.noti-time{ color:#7c8793; font-size:12px; margin-top:2px }
.noti-actions{ display:flex; gap:8px; margin-left:6px }
.noti-link{
  border:0; background:none; color:#7aa2ff; cursor:pointer; font-size:13px;
}
.noti-item.unread .noti-title{ font-weight:600 }
.noti-dot{
  width:8px; height:8px; border-radius:50%; background:#2ea043; margin-top:6px;
}
/* ==== Contacts Modal (schön) ==== */
#contactsModal .hhc-backdrop{
  position:absolute; inset:0; background:rgba(0,0,0,.6);
}
#contactsModal .hhc-panel{
  position:absolute; right:24px; top:24px; bottom:24px;
  width:min(420px,94vw);
  background:#0f1113; border:1px solid rgba(255,255,255,.10);
  border-radius:18px; box-shadow:0 20px 60px rgba(0,0,0,.45);
  display:flex;                  
  flex-direction:column;         
  overflow:hidden;
  transform:translateY(6px) scale(.98); opacity:0;
  transition:.18s cubic-bezier(.2,.8,.2,1);
}
#contactsModal:not(.hidden) .hhc-panel{ transform:none; opacity:1; }

#contactsModal .hhc-hd{
  position:sticky; top:0; z-index:1;
  display:flex; align-items:center; gap:10px; padding:12px 14px;
  background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.00));
  border-bottom:1px solid rgba(255,255,255,.08);
}
#contactsModal .hhc-title{ color:#fff; font-weight:700; font-size:16px }
#contactsModal .hhc-close{
  margin-left:auto; width:30px; height:30px; border-radius:8px;
  display:grid; place-items:center; color:#9aa5b1; background:transparent;
  border:1px solid transparent; cursor:pointer;
}
#contactsModal .hhc-close:hover{ background:rgba(255,255,255,.06) }

#contactsModal .hhc-search{
  display:flex; align-items:center; gap:8px; margin:10px 12px 12px;
  padding:8px 10px; border-radius:12px;
  background:#121417; border:1px solid rgba(255,255,255,.08);
}
#contactsModal .hhc-search input{
  width:100%; background:transparent; border:0; outline:0; color:#e5e7eb;
  font-size:14px;
}

#contactsModal .hhc-list{
  flex:1;                     
  padding:8px; overflow:auto; scrollbar-width:thin;
}

#contactsModal .hhc-row{
  width:100%; display:flex; align-items:center; gap:12px;
  padding:10px 12px; border-radius:14px; border:1px solid transparent;
  background:transparent; cursor:pointer; text-align:left;
  transition:background .15s, border-color .15s;
}
#contactsModal .hhc-row:hover{
  background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.06);
}
#contactsModal .hhc-ava{
  width:44px; height:44px; flex:0 0 44px; border-radius:9999px;
  object-fit:cover; border:1px solid rgba(255,255,255,.10);
}
#contactsModal .hhc-main{ min-width:0; flex:1 }
#contactsModal .hhc-top{ display:flex; align-items:center; gap:6px }
#contactsModal .hhc-name{
  color:#fff; font-weight:600; line-height:1.1;
  max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
#contactsModal .hhc-badge{
  margin-left:auto; background:#d9480f; color:#fff; border-radius:9999px;
  min-width:20px; height:20px; padding:0 6px; line-height:20px; font-size:12px;
  text-align:center;
}
#contactsModal .hhc-sub{
  color:#9aa5b1; font-size:12px; margin-top:2px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
/* Basis: runder Avatar-Container */
.avatar {
  position: relative;
  display: inline-block;
  border-radius: 50%;
             /* schneidet Überstand ab */
}

/* deine vorhandene Klasse nutzen */
.avatar.size-60p {
  width: 60px;
  height: 60px;
  min-width: 60px;               /* verhindert Schrumpfen z.B. in Flexbox/Swiper */
  min-height: 60px;
}

/* Bild füllt den Container vollständig und wird zentriert beschnitten */
.avatar > img {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover;
  object-position: center;
  display: block;                /* entfernt weiße Lücken durch Inline-IMG */
}
/* Container darf NICHT clippen */


/* Rund zuschneiden nur das Bild */
.avatar > img{
  width:100%; height:100%;
  display:block;
  object-fit:cover; object-position:center;
  border-radius:50%;
}

/* Falls das Badge außerhalb liegen soll, braucht die Slide auch sichtbares Overflow */
.swiper-slide{ overflow: visible; }  /* nur wenn du -top-1/-right-1 nutzt */

/* Optional: eigene Klasse fürs Badge (wenn du Tailwind nicht nutzen willst) */
.avatar .badge{
  position:absolute; right:2px; top:2px;
  z-index:30; min-width:18px; height:18px; line-height:18px;
  padding:0 6px; font-size:12px; border-radius:9999px;
  background:var(--primary, #22c55e); color:#fff;
  pointer-events:none;
}
/* Avatar bleibt rund über das IMG */
.avatar > img{
  width:100%; height:100%; display:block;
  object-fit:cover; object-position:center;
  border-radius:50%;
}

/* Glow bei Ungelesen */
.avatar.has-unread > img{
  /* dünner Ring + weicher Glow */
  box-shadow:
  0 0 0 1px rgb(242 150 32), 0 0 10px 1px rgb(242 150 32 / 98%)

}

/* Optional: sanftes Pulsieren nur für Nutzer ohne Motion-Reduktion */
@media (prefers-reduced-motion: no-preference){
  .avatar.has-unread.pulse > img{
    animation: avatarGlow 2.2s ease-in-out infinite;
  }
  @keyframes avatarGlow{
    0%,100%{ box-shadow: 0 0 0 1px rgba(34,197,94,.95), 0 0 10px 1px rgba(34,197,94,.35); }
    50%    { box-shadow: 0 0 0 1px rgba(34,197,94,1),   0 0 10px 1px rgba(34,197,94,.55); }
  }
}

/* Falls dein Slider Badge/Glow abschneidet: */
.swiper-slide{ overflow: visible; } /* nur setzen, wenn nötig */
/* Badge zentriert im Avatar */
.avatar .badge-center{
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  z-index:20; pointer-events:none;
  color:#fff; font-weight:700; font-size:18px;
  text-shadow: 0 1px 2px rgba(0,0,0,.85), 0 0 6px rgba(0,0,0,.6);
}
/* optionaler runder, halbtransparenter Hintergrund hinter der Zahl */
.avatar .badge-center::before{
  content:"";
  position:absolute; width:26px; height:26px; border-radius:50%;
  background: rgba(0,0,0,.45);
}
.chat-presence { display:inline-flex; align-items:center; gap:.4rem; }
.chat-presence .dot { width:.6rem; height:.6rem; border-radius:9999px; display:inline-block; box-shadow:0 0 0 2px rgba(0,0,0,.35) inset; }
.chat-presence .dot.is-online { background:#22c55e; } /* grün */
.chat-presence .dot.is-offline { background:#9ca3af; } /* grau */
.chat-presence .last { font-size:.8rem; opacity:.8; }
</style>
     <div class="hidden lg:flex items-center gap-1 shrink-0">
   
                <div class="relative hidden lg:block">
<div id="frDropdown" class="relative">
  <button id="frBell" type="button"
          class="relative btn-c btn-c-xxl btn-neutral-2 text-w-neutral-4"
          aria-haspopup="true" aria-expanded="false">
    <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4c.96 0 1.84 .338 2.53 .901" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>
    <span id="frBadge"
          class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-[5px] rounded-full text-[11px] leading-[18px] text-white bg-primary text-center hidden"></span>
  </button>

  <div id="frMenu" class="hh-dd hidden" role="menu" aria-label="Freundschaftsanfragen">
    <div class="hh-dd__header">
      <span class="hh-dd__title" data-i18n="friendsadd"><?php echo $L['friendsadd']; ?></span>
      <span id="frCount" class="hh-dd__count">0</span>
    </div>
    <div id="frList" class="hh-dd__list"></div>
    <div id="frEmpty" class="hh-dd__empty hidden">
      <i class="ti ti-bell"></i>
      <div data-i18n="friendsnoopen"><?php echo $L['friendsnoopen']; ?></div>
    </div>
  </div>
</div>

<script>
(function(){
  // nur für eingeloggte Nutzer; wenn $me fehlt, Menü nicht öffnen
  const meId = <?= (int)($me['id'] ?? 0) ?>;
  if (!meId) return;

  const BASE  = "<?= $APP_BASE ?>";
  const CSRF  = "<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>";

  const bell  = document.getElementById('frBell');
  const menu  = document.getElementById('frMenu');
  const list  = document.getElementById('frList');
  const empty = document.getElementById('frEmpty');
  const badge = document.getElementById('frBadge');
  const count = document.getElementById('frCount');

  function setBadge(n){
    if (count) count.textContent = String(n);
    if (n > 0) {
      badge.textContent = (n > 99) ? '99+' : String(n);
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }
  }

  function openMenu(open){
    if (open === undefined) open = menu.classList.contains('hidden');
    if (open) {
      menu.classList.remove('hidden');
      menu.classList.add('show');
      bell.setAttribute('aria-expanded','true');
      loadRequests();
    } else {
      menu.classList.add('hidden');
      menu.classList.remove('show');
      bell.setAttribute('aria-expanded','false');
    }
  }

  document.addEventListener('click', (e)=>{
    if (!bell || !menu) return;
    if (bell.contains(e.target)) { e.preventDefault(); openMenu(); }
    else if (!menu.contains(e.target)) { openMenu(false); }
  });

  async function api(path, opts){
    const r = await fetch(`${BASE}/api/friends/${path}`, {
      headers:{ 'Accept':'application/json' },
      credentials:'same-origin',
      ...opts
    });
    const ct = r.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await r.text();
      throw new Error('Kein JSON (HTTP '+r.status+'): '+text.slice(0,120));
    }
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || 'Fehler');
    return j;
  }

  function esc(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  function renderItem(row){
    const uid   = Number(row.id) || 0;
    const reqId = Number(row.request_id) || 0;
    const name  = esc(row.display_name || 'Unbekannt');
    const slug  = encodeURIComponent(row.slug || '');
    const avatar = row.avatar_path ? esc(row.avatar_path) : '<?= $APP_BASE ?>/assets/images/avatars/placeholder.png';
    const profileUrl = '<?= $APP_BASE ?>/user.php?id=' + uid + '&slug=' + slug;

    return `
      <div class="hh-dd__item" data-item="${reqId}">
        <img src="${avatar}" class="hh-dd__avatar" alt="">
        <div class="hh-dd__body">
          <a href="${profileUrl}" class="hh-dd__name">${name}</a>
          <div class="hh-dd__sub">möchte befreundet sein</div>
        </div>
        <div class="hh-dd__actions">
          <button class="hh-accept"  data-act="accept"  data-req="${reqId}">Annehmen</button>
          <button class="hh-decline" data-act="decline" data-req="${reqId}">Ablehnen</button>
        </div>
      </div>`;
  }


  async function loadRequests(){
    list.innerHTML = `<div class="hh-dd__item"><div class="hh-spinner"></div><div class="hh-dd__sub" style="margin-left:10px">Lade…</div></div>`;
    empty.classList.add('hidden');
    try {
      const data = await api('pending.php');
      const items = data.incoming || [];
      setBadge(items.length);

      if (!items.length) {
        list.innerHTML = '';
        empty.classList.remove('hidden');
        return;
      }
      list.innerHTML = items.map(renderItem).join('');
    } catch (e) {
      list.innerHTML = `<div class="hh-dd__item"><div class="hh-dd__sub" style="color:#ef4444">Fehler: ${esc(e.message)}</div></div>`;
      setBadge(0);
    }
  }

  list?.addEventListener('click', async (e)=>{
    const btn = e.target.closest('button[data-req]');
    if (!btn) return;
    const reqId  = btn.dataset.req;
    const action = btn.dataset.act;
    const row    = list.querySelector(`[data-item="${reqId}"]`);
    btn.disabled = true;

    try {
      const fd = new FormData();
      fd.append('csrf', "<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>");
      fd.append('request_id', String(reqId));
      fd.append('action', action);
      await api('respond_request.php', { method: 'POST', body: fd });

      row?.remove();
      const remaining = list.querySelectorAll('button[data-act="accept"]').length;
      setBadge(remaining);
      if (remaining === 0) {
        empty.classList.remove('hidden');
      }
    } catch (err) {
      row?.insertAdjacentHTML('beforeend',
        `<div class="hh-dd__sub" style="color:#ef4444;margin-left:8px">Fehlgeschlagen: ${esc(err.message)}</div>`);
      btn.disabled = false;
    }
  });

  // Initial laden
  loadRequests();
})();
</script>



                </div>
              </div>
			  <!-- NOTIFICATIONS (Header) -->
<div id="noti-root" class="relative btn-c btn-c-xxl btn-neutral-2 text-w-neutral-4" data-base="<?= rtrim($APP_BASE,'/') ?>">
  <button id="noti-btn" class="relative text-white icon-20" aria-haspopup="menu" aria-expanded="false" title="Benachrichtigungen">
    <i class="ti ti-bell"></i>
    <span id="noti-badge" class="noti-badge hidden">0</span>
  </button>

  <div id="noti-menu" class="noti-menu hidden" role="menu" aria-label="Benachrichtigungen">
    <div class="noti-hd">
      <span style="
    color: #fff;
    font-size: 20px;
" data-i18n="notifications"><?php echo $L['notifications']; ?></span>
      <button type="button" id="noti-markall" class="noti-link" data-i18n="readall"><?php echo $L['readall']; ?></button>
    </div>

    <div id="noti-list" class="noti-list">
      <!-- wird per JS gefüllt -->
      <div class="noti-empty" data-i18n="loading"><?php echo $L['loading']; ?></div>
    </div>

    <div class="noti-ft">
      <button type="button" id="noti-refresh" class="noti-link" data-i18n="refresh"><i class="ti ti-refresh"></i> <?php echo $L['refresh']; ?></button>
    </div>
  </div>
</div>

           <div x-data="dropdown" class="dropdown relative shrink-0 lg:block hidden">
                <button @click="toggle()" class="dropdown-toggle gap-24p" style="padding:.55rem .8rem;border-radius:10px;border:1px solid rgba(255,255,255,.2);background:#1b1b1b;color:#eee;cursor:pointer">
                  <span class="flex items-center gap-3">
                   <?php if ($me): ?> <img class="size-48p rounded-full shrink-0" src="<?= htmlspecialchars($avatar) ?>" alt="profile" />
				   <?php else: ?>
				   
				    <?php endif; ?>
                    <span class="">
                      <span class="text-m-medium text-w-neutral-1 mb-1">
                        <?= htmlspecialchars($displayName) ?> 
                      </span>
                      <span class="text-sm text-w-neutral-4 block" data-i18n="loggedin">
                       <?= $me ? t('loggedin') : t('guest') ?>
                      </span>
                    </span>
                  </span>
                  <span :class="isOpen ? '-rotate-180' : ''"
                    class="btn-c btn-c-lg text-w-neutral-4 icon-32 transition-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                      stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                      class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-down">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M6 9l6 6l6 -6" />
                    </svg>
                  </span>
                </button>

                <div x-show="isOpen" x-transition @click.away="close()" class="dropdown-content">
  



 
    <?php if ($me): ?>
	
      <a href="/user.php?id=<?= (int)$me['id'] ?>" class="dropdown-item" data-i18n="profile"><?php echo $L['profile']; ?></a>
	  <a href="/wallet.php" class="dropdown-item" >Punkteshop</a>
      <a href="/profile.php" class="dropdown-item" data-i18n="settings"><?php echo $L['settings']; ?></a>
	  <a href="/bugs/" class="dropdown-item" data-i18n="help"><?php echo $L['help']; ?></a>
      <button type="button" id="logout" @click="close()" class="dropdown-item" data-i18n="logout"><?php echo $L['logout']; ?></button>
      <?php if (!empty($me['is_twitch_streamer'])): ?>
1111
<?php endif; ?>
	  <?php if (!empty($me) && function_exists('is_admin') && is_admin($me)): ?>
  <a href="<?= $APP_BASE ?>/admin.php" class="dropdown-item">Admin</a>
<?php endif; ?>
    <?php else: ?>
	<style>
/* ===== Hunthub Register Popup (namespaced hh-) ===== */
:root {
  --hh-bg: #0d0d0f;
  --hh-surface: #141417;
  --hh-surface-2: #1b1b20;
  --hh-text: #e7e7ea;
  --hh-text-dim: #b6b6c0;
  --hh-red: #d94141;
  --hh-red-strong: #c62f2f;
  --hh-border: #2a2a31;
  --hh-ring: 0 0 0 3px rgba(217,65,65,.25);
}

#hh-register.hh-hidden { display: none; }
#hh-register { position: fixed; inset: 0; z-index: 1000; }

.hh-backdrop {
  position: absolute; inset: 0;
  background: rgba(0,0,0,.7); backdrop-filter: blur(3px);
  opacity: 0; transition: opacity .28s ease;
}

.hh-wrap {
  position: absolute; inset: 0;
  display: grid; place-items: center; padding: 1.25rem;
}

.hh-card {
  width: min(680px, 100%);
  background: linear-gradient(180deg, var(--hh-surface) 0%, var(--hh-surface-2) 100%);
  border: 1px solid var(--hh-border);
  border-radius: 18px;
  box-shadow: 0 30px 80px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.03) inset;
  color: var(--hh-text);
  transform: translateY(6px) scale(.98);
  opacity: 0;
  transition: transform .28s ease, opacity .28s ease;
}

.hh-card-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--hh-border);
}
.hh-title { font-size: 1.1rem; font-weight: 800; letter-spacing: .4px; }

.hh-close {
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border-radius: 50%;
  color: var(--hh-text-dim);
  background: transparent; border: 1px solid transparent;
  transition: .2s ease;
}
.hh-close:hover { background: #202026; color: var(--hh-text); border-color: var(--hh-border); }

.hh-card-body { padding: 18px 20px 20px; }

.hh-alert {
  border: 1px solid var(--hh-border); border-radius: 12px;
  padding: 10px 12px; font-size: .92rem; margin-bottom: 14px;
}
.hh-alert.err { background: rgba(198,47,47,.1); border-color: #5a1f1f; color: #ffc9c9; }
.hh-alert.ok  { background: rgba(47,198,120,.1); border-color: #205a35; color: #c8ffdf; }

.hh-form { display: grid; gap: 14px; }

.hh-field { display: grid; gap: 6px; }
.hh-label { font-size: .9rem; color: var(--hh-text); font-weight: 600; }
.hh-help { font-size: .8rem; color: var(--hh-text-dim); }

.hh-input {
  appearance: none; width: 100%;
  background: #111114; color: var(--hh-text);
  border: 1px solid var(--hh-border); border-radius: 12px;
  padding: 12px 12px; font-size: .95rem;
  transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.hh-input::placeholder { color: #7c7c86; }
.hh-input:focus { outline: none; border-color: var(--hh-red); box-shadow: var(--hh-ring); background: #0f0f12; }

.hh-two { display: grid; gap: 14px; grid-template-columns: 1fr; }
@media (min-width: 640px) { .hh-two { grid-template-columns: 1fr 1fr; } }

.hh-check { display: grid; grid-template-columns: 18px 1fr; gap: 10px; align-items: start; font-size: .92rem; color: var(--hh-text-dim); }
.hh-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--hh-red); }

.hh-btn {
  width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
  border: 1px solid transparent; border-radius: 12px; padding: 12px 14px;
  background: var(--hh-red); color: #fff; font-weight: 700; letter-spacing: .2px;
  transition: background .15s ease, transform .05s ease, box-shadow .15s ease;
}
.hh-btn:hover { background: var(--hh-red-strong); }
.hh-btn:active { transform: translateY(1px); }
.hh-btn:disabled { opacity: .6; cursor: not-allowed; }

.hh-spinner {
  width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid rgba(255,255,255,.8); border-top-color: transparent;
  animation: hh-spin .7s linear infinite; display: none;
}
@keyframes hh-spin { to { transform: rotate(360deg); } }

.hh-footnote { font-size: .9rem; color: var(--hh-text-dim); text-align: center; margin-top: 6px; }
.hh-footnote a { color: #f2b6b6; text-decoration: underline; }

/* Visible state toggles */
#hh-register.hh-open .hh-backdrop { opacity: 1; }
#hh-register.hh-open .hh-card { transform: translateY(0) scale(1); opacity: 1; }

/* utility */
.hh-hidden { display: none !important; }

/* optional: Scroll lock helper (JS setzt class) */
.hh-lock { overflow: hidden; }
</style>

      <div class="text-w-neutral-3 px-2 pb-1" data-i18n="signin"><?php echo $L['signin']; ?></div>
      <form id="loginForm" class="grid gap-2 px-2 pb-2">
        <input class="box-input-3" type="email" name="email" placeholder="E-Mail" required>
        <input class="box-input-3" type="password" name="password" placeholder="Passwort" required>
        <div class="form-row">
          <button class="btn btn-md btn-primary rounded-12 mt-60p" type="submit" data-i18n="login"><?php echo $L['login']; ?></button>
          <a class="btn btn-md btn-primary rounded-12 mt-60p" href="/register.php" data-i18n="register"><?php echo $L['register']; ?></a>
        </div>
        <div id="loginMsg" class="msg err"></div>
      </form>
	  <div class="social-logins">
  <a class="btn btn-google"   href="/api/auth/oauth_start.php?provider=google" data-i18n="lgoogle"><?php echo $L['lgoogle']; ?></a>

  <a class="btn btn-facebook" href="/api/auth/oauth_start.php?provider=facebook" data-i18n="lfacebook"><?php echo $L['lfacebook']; ?></a>
  <a class="btn btn-twitch"   href="/api/auth/oauth_start.php?provider=twitch" data-i18n="ltwitch"><?php echo $L['ltwitch']; ?></a>
</div>
	  <!-- Hunthub Register Modal -->
<div id="hh-register" class="hh-hidden" aria-hidden="true">
  <div class="hh-backdrop" data-hh-backdrop></div>

  <div class="hh-wrap" role="dialog" aria-modal="true" aria-labelledby="hh-reg-title">
    <div class="hh-card">
      <div class="hh-card-head">
        <h2 id="hh-reg-title" class="hh-title"><?php echo $L['signup']; ?></h2>
        <button type="button" class="hh-close" data-hh-close aria-label="Schließen">
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
      </div>

      <div class="hh-card-body">
        <div id="hh-reg-alert" class="hh-alert hh-hidden" role="alert" aria-live="polite"></div>

        <form id="hh-reg-form" class="hh-form" novalidate>
          <div class="hh-field">
            <label for="hh-username" class="hh-label" data-i18n="username"><?php echo $L['username']; ?></label>
            <input id="hh-username" name="username" type="text" class="hh-input"
                   autocomplete="username" required minlength="3" maxlength="32" placeholder="z. B. HuntFan83">
            <div class="hh-help" data-i18n="signupb"><?php echo $L['signupb']; ?></div>
          </div>

          <div class="hh-field">
            <label for="hh-email" class="hh-label" data-i18n="email"><?php echo $L['email']; ?></label>
            <input id="hh-email" name="email" type="email" class="hh-input"
                   autocomplete="email" required placeholder="du@example.com">
          </div>

          <div class="hh-two">
            <div class="hh-field">
              <label for="hh-pass" class="hh-label" data-i18n="password"><?php echo $L['password']; ?></label>
              <input id="hh-pass" name="password" type="password" class="hh-input"
                     autocomplete="new-password" required minlength="8" placeholder="Mind. 8 Zeichen">
            </div>
            <div class="hh-field">
              <label for="hh-pass2" class="hh-label" data-i18n="password2"><?php echo $L['password2']; ?></label>
              <input id="hh-pass2" name="password_confirm" type="password" class="hh-input"
                     autocomplete="new-password" required minlength="8">
            </div>
          </div>

          <label class="hh-check">
            <input id="hh-accept" name="accept" type="checkbox" required>
            <span data-i18n="agb"><?php echo $L['agb']; ?></span>
          </label>

          <button id="hh-submit" type="submit" class="hh-btn">
            <span class="hh-spinner" aria-hidden="true" data-i18n="signup2"></span>
            <?php echo $L['signup2']; ?>
          </button>

          <div class="hh-footnote" data-i18n="already">
           <?php echo $L['already']; ?> 
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const modal   = document.getElementById('hh-register');
  if (!modal) return;

  const wrap    = modal.querySelector('.hh-wrap');
  const backdrop= modal.querySelector('[data-hh-backdrop]');
  const card    = modal.querySelector('.hh-card');
  const closeBtn= modal.querySelector('[data-hh-close]');
  const openers = document.querySelectorAll('[data-open-register]');
  const form    = document.getElementById('hh-reg-form');
  const btn     = document.getElementById('hh-submit');
  const alertEl = document.getElementById('hh-reg-alert');
  const spinner = btn?.querySelector('.hh-spinner');

  const getCsrf = () => (document.querySelector('meta[name="csrf-token"]')?.content || '');

  function showAlert(type, msg) {
    alertEl.classList.remove('hh-hidden','ok','err');
    alertEl.classList.add(type === 'ok' ? 'ok' : 'err');
    alertEl.textContent = msg;
  }
  function hideAlert() { alertEl.classList.add('hh-hidden'); alertEl.textContent = ''; }

  function setLoading(v) {
    if (!btn || !spinner) return;
    btn.disabled = v;
    spinner.style.display = v ? 'inline-block' : 'none';
  }

function validate() {
  const u  = document.getElementById('hh-username').value.trim();
  const e  = document.getElementById('hh-email').value.trim();
  const p1 = document.getElementById('hh-pass').value;
  const p2 = document.getElementById('hh-pass2').value;
  const a  = document.getElementById('hh-accept').checked;

  if (u.length < 3 || u.length > 32) return L['err_username_length'];
  if (!/^[A-Za-z0-9_\-]+$/.test(u))  return L['err_username_chars'];
  if (!e)                            return L['err_email_required'];
  if (p1.length < 8)                 return L['err_pass_length'];
  if (p1 !== p2)                     return L['err_pass_mismatch'];
  if (!a)                            return L['err_accept_terms'];
  return null;
}


  // Focus trap (einfach)
  function trapFocus(e) {
    if (!modal.classList.contains('hh-open')) return;
    const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    const first = focusables[0], last = focusables[focusables.length - 1];
    if (e.key === 'Tab') {
      if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    }
  }

  function openModal() {
    hideAlert();
    modal.classList.remove('hh-hidden');
    // small raf, then animate in:
    requestAnimationFrame(() => modal.classList.add('hh-open'));
    document.documentElement.classList.add('hh-lock');
    // Fokus erstes Feld
    setTimeout(()=> document.getElementById('hh-username')?.focus(), 20);
  }

  function closeModal() {
    modal.classList.remove('hh-open');
    setTimeout(() => {
      modal.classList.add('hh-hidden');
      document.documentElement.classList.remove('hh-lock');
    }, 280);
  }

  openers.forEach(o => o.addEventListener('click', e => { e.preventDefault(); openModal(); }));
  closeBtn?.addEventListener('click', closeModal);
  backdrop?.addEventListener('click', closeModal);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('hh-open')) closeModal(); });
  document.addEventListener('keydown', trapFocus);

form?.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  hideAlert();

  // 1) Client-Validierung
  const u  = document.getElementById('hh-username').value.trim();
  const e  = document.getElementById('hh-email').value.trim();
  const p1 = document.getElementById('hh-pass').value;
  const p2 = document.getElementById('hh-pass2').value;
  const a  = document.getElementById('hh-accept').checked;
  const csrf = getCsrf();

// harte Checks
if (u.length < 3 || u.length > 32) return showAlert('err', L['err_username_length']);
if (!/^[A-Za-z0-9_\-]+$/.test(u))  return showAlert('err', L['err_username_chars']);
if (!e)                            return showAlert('err', L['err_email_required']);
if (p1.length < 8)                 return showAlert('err', L['err_pass_length']);
if (p1 !== p2)                     return showAlert('err', L['err_pass_mismatch']);
if (!a)                            return showAlert('err', L['err_accept_terms']);


  // 2) Payload explizit urlencoded bauen (nicht FormData)
  const params = new URLSearchParams();

  // Primär-Schlüssel (GENAU so benannt, wie die meisten Register-APIs erwarten)
  params.set('username', u);
  params.set('email', e);
  params.set('password', p1);
  params.set('password_confirm', p2);
  params.set('accept', '1');

  // CSRF doppelt: im Header & im Body
  if (csrf) params.set('csrf', csrf);

  // Sicherheitsnetz: Synonyme mitschicken (falls deine API andere Keys nimmt)
  params.set('password2', p2);
  params.set('name', u);
  params.set('nickname', u);
  params.set('display_name', u);
  params.set('terms', '1');
  params.set('accept_terms', '1');
  params.set('privacy', '1');
  params.set('accept_privacy', '1');

  // Debug: Zeig an, was wir senden (Passwörter maskiert)
  console.log('[register] payload', {
    username: u,
    email: e,
    password: `***(${p1.length})`,
    password_confirm: `***(${p2.length})`,
    accept: '1',
    csrf: csrf ? 'present' : 'missing'
  });

  setLoading(true);
  try {
    const res = await fetch('/api/auth/register.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-CSRF-Token': csrf || ''
      },
      body: params.toString(),
      credentials: 'include'
    });

    let data = null; try { data = await res.json(); } catch {}

if (!res.ok) {
  showAlert('err', (data && (data.error || data.message)) || L['err_register_failed']);
  setLoading(false);
  return;
}

const ok = data && (data.ok === true || data.success === true);
if (ok) {
  showAlert('ok', L['register_success']);
  setTimeout(() => window.location.reload(), 700);
} else {
  // Falls weiterhin missing_fields o.ä.: Response sichtbar machen
  console.warn('[register] response', data);
  showAlert('err', (data && (data.error || data.message)) || L['err_register_failed']);
  setLoading(false);
}
} catch (err) {
  console.error(err);
  showAlert('err', L['err_network']);
  setLoading(false);
}

});


})();
</script>

    <?php endif; ?>

 </div>
              </div>
<!-- chat -->
              <button class="lg:hidden btn-c btn-c-lg btn-c-dark-outline nav-toggole shrink-0">
                <i class="ti ti-menu-2"></i>
              </button>
            </div>
          </div>
        </nav>
      </div>
    </div>
	<!-- HEADER END -->
	
	
	
	
	
    <nav class="w-full flex justify-between items-center">
      <div
        class="small-nav fixed top-0 left-0 h-screen w-full shadow-lg z-[999] transform transition-transform ease-in-out invisible md:translate-y-full max-md:-translate-x-full duration-500">
        <div class="absolute z-[5] inset-0 bg-b-neutral-3 flex-col-c min-h-screen max-md:max-w-[400px]">
          <div class="container max-md:p-0 md:overflow-y-hidden overflow-y-scroll scrollbar-sm lg:max-h-screen">
            <div class="p-40p">
              <div class="flex justify-between items-center mb-10">
                <a href="index-2.html">
                  <img class="w-[142px]" src="../assets/images/icons/logo.png" alt="HuntHub" />
                </a>
                <button class="nav-close btn-c btn-c-md btn-c-primary">
                  <i class="ti ti-x"></i>
                </button>
              </div>
              <div class="grid grid-cols-12 gap-x-24p gap-y-10 sm:p-y-48p">
                <div class="xl:col-span-8 md:col-span-7 col-span-12">
                  <div
                    class="overflow-y-scroll overflow-x-hidden scrollbar scrollbar-sm xl:max-h-[532px] md:max-h-[400px] md:pr-4">
                    <ul class="flex flex-col justify-center items-start gap-20p text-w-neutral-1">
            
                      <li class="mobail-menu">
                        <a href="/" data-i18n="home"><?php echo $L['home']; ?> </a>
                      </li>
					  <li class="mobail-menu">
                        <a href="/wall/" data-i18n="community">Community Wall </a>
                      </li>
                      <li class="mobail-menu">
                        <a href="/forum/boards.php" data-i18n="community"><?php echo $L['community']; ?> </a>
                      </li>
                      <li class="mobail-menu">
                        <a href="/lvl" data-i18n="quests"><?php echo $L['quests']; ?> </a>
                      </li>
                      <li class="mobail-menu">
                        <a href="/tournaments/view.php?id=2" data-i18n="tournament"><?php echo $L['event']; ?> </a>
                      </li>
                      
                      
                      
                      <!--
                      <li class="sub-menu mobail-submenu">
                        <span class="mobail-submenu-btn">
                          <span class="submenu-btn">Tournaments</span>
                          <span class="collapse-icon mobail-submenu-icon">
                            <i class="ti ti-chevron-down"></i>
                          </span>
                        </span>
                        <ul class="grid grid-cols-2 sm:gap-y-16p gap-y-2 px-16p">
                          <li class="pt-2">
                            <a aria-label="item" class="text-base hover:text-primary transition-1"
                              href="tournaments.html">
                              - Chat
                            </a>
                          </li>
                          <li>
                            <a aria-label="item" class="text-base hover:text-primary transition-1"
                              href="tournament-overview.html">
                              - Tournament Overview
                            </a>
                          </li>
                          <li>
                            <a aria-label="item" class="text-base hover:text-primary transition-1"
                              href="tournament-prizes.html">
                              - Tournament Prizes
                            </a>
                          </li>
                          <li>
                            <a aria-label="item" class="text-base hover:text-primary transition-1"
                              href="tournament-participants.html">
                              - Tournament Participants
                            </a>
                          </li>
                          <li>
                            <a aria-label="item" class="text-base hover:text-primary transition-1"
                              href="tournament-matches.html">
                              - Tournament Matches
                            </a>
                          </li>
                          <li>
                            <a aria-label="item" class="text-base hover:text-primary transition-1"
                              href="tournament-brackets.html">
                              - Tournament Brackets
                            </a>
                          </li>
                        </ul>
                      </li>
					  -->

                      
                      
                      
                      <li class="mobail-menu">
                        <a href="contact-us.html" data-i18n="contactus"><?php echo $L['contactus']; ?></a>
                      </li>
                    </ul>
					
                  </div>
                </div>
                <div class="xl:col-span-4 md:col-span-5 col-span-12">
                  <div class="flex flex-col items-baseline justify-between h-full">
                    <form
                      class="w-full flex items-center justify-between px-16p py-2 pr-1 border border-w-neutral-4/60 rounded-full">
                      <input class="placeholder:text-w-neutral-4 bg-transparent w-full" type="text" name="search-media"
                        placeholder="Search Media" id="search-media" />
                      <button type="submit" class="btn-c btn-c-md text-w-neutral-4">
                        <i class="ti ti-search"></i>
                      </button>
                    </form>
                    <div class="mt-40p">
                      <img class="mb-16p" src="../assets/images/icons/logo.png" alt="logo" />
					  <div id="lang-switcher" class="relative">
  <!-- Button zum Öffnen/Schließen -->
  <button type="button"
          class="inline-flex items-center gap-2 pl-3 pr-3 py-2 rounded-full bg-b-neutral-4/50 hover:bg-b-neutral-4 text-w-neutral-1 transition-colors"
          aria-haspopup="true" aria-expanded="false">
    <i class="ti ti-language icon-18 opacity-80"></i>
    <span class="text-base"><?= $label ?></span>
    <i class="ti ti-chevron-down opacity-70 transition-transform duration-300"></i>
  </button>

  <!-- Dropdown-Menü -->
  <div class="dropdown-content absolute left-0 mt-2 w-[220px] rounded-12 border border-w-neutral-4/20 bg-b-neutral-3 shadow-xl p-6 z-[1000]"
       hidden> <!-- 'hidden' sorgt dafür, dass es beim Laden geschlossen ist -->
    <a href="?lang=de"
       class="lang-opt flex items-center gap-2 px-3 py-2 rounded-10 hover:bg-b-neutral-4/60 transition
              <?= $lang==='de' ? 'is-active' : '' ?>"
       data-lang="de">
      <span class="text-w-neutral-1 flex-1">Deutsch</span>
      <span class="px-1.5 py-0.5 text-xs rounded bg-b-neutral-4/70 text-w-neutral-2">DE</span>
      <?= $lang==='de' ? '<i class="ti ti-check ml-1"></i>' : '' ?>
    </a>

    <a href="?lang=en"
       class="lang-opt flex items-center gap-2 px-3 py-2 rounded-10 hover:bg-b-neutral-4/60 transition mt-2
              <?= $lang==='en' ? 'is-active' : '' ?>"
       data-lang="en">
      <span class="text-w-neutral-1 flex-1">English</span>
      <span class="px-1.5 py-0.5 text-xs rounded bg-b-neutral-4/70 text-w-neutral-2">EN</span>
      <?= $lang==='en' ? '<i class="ti ti-check ml-1"></i>' : '' ?>
    </a>
  </div>
</div>
<script>
(() => {
  const root = document.getElementById('lang-switcher');
  if (!root) return;
  const btn  = root.querySelector('button');
  const menu = root.querySelector('.dropdown-content');

  // Initial sicher schließen
  menu.setAttribute('hidden','');

  function open()  { menu.removeAttribute('hidden'); btn.setAttribute('aria-expanded','true'); }
  function close() { menu.setAttribute('hidden','');  btn.setAttribute('aria-expanded','false'); }

  btn.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    menu.hasAttribute('hidden') ? open() : close();
  });

  document.addEventListener('click', e => {
    if (!root.contains(e.target)) close();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') close();
  });

  // Sprachwechsel
  root.querySelectorAll('.lang-opt').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const lang = a.dataset.lang || 'de';
      document.cookie = `lang=${lang}; path=/; max-age=${60*60*24*365}; SameSite=Lax`;
      const url = new URL(window.location.href);
      url.searchParams.set('lang', lang);
      window.location.href = url.toString();
    });
  });
})();
</script>
                      <p class="text-base text-w-neutral-3 mb-32p" data-i18n="descmenu">
					  <?php echo $L['descmenu']; ?>
                
                      </p>
                      <div class="flex items-center flex-wrap gap-3">
                        <a href="#" class="btn-socal-primary">
                          <i class="ti ti-brand-facebook"></i>
                        </a>
                        <a href="#" class="btn-socal-primary">
                          <i class="ti ti-brand-twitch"></i>
                        </a>
                        <a href="#" class="btn-socal-primary">
                          <i class="ti ti-brand-instagram"></i>
                        </a>
                        <a href="#" class="btn-socal-primary">
                          <i class="ti ti-brand-discord"></i>
                        </a>
                        <a href="#" class="btn-socal-primary">
                          <i class="ti ti-brand-youtube"></i>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="nav-close min-h-[200vh] navbar-overly"></div>
      </div>
    </nav>
  </div>
</header>
    <!-- sidebar start -->
    <div>
    <!-- left sidebar start-->
    <div
        class="fixed top-0 left-0 lg:translate-x-0 -translate-x-full h-screen z-30 bg-b-neutral-4 pt-30 px-[27px] transition-1">
        <div class="max-h-screen overflow-y-auto scrollbar-0">
            <div class="flex flex-col items-center xxl:gap-[30px] xl:gap-6 lg:gap-5 gap-4 h-[700px] side-navbar-one">
                <button class="nav-toggole btn-c btn-c-3xl btn-primary icon-32 shrink-0" >
<svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.79701 2.87735C2.02243 1.91635 2.77279 1.16599 3.7338 0.940565V0.940565C4.56663 0.74521 5.43337 0.745209 6.2662 0.940565V0.940565C7.22721 1.16599 7.97757 1.91635 8.20299 2.87735V2.87735C8.39835 3.71019 8.39835 4.57692 8.20299 5.40976V5.40976C7.97757 6.37076 7.22721 7.12112 6.2662 7.34654V7.34654C5.43337 7.5419 4.56663 7.5419 3.7338 7.34654V7.34654C2.77279 7.12112 2.02243 6.37076 1.79701 5.40976V5.40976C1.60166 4.57693 1.60166 3.71018 1.79701 2.87735V2.87735Z" stroke="currentColor" stroke-width="1.5"></path><path d="M1.79701 13.8774C2.02243 12.9163 2.77279 12.166 3.7338 11.9406V11.9406C4.56663 11.7452 5.43337 11.7452 6.2662 11.9406V11.9406C7.22721 12.166 7.97757 12.9163 8.20299 13.8774V13.8774C8.39835 14.7102 8.39835 15.5769 8.20299 16.4098V16.4098C7.97757 17.3708 7.22721 18.1211 6.2662 18.3465V18.3465C5.43337 18.5419 4.56663 18.5419 3.7338 18.3465V18.3465C2.77279 18.1211 2.02243 17.3708 1.79701 16.4098V16.4098C1.60166 15.5769 1.60166 14.7102 1.79701 13.8774V13.8774Z" class="icon_main" stroke-width="1.5"></path><path d="M12.797 2.87735C13.0224 1.91635 13.7728 1.16599 14.7338 0.940565V0.940565C15.5666 0.74521 16.4334 0.745209 17.2662 0.940565V0.940565C18.2272 1.16599 18.9776 1.91635 19.203 2.87735V2.87735C19.3983 3.71019 19.3983 4.57692 19.203 5.40976V5.40976C18.9776 6.37076 18.2272 7.12112 17.2662 7.34654V7.34654C16.4334 7.5419 15.5666 7.5419 14.7338 7.34654V7.34654C13.7728 7.12112 13.0224 6.37076 12.797 5.40976V5.40976C12.6017 4.57693 12.6017 3.71018 12.797 2.87735V2.87735Z" stroke="currentColor" stroke-width="1.5"></path><path d="M12.797 13.8774C13.0224 12.9163 13.7728 12.166 14.7338 11.9406V11.9406C15.5666 11.7452 16.4334 11.7452 17.2662 11.9406V11.9406C18.2272 12.166 18.9776 12.9163 19.203 13.8774V13.8774C19.3983 14.7102 19.3983 15.5769 19.203 16.4098V16.4098C18.9776 17.3708 18.2272 18.1211 17.2662 18.3465V18.3465C16.4334 18.5419 15.5666 18.5419 14.7338 18.3465V18.3465C13.7728 18.1211 13.0224 17.3708 12.797 16.4098V16.4098C12.6017 15.5769 12.6017 14.7102 12.797 13.8774V13.8774Z" stroke="currentColor" stroke-width="1.5"></path></svg>
                </button>
				
                <div class="flex flex-col gap-2 rounded-full bg-b-neutral-1 w-fit p-2 shrink-0">
<a data-toggle-global-chat class="nav-item btn-c btn-c-3xl text-white transition-1">
<svg width="26" height="27" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M1.14651 6.134C1.71018 3.68174 3.54739 1.77646 5.88882 1.21598L6.07469 1.17149C7.33452 0.86992 8.64278 0.86992 9.90261 1.17149L10.2202 1.24751C12.4809 1.78868 14.2548 3.6283 14.7991 5.99604L14.8731 6.31815C15.1535 7.53803 15.1535 8.8105 14.8731 10.0304C14.3085 12.4869 12.2158 14.2176 9.81017 14.2176H8.92569C8.63523 14.2176 8.37235 14.4049 8.26196 14.6871C7.86082 15.7127 6.74095 16.216 5.76336 15.7981L5.66489 15.756C3.40582 14.7902 1.72181 12.7174 1.14933 10.2268C0.841106 8.88588 0.83829 7.47491 1.14651 6.134Z" stroke="currentColor" stroke-width="0.5" stroke-linecap="round"></path>
<path d="M5.24683 5.99414H10.2732M5.24683 8.87428H8.44544" class="icon_main" stroke-width="0.5" stroke-linecap="round"></path>
</svg>
</a>
<script>
// Punkt anzeigen/verstecken
function toggleChatDot(show) {
document.querySelectorAll('[data-toggle-global-chat]').forEach(el => {
if (show) {
el.classList.add('chat-has-new');
} else {
el.classList.remove('chat-has-new');
}
});
}


// Punkt automatisch entfernen, sobald Chat geöffnet wird
const chatToggles = document.querySelectorAll('[data-toggle-global-chat]');
chatToggles.forEach(el => {
el.addEventListener('click', () => {
toggleChatDot(false);
});
});


// Demo: nach 3 Sekunden einschalten
setTimeout(() => toggleChatDot(true), 3000);
</script>
                    <a href="/forum/boards.php" class="nav-item btn-c btn-c-3xl text-white  transition-1">
               <svg width="30" height="27" viewBox="0 0 20 17" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M1 14.531C1 12.6539 2.33361 11.055 4.14603 10.7592L4.30929 10.7326C5.76058 10.4957 7.23942 10.4957 8.69071 10.7326L8.85397 10.7592C10.6664 11.055 12 12.6539 12 14.531C12 15.3423 11.3569 16 10.5636 16H2.43639C1.64309 16 1 15.3423 1 14.531Z" stroke="currentColor" stroke-width="0.5"></path>
<path d="M9.70833 4.28125C9.70833 6.09343 8.27191 7.5625 6.5 7.5625C4.72809 7.5625 3.29167 6.09343 3.29167 4.28125C3.29167 2.46907 4.72809 1 6.5 1C8.27191 1 9.70833 2.46907 9.70833 4.28125Z" stroke="currentColor" stroke-width="0.5"></path>
<path d="M12 7.5625C14.2552 7.5625 16.0833 6.09343 16.0833 4.28125C16.0833 2.46907 14.2552 1 12 1M14.7882 16H17.1719C18.1815 16 19 15.3423 19 14.531C19 12.6539 17.3027 11.055 14.996 10.7592L14.7882 10.7326C14.2285 10.7326 14.195 10.7326 13.448 10.7326" class="icon_main" stroke-width="0.5" stroke-linecap="round"></path>
</svg>
                    </a>
                    <a  class="nav-item btn-c btn-c-3xl text-white  transition-1">
                       <svg width="25" height="25" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10 1C8.97667 1 7.95334 1.11763 6.95043 1.35288C4.17301 2.00437 2.00437 4.17301 1.35288 6.95043C0.882374 8.95626 0.882374 11.0437 1.35288 13.0496C2.00437 15.827 4.17301 17.9956 6.95044 18.6471C8.95626 19.1176 11.0437 19.1176 13.0496 18.6471C15.827 17.9956 17.9956 15.827 18.6471 13.0496C18.8824 12.0466 19 11.0233 19 9.99997" class="icon_main" stroke-width="0.5" stroke-linecap="round"></path>
<path d="M1.35288 13.0496C0.882374 11.0437 0.882374 8.95626 1.35288 6.95043C2.00437 4.17301 4.17301 2.00437 6.95043 1.35288C7.95334 1.11763 8.97667 1 10 1M6.95044 18.6471C8.95626 19.1176 11.0437 19.1176 13.0496 18.6471C15.827 17.9956 17.9956 15.827 18.6471 13.0496C18.8824 12.0466 19 11.0233 19 9.99997" stroke="currentColor" stroke-width="0.5" stroke-linecap="round"></path>
<path d="M19 4.5C19 5.88071 17.8807 7 16.5 7C15.1193 7 14 5.88071 14 4.5C14 3.11929 15.1193 2 16.5 2C17.8807 2 19 3.11929 19 4.5Z" class="icon_main" stroke-width="0.5"></path>
</svg>
                    </a>
                    <a href="/lvl" class="nav-item btn-c btn-c-3xl text-white  transition-1">
                <svg width="24" height="26" viewBox="0 0 19 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.3754 18.131L13.5559 18.0061C14.1209 17.6154 14.4035 17.42 14.6562 17.2106C16.0294 16.0724 16.9518 14.486 17.2588 12.7341C17.3153 12.4117 17.3446 12.0706 17.4033 11.3885L17.4337 11.0358C17.5309 9.90671 17.5212 8.77105 17.4048 7.64379L17.3687 7.29494C17.165 5.32225 15.9539 3.59327 14.1644 2.72018C11.2221 1.28468 7.77785 1.28468 4.83559 2.72018C3.04608 3.59327 1.83504 5.32225 1.63127 7.29494L1.59524 7.64379C1.4788 8.77105 1.46911 9.90671 1.5663 11.0358L1.59666 11.3885C1.65537 12.0706 1.68473 12.4117 1.74123 12.7341C2.04821 14.486 2.97055 16.0724 4.3438 17.2106C4.59652 17.42 4.8791 17.6154 5.44406 18.0061L5.62461 18.131C6.39273 18.6621 6.77682 18.9277 7.16175 19.1123C8.63929 19.8207 10.3607 19.8207 11.8382 19.1123C12.2232 18.9277 12.6073 18.6621 13.3754 18.131Z" class="icon_main" stroke-width="0.5"></path><path d="M17.4033 11.3885L17.4337 11.0358C17.5309 9.90671 17.5212 8.77105 17.4048 7.64379L17.3687 7.29494C17.165 5.32225 15.9539 3.59327 14.1644 2.72018C11.2221 1.28468 7.77785 1.28468 4.83559 2.72018C3.04608 3.59327 1.83504 5.32225 1.63127 7.29494L1.59524 7.64379C1.4788 8.77105 1.46911 9.90671 1.5663 11.0358L1.59666 11.3885C1.65537 12.0706 1.68473 12.4117 1.74123 12.7341C2.04821 14.486 2.97055 16.0724 4.3438 17.2106C4.59652 17.42 4.8791 17.6154 5.44406 18.0061L5.62461 18.131C6.39273 18.6621 6.77682 18.9277 7.16175 19.1123" stroke="currentColor" stroke-width="0.5" stroke-linecap="round"></path><path d="M6.75 10.3936L8.75 12.3936L12.25 8.64355" class="icon_main" stroke-width="0.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </a>
                </div>
                <div class="flex flex-col gap-2 rounded-full w-fit p-2 shrink-0">
                    <a href="/tournaments/view.php?id=2" class="nav-item btn-c btn-c-3xl ">
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"
     class="icon icon-tabler icons-tabler-outline icon-tabler-diamond">
  <path stroke="none" d="M0 0h24v24H0z"  fill="none"/>
  <path d="M6 5h12l3 5l-8.5 9.5a.7 .7 0 0 1 -1 0l-8.5 -9.5l3 -5"/>
  <!-- innerer Strich in Rot -->
  <path d="M10 12l-2 -2.2l.6 -1" stroke="#c64d53"/>
</svg>

                    </a>
       
					
                    <a href="/profile.php" class="nav-item btn-c btn-c-3xl ">
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"
     class="icon icon-tabler icons-tabler-outline icon-tabler-user">
  <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
  <!-- Kopf: rote Kontur -->
  <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" stroke="#c64d53"/>
  <!-- Körper: weiß (currentColor) -->
  <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
</svg>

                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- left sidebar end -->
    <!-- right sidebar start -->
   <?php if ($me): ?>  <div
        class="fixed top-0 right-0 lg:translate-x-0 translate-x-full h-screen z-30 bg-b-neutral-4 pt-30 px-[27px] transition-1">
        <div class="flex flex-col items-center xxl:gap-[30px] xl:gap-6 lg:gap-5 gap-4">
            <div class="flex flex-col items-center gap-16p rounded-full w-fit p-2">
<div class="swiper infinity-slide-vertical messenger-carousel max-h-[288px] w-full">
  <div class="swiper-wrapper" id="recentContactsWrap">
    <!-- JS füllt hier Slides -->
  </div>
</div>
                <a href="#" id="openContactsBtn"
   class="btn-c btn-c-xl bg-b-neutral-1 hover:bg-primary text-white hover:text-b-neutral-4 transition-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg>
                </a>
            </div>
            <div class="w-full h-1px bg-b-neutral-1"></div>
            
        </div>
    </div>
	<!-- Modal: alle Kontakte -->
<!-- Modal: alle Kontakte (schön) -->
<div id="contactsModal" class="fixed inset-0 hidden" style="z-index:9999;">
  <div class="hhc-backdrop" data-close-contacts></div>
  <div class="hhc-panel">
    <div class="hhc-hd">
      <div class="hhc-title" data-i18n="chats"><?php echo $L['chats']; ?></div>
      <button class="hhc-close" type="button" data-close-contacts>&times;</button>
    </div>

    <div class="hhc-search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>
      </svg>
      <input id="contactsSearch" type="text" placeholder="Suchen …">
    </div>

    <div id="contactsList" class="hhc-list">
      <!-- JS füllt hier die Einträge -->
    </div>
  </div>
</div>
<?php endif; ?>

    <!-- right sidebar end -->
</div>
<script>
(function(){
  async function postJSON(url, data) {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(data)
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw json;
    return json;
  }
  function hardReload() {
    location.assign(location.pathname + location.search);
  }

  const loginForm = document.getElementById('loginForm');
  if (loginForm) loginForm.onsubmit = async (e)=>{
    e.preventDefault();
    const fd  = new FormData(loginForm);
    const msg = document.getElementById('loginMsg');
    msg.classList.remove('ok'); msg.classList.add('err'); msg.textContent = '…';
    try {
      const out = await postJSON('/api/auth/login.php', {
        email: fd.get('email'),
        password: fd.get('password')
      });
      msg.classList.remove('err'); msg.classList.add('ok'); msg.textContent = 'Eingeloggt.';
      if (out.redirect) { location.assign(out.redirect); return; }
      hardReload();
    } catch (err) {
      msg.classList.remove('ok'); msg.classList.add('err');
      msg.textContent = (err && err.error) ? err.error : 'Login fehlgeschlagen.';
    }
  };

  const logoutBtn = document.getElementById('logout');
  if (logoutBtn) logoutBtn.onclick = async (e)=>{
    e.preventDefault();
    try {
      await fetch('/api/auth/logout.php', { method:'POST', credentials:'include' });
    } finally {
      hardReload();
    }
  };
})();
</script>
<?php if ($me): ?>
<!-- ===== Notifications (bestehend) ===== -->
<script>
(() => {
  const ROOT   = document.getElementById('noti-root');
  if (!ROOT) return;

  const BTN    = document.getElementById('noti-btn');
  const MENU   = document.getElementById('noti-menu');
  const LIST   = document.getElementById('noti-list');
  const BADGE  = document.getElementById('noti-badge');
  const MARKALL= document.getElementById('noti-markall');
  const REFRESH= document.getElementById('noti-refresh');

  const BASE   = ROOT.getAttribute('data-base') || '';
  const API_L  = BASE + '/api/notifications/list.php';
  const API_R  = BASE + '/api/notifications/mark_read.php';
  const CSRF   = document.querySelector('meta[name="csrf"]')?.content || '';

  let cache = { items: [], ts: 0 };

  function timeAgo(ts) {
    try {
      const d = new Date(ts.replace(' ', 'T') + 'Z');
      const diff = Math.floor((Date.now() - d.getTime())/1000);
      if (diff < 60)   return 'gerade eben';
      if (diff < 3600) return Math.floor(diff/60) + ' min';
      if (diff < 86400)return Math.floor(diff/3600) + ' h';
      return Math.floor(diff/86400) + ' d';
    } catch { return ts; }
  }

  function buildLink(n) {
    const t = Number(n.thread_id || 0);
    const p = Number(n.post_id || 0);
    if (!t) return '#';
    return BASE + '/forum/thread.php?t=' + t + (p ? ('#comment-' + p) : '');
  }

function kindText(n) {
  const actor = n.actor_name || L['someone'];
  switch (n.type) {
    case 'mention_post':   return actor + ' ' + L['notif_mention_post'];
    case 'mention_thread': return actor + ' ' + L['notif_mention_thread'];
    case 'reply_post':     return actor + ' ' + L['notif_reply_post'];
    case 'comment_thread': return actor + ' ' + L['notif_comment_thread'];
    case 'like_post':      return actor + ' ' + L['notif_like_post'];
    case 'like_thread':    return actor + ' ' + L['notif_like_thread'];
    default:               return L['notif_new_from'] + ' ' + actor + '.';
  }
}

  function updateBadge() {
    const unread = cache.items.filter(x => !x.read_at).length;
    if (unread > 0) {
      BADGE.textContent = unread > 99 ? '99+' : String(unread);
      BADGE.classList.remove('hidden');
    } else {
      BADGE.classList.add('hidden');
    }
  }

function render() {
  if (!cache.items.length) {
    LIST.innerHTML = '<div class="noti-empty">' + t('no_notifications') + '</div>';
    updateBadge();
    return;
  }

  var BASE = (typeof APP_BASE === 'string') ? APP_BASE : '';

  LIST.innerHTML = cache.items.map(function(n) {
    var ava = n.actor_avatar || (BASE + '/assets/images/avatars/placeholder.png');
    var link = buildLink(n);
    var unread = !n.read_at;

    return (
      '<div class="noti-item ' + (unread ? 'unread' : '') + '" data-id="' + n.id + '">' +
        '<img class="noti-ava" src="' + ava + '" alt="' + (n.actor_name || '') + '" ' +
             'onerror="this.onerror=null;this.src=\'' + (BASE + '/assets/images/avatars/placeholder.png') + '\';">' +
        '<div class="noti-body">' +
          '<a class="noti-title" href="' + link + '">' + kindText(n) + '</a>' +
          '<div class="noti-text">' + t('thread') + ' #' + n.thread_id + (n.post_id ? ' · ' + t('post') + ' #' + n.post_id : '') + '</div>' +
          '<div class="noti-time">' + (n.created_at ? timeAgo(n.created_at) : '') + '</div>' +
        '</div>' +
        '<div class="noti-actions">' +
          (unread ? '<span class="noti-dot" title="' + t('unread') + '"></span>' : '') +
          '<button type="button" class="noti-link noti-readone">' + t('mark_read') + '</button>' +
        '</div>' +
      '</div>'
    );
  }).join('');

  updateBadge();
}


  async function fetchList() {
    try {
      const res = await fetch(API_L + '?limit=20', {
        credentials: 'include',
        headers: { 'Accept':'application/json' }
      });
      const out = await res.json();
      if (res.ok && out?.ok) {
        cache.items = Array.isArray(out.items) ? out.items : [];
        cache.ts = Date.now();
        render();
      } else {
        LIST.innerHTML = '<div class="noti-empty">Fehler beim Laden.</div>';
      }
    } catch {
      LIST.innerHTML = '<div class="noti-empty">Netzwerkfehler.</div>';
    }
  }

async function markRead(id = null) {
  const body = new URLSearchParams(id ? { id: String(id), csrf: CSRF } : { csrf: CSRF });

  const res = await fetch(API_R, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-CSRF': CSRF
    },
    body,
    keepalive: true
  });

  const out = await res.json().catch(()=>({}));
  if (!(res.ok && out?.ok)) {
    console.warn('mark_read failed', res.status, out);
    return false;
  }
  return true;
}


  function openMenu(){
    const MENU = document.getElementById('noti-menu');
    MENU.classList.remove('hidden');
    document.getElementById('noti-btn').setAttribute('aria-expanded','true');
    if (!cache.ts || (Date.now() - cache.ts) > 20000) fetchList();
  }
  function closeMenu(){
    const MENU = document.getElementById('noti-menu');
    MENU.classList.add('hidden');
    document.getElementById('noti-btn').setAttribute('aria-expanded','false');
  }

  document.getElementById('noti-btn').addEventListener('click', (e) => {
    e.preventDefault();
    const MENU = document.getElementById('noti-menu');
    MENU.classList.contains('hidden') ? openMenu() : closeMenu();
  });
  document.addEventListener('click', (e) => {
    const ROOT = document.getElementById('noti-root');
    if (!ROOT.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

  document.getElementById('noti-markall')?.addEventListener('click', (e) => {
    e.preventDefault();
    cache.items = cache.items.map(x => ({ ...x, read_at: new Date().toISOString() }));
    render();
    markRead(null);
  });

  document.getElementById('noti-refresh')?.addEventListener('click', (e) => {
    e.preventDefault();
    fetchList();
  });

  document.getElementById('noti-list').addEventListener('click', (e) => {
    const row  = e.target.closest('.noti-item');
    if (!row) return;

    if (e.target.closest('.noti-readone')) {
      e.preventDefault();
      const id = row.getAttribute('data-id');
      if (!id) return;
      row.classList.remove('unread');
      row.querySelector('.noti-dot')?.remove();
      const it = cache.items.find(x => x.id == id);
      if (it) it.read_at = new Date().toISOString();
      updateBadge();
      markRead(id);
      return;
    }

    const link = row.querySelector('a.noti-title');
    if (!link) return;

    const href   = link.getAttribute('href') || '#';
    const newTab = e.button === 1 || e.metaKey || e.ctrlKey;

    if (!newTab) {
      e.preventDefault();
      location.href = href;
    }
  });

  (async function boot() {
    await fetchList();
    setInterval(fetchList, 60000);
  })();
})();
</script>

<!-- ===== Contacts / Chat-Open: Sidebar & Modal (OHNE Fallback) ===== -->
<script>
(() => {
  const BASE         = '<?= rtrim($APP_BASE ?? "", "/") ?>';
  const API_RECENT   = BASE + '/api/messages/recent_contacts.php';
  const API_ALL      = BASE + '/api/messages/my_contacts.php';
  const AVA_FALLBACK = BASE + '/assets/images/avatars/placeholder.png';

  function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
  function normalizeUrl(u){
    if (!u) return AVA_FALLBACK;
    if (/^https?:\/\//i.test(u)) return u;
    if (u.startsWith('//')) return location.protocol + u;
    if (u.startsWith('/')) return (BASE || '') + u;
    return (BASE ? BASE + '/' : '/') + u.replace(/^\.?\//,'');
  }

  // Recent (Top 5) in der Sidebar
  const recentWrap = document.getElementById('recentContactsWrap');

  async function loadRecent(){
    try{
      const res = await fetch(API_RECENT + '?limit=5', { credentials:'include' });
      const out = await res.json();
      if (!res.ok || !out?.ok) throw 0;

      const items = out.items || [];
      recentWrap.innerHTML = items.map(it => {
        const name = it.display_name || ('User ' + (it.user_id || ''));
        const ava  = normalizeUrl(it.avatar_path || AVA_FALLBACK);
        const unread = Number(it.unread_count ?? it.unread ?? it.unseen ?? 0);
		const unreadCls = unread > 0 ? ' has-unread' : ''; 
const badge  = unread > 0
  ? `<span class="badge-center">${unread > 99 ? '99+' : unread}</span>`
  : '';

        return `
         <div class="swiper-slide">
  <a href="#"
     data-open-chat="${it.user_id}"
     data-name="${esc(name)}"
     data-avatar="${esc(ava)}"
     class="relative avatar size-60p${unreadCls}"
     title="${esc(name)}">
    <img src="${esc(ava)}" alt="${esc(name)}" loading="lazy" decoding="async" width="60" height="60">
    ${badge}
  </a>
</div>
`;
      }).join('');

      if (window.Swiper) {
        new Swiper(recentWrap.closest('.swiper'), {
          direction: 'vertical',
          slidesPerView: 'auto',
          freeMode: true,
          mousewheel: true
        });
      }
    }catch{
      recentWrap.innerHTML = '<div class="text-w-neutral-4 text-sm p-2">Fehler…</div>';
    }
  }

  // Modal „alle Kontakte“
  const openBtn  = document.getElementById('openContactsBtn');
  const modal    = document.getElementById('contactsModal');
  const listBox  = document.getElementById('contactsList');
  const searchIn = document.getElementById('contactsSearch');

  function openModal(){ modal.classList.remove('hidden'); searchIn.value=''; renderList(); }
  function closeModal(){ modal.classList.add('hidden'); }
  modal?.addEventListener('click', (e)=> { if (e.target.matches('[data-close-contacts]')) closeModal(); });
  openBtn?.addEventListener('click', (e)=>{ e.preventDefault(); fetchAll(); });

  let allItems = [];
  async function fetchAll(){
    try{
      const res = await fetch(API_ALL, { credentials:'include' });
      const out = await res.json();
      if (!res.ok || !out?.ok) throw 0;
      allItems = out.items || [];
      openModal();
    }catch{
      allItems = [];
      openModal();
      listBox.innerHTML = '<div class="text-w-neutral-4 text-sm p-2">Fehler beim Laden…</div>';
    }
  }

  function lastPreview(item){
    if (item.attachment_type === 'video') return '🎥 Video';
    if (item.attachment_type === 'image') return '🖼️ Bild';
    if (item.last_body && item.last_body.startsWith('{"type":"attach"')) return '📎 Anhang';
    return (item.last_body || '').slice(0, 60);
  }

function renderList(){
  const all = allItems || [];               // <— hier lag der Fehler
  const q = (searchIn.value || '').trim().toLowerCase();

  const items = !q ? all : all.filter(x => {
    const n = (x.display_name || x.name || x.username || x.user?.display_name || '');
    return n.toLowerCase().includes(q);
  });

  const html = items.map(it => {
    const uid  = it.user_id ?? it.id;
    const name = (it.display_name || it.name || it.username || it.user?.display_name || ('User ' + (uid||'')));
    const ava  = (()=>{
      const v = it.avatar_path || it.avatar || it.user?.avatar_path || '';
      if (!v) return AVA_FALLBACK;
      if (/^https?:\/\//i.test(v)) return v;
      if (v.startsWith('//')) return location.protocol + v;
      if (v.startsWith('/'))  return (BASE || '') + v;
      return (BASE ? BASE + '/' : '/') + v.replace(/^\.?\//,'');
    })();
    const preview = (it.attachment_type==='video') ? '🎥 Video'
                  : (it.attachment_type==='image') ? '🖼️ Bild'
                  : (it.last_body?.startsWith?.('{"type":"attach"')) ? '📎 Anhang'
                  : (it.last_body || '').slice(0, 60);

const unread = Number(it.unread_count ?? it.unread ?? it.unseen ?? 0);
const badge  = unread > 0 ? `<span class="hhc-badge">${unread>99?'99+':unread}</span>` : '';


    return `
      <button type="button"
              data-open-chat="${uid}"
              data-name="${escapeHtml(name)}"
              data-avatar="${escapeHtml(ava)}"
              class="hhc-row">
        <img class="hhc-ava" src="${escapeHtml(ava)}" alt="${escapeHtml(name)}">
        <div class="hhc-main">
          <div class="hhc-top">
            <span class="hhc-name">${escapeHtml(name)}</span>
            ${badge}
          </div>
          <div class="hhc-sub">${escapeHtml(preview)}</div>
        </div>
      </button>`;
  }).join('');

  listBox.innerHTML = html || '<div class="text-w-neutral-4 text-sm p-2">Keine Einträge</div>';
}
function escapeHtml(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }


  loadRecent();
  setInterval(loadRecent, 30000);
})();
</script>
<?php endif; ?>
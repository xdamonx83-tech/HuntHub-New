<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth/guards.php';

$me = function_exists('optional_auth') ? optional_auth() : current_user();

$displayName = $me['display_name'] ?? 'Gast';
$avatar      = !empty($me['avatar_path']) ? $me['avatar_path'] : '/assets/images/avatars/placeholder.png';

?>















<!-- Mobile Bottom Bar: nur auf Handy sichtbar -->
<!-- Mobile Bottom Bar + Profil-Fullscreen -->


<!--
  Hunthub Mobile Bottom Bar – v7 (data-open-chat bridge)
  ✔ Namespaced (hhbb-*) – keine Klassennamen-Konflikte
  ✔ Nutzt BASE wie auf Desktop, API-Pfade relativ zu BASE
  ✔ Klick auf Kontakt: simuliert Click auf vorhandenes `[data-open-chat="UID"]` (wie Desktop)
     → fällt zurück auf window.openChat / Events
  Einbau: diesen Block direkt vor </body> einfügen. Für Live kannst du die Desktop-Sichtbarkeit
  via @media unten wieder abschalten.
-->

<script>
// BASE wie in deiner Desktop-Version
const HHBB_BASE = '<?= rtrim($APP_BASE ?? "", "/") ?>';
const CSRF = "<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES, 'UTF-8') ?>";
</script>

<style>
  :root { --hhbb-h: 64px; }
  .hhbb-wrap{position:fixed;left:0;right:0;bottom:0;z-index:999}
  .hhbb-bar{height:var(--hhbb-h);padding-bottom:env(safe-area-inset-bottom);display:flex;gap:10px;align-items:center;justify-content:space-around;background:linear-gradient(180deg,rgba(12,12,12,.65) 0%,rgba(5,5,5,.95) 100%);border-top:1px solid rgba(255,255,255,.08);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
  .hhbb-btn{position:relative;appearance:none;border:0;background:transparent;color:#ededed;display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 12px;border-radius:14px;font-size:12px}
  .hhbb-icon{font-size:22px;opacity:.95}
  .hhbb-label{opacity:.85;font-weight:700;letter-spacing:.2px}
  .hhbb-badge{position:absolute;top:4px;right:6px;min-width:18px;height:18px;padding:0 5px;border-radius:10px;background:#d12;color:#fff;font-size:11px;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 2px rgba(0,0,0,.7)}
  .hhbb-badge.is-hidden{display:none}
  /* Für Live auskommentieren, damit am Desktop ausgeblendet wird
  @media(min-width:1024px){.hhbb-wrap{display:none}}
  */

  .hhbb-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .18s ease;z-index:999999980}
  .hhbb-overlay.hhbb-open{opacity:1;pointer-events:auto}
  .hhbb-sheet{position:fixed;left:0;right:0;bottom:0;transform:translateY(110%);transition:transform .22s ease;z-index:999999995}
  .hhbb-sheet.hhbb-open{transform:translateY(0)}
  .hhbb-panel{background:linear-gradient(180deg,#0c0c0c 0%,#0a0a0a 100%);color:#f5f5f5;border-top:1px solid rgba(255,255,255,.08);border-radius:16px 16px 0 0;padding:10px 12px 12px;max-height:min(72vh,560px);box-shadow:0 -10px 30px rgba(0,0,0,.4)}
  .hhbb-hd{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 4px 8px}
  .hhbb-hd h3{margin:0;font-size:16px;letter-spacing:.4px;text-transform:uppercase;opacity:.95}
  .hhbb-close{appearance:none;border:0;background:rgba(255,255,255,.06);color:#eee;padding:8px 10px;border-radius:12px;font-weight:600}
  .hhbb-body{overflow:auto;max-height:calc(min(72vh,560px) - 42px);padding:6px 2px 12px}

  .hhbb-list{display:flex;flex-direction:column;gap:8px}
  .hhbb-li{display:flex;align-items:center;gap:12px;padding:10px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.05);cursor:pointer}
  .hhbb-ava{width:44px;height:44px;border-radius:50%;object-fit:cover;background:#222;border:1px solid rgba(255,255,255,.08)}
  .hhbb-main{flex:1;min-width:0}
  .hhbb-title{font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .hhbb-sub{color:#c9c9c9;font-size:12px;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .hhbb-actions{display:flex;gap:8px}
  .hhbb-ghost{appearance:none;border:1px solid rgba(255,255,255,.14);background:transparent;color:#f0f0f0;padding:8px 10px;border-radius:10px;font-size:13px;font-weight:700}
  .hhbb-ghost.is-danger{border-color:rgba(220,60,60,.5);color:#ffdddd}
  .hhbb-ghost.is-primary{border-color:rgba(120,180,255,.6);color:#e9f2ff}
  .hhbb-unread{width:10px;height:10px;border-radius:50%;background:#3fa65a;box-shadow:0 0 0 2px rgba(0,0,0,.6)}
  .hhbb-empty{opacity:.7;text-align:center;padding:18px 6px}
  .ps-social { margin-top: 12px; }
.ps-social-sep { display:flex; align-items:center; gap:10px; margin:8px 0 10px; color:#bdbdbd; font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
.ps-social-sep::before, .ps-social-sep::after { content:""; flex:1; height:1px; background:rgba(255,255,255,.1); }
.ps-social-grid { display:grid; grid-template-columns:1fr; gap:8px; }
.ps-oauth { display:flex; align-items:center; justify-content:center; gap:8px; padding:10px 12px; border-radius:10px; font-weight:700; border:1px solid transparent; text-decoration:none; }
.ps-oauth i { font-size:18px; line-height:1; }

.ps-google   { background:#fff; color:#111; border-color:#e5e5e5; }
.ps-google:hover { filter:brightness(.98); }

.ps-facebook { background:#1877f2; color:#fff; }
.ps-facebook:hover { filter:brightness(1.05); }

.ps-twitch   { background:#9146ff; color:#fff; }
.ps-twitch:hover { filter:brightness(1.05); }

/* optional: auf breiteren Phones 2-spaltig */
@media (min-width: 420px){
  .ps-social-grid { grid-template-columns: 1fr 1fr; }
  .ps-social-grid .ps-google { grid-column: span 2; }
}

</style>

<!-- Overlay -->
<div id="hhbb-overlay" class="hhbb-overlay" data-hhbb-close="overlay"></div>

<!-- Sheets -->
<div id="hhbb-requests" class="hhbb-sheet" aria-hidden="true">
  <div class="hhbb-panel">
    <div class="hhbb-hd"><h3><?php echo $L['friendsadd']; ?></h3><button class="hhbb-close" data-hhbb-close="hhbb-requests"><?php echo $L['close']; ?></button></div>
    <div id="hhbb-requests-list" class="hhbb-body"><div class="hhbb-empty">Lade Anfragen…</div></div>
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
<div id="hhbb-messages" class="hhbb-sheet" aria-hidden="true">
  <div class="hhbb-panel">
    <div class="hhbb-hd"><h3><?php echo $L['messages']; ?></h3><button class="hhbb-close" data-hhbb-close="hhbb-messages"><?php echo $L['close']; ?></button></div>
    <div id="hhbb-recent-list" class="hhbb-body"><div class="hhbb-empty">Lade Chats…</div></div>
  </div>
</div>

<div id="hhbb-profile" class="hhbb-sheet" aria-hidden="true">
  <div class="hhbb-panel">
    <div class="hhbb-hd"><h3><?php echo $L['profile']; ?></h3><button class="hhbb-close" data-hhbb-close="hhbb-profile"><?php echo $L['close']; ?></button></div>
    <div  class="hhbb-body"><div class="hhbb-empty">      <?php if ($me): ?>
        <div class="ps-user">
          <img class="ps-ava" src="<?= htmlspecialchars($meAvatar) ?>" alt="">
          <div>
            <div class="ps-name"><?= htmlspecialchars($me['display_name'] ?? 'Profil') ?></div>
            <a class="ps-link" href="/user.php?id=<?= (int)$me['id'] ?>"><?php echo $L['watch_profile']; ?></a>
          </div>
        </div>

        <div class="ps-actions">
          <a class="ps-btn" href="/user.php?id=<?= (int)$me['id'] ?>">
		  <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg><span><?php echo $L['profile']; ?></span></a>
          <a class="ps-btn" href="/profile.php">
		  <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-settings"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg><span><?php echo $L['edit_profile']; ?></span></a>
		  <a class="ps-btn" href="/wallet.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.5 7.64355L10.5 7.64355" class="icon_main" stroke-width="1" stroke-linecap="round"></path><path d="M1.38539 13.7948L2.11329 13.6141L1.38539 13.7948ZM1.38539 7.4923L2.11329 7.67305L1.38539 7.4923ZM19.6146 7.49231L20.3425 7.31156L19.6146 7.49231ZM19.6146 13.7948L18.8867 13.6141L19.6146 13.7948ZM13.6156 19.3025L13.4533 18.5703H13.4533L13.6156 19.3025ZM7.38443 19.3025L7.54668 18.5703L7.38443 19.3025ZM7.38443 1.9846L7.54668 2.71684L7.38443 1.9846ZM13.6156 1.9846L13.7778 1.25236V1.25236L13.6156 1.9846ZM6.93056 19.2019L6.7683 19.9342L6.93056 19.2019ZM14.0694 19.2019L14.2317 19.9342H14.2317L14.0694 19.2019ZM14.0694 2.08517L13.9072 2.81741V2.81741L14.0694 2.08517ZM6.93056 2.08517L6.7683 1.35294V1.35294L6.93056 2.08517ZM17.1933 13.1472L17.0588 13.885L17.0588 13.885L17.1933 13.1472ZM17.1347 13.1365L17.2693 12.3987L17.2693 12.3987L17.1347 13.1365ZM17.1347 8.15059L17.2693 8.88842L17.2693 8.88842L17.1347 8.15059ZM17.1933 8.1399L17.0588 7.40207L17.0588 7.40207L17.1933 8.1399ZM19.5 9.13085C19.8795 9.29685 20.3217 9.12378 20.4877 8.74429C20.6537 8.36479 20.4807 7.92258 20.1012 7.75658L19.5 9.13085ZM20.1012 13.5305C20.4807 13.3645 20.6537 12.9223 20.4877 12.5428C20.3217 12.1633 19.8795 11.9903 19.5 12.1563L20.1012 13.5305ZM15.0782 11.2718L14.357 11.4778L15.0782 11.2718ZM15.0782 10.0153L15.7993 10.2213L15.0782 10.0153ZM7.09281 2.81741L7.54668 2.71684L7.22218 1.25236L6.7683 1.35294L7.09281 2.81741ZM13.4533 2.71684L13.9072 2.81741L14.2317 1.35293L13.7778 1.25236L13.4533 2.71684ZM13.9072 18.4697L13.4533 18.5703L13.7778 20.0347L14.2317 19.9342L13.9072 18.4697ZM7.54668 18.5703L7.09281 18.4697L6.7683 19.9342L7.22218 20.0347L7.54668 18.5703ZM2.11329 13.6141C1.6289 11.6634 1.6289 9.62375 2.11329 7.67305L0.657498 7.31156C0.114168 9.49965 0.114167 11.7875 0.657498 13.9756L2.11329 13.6141ZM18.8867 7.67305C19.3711 9.62376 19.3711 11.6634 18.8867 13.6141L20.3425 13.9755C20.8858 11.7875 20.8858 9.49966 20.3425 7.31156L18.8867 7.67305ZM13.4533 18.5703C11.508 19.0013 9.49198 19.0013 7.54668 18.5703L7.22218 20.0347C9.38122 20.5132 11.6188 20.5132 13.7778 20.0347L13.4533 18.5703ZM7.54668 2.71684C9.49198 2.28579 11.508 2.28579 13.4533 2.71684L13.7778 1.25236C11.6188 0.773952 9.38122 0.773951 7.22218 1.25236L7.54668 2.71684ZM7.09281 18.4697C4.64627 17.9276 2.71736 16.0468 2.11329 13.6141L0.657498 13.9756C1.39874 16.9607 3.76576 19.2689 6.7683 19.9342L7.09281 18.4697ZM14.2317 19.9342C17.2342 19.2689 19.6013 16.9607 20.3425 13.9755L18.8867 13.6141C18.2826 16.0468 16.3537 17.9276 13.9072 18.4697L14.2317 19.9342ZM13.9072 2.81741C16.3537 3.35953 18.2826 5.24035 18.8867 7.67305L20.3425 7.31156C19.6013 4.32644 17.2342 2.01825 14.2317 1.35293L13.9072 2.81741ZM6.7683 1.35294C3.76576 2.01825 1.39874 4.32643 0.657498 7.31156L2.11329 7.67305C2.71736 5.24035 4.64627 3.35953 7.09281 2.81741L6.7683 1.35294ZM17.3279 12.4094L17.2693 12.3987L17.0002 13.8744L17.0588 13.885L17.3279 12.4094ZM17.2693 8.88842L17.3279 8.87774L17.0588 7.40207L17.0002 7.41275L17.2693 8.88842ZM17.3279 8.87774C18.061 8.74407 18.8173 8.8322 19.5 9.13085L20.1012 7.75658C19.1449 7.33828 18.0856 7.21485 17.0588 7.40207L17.3279 8.87774ZM17.0588 13.885C18.0856 14.0723 19.1449 13.9488 20.1012 13.5305L19.5 12.1563C18.8173 12.4549 18.061 12.543 17.3279 12.4094L17.0588 13.885ZM15.7993 11.0658C15.7205 10.7898 15.7205 10.4973 15.7993 10.2213L14.357 9.80934C14.2013 10.3546 14.2013 10.9325 14.357 11.4778L15.7993 11.0658ZM17.0002 7.41275C15.7349 7.64346 14.7103 8.57256 14.357 9.80934L15.7993 10.2213C15.9958 9.53335 16.5657 9.01671 17.2693 8.88842L17.0002 7.41275ZM17.2693 12.3987C16.5657 12.2704 15.9958 11.7538 15.7993 11.0658L14.357 11.4778C14.7103 12.7145 15.7349 13.6436 17.0002 13.8744L17.2693 12.3987Z" fill="currentColor"></path></svg><span>Punkteshop</span></a>
          <button class="ps-btn danger" id="logout">
		  <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-logout-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2" /><path d="M15 12h-12l3 -3" /><path d="M6 15l-3 -3" /></svg><span><?php echo $L['logout']; ?></span></button>
        </div>
      <?php else: ?>


  <div class="ps-tabs" role="tablist" aria-label="Anmeldung">
    <button type="button" class="ps-tab is-active" data-auth-tab="login" aria-selected="true"><?php echo $L['login']; ?></button>
    <button type="button" class="ps-tab" data-open-register aria-selected="false"><?php echo $L['register']; ?></button>
  </div>
  


  <form data-auth="login" class="ps-authform">
    <div class="ps-field">
      <label for="loginEmail"><?php echo $L['email']; ?></label>
      <input id="loginEmail" name="email" type="email" class="ps-input" required>
    </div>
    <div class="ps-field">
      <label for="loginPass"><?php echo $L['password']; ?></label>
      <input id="loginPass" type="password" name="password" class="ps-input" required>
    </div>
    <button class="ps-submit" type="submit">
      <i class="ti ti-login-2"></i><span><?php echo $L['login']; ?></span>
    </button>
   <div data-auth-msg class="text-xs mt-2"></div>
   <div class="ps-social">
  <div class="ps-social-sep"><span><?php echo $L['or']; ?></span></div>

  <div class="ps-social-grid">
    <a class="ps-oauth ps-google"
       href="<?= rtrim($APP_BASE ?? '', '/') ?>/api/auth/oauth_start.php?provider=google"
       data-i18n="lgoogle">
      <i class="ti ti-brand-google"></i> Google
    </a>

    <a class="ps-oauth ps-facebook"
       href="<?= rtrim($APP_BASE ?? '', '/') ?>/api/auth/oauth_start.php?provider=facebook"
       data-i18n="lfacebook">
      <i class="ti ti-brand-facebook"></i> Facebook
    </a>

    <a class="ps-oauth ps-twitch"
       href="<?= rtrim($APP_BASE ?? '', '/') ?>/api/auth/oauth_start.php?provider=twitch"
       data-i18n="ltwitch">
      <i class="ti ti-brand-twitch"></i> Twitch
    </a>
  </div>
</div>
  </form>
    <form data-auth="register" id="regForm" autocomplete="on" class="ps-authform" hidden>
    <div class="ps-field">
      <label for="regName"><?php echo $L['displayname']; ?></label>
      <input id="regName" name="display_name" class="ps-input" required>
    </div>
    <div class="ps-field">
      <label for="regEmail"><?php echo $L['email']; ?></label>
      <input id="regEmail" name="email" type="email" class="ps-input" required>
    </div>
    <div class="ps-field">
      <label for="regPass"><?php echo $L['password']; ?></label>
      <input id="regPass" name="password" type="password" class="ps-input" required>
    </div>
	  <div class="ps-field ps-consent">
    <label class="ps-checkbox">
      <input id="regConsent" name="privacy_consent" type="checkbox" required>
      <span>
        Ich habe die
        <a href="/privacy.php" target="_blank" rel="noopener">Datenschutzerklärung</a>
        gelesen und stimme der Verarbeitung meiner Daten zum Zweck der Registrierung zu.
      </span>
    </label>
    <!-- optional: Versionierung deiner Policy -->
    <input type="hidden" name="privacy_version" value="2025-08-29">
  </div>
    <button class="ps-submit" type="submit">
      <i class="ti ti-user-plus"></i><span><?php echo $L['register']; ?></span>
    </button>
   
	<div id="loginMsg" class="msg err"></div>
  </form>
        <?php endif; ?></div></div>
  </div>
</div>

<!-- Bottom Bar -->
<div class="hhbb-wrap" role="navigation" aria-label="Mobile bottom bar">
  <div class="hhbb-bar">
   <?php if (!empty($me['id'])): ?> <button id="hhbb-btn-requests" class="hhbb-btn" aria-haspopup="dialog" aria-controls="hhbb-requests"><span class="hhbb-icon"><svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user-screen"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.03 17.818a3 3 0 0 0 1.97 -2.818v-8a3 3 0 0 0 -3 -3h-12a3 3 0 0 0 -3 3v8c0 1.317 .85 2.436 2.03 2.84" /><path d="M10 14a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M8 21a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2" /></svg></span><span >Anfragen</span><span id="hhbb-badge-requests" class="hhbb-badge is-hidden">0</span></button>
   <button type="button" data-toggle-global-chat class="hhbb-btn">
<span class="hhbb-icon">
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9h8" /><path d="M8 13h6" /><path d="M12.01 18.594l-4.01 2.406v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v5.5" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>
</span>
<span>Chat</span>
</button>
	<button id="hhbb-btn-messages" class="hhbb-btn" aria-haspopup="dialog" aria-controls="hhbb-messages"><span class="hhbb-icon"><svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-message-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9h8" /><path d="M8 13h6" /><path d="M12.01 18.594l-4.01 2.406v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v5.5" /><path d="M16 19h6" /><path d="M19 16v6" /></svg></span><span >Nachrichten</span><span id="hhbb-badge-messages" class="hhbb-badge is-hidden">0</span></button><?php endif; ?>
    <?php if ($me): ?>
	<button id="hhbb-btn-profile"  class="hhbb-btn" aria-haspopup="dialog" aria-controls="hhbb-profile"><span class="hhbb-icon"><svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user-square-rounded"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 13a3 3 0 1 0 0 -6a3 3 0 0 0 0 6z" /><path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" /><path d="M6 20.05v-.05a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v.05" /></svg></span><span >Profil</span></button>
	<?php else: ?>
	<button id="hhbb-btn-profile"  class="hhbb-btn" aria-haspopup="dialog" aria-controls="hhbb-profile"><span class="hhbb-icon"><svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user-square-rounded"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 13a3 3 0 1 0 0 -6a3 3 0 0 0 0 6z" /><path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" /><path d="M6 20.05v-.05a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v.05" /></svg></span><span >Login</span></button>
	<?php endif; ?>
  </div>
</div>

<script>
(function(){
  const $  = (s,r=document)=>r.querySelector(s);
  const $$ = (s,r=document)=>Array.from(r.querySelectorAll(s));
  const overlay = $('#hhbb-overlay');
  const sheets = { requests: $('#hhbb-requests'), messages: $('#hhbb-messages'), profile: $('#hhbb-profile') };
  const badges = { requests: $('#hhbb-badge-requests'), messages: $('#hhbb-badge-messages') };

  // API‑Pfade relativ zu BASE
  const API = {
    recent:  (HHBB_BASE||'') + '/api/messages/recent_contacts.php?limit=10',
    pending: (HHBB_BASE||'') + '/api/friends/pending.php',
    respond: (HHBB_BASE||'') + '/api/friends/respond_request.php',
    me:      (HHBB_BASE||'') + '/api/auth/me.php',

  };
  const AVA_FALLBACK = (HHBB_BASE||'') + '/assets/images/avatars/placeholder.png';

  function openSheet(key){ const el=sheets[key]; if(!el) return; overlay.classList.add('hhbb-open'); el.classList.add('hhbb-open'); el.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
  function closeSheet(key){ const el=sheets[key]; if(!el) return; el.classList.remove('hhbb-open'); el.setAttribute('aria-hidden','true'); if(!Object.values(sheets).some(e=>e.classList.contains('hhbb-open'))){ overlay.classList.remove('hhbb-open'); document.body.style.overflow=''; } }
  function closeAll(){ Object.keys(sheets).forEach(closeSheet); }

  overlay?.addEventListener('click', closeAll);
  $$('[data-hhbb-close]').forEach(b=> b.addEventListener('click', ()=> closeAll()));
  $('#hhbb-btn-requests')?.addEventListener('click', ()=>{ openSheet('requests'); renderFriendRequests(); });
  $('#hhbb-btn-messages')?.addEventListener('click', ()=>{ openSheet('messages'); renderRecentContacts(); });
  $('#hhbb-btn-profile') ?.addEventListener('click', ()=>{ openSheet('profile');  renderProfileBox(); });

  async function j(url,opt){ const res=await fetch(url,Object.assign({credentials:'include'},opt||{})); if(!res.ok) throw new Error('HTTP '+res.status); return res.json(); }
  function ava(p){ return p && p.length ? normalizeUrl(p) : AVA_FALLBACK; }
  function setBadge(el,n){ if(!el) return; if(n>0){ el.textContent=n>99?'99+':String(n); el.classList.remove('is-hidden'); } else { el.classList.add('is-hidden'); } }
  function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
  function normalizeUrl(u){
    if (!u) return AVA_FALLBACK;
    if (/^https?:\/\//i.test(u)) return u;
    if (u.startsWith('//')) return location.protocol + u;
    if (u.startsWith('/')) return (HHBB_BASE || '') + u;
    return (HHBB_BASE ? HHBB_BASE + '/' : '/') + u.replace(/^\.?\//,'');
  }

  // --- Friend Requests ---
async function renderFriendRequests(){
const box = document.getElementById('hhbb-requests-list');
box.innerHTML = '<div class="hhbb-empty">Lade Anfragen…</div>';
try {
const d = await j(API.pending);
// Desktop liefert d.incoming; ältere APIs d.items / d.pending
const items = d.incoming || d.items || d.pending || [];


setBadge(badges.requests, items.length);
if (!items.length){
box.innerHTML = '<div class="hhbb-empty">Keine offenen Anfragen.</div>';
return;
}


const list = document.createElement('div');
list.className = 'hhbb-list';


items.forEach(it => {
const reqId = it.request_id ?? it.id ?? it.req_id ?? null; // wie im Header
const name = esc(it.display_name || it.name || 'User');
const avatar = ava(it.avatar_path || it.avatar);


list.insertAdjacentHTML('beforeend', `
<div class="hhbb-li">
<img class="hhbb-ava" src="${avatar}">
<div class="hhbb-main">
<div class="hhbb-title">${name}</div>
<div class="hhbb-sub">möchte befreundet sein</div>
</div>
<div class="hhbb-actions">
<button class="hhbb-ghost is-primary" data-act="accept" data-req="${reqId ?? ''}">Annehmen</button>
<button class="hhbb-ghost is-danger" data-act="decline" data-req="${reqId ?? ''}">Ablehnen</button>
</div>
</div>`);
});


box.innerHTML = '';
box.appendChild(list);


// Delegierter Click‑Handler
list.addEventListener('click', async (e) => {
const btn = e.target.closest('button[data-req]');
if (!btn) return;
btn.disabled = true;
try {
const body = new URLSearchParams();
if (CSRF) body.set('csrf', CSRF); // wie im Header
body.set('request_id', String(btn.dataset.req));
body.set('action', btn.dataset.act); // accept | decline


const res = await fetch(API.respond, {
method: 'POST',
credentials: 'include',
headers: { 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
body
});
const out = await res.json().catch(() => ({}));
if (!res.ok || out.ok === false) throw new Error(out.error || 'Fehler');
renderFriendRequests(); // Liste neu
} catch (err) {
alert('Aktion fehlgeschlagen');
btn.disabled = false;
}
});
} catch (e) {
console.warn('[HHBB] pending.php', e);
box.innerHTML = '<div class="hhbb-empty">Fehler beim Laden.</div>';
}
}
async function decideFR(requestId, userId, ok){
// Desktop‑API erwartet: request_id + action (accept|decline) + csrf
const body = new URLSearchParams();
if (requestId) body.set('request_id', String(requestId));
// Fallback: einige Backends akzeptieren user_id zusätzlich
if (userId) body.set('user_id', String(userId));
body.set('action', ok ? 'accept' : 'decline');
if (CSRF) body.set('csrf', CSRF);


try{
const res = await fetch(API.respond, {
method: 'POST',
credentials: 'include',
headers: {
'Accept': 'application/json',
'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
},
body
});
const out = await res.json().catch(()=>({}));
if (!res.ok || out.ok === false){
throw new Error(out.error || 'Antwort fehlgeschlagen');
}
// Liste + Badge neu laden
renderFriendRequests();
} catch (e){
alert('Aktion fehlgeschlagen');
console.warn('[HHBB] respond_request', e);
}
}

  // --- Recent Contacts (Messages) ---
  async function renderRecentContacts(){
    const box=$('#hhbb-recent-list'); box.innerHTML='<div class="hhbb-empty">Lade Chats…</div>';
    try{ const d=await j(API.recent); const items=d.items||[]; const totalUnread=items.reduce((a,b)=>a+(b.unread||0),0); setBadge(badges.messages,totalUnread);
      if(!items.length){ box.innerHTML='<div class="hhbb-empty">Noch keine Chats.</div>'; return; }
      const list=document.createElement('div'); list.className='hhbb-list';
      items.forEach(m=>{ const li=document.createElement('div'); li.className='hhbb-li';
        li.innerHTML=`<img class="hhbb-ava" src="${ava(m.avatar_path)}"><div class="hhbb-main"><div class="hhbb-title">${esc(m.display_name||'User')}</div><div class="hhbb-sub">${esc(m.last_text||'')}</div></div>${m.unread>0?'<span class="hhbb-unread"></span>':''}`;
        li.addEventListener('click',()=> openChatOrProfile(m.user_id));
        list.appendChild(li); });
      box.innerHTML=''; box.appendChild(list);
    }catch(e){ box.innerHTML='<div class="hhbb-empty">Fehler beim Laden.</div>'; }
  }

  // --- Chat-Öffnen wie Desktop: bevorzugt existierendes [data-open-chat="UID"] anklicken
  function openChatOrProfile(uid){
    let opened = false;
    try {
      // 1) wenn irgendwo im DOM (z. B. Sidebar) ein data-open-chat vorhanden ist → click simulieren
      const target = document.querySelector(`[data-open-chat="${uid}"]`);
      if (target) {
        console.debug('[HHBB] trigger click on existing [data-open-chat]', uid);
        target.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        opened = true;
      }
      // 2) Fallback: globale Funktionen
      if (!opened && typeof window.openChat === 'function') { console.debug('[HHBB] window.openChat(',uid,')'); window.openChat(uid); opened = true; }
      if (!opened && window.Chat && typeof window.Chat.open === 'function') { console.debug('[HHBB] window.Chat.open(',uid,')'); window.Chat.open(uid); opened = true; }
      if (!opened && window.HHChat && typeof window.HHChat.open === 'function') { console.debug('[HHBB] HHChat.open(',uid,')'); window.HHChat.open(uid); opened = true; }
      if (!opened && window.App && typeof window.App.openChat === 'function') { console.debug('[HHBB] App.openChat(',uid,')'); window.App.openChat(uid); opened = true; }
      // 3) Events signalisieren (falls deine App darauf hört)
      if (!opened) {
        try { window.dispatchEvent(new CustomEvent('hunthub:openChat', { detail: { userId: uid } })); } catch(_){ }
        try { document.dispatchEvent(new CustomEvent('hunthub:openChat', { detail: { userId: uid } })); } catch(_){ }
        try { window.postMessage({ type: 'hunthub:openChat', userId: uid }, '*'); } catch(_){ }
        try { localStorage.setItem('hhbb:openChat', String(uid)); } catch(_){ }
        opened = true; // assume handled
      }
    } catch(err){ console.warn('[HHBB] openChatOrProfile error', err); }
    if (opened) { closeAll(); }
  }

  // --- Profil Sheet ---


  // --- Badges zyklisch aktualisieren ---
async function tick(){
try{
const d = await j(API.recent);
setBadge(
badges.messages,
(d.items || []).reduce((a,b)=> a + (b.unread || b.unread_count || 0), 0)
);
}catch(_){}


try{
const d = await j(API.pending);
const items = d.incoming || d.items || d.pending || [];
setBadge(badges.requests, items.length);
}catch(_){}
}
  setInterval(tick,30000); document.addEventListener('visibilitychange',()=>{ if(document.visibilityState==='visible') tick(); }); tick();
})();
</script>
<script>
(function(){
const MOBILE = () => matchMedia('(max-width: 900px)').matches;


function setFS(win, on){
if (!win) return;
if (on){
document.body.classList.add('hhbb-chat-fullscreen');
win.classList.add('hh-fs');
} else {
document.body.classList.remove('hhbb-chat-fullscreen');
win.classList.remove('hh-fs');
}
}


function findWin(){
const tabs = document.getElementById('chatTabs');
const inTabs = tabs?.querySelectorAll('.hh-window');
if (inTabs && inTabs.length) return inTabs[inTabs.length-1];
const all = document.querySelectorAll('.hh-window');
return all.length ? all[all.length-1] : null;
}


function maximizeSoon(){
if (!MOBILE()) return;
let tries = 0;
const t = setInterval(()=>{
tries++;
const w = findWin();
if (w){ setFS(w, true); watch(w); clearInterval(t); }
if (tries>20) clearInterval(t);
}, 120);
}


function watch(win){
// click on close/minimize buttons
win.addEventListener('click', e=>{
const t = e.target.closest('.hh-btn, [data-close], .btn-close');
if (!t) return;
const title = (t.getAttribute('title')||'').toLowerCase();
if (t.matches('[data-close], .btn-close') || title.includes('schließ') || title.includes('close')){
setTimeout(()=> setFS(win,false), 200);
}
}, true);


// leave fullscreen when minimized or removed
const mo = new MutationObserver(()=>{
if (win.classList.contains('hh-min') || !document.body.contains(win)){
setFS(win,false); mo.disconnect();
}
});
mo.observe(win,{ attributes:true, attributeFilter:['class'], childList:true, subtree:true });


// if user rotates / resizes to desktop
addEventListener('resize', ()=>{ if (!MOBILE()) setFS(win,false); });
}


// Hooks: same signals your bottom bar v7 emits
window.addEventListener('hunthub:openChat', maximizeSoon);
window.addEventListener('message', e=>{ if (e.data?.type==='hunthub:openChat') maximizeSoon(); });
try { const v = localStorage.getItem('hhbb:openChat'); if (v){ maximizeSoon(); localStorage.removeItem('hhbb:openChat'); } } catch{}
// Also when user clicks any existing data-open-chat
document.addEventListener('click', e=>{ if (e.target.closest('[data-open-chat]')) setTimeout(maximizeSoon, 0); });
})();
</script>
<script>
async function tick(){
try {
const d = await j(API.recent);
setBadge(
badges.messages,
(d.items || []).reduce((a,b)=> a + (b.unread || b.unread_count || 0), 0)
);
} catch(_){}


try {
const d = await j(API.pending);
setBadge(badges.requests, (d.incoming || d.items || d.pending || []).length);
} catch(_){}
}
</script>
<script>
(() => {
  const SHEET = document.getElementById('hhbb-profile'); // <- richtiges Sheet
  if (!SHEET) return;

  async function postJSON(url, data) {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data)
    });
    const out = await res.json().catch(()=> ({}));
    if (!res.ok || out.ok === false) throw new Error(out?.error || `HTTP ${res.status}`);
    return out;
  }
  function hardReload(){ location.assign(location.pathname + location.search); }

  // LOGIN + REGISTER (delegiert – greift auch bei dynamischem Inhalt)
  SHEET.addEventListener('submit', async (e) => {
    const form = e.target.closest('form');
    if (!form) return;

    // login-form wird per data-auth="login" markiert; fallback auf id="loginForm"
    const isLogin = form.matches('[data-auth="login"], #loginForm');
    const isRegister = form.matches('[data-auth="register"], #regForm');

    if (!isLogin && !isRegister) return;

    e.preventDefault();
    const fd  = new FormData(form);
    const msg = form.querySelector('[data-auth-msg], #loginMsg');

    try {
      msg && (msg.textContent = 'Bitte warten …');

      if (isLogin) {
        const out = await postJSON('/api/auth/login.php', {
          email: String(fd.get('email') || '').trim(),
          password: fd.get('password')
        });
        msg && (msg.textContent = 'Eingeloggt.');
        out.redirect ? location.assign(out.redirect) : hardReload();
      } else {
        // Registrierung (optional; läuft, falls du sie nutzt)
        const out = await postJSON('/api/auth/register.php', {
          display_name: fd.get('display_name') || fd.get('username') || '',
          email: fd.get('email'),
          password: fd.get('password')
        });
        msg && (msg.textContent = 'Konto erstellt. Du wirst eingeloggt …');
        out.redirect ? location.assign(out.redirect) : hardReload();
      }
    } catch (err) {
      msg && (msg.textContent = err?.message || 'Aktion fehlgeschlagen.');
    }
  });

  // LOGOUT (delegiert – funktioniert für #logout ODER #hhbb-btn-logout)
  SHEET.addEventListener('click', async (e) => {
    const btn = e.target.closest('#logout, #hhbb-btn-logout, [data-logout]');
    if (!btn) return;
    e.preventDefault();
    try {
      await fetch('/api/auth/logout.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
    } finally {
      hardReload();
    }
  });
})();
(() => {
  if (window.__psAuthTabsInstalled) return;
  window.__psAuthTabsInstalled = true;

  function showAuth(mode) {
    const loginForm = document.querySelector('[data-auth="login"]');
    const regForm   = document.querySelector('[data-auth="register"]');

    const loginTab  = document.querySelector('[data-auth-tab="login"]');
    const regTab    = document.querySelector('[data-open-register]');

    const toLogin = mode !== 'register';

    if (loginForm) loginForm.hidden = !toLogin;
    if (regForm)   regForm.hidden   =  toLogin;

    if (loginTab) {
      loginTab.classList.toggle('is-active', toLogin);
      loginTab.setAttribute('aria-selected', String(toLogin));
    }
    if (regTab) {
      regTab.classList.toggle('is-active', !toLogin);
      regTab.setAttribute('aria-selected', String(!toLogin));
    }

    // Fokus setzen
    const targetForm = toLogin ? loginForm : regForm;
    if (targetForm) {
      const focusEl =
        targetForm.querySelector('input[autofocus], input, select, textarea, button');
      if (focusEl) setTimeout(() => focusEl.focus(), 0);
    }
  }

  // Delegation: trifft auch dynamisch gerenderte Buttons
  document.addEventListener('click', (ev) => {
    const regBtn   = ev.target.closest('[data-open-register]');
    const loginBtn = ev.target.closest('[data-auth-tab="login"], [data-open-login]');
    if (!regBtn && !loginBtn) return;

    ev.preventDefault();
    showAuth(regBtn ? 'register' : 'login');
  });

  // Optional: globales API, falls du von anderswo öffnen willst
  window.hhAuth = window.hhAuth || {};
  window.hhAuth.open = (mode = 'login') => showAuth(mode);
})();
</script>

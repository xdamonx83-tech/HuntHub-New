async function renderProfileBox(){
  const box=$('#profile-box'); box.innerHTML='<div class="empty">Lade Profil…</div>';
  const BASE = (window.HHBB_BASE||'');
  const esc = s => (s||'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));
  const ava = (p)=>{ const f = BASE + '/assets/images/avatars/placeholder.png';
                     if(!p) return f;
                     if(/^https?:\/\//i.test(p)) return p;
                     if(p.startsWith('//')) return location.protocol + p;
                     if(p.startsWith('/')) return BASE + p;
                     return (BASE?BASE+'/':'/') + p.replace(/^\.?\//,''); };

  try{
    let u = (window.HH_ME && window.HH_ME.id) ? window.HH_ME : null;
    if (!u) {
      const raw = await j(BASE + '/api/auth/me.php');
      u = (raw && (raw.user || raw.me || raw?.data?.user || raw?.data?.me || raw.profile || raw)) || {};
    }
    const uid = u.id ?? u.user_id ?? u.uid ?? null;
    if(!uid){ box.innerHTML='<div class="empty">Nicht eingeloggt.</div>'; return; }

    box.innerHTML=`<div class="li" style="align-items:flex-start"><img class="ava" src="${ava(u.avatar_path||u.avatar)}"><div class="li-main"><div class="li-title" style="font-size:16px">${esc(u.display_name||u.email||('User '+uid))}</div><div class="li-sub">${esc(u.email||'')}</div></div></div><div class="list" style="margin-top:10px"><a class="li" href="${BASE}/profile.php">Mein Profil</a><a class="li" href="${BASE}/cms/public/admin.php">Einstellungen</a><button class="li" id="btn-logout">Logout</button></div>`;
    $('#btn-logout')?.addEventListener('click',async()=>{ try{ await j(BASE + '/api/auth/logout.php'); location.reload(); }catch(_){ } });
  }catch(e){
    console.error('[MBB] me.php',e); box.innerHTML='<div class="empty">Fehler beim Laden.</div>';
  }
}
// Mobile-Bar: Login/Register Trigger (defensiv & idempotent)
(function () {
  if (window.__mbbAuthHookInstalled) return;
  window.__mbbAuthHookInstalled = true;

  const APP_BASE =
    window.APP_BASE ||
    document.querySelector('meta[name="app-base"]')?.content ||
    '';

  document.addEventListener('click', (ev) => {
    const regBtn = ev.target.closest('[data-open-register]');
    const logBtn = ev.target.closest('[data-open-login]');

    if (!regBtn && !logBtn) return;

    ev.preventDefault();

    const mode = regBtn ? 'register' : 'login';

    // 1) Custom Event (für Module wie auth-forms.js)
    try {
      window.dispatchEvent(
        new CustomEvent('hh:open-auth', { detail: { mode } })
      );
    } catch (e) {}

    // 2) Direkter Call, wenn globales API existiert
    try {
      if (window.hhAuth && typeof window.hhAuth.open === 'function') {
        window.hhAuth.open(mode); // erwartet 'login' | 'register'
        return;
      }
      if (typeof window.openAuthPopup === 'function') {
        window.openAuthPopup(mode);
        return;
      }
      if (typeof window.openAuthSheet === 'function') {
        window.openAuthSheet(mode);
        return;
      }
    } catch (e) {
      // ignore and fall back
    }

    // 3) Fallback: Seite aufrufen
    const target = mode === 'register' ? '/register.php' : '/theme/login.php';
    window.location.href = APP_BASE + target;
  });
})();

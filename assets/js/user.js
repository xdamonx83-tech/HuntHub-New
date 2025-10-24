 
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


(function(){
    const btn = document.getElementById('friendAction');
    if (!btn) return;

    const BASE = "<?= $APP_BASE ?>";              // "" im Root
    const API  = (p) => `${BASE}/api/friends/${p}`;

    // CSRF bei jedem Request frisch holen
    function getCSRF(){
        return btn.dataset.csrf || (document.querySelector('meta[name="csrf"]')?.content || '');
    }

    const other = parseInt(btn.dataset.other || "0", 10);
    const me    = parseInt(btn.dataset.me    || "0", 10);

    if (!other) { btn.disabled = true; btn.textContent = 'Unbekannter Nutzer'; return; }
    if (!me)    { btn.disabled = true; btn.textContent = 'Bitte Einloggen';    return; }
    if (me === other) { btn.disabled = true; btn.textContent = 'Dein Profil';  return; }

    function label(status){
        switch(status){
            case 'friends':           return 'Freund entfernen';    // aktiv löscht
            case 'pending_outgoing':  return 'Anfrage gesendet';
            case 'pending_incoming':  return 'Anfrage annehmen?';
            default:                  return 'Freund hinzufügen';
        }
    }
    function stylize(status){
        btn.classList.remove('btn-primary','btn-neutral-2','btn-danger');
        if (status === 'friends')               btn.classList.add('btn-danger');    // rot
        else if (status === 'pending_outgoing') btn.classList.add('btn-neutral-2'); // grau
        else                                    btn.classList.add('btn-primary');   // blau
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
            throw new Error(`Kein JSON (HTTP ${r.status}). ${text.slice(0,180)}`);
        }
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || `HTTP ${r.status}`);
        return j;
    }
    async function getStatus(){
        const j = await api(API('status.php?user_id=' + other));
        return j.status || 'none';
    }

    // ---------- schönes, zentriertes Modal (ohne externes CSS) ----------
    function showModal({title='', message='', confirmText='OK', cancelText=null, danger=false}) {
        return new Promise(resolve=>{
            const z = 2147483647; // sicher über allem
            const wrap = document.createElement('div');
            wrap.setAttribute('role','dialog');
            wrap.setAttribute('aria-modal','true');
            wrap.style.cssText = `position:fixed;inset:0;z-index:${z};display:flex;align-items:center;justify-content:center`;
            wrap.innerHTML = `
                <div style="position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(2px)"></div>
                <div style="position:relative;width:min(92vw,520px);background:#1a1c1f;border:1px solid #2c2f33;border-radius:14px;box-shadow:0 20px 80px rgba(0,0,0,.55);padding:22px 22px;transform:translateY(6px) scale(.97);opacity:0;transition:.16s cubic-bezier(.2,.8,.2,1)">
                    <button aria-label="Schließen" style="position:absolute;top:10px;right:10px;background:transparent;border:0;color:#cbd0d6;font-size:18px;cursor:pointer">✕</button>
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
            const btnX   = panel.querySelector('button[aria-label="Schließen"]');

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
            // Overlay schließt nur, wenn "Abbrechen" existiert
            wrap.firstElementChild.addEventListener('click', ()=>{ if (btnCan) close(false); });
        });
    }
    const confirmDialog = (msg)=>showModal({title:'Bestätigen', message:msg, confirmText:'Ja', cancelText:'Abbrechen', danger:true});
    const errorDialog   = (msg)=>showModal({title:'Fehler',     message:msg, confirmText:'Schließen', danger:false});

    async function init(){
        try { await setStatus(await getStatus()); }
        catch(e){ console.error(e); btn.disabled = true; btn.textContent = 'Fehler'; }
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
                await setStatus(j.status); // -> pending_outgoing

            } else if (s === 'pending_incoming') {
                const pending = await api(API('pending.php'));
                const req = (pending.incoming || []).find(x => Number(x.id) === other);
                if (!req) { await errorDialog('Anfrage nicht gefunden.'); return; }
                const fd = new FormData();
                fd.append('csrf', getCSRF());
                fd.append('request_id', String(req.request_id));
                fd.append('action','accept');
                const j2 = await api(API('respond_request.php'), { method:'POST', body: fd });
                await setStatus(j2.status); // -> friends

            } else if (s === 'pending_outgoing') {
                // Optional: ausgehende Anfrage zurückziehen
                const ok = await confirmDialog('Eigene Freundschaftsanfrage zurückziehen?');
                if (!ok) return;
                const fd = new FormData();
                fd.append('csrf', getCSRF());
                fd.append('user_id', String(other));
                await api(API('cancel_request.php'), { method:'POST', body: fd });
                await setStatus('none');

            } else if (s === 'friends') {
                const ok = await confirmDialog('Freund wirklich entfernen?');
                if (!ok) return;
                const fd = new FormData();
                fd.append('csrf', getCSRF());
                fd.append('user_id', String(other));
                await api(API('unfriend.php'), { method:'POST', body: fd });
                await setStatus('none');
            }

        } catch (e) {
            console.error(e);
            await errorDialog('Aktion fehlgeschlagen: ' + e.message);
        } finally {
            btn.disabled = false;
        }
    });

    init();
})();
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
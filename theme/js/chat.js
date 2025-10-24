
(function(){
	const L = (window.L && typeof window.L === 'object') ? window.L : {};
  const meDock = document.getElementById('chatDock');
  const meId   = parseInt(meDock?.dataset.me || '0', 10);
  if (!meId) return; // nicht eingeloggt

  const BASE = document.querySelector('meta[name="app-base"]')?.content || '';
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const API   = (p)=> `${BASE}/api/messages/${p}`;

  const tabs = document.getElementById('chatTabs');
  const fab  = document.getElementById('chatOpenBtn');

  const open    = new Map();  // otherId -> state
  const pending = new Map();  // otherId -> [messages] (wenn Fenster noch nicht existiert)
  const cache   = new Map();  // otherId -> [messages] (lokaler Verlauf)  <<< NEU
  let globalLast = 0;

  // ---------------- Styles (inkl. Foot-Fix + Lightbox) ----------------
  (function injectStyle(){
    if (document.getElementById('hh-removed-style')) return;
    const css = `
      .hh-window{display:flex;flex-direction:column}
      .hh-body{flex:1 1 auto;overflow:auto}

      .hh-msg.hh-removed{
        background:#3b2326;border:1px dashed #f59e0b;color:#ffdede;
        font-style:italic;position:relative;padding-left:30px
      }
      .hh-msg.hh-removed::before{
        content:"⚠";position:absolute;left:10px;top:50%;
        transform:translateY(-50%);font-size:14px;opacity:.9
      }

      .hh-msg .hh-meta{margin-top:4px;font-size:12px;opacity:.65;line-height:1.2}
      .hh-msg.me .hh-meta{text-align:right}

      .hh-emoji-bar{display:flex;flex-wrap:wrap;gap:6px;padding:8px 10px;margin:8px 10px 0;background:#171b21;border:1px solid #2d333b;border-radius:10px}
      .hh-emoji-bar[hidden]{display:none}
      .hh-emoji-bar .hh-emo{all:unset;cursor:pointer;font-size:18px;line-height:1;padding:6px 8px;border-radius:8px}
      .hh-emoji-bar .hh-emo:hover{background:#1f2630}

      .hh-foot{display:flex;align-items:center;gap:8px;padding:10px;border-top:1px solid #2d333b;flex:0 0 auto;flex-wrap:nowrap}
      .hh-foot .hh-attach,.hh-foot .hh-emoji-toggle,.hh-foot .hh-send{flex:0 0 auto;white-space:nowrap}
      .hh-foot .hh-file{display:none}
      .hh-foot .hh-input{flex:1 1 auto;min-width:0;background:#0f1114;color:#e5e7eb;border:1px solid #2d333b;border-radius:10px;padding:8px 10px}
      .hh-foot .hh-emoji-toggle{all:unset;cursor:pointer;margin-right:6px;font-size:18px;line-height:1;padding:6px 8px;border:1px solid #2d333b;border-radius:10px;background:#121417;color:#e5e7eb}
      .hh-foot .hh-emoji-toggle:hover{background:#1a1f24}
      .hh-foot .hh-send{all:unset;background:#273444;color:#e5e7eb;border:1px solid #32475b;padding:6px 12px;border-radius:10px;cursor:pointer}

      .hh-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;padding:24px;z-index:2147483000}
      .hh-lightbox[hidden]{display:none}
      .hh-lightbox img{max-width:min(96vw,1600px);max-height:96vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.6)}
      .hh-lightbox .hh-lb-close{position:absolute;top:12px;right:12px;all:unset;cursor:pointer;background:#111;border:1px solid #2d333b;color:#e5e7eb;border-radius:10px;padding:6px 10px}
      .hh-lightbox .hh-lb-close:hover{background:#1a1f24}
    `;
    const style = document.createElement('style');
    style.id = 'hh-removed-style';
    style.textContent = css;
    document.head.appendChild(style);
  })();

  // ---------------- Lightbox ----------------
  let LB = null, LBimg = null, prevOverflowHtml = '', prevOverflowBody = '';
  function ensureLightbox(){
    if (LB) return LB;
    LB = document.createElement('div');
    LB.className = 'hh-lightbox';
    LB.hidden = true;
    LB.innerHTML = `<button class="hh-lb-close" type="button" aria-label="Schließen">✕</button><img class="hh-lb-img" alt="">`;
    document.body.appendChild(LB);
    LBimg = LB.querySelector('.hh-lb-img');
    const hide = ()=>{ LB.hidden = true; document.documentElement.style.overflow = prevOverflowHtml; document.body.style.overflow = prevOverflowBody; };
    LB.addEventListener('click', (e)=>{ if (e.target === LB) hide(); });
    LB.querySelector('.hh-lb-close').addEventListener('click', hide);
    document.addEventListener('keydown', (e)=>{ if (!LB.hidden && e.key === 'Escape') hide(); });
    return LB;
  }
  function openLightbox(src, alt){
    ensureLightbox();
    LBimg.src = src; LBimg.alt = alt || '';
    prevOverflowHtml = document.documentElement.style.overflow;
    prevOverflowBody = document.body.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    LB.hidden = false;
  }

  // ---------------- Persistenz (geöffnete Fenster) ----------------
  const PERSIST_KEY = 'hhOpenChats';
  function persistOpen() {
    try {
      const arr = [];
      for (const st of open.values()) {
        arr.push({ id: st.user.id, name: st.user.display_name || '', avatar: st.user.avatar_path || '', minimized: !!st.minimized });
      }
      sessionStorage.setItem(PERSIST_KEY, JSON.stringify(arr));
    } catch {}
  }
  async function restoreOpen() {
    try {
      const raw = sessionStorage.getItem(PERSIST_KEY);
      if (!raw) return;
      const list = JSON.parse(raw);
      if (!Array.isArray(list)) return;
      for (const it of list) {
        const st = createWindow({ id: it.id, display_name: it.name, avatar_path: it.avatar });
        if (it.minimized) {
          st.minimized = true;
          st.el.classList.add('hh-min');
          st.el.querySelector('.hh-foot').style.display = 'none';
          st.el.querySelector('.hh-body').style.display = 'none';
        }
      }
    } catch {}
  }

  function focusWindow(st) {
    const base = 1000; let maxZ = base;
    tabs.querySelectorAll('.hh-window').forEach(w => {
      const z = parseInt(getComputedStyle(w).zIndex || base, 10) || base;
      if (z > maxZ) maxZ = z;
    });
    st.el.style.zIndex = String(maxZ + 1);
  }

  const esc = (s)=> String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

  // Platzhalter-Erkennung
  const isCleanupPlaceholder = (t)=>{
    const s = String(t||'').trim();
    if (!s) return false;
    if (/^\[purged\]\s*/i.test(s)) return true;
    if (/^Anhang\s*\(Bild\/Video\)\s+automatisch\s+gel(?:ö|oe)scht\s*[–—-]\s*(?:ä|ae)lter\s+als\s+\d+\s*Tage\.?$/i.test(s)) return true;
    if (/^(?:gel(?:ö|oe)scht)\s+da\s+(?:ä|ae)lter\s+als\s+\d+\s*tage\.?$/i.test(s)) return true;
    return false;
  };

  async function refreshSeen(otherId){
    try{
      const r = await fetch(`${API('last_seen.php')}?user_id=${otherId}`, {headers:{'Accept':'application/json'}});
      const j = await r.json(); if (!j.ok) return;
      const last = Number(j.last_seen_id||0);
      const st = open.get(otherId); if (!st) return;
      st.list.querySelectorAll('.hh-msg.me').forEach(msg=>{
        const id = Number(msg.dataset.id||0);
        let badge = msg.querySelector('.hh-seen');
        if (!badge) { badge = document.createElement('span'); badge.className = 'hh-seen'; badge.textContent = '✓'; msg.appendChild(badge); }
        badge.classList.toggle('is-seen', id > 0 && id <= last);
      });
    }catch(e){}
  }

  async function apiGET(path){
    const r = await fetch(API(path), {credentials:'same-origin', headers:{'Accept':'application/json'}});
    const ct = r.headers.get('content-type') || ''; let j=null;
    try { j = ct.includes('application/json') ? await r.json() : null; } catch {}
    if (!j || !j.ok) { let msg = !j ? await r.text() : (j.error || 'error'); throw new Error(`GET ${path} -> HTTP ${r.status}: ${msg}`); }
    return j;
  }
  async function apiPOST(path, fd){
    const r = await fetch(API(path), {method:'POST', body: fd, credentials:'same-origin', headers:{'Accept':'application/json'}});
    const ct = r.headers.get('content-type')||''; let j=null;
    try { if (ct.includes('application/json')) j = await r.json(); } catch {}
    if (!j || !j.ok) { const text = (!j && r) ? await r.text() : (j && (j.error + (j.detail?': '+j.detail:''))); throw new Error(`POST ${path} -> HTTP ${r.status}: ${text?.slice?.(0,200) || 'error'}`); }
    return j;
  }

  // ---------------- Upload ----------------
  async function apiUpload(file, extra = {}) {
    const fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('file', file);
    if (extra.trim_start != null) fd.append('trim_start', String(extra.trim_start));
    if (extra.trim_end   != null) fd.append('trim_end',   String(extra.trim_end));
    const r = await fetch(API('upload.php'), { method:'POST', body: fd, credentials:'same-origin' });
    const j = await r.json().catch(()=>null);
    if (!r.ok || !j || !j.ok) { throw new Error(j?.error || `HTTP ${r.status}`); }
    return j;
  }
  function uploadWithProgress(formData, onUploadPercent) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', API('upload.php'), true);
      xhr.responseType = 'json'; xhr.setRequestHeader('Accept','application/json');
      xhr.upload.onprogress = (e) => { if (e.lengthComputable && typeof onUploadPercent === 'function') onUploadPercent(Math.max(0, Math.min(100, Math.round((e.loaded/e.total)*100)))); };
      xhr.onload = () => { const res = xhr.response; if (xhr.status >= 200 && xhr.status < 300 && res?.ok) resolve(res); else reject(new Error(res?.error || `HTTP ${xhr.status}`)); };
      xhr.onerror = () => reject(new Error('network')); xhr.send(formData);
    });
  }

  // ---------------- UI ----------------
  function createWindow(user){
    let st = open.get(user.id);
    if (st) { focusWindow(st); return st; }

    const el = document.createElement('div');
    el.className = 'hh-window';
el.innerHTML = `
  <div class="hh-head">
    <img src="${esc(user.avatar_path || (BASE + '/assets/images/avatars/placeholder.png'))}" 
         style="width:28px;height:28px;border-radius:8px;border:1px solid #3a3f46" alt="">
    <div class="hh-title">
      ${esc(user.display_name || ('User '+user.id))}
      <span class="chat-presence" id="chatPresence" data-peer-id="${user.id}">
        <span class="dot is-offline" aria-hidden="true"></span>
        <span class="last" id="chatPresenceLast">offline</span>
      </span>
    </div>
    <button class="hh-btn" data-min title="Minimieren">▾</button>
    <button class="hh-btn" data-close title="Schließen">✕</button>
  </div>
  <div class="hh-body"></div>

  <div class="hh-emoji-bar" hidden></div>

  <div class="hh-trim" aria-live="polite" hidden>
    <video class="hh-trim-video" controls playsinline></video>
    <div class="row"><small>Start</small><input class="hh-trim-start" type="range" min="0" max="0" step="0.1"><span class="hh-trim-start-lbl">0.0s</span></div>
    <div class="row"><small>Ende</small><input class="hh-trim-end" type="range" min="0" max="0" step="0.1"><span class="hh-trim-end-lbl">0.0s</span></div>
    <div class="trim-actions">
      <button class="btn-send-trim hh-send">${L.send_message_trimm ?? 'Zuschneiden & Senden'}</button>
      <div class="hh-progress" id="trimProgress" hidden>
        <div class="bar"></div>
        <span class="txt">Upload 0%</span>
      </div>
    </div>
  </div>

  <div class="hh-foot">
    <button class="hh-attach" title="Datei senden">📎</button>
    <button class="hh-emoji-toggle" title="Emoji">😊</button>
    <input class="hh-file" type="file" accept="image/*,video/*">
    <input class="hh-input" type="text" placeholder="${L.send_message ?? 'Nachricht schreiben…'}">
    <button class="hh-send" type="button">${L.send ?? 'Senden'}</button>
  </div>
`;

// --- Presence + Heartbeat nach dem Render starten ---
(function(){
  const APP_BASE = window.APP_BASE || '';
  const root = el.querySelector('#chatPresence');
  if (!root) return;
  const peerId = parseInt(root.getAttribute('data-peer-id')||'0',10);
  const dot = root.querySelector('.dot');
  const last = root.querySelector('#chatPresenceLast');

  async function fetchPresence(){
    const body = new URLSearchParams();
    body.append('ids[]', String(peerId));
    const res = await fetch(APP_BASE + '/api/messages/presence.php', {
      method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
    });
    if (!res.ok) return;
    const data = await res.json();
    const u = data?.users?.[peerId];
    const online = !!(u && u.online);
    dot.classList.toggle('is-online', online);
    dot.classList.toggle('is-offline', !online);
    last.textContent = online ? 'online' : (u?.last_seen ? 'zuletzt: ' + new Date(u.last_seen).toLocaleString() : 'offline');
  }

  // Heartbeat für eigenen Status
  setInterval(() => {
    fetch(APP_BASE + '/api/messages/heartbeat.php', { method:'POST' }).catch(()=>{});
  }, 30000);

  // Polling für Presence
  fetchPresence();
  setInterval(fetchPresence, 20000);
})();

    tabs.appendChild(el);

    const list   = el.querySelector('.hh-body');
    const input  = el.querySelector('.hh-input');
    const send   = el.querySelector('.hh-foot .hh-send');
    const attach = el.querySelector('.hh-attach');
    const fileIn = el.querySelector('.hh-file');

    // Emoji-Bar
    const emojiBar    = el.querySelector('.hh-emoji-bar');
    const emojiToggle = el.querySelector('.hh-emoji-toggle');
    const EMOJIS = ['😀','😁','😂','🤣','😊','😉','🙂','🙃','😍','😘','😜','😎','🤔','😢','😭','😡','👍','👎','👋','🙏','❤️','💔','🔥','💯','🎉','😅','😬','🤷'];
    if (emojiBar) {
      emojiBar.innerHTML = EMOJIS.map(e=>`<button type="button" class="hh-emo" aria-label="Emoji">${e}</button>`).join('');
      emojiBar.addEventListener('click', (ev)=>{
        const btn = ev.target.closest('.hh-emo'); if (!btn) return;
        insertAtCursor(input, btn.textContent || '');
      });
    }
    emojiToggle?.addEventListener('click', ()=>{ if (!emojiBar) return; emojiBar.hidden = !emojiBar.hidden; });

    // Trim-UI Refs
    const trimBox   = el.querySelector('.hh-trim');
    const trimVid   = el.querySelector('.hh-trim-video');
    const trimStart = el.querySelector('.hh-trim-start');
    const trimEnd   = el.querySelector('.hh-trim-end');
    const trimStartLbl = el.querySelector('.hh-trim-start-lbl');
    const trimEndLbl   = el.querySelector('.hh-trim-end-lbl');
    const trimCancel   = el.querySelector('.hh-trim-cancel');
    const trimSend     = el.querySelector('.btn-send-trim');
    const progressWrap = el.querySelector('.hh-progress');
    const progressBar  = progressWrap?.querySelector('.bar');
    const progressTxt  = progressWrap?.querySelector('.txt');
    const trimRowStart = trimStart?.closest('.row') || null;
    const trimRowEnd   = trimEnd?.closest('.row')   || null;

    el.querySelector('[data-close]').addEventListener('click', ()=>{ open.delete(user.id); el.remove(); persistOpen(); });
    el.querySelector('[data-min]').addEventListener('click', ()=>{
      st.minimized = !st.minimized;
      el.classList.toggle('hh-min', st.minimized);
      el.querySelector('.hh-foot').style.display = st.minimized ? 'none' : '';
      el.querySelector('.hh-body').style.display = st.minimized ? 'none' : '';
      if (trimBox) trimBox.style.display = st.minimized ? 'none' : '';
      persistOpen();
    });

    send.addEventListener('click', ()=> onSend(user.id, input));
    input.addEventListener('keydown', (e)=>{ if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onSend(user.id, input); } });

    attach.addEventListener('click', ()=> fileIn.click());
    fileIn.addEventListener('change', async ()=>{
      const f = fileIn.files?.[0];
      if (!f) return;
      if (f.type.startsWith('image/')) {
        try{
          const up = await apiUpload(f);
          const body = JSON.stringify({ type:'attach', kind:'image', url: up.url, meta: up.meta||{} });
          await onSendRaw(user.id, body);
        }catch(e){ console.error(e); toast(st, 'Bild-Upload fehlgeschlagen'); }
        fileIn.value = '';
      } else if (f.type.startsWith('video/')) {
        openTrimUI(f);
      } else { toast(st, 'Dateityp wird nicht unterstützt'); fileIn.value = ''; }
    });

    // Fokus
    el.addEventListener('mousedown', ()=> focusWindow(st));

    st = { el, list, input, lastId: 0, minimized:false, user };
    open.set(user.id, st);
    persistOpen();
    focusWindow(st);

    // ---- Reihenfolge beim Öffnen: Cache -> pending -> History ----
    const cached = cache.get(user.id);
    if (cached?.length) appendMessages(st, cached);
    const buf = pending.get(user.id);
    if (buf?.length) { appendMessages(st, buf); pending.delete(user.id); }
    loadHistory(user.id, 0); // Server-Verlauf (Dubletten werden ignoriert)

    return st;

    function openTrimUI(file){
      if (!trimBox) return;
      trimBox.hidden = false; trimBox.classList.add('open');
      if (progressWrap) progressWrap.hidden = true;
      if (progressBar)  progressBar.style.setProperty('--p','0%');
      if (progressTxt)  progressTxt.textContent = 'Upload 0%';
      if (trimSend)     trimSend.style.display = '';
      if (progressBar)  progressBar.style.display = '';
      if (trimRowStart) trimRowStart.style.display = '';
      if (trimRowEnd)   trimRowEnd.style.display   = '';

      const url = URL.createObjectURL(file);
      trimVid.src = url;
      trimVid.onloadedmetadata = ()=>{
        const dur = Math.max(0, trimVid.duration || 0);
        trimStart.max = String(dur.toFixed(1));
        trimEnd.max   = String(dur.toFixed(1));
        trimStart.value = '0';
        trimEnd.value   = String(dur.toFixed(1));
        trimStartLbl.textContent = '0.0s';
        trimEndLbl.textContent   = `${dur.toFixed(1)}s`;
      };
      const syncLabels = ()=>{
        const s = parseFloat(trimStart.value || '0');
        const e = parseFloat(trimEnd.value   || '0');
        trimStartLbl.textContent = `${s.toFixed(1)}s`;
        trimEndLbl.textContent   = `${e.toFixed(1)}s`;
        if (!isNaN(s)) trimVid.currentTime = s;
      };
      trimStart.oninput = syncLabels;
      trimEnd.oninput   = syncLabels;

      if (trimCancel) trimCancel.onclick = ()=>{
        trimVid.pause(); trimVid.removeAttribute('src'); trimVid.load();
        URL.revokeObjectURL(url); trimBox.classList.remove('open'); trimBox.hidden = true; fileIn.value = '';
      };

      if (trimSend) trimSend.onclick = async ()=>{
        const s = parseFloat(trimStart.value || '0');
        const e = parseFloat(trimEnd.value   || '0');
        if (!(e > s)) { toast(st,'Ende muss nach Start liegen'); return; }
        let sendingTicker = null, dots = 0, sendingShown = false;

        if (trimRowStart) trimRowStart.style.display = 'none';
        if (trimRowEnd)   trimRowEnd.style.display   = 'none';
        if (trimSend)     trimSend.style.display     = 'none';
        if (progressWrap) progressWrap.hidden        = false;
        if (progressBar)  { progressBar.style.display = ''; progressBar.style.setProperty('--p','0%'); }
        if (progressTxt)  progressTxt.textContent    = 'Upload 0%';

        try{
          const fd = new FormData();
          fd.append('csrf', CSRF); fd.append('file', file);
          fd.append('trim_start', String(s)); fd.append('trim_end', String(e));

          const up = await uploadWithProgress(fd, (p)=>{
            if (!progressBar || !progressTxt) return;
            if (p < 100) {
              progressBar.style.display = '';
              progressBar.style.setProperty('--p', `${p}%`);
              progressTxt.textContent = `Upload ${p}%`;
            } else {
              if (!sendingShown) {
                sendingShown = true; progressBar.style.display = 'none'; progressTxt.textContent = 'Wird gesendet';
                sendingTicker = setInterval(()=>{ dots = (dots + 1) % 4; progressTxt.textContent = 'Wird gesendet' + '.'.repeat(dots); }, 300);
              }
            }
          });

          const body = JSON.stringify({ type:'attach', kind:'video', url: up.url, meta: up.meta||{} });
          await onSendRaw(user.id, body);

          if (sendingTicker) { clearInterval(sendingTicker); sendingTicker = null; }
          if (typeof trimCancel?.onclick === 'function') trimCancel.onclick();
          else {
            trimVid.pause(); trimVid.removeAttribute('src'); trimVid.load();
            URL.revokeObjectURL(url); trimBox.classList.remove('open'); trimBox.hidden = true; fileIn.value = '';
          }
        }catch(err){
          console.error(err); toast(st,'Video-Upload fehlgeschlagen');
          if (trimRowStart) trimRowStart.style.display = '';
          if (trimRowEnd)   trimRowEnd.style.display   = '';
          if (trimSend)     trimSend.style.display     = '';
          if (progressBar)  { progressBar.style.display = ''; progressBar.style.setProperty('--p','0%'); }
          if (progressTxt)  progressTxt.textContent = 'Upload 0%';
        }finally{ if (sendingTicker) { clearInterval(sendingTicker); sendingTicker = null; } }
      };
    }

    function toast(st, text){
      const div = document.createElement('div');
      div.className = 'hh-msg them'; div.style.background = '#5b1f1f'; div.textContent = text;
      st.list.appendChild(div); st.list.scrollTop = st.list.scrollHeight + 500;
      setTimeout(()=>div.remove(), 4000);
    }
  }

  // ---------------- Attachment-Renderer ----------------
  function renderAttachment(container, data){
    const wrap = document.createElement('div');
    wrap.className = 'hh-asset';
    try{
      if (data.kind === 'image') {
        const img = document.createElement('img');
        img.src = data.url; img.alt = 'Bild'; img.style.cursor = 'zoom-in';
        img.addEventListener('click', ()=> openLightbox(data.url, 'Bild'));
        wrap.appendChild(img);
      } else if (data.kind === 'video') {
        const vid = document.createElement('video');
        vid.controls = true; vid.playsInline = true;
        const trySetPoster = (url)=>{ fetch(url, { method:'HEAD', cache:'no-store' })
          .then(r => { if (r.ok) vid.poster = url; else generatePosterFromVideo(vid); })
          .catch(()=> generatePosterFromVideo(vid)); };
        if (data.meta && data.meta.thumb) trySetPoster(data.meta.thumb); else generatePosterFromVideo(vid);
        const src = document.createElement('source'); src.src = data.url; src.type = 'video/mp4'; vid.appendChild(src);
        wrap.appendChild(vid);
      }
    }catch(e){ wrap.textContent = '[Anhang konnte nicht angezeigt werden]'; }
    container.appendChild(wrap);
  }

  // ---------------- Utils: Zeit/Emoji/Text ----------------
  function parseTs(v){
    if (v instanceof Date) return v;
    if (typeof v === 'number') return new Date(v < 1e12 ? v*1000 : v);
    if (v == null) return null;
    const s = String(v).trim(); const n = Number(s);
    if (!Number.isNaN(n) && s !== '') return new Date(n < 1e12 ? n*1000 : n);
    const d = new Date(s); return isNaN(d.getTime()) ? null : d;
  }
  function formatTs(v){
    const d = parseTs(v) || new Date();
    return { iso: d.toISOString(), label: d.toLocaleString('de-DE', {year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) };
  }
  function replaceEmoticons(s){
    if (!s) return s;
    s = s.replace(/:'-\(|:'\(/g, '😢'); s = s.replace(/:-D|:D/g, '😃'); s = s.replace(/xD|XD/g, '😆');
    s = s.replace(/;-\)|;\)/g, '😉');   s = s.replace(/:-P|:P|:-p|:p/g, '😛');
    s = s.replace(/:-\)|:\)/g, '🙂');   s = s.replace(/:-\(|:\(/g, '🙁');
    s = s.replace(/:-O|:O|:-o|:o/g, '😮'); s = s.replace(/:\|/g, '😐');
    s = s.replace(/(^|[\s])(:-?\/)(?=$|[\s.,!?;:])/g, '$1😕'); s = s.replace(/<3/g, '❤️'); s = s.replace(/\^\^/g, '😊');
    return s;
  }
  function insertAtCursor(inp, text){
    if (!inp) return;
    const start = inp.selectionStart ?? inp.value.length;
    const end   = inp.selectionEnd   ?? inp.value.length;
    const v = inp.value;
    inp.value = v.slice(0,start) + text + v.slice(end);
    const pos = start + text.length;
    try { inp.setSelectionRange(pos,pos); } catch {}
    inp.focus();
  }

  // ---------------- Cache-Helfer (NEU) ----------------
  function cacheAddMessages(otherId, msgs){
    if (!msgs || !msgs.length) return;
    const byId = new Map((cache.get(otherId) || []).map(m => [m.id, m]));
    for (const m of msgs) if (m && m.id != null) byId.set(m.id, m);
    const arr = Array.from(byId.values()).sort((a,b)=> (a.id||0) - (b.id||0)).slice(-200);
    cache.set(otherId, arr);
  }

  // ---------------- Rendering ----------------
  function appendMessages(st, msgs){
    if (!msgs || !msgs.length) return;

    // erst Cache aktualisieren
    cacheAddMessages(st.user.id, msgs);

    for (const m of msgs){
      if (m.id && st.list.querySelector(`[data-id="${m.id}"]`)) {
        st.lastId   = Math.max(st.lastId, m.id);
        globalLast  = Math.max(globalLast, m.id);
        continue;
      }

      const div = document.createElement('div');
      div.className = 'hh-msg ' + (m.sender_id === meId ? 'me' : 'them');
      div.dataset.id = m.id;

      let rendered = false;
      try {
        const data = JSON.parse(m.body);
        if (data && data.type === 'attach' && (data.kind === 'image' || data.kind === 'video') && data.url) {
          renderAttachment(div, data); rendered = true;
        }
      } catch {}

      if (!rendered) {
        const plain = String(m.body || '');
        if (isCleanupPlaceholder(plain)) {
          div.classList.add('hh-removed'); div.textContent = plain;
          div.setAttribute('aria-label','Automatisch gelöscht (älter als Aufbewahrungsfrist)');
        } else {
          const withEmoji = replaceEmoticons(plain);
          div.innerHTML = esc(withEmoji);
        }
      }

      const tsVal = m.created_at ?? m.createdAt ?? m.created ?? m.ts ?? m.timestamp ?? Date.now();
      const { iso, label } = formatTs(tsVal);
      const meta = document.createElement('div');
      meta.className = 'hh-meta';
      meta.innerHTML = `<time datetime="${iso}">${label}</time>`;
      div.appendChild(meta);

      st.list.appendChild(div);
      st.lastId   = Math.max(st.lastId, m.id);
      globalLast  = Math.max(globalLast, m.id);
    }
    st.list.scrollTop = st.list.scrollHeight + 1000;
    markRead(st.user.id, st.lastId);
  }

  async function loadHistory(otherId, sinceId=0){
    try{
      const st = open.get(otherId); if (!st) return;
      const j = await apiGET(`history.php?user_id=${otherId}&since_id=${sinceId}`);
      appendMessages(st, j.messages || []);
    }catch(e){
      console.warn('history first try failed', e);
      setTimeout(()=> {
        const st2 = open.get(otherId);
        if (st2 && st2.list && st2.el.isConnected) {
          loadHistory(otherId, 0);
        }
      }, 800);
    }
  }

  // ---------------- WebSocket ----------------
  let socket = null, wsReady = false, wsConnectTried = false;

  async function connectSocket(){
    if (wsConnectTried) return;
    wsConnectTried = true;
    try{
      const r = await fetch(`${BASE}/api/messages/ws_token.php`, {credentials:'same-origin'});
      const j = await r.json();
      if (!j.ok) throw new Error(j.error||'ws_token');

      socket = io(j.ws_url, { path: '/socket.io', transports: ['websocket'], withCredentials: true, auth: { token: j.token } });

      socket.on('connect', ()=>{
        wsReady = true;
        socket.emit('hello', { since_id: globalLast || 0 }, (ack)=>{
          if (ack?.ok && (ack.messages||[]).length){
            for (const m of ack.messages) handleIncoming(m);
            globalLast = ack.last_id || globalLast || 0;
          }
        });
      });

      socket.on('disconnect', ()=>{ wsReady = false; });
      socket.on('message:new', (m)=> handleIncoming(m));
    } catch(e){
      console.error('WS connect failed, fallback to poll', e);
      wsReady = false;
    }
  }

  function bufferMessage(m){
    const otherId = (m.sender_id === meId) ? m.recipient_id : m.sender_id;
    const arr = pending.get(otherId) || [];
    arr.push(m);
    pending.set(otherId, arr);
  }

  function handleIncoming(m){
    const otherId = (m.sender_id === meId) ? m.recipient_id : m.sender_id;
    const st = open.get(otherId);
    // Immer in den Cache legen, damit beim nächsten Öffnen sofort etwas da ist
    cacheAddMessages(otherId, [m]);                 // <<< NEU
    if (st) {
      appendMessages(st, [m]);
    } else {
      bufferMessage(m);
      if (m.recipient_id === meId) {
        const n = (parseInt(fab.dataset.unread || '0', 10) + 1);
        fab.dataset.unread = String(n);
        fab.innerHTML = `<i class="ti ti-message"></i><span class="hh-badge">${n}</span>`;
      }
    }
  }

  async function sendViaSocket(otherId, text){
    if (!socket || !wsReady) throw new Error('ws_not_ready');
    return new Promise((resolve, reject)=>{
      socket.emit('message:send', { to: otherId, body: text }, (ack)=>{
        if (ack?.ok) resolve(ack.message); else reject(new Error(ack?.error||'send_failed'));
      });
    });
  }
  function markReadViaSocket(otherId, upToId){
    if (!socket || !wsReady) return;
    socket.emit('message:read_upto', { user_id: otherId, up_to_id: upToId }, ()=>{});
  }
async function fetchList() {
  try {
    const res = await fetch(API_L + '?limit=20&ts=' + Date.now(), {
      credentials: 'include',
      headers: { 'Accept':'application/json', 'Cache-Control':'no-cache' },
      cache: 'no-store'
    });
    const out = await res.json();
    if (res.ok && out?.ok) {
      cache.items = Array.isArray(out.items) ? out.items : [];
      cache.ts = Date.now();
      render();
    } else {
      console.warn('notifications/list failed', res.status, out);
      LIST.innerHTML = '<div class="noti-empty">Fehler beim Laden.</div>';
    }
  } catch (e) {
    console.warn('notifications/list error', e);
    LIST.innerHTML = '<div class="noti-empty">Netzwerkfehler.</div>';
  }
}

  // ---------------- Senden/Lesen ----------------
  async function onSend(otherId, inputEl){
    const text = inputEl.value.trim(); if (!text) return;
    inputEl.value = '';
    try{
      const msg = await onSendRaw(otherId, text);
      // Sicherheit: auch gesendete Nachricht in Cache (falls DOM nicht offen)
      cacheAddMessages(otherId, [msg]);
    }catch(e){ console.error('send', e); inputEl.value = text; }
  }

  async function onSendRaw(otherId, bodyText){
    let msg;
    try { msg = await sendViaSocket(otherId, bodyText); }
    catch {
      const fd = new FormData();
      fd.append('csrf', CSRF); fd.append('user_id', String(otherId)); fd.append('body', bodyText);
      const j = await apiPOST('send.php', fd); msg = j.message;
    }
    const st = open.get(otherId); if (st) appendMessages(st, [msg]);
    return msg;
  }

  function generatePosterFromVideo(videoEl){
    const draw = ()=>{ try{
      const w = videoEl.videoWidth, h = videoEl.videoHeight; if (!w || !h) return;
      const canvas = document.createElement('canvas'); canvas.width = w; canvas.height = h;
      const ctx = canvas.getContext('2d', { willReadFrequently:true });
      ctx.drawImage(videoEl, 0, 0, w, h); videoEl.setAttribute('poster', canvas.toDataURL('image/jpeg', 0.8));
    }catch(e){} };
    const onSeeked = ()=>{ videoEl.removeEventListener('seeked', onSeeked); draw(); };
    const onLoadedMeta = ()=>{
      videoEl.removeEventListener('loadedmetadata', onLoadedMeta);
      const t = Math.min(0.1, (videoEl.duration || 1) / 10);
      videoEl.addEventListener('seeked', onSeeked, { once:true });
      try { videoEl.currentTime = t; } catch {}
    };
    videoEl.preload = 'metadata';
    if (videoEl.readyState >= 1) onLoadedMeta(); else videoEl.addEventListener('loadedmetadata', onLoadedMeta, { once:true });
  }

  function markRead(otherId, upToId){
    if (socket && wsReady) markReadViaSocket(otherId, upToId);
    else {
      const fd = new FormData(); fd.append('csrf', CSRF); fd.append('user_id', String(otherId)); fd.append('up_to_id', String(upToId));
      apiPOST('mark_read.php', fd).catch(()=>{});
    }
  }
(function(){
const APP_BASE = window.APP_BASE || '';


// Helper: einfache Fetch-Funktion für Presence
async function fetchPresence(peerId){
const body = new URLSearchParams();
body.append('ids[]', String(peerId));
const res = await fetch(APP_BASE + '/api/messages/presence.php', {
method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
});
if (!res.ok) throw new Error('presence http ' + res.status);
return res.json();
}


function formatLastSeenISO(iso){
if (!iso) return 'offline';
try {
const d = new Date(iso);
return 'zuletzt: ' + d.toLocaleString();
} catch(e){ return 'offline'; }
}


async function updatePresenceOnce(root){
const peerId = parseInt(root.getAttribute('data-peer-id') || '0', 10);
if (!peerId) return;


const dot = root.querySelector('.dot');
const last = root.querySelector('#chatPresenceLast');
if (!dot || !last) return;


try {
const data = await fetchPresence(peerId);
const u = data && data.users ? data.users[peerId] : null;
const online = !!(u && u.online);


dot.classList.toggle('is-online', online);
dot.classList.toggle('is-offline', !online);
last.textContent = online ? 'online' : formatLastSeenISO(u && u.last_seen);
} catch(e) {
// Fehler unkritisch – wir verstecken ihn im UI
}
}


// Heartbeat alle 30s – hält den EIGENEN Status aktuell
function startHeartbeat(){
const beat = () => fetch(APP_BASE + '/api/messages/heartbeat.php', { method:'POST' }).catch(()=>{});
beat();
return setInterval(beat, 30000);
}


// Presence-Polling für den aktuell geöffneten privaten Chat (20s)
function startPresencePolling(root){
const tick = () => updatePresenceOnce(root);
tick();
return setInterval(tick, 20000);
}


// Auto-Init, wenn ein Element #chatPresence vorhanden ist
document.addEventListener('DOMContentLoaded', () => {
const root = document.getElementById('chatPresence');
if (!root) return;
startHeartbeat();
startPresencePolling(root);
});


// Optional: Exporte, falls du den Chat dynamisch mountest/wechselst
window.HHPresence = {
updateOnce: updatePresenceOnce,
startHeartbeat,
startPresencePolling,
};
})();
  // ---------------- Fallback-Polling ----------------
  async function poll(){
    try{
      const j = await apiGET(`poll.php?since_id=${globalLast}`);
      for (const m of (j.messages||[])) handleIncoming(m);
      globalLast = j.last_id || globalLast;
    }catch(e){ /* still */ }
    setTimeout(poll, 3000);
  }

  // ---------------- Triggers ----------------
  document.addEventListener('click', (e)=>{
    const t = e.target.closest('[data-open-chat]'); if (!t) return;
    e.preventDefault();
    const uid = parseInt(t.dataset.openChat || '0', 10);
    if (!uid || uid === meId) return;
    const user = { id: uid, display_name: t.dataset.name || ('User '+uid), avatar_path: t.dataset.avatar || '' };
    createWindow(user);
  });

  fab.addEventListener('click', async ()=>{
    try{
      if (open.size > 0) { const last = Array.from(open.values()).pop(); focusWindow(last); return; }
      const j = await apiGET('threads.php?limit=1');
      const t = (j.threads||[])[0]; if (!t) return;
      createWindow({ id: parseInt(t.other_id,10), display_name: t.user.display_name, avatar_path: t.user.avatar_path });
    }catch(e){ console.error(e); }
  });

  // ---------------- Start ----------------
  restoreOpen();
  window.addEventListener('beforeunload', persistOpen);
  connectSocket();
  poll();
})();

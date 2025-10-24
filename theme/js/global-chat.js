// /theme/js/global-chat.js

/* Optional: Toggle per Button mit data-toggle-global-chat */
if (!window.toggleGlobalChat) {
  window.toggleGlobalChat = function () {
    const el = document.getElementById('global-chat');
    if (!el) return;
    el.classList.toggle('hidden');
    try {
      const st = JSON.parse(localStorage.getItem('globalChat.state') || '{}');
      st.open = !el.classList.contains('hidden');
      localStorage.setItem('globalChat.state', JSON.stringify(st));
    } catch (_) {}
  };
}

(function () {
  // --------------- Helpers & Config ---------------
  const CSRF = document.querySelector('meta[name="csrf-token"], meta[name="csrf"]')?.content || '';
  const META_USERNAME = document.querySelector('meta[name="username"]')?.content || '';
  const SESSION_USERNAME = (window.CURRENT_USER && window.CURRENT_USER.username) || '';
  const API = (p) => (p.startsWith('/') ? p : '/' + p);
  const withCreds = { credentials: 'same-origin' };

  // stabile Client-ID, um eigene Messages sicher zu erkennen
  function getClientKey() {
    try {
      let k = localStorage.getItem('globalChat.clientKey');
      if (!k) {
        k = 'gc_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        localStorage.setItem('globalChat.clientKey', k);
      }
      return k;
    } catch { return 'gc_static'; }
  }
  const CLIENT_KEY = getClientKey();

  // Displayname: Quelle(n) in dieser Reihenfolge: META → SESSION → LocalStorage → Prompt
  function getStoredDisplayName() {
    try { return localStorage.getItem('globalChat.displayName') || ''; } catch { return ''; }
  }
  function setStoredDisplayName(n) {
    try { localStorage.setItem('globalChat.displayName', n); } catch {}
  }
  function pickDisplayName() {
    let n = META_USERNAME || SESSION_USERNAME || getStoredDisplayName();
    if (!n || !n.trim()) {
      n = (window.prompt('Dein Chat‑Name (wird angezeigt):', '') || '').trim();
      if (!n) n = 'Ich'; // niemals „Gast“
      setStoredDisplayName(n);
    }
    return n;
  }
  let DISPLAY_NAME = pickDisplayName();

  // State speichern
  const store = {
    get() { try { return JSON.parse(localStorage.getItem('globalChat.state') || '{}'); } catch { return {}; } },
    set(patch) { const cur = this.get(); localStorage.setItem('globalChat.state', JSON.stringify(Object.assign(cur, patch))); },
  };
  let state = Object.assign({ open: true, minimized: false, x: null, y: null, lastId: 0 }, store.get());

  // --------------- UI ---------------
  const root = document.createElement('div');
  root.id = 'global-chat';
  root.style.cssText = `
    position: fixed; right: 20px; bottom: 20px; width: 320px; height: 420px;
    background: rgba(22,22,22,0.95); border:1px solid rgba(255,255,255,0.08);
    border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.5);
    display:flex; flex-direction:column; z-index:9999; color:#eee; font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Arial,"Apple Color Emoji","Segoe UI Emoji";
  `;
  if (!state.open) root.classList.add('hidden');
  if (state.minimized) root.classList.add('minimized');

  root.innerHTML = `
    <style>
      #global-chat.hidden { display:none; }
      #global-chat.minimized { height: 44px !important; }
      #global-chat header { height:44px; display:flex; align-items:center; gap:8px; padding:0 10px; cursor:move; }
      #global-chat header .title { font-weight:600; font-size:14px; letter-spacing:.3px; }
      #global-chat header .spacer { flex:1; }
      #global-chat header button { background:transparent; border:0; font-size:16px; cursor:pointer; color:#ddd; }
      #global-chat .messages { flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px; }
      #global-chat .msg { display:flex; }
      #global-chat .bubble { background:#232323; border:1px solid rgba(255,255,255,0.06); padding:8px 10px; border-radius:10px; max-width:85%; white-space:pre-wrap; word-wrap:break-word; font-size:13px; }
      #global-chat .msg.me .bubble { background:#2a2a2a; border-color:rgba(255,255,255,0.12); }
      #global-chat .bubble .meta { opacity:.7; font-size:11px; margin-top:4px; }
      #global-chat footer { border-top:1px solid rgba(255,255,255,0.08); padding:8px; display:flex; gap:8px; }
      #global-chat footer input { flex:1; background:#1a1a1a; border:1px solid rgba(255,255,255,0.08); color:#eee; height:34px; border-radius:8px; padding:0 10px; }
      #global-chat footer button { height:34px; padding:0 12px; border-radius:8px; border:0; background:#3a3a3a; color:#eee; cursor:pointer; }
      #global-chat .hint { font-size:11px; opacity:.85; padding:4px 10px 8px; }
      #global-chat .namebar { font-size:11px; opacity:.95; padding:6px 10px; border-top:1px solid rgba(255,255,255,0.08);
        display:flex; gap:6px; align-items:center; }
      #global-chat .namebar input { flex:1; height:28px; border-radius:6px; border:1px solid rgba(255,255,255,0.08); background:#1a1a1a; color:#eee; padding:0 8px; }
      #global-chat .namebar button { height:28px; border:0; border-radius:6px; background:#3a3a3a; color:#eee; padding:0 10px; cursor:pointer; }
    </style>
    <header>
      <span class="title">Global Chat</span>
      <span class="spacer"></span>
      <button data-action="min" title="Minimieren">_</button>
      <button data-action="close" title="Schließen">×</button>
    </header>
    <div class="messages" id="gc-messages"></div>
    <div class="hint" id="gc-hint">Letzte 24 Stunden. Älteres wird automatisch entfernt.</div>
    <footer>
      <input id="gc-input" maxlength="2000" placeholder="Nachricht schreiben…" />
      <button id="gc-send">Senden</button>
    </footer>
    <div class="namebar">
      <span>Dein Name:</span>
      <input id="gc-name" value="" placeholder="Ich" />
      <button id="gc-save-name">Speichern</button>
    </div>
  `;

  document.addEventListener('DOMContentLoaded', () => {
    document.body.appendChild(root);
    nameInput.value = DISPLAY_NAME;
  });

  // Position wiederherstellen
  function applyPosition() {
    if (state.x !== null && state.y !== null) {
      root.style.left = state.x + 'px';
      root.style.top  = state.y + 'px';
      root.style.right = 'auto';
      root.style.bottom = 'auto';
    }
  }
  applyPosition();

  // Drag & Drop
  (function enableDrag() {
    const header = root.querySelector('header');
    let dragging = false, sx = 0, sy = 0, startX = 0, startY = 0;
    header.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return;
      dragging = true;
      const rect = root.getBoundingClientRect();
      startX = rect.left; startY = rect.top; sx = e.clientX; sy = e.clientY;
      e.preventDefault();
    });
    document.addEventListener('mousemove', (e) => {
      if (!dragging) return;
      const nx = startX + (e.clientX - sx);
      const ny = startY + (e.clientY - sy);
      root.style.left = nx + 'px';
      root.style.top  = ny + 'px';
      root.style.right = 'auto';
      root.style.bottom = 'auto';
    });
    document.addEventListener('mouseup', () => {
      if (!dragging) return;
      dragging = false;
      const r = root.getBoundingClientRect();
      state.x = Math.max(8, Math.min(window.innerWidth  - r.width  - 8, r.left));
      state.y = Math.max(8, Math.min(window.innerHeight - r.height - 8, r.top));
      store.set({ x: state.x, y: state.y });
    });
  })();

  // Buttons (min/close)
  root.addEventListener('click', (e) => {
    const btn = e.target.closest('button'); if (!btn) return;
    const act = btn.dataset.action;
    if (act === 'close') {
      state.open = false; store.set({ open: false }); root.classList.add('hidden');
    } else if (act === 'min') {
      state.minimized = !state.minimized; store.set({ minimized: state.minimized });
      root.classList.toggle('minimized');
    }
  });

  // Elemente
  const list = root.querySelector('#gc-messages');
  const hint = root.querySelector('#gc-hint');
  const nameInput = root.querySelector('#gc-name');
  const nameSaveBtn = root.querySelector('#gc-save-name');

  // Name bearbeiten
  nameInput.value = DISPLAY_NAME;
  nameSaveBtn.addEventListener('click', () => {
    const v = (nameInput.value || '').trim();
    DISPLAY_NAME = v || 'Ich';
    setStoredDisplayName(DISPLAY_NAME);
    hint.textContent = `Name gespeichert: ${DISPLAY_NAME}`;
    setTimeout(() => { hint.textContent = 'Letzte 24 Stunden. Älteres wird automatisch entfernt.'; }, 1500);
  });

  // --------------- Render ---------------
  function escapeHtml(s) { return String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }

  function renderMessage(m) {
    // Eigene Nachricht erkennen
    const isMine = (m.client_key && m.client_key === CLIENT_KEY) || m._fromMe === true;
    // Wer?
    let who = m.username || (m.user_id ? ('User#' + m.user_id) : 'Gast');
    if (isMine) who = DISPLAY_NAME || 'Ich';   // **erzwinge** deinen Namen für eigene Messages
    // Zeit
    let t = (m.created_at || '').trim();
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(t)) t = t.replace(' ', 'T');
    const time = new Date(t).toLocaleTimeString();

    const div = document.createElement('div');
    div.className = 'msg' + (isMine ? ' me' : '');
    div.innerHTML = `<div class="bubble"><strong>${escapeHtml(who)}</strong>\n${escapeHtml(m.body)}<div class="meta">${time}</div></div>`;
    list.appendChild(div);
  }
  function scrollToBottom() { list.scrollTop = list.scrollHeight; }

  // --------------- API ---------------
  async function loadInitial() {
    try {
      const r = await fetch(API('/api/global_chat/history.php?limit=120'), withCreds);
      const j = await r.json();
      list.innerHTML = '';
      (j.messages || []).forEach(m => {
        renderMessage(m);
        state.lastId = Math.max(state.lastId || 0, m.id);
      });
      store.set({ lastId: state.lastId });
      scrollToBottom();
    } catch { hint.textContent = 'Fehler beim Laden der History'; }
  }

  async function poll() {
    try {
      const r = await fetch(API('/api/global_chat/history.php?since_id=' + (state.lastId || 0)), withCreds);
      const j = await r.json();
      (j.messages || []).forEach(m => {
        renderMessage(m);
        state.lastId = Math.max(state.lastId || 0, m.id);
      });
      if ((j.messages || []).length) { store.set({ lastId: state.lastId }); scrollToBottom(); }
    } catch { /* still */ }
  }

  // --------------- Senden ---------------
  const input = root.querySelector('#gc-input');
  const sendBtn = root.querySelector('#gc-send');

  async function send() {
    const body = input.value.trim(); if (!body) return;
    input.value = '';

    const nameToSend = (DISPLAY_NAME && DISPLAY_NAME.trim()) ? DISPLAY_NAME.trim() : 'Ich';

    try {
      const r = await fetch(API('/api/global_chat/send.php'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF,
          'X-Client-Key': CLIENT_KEY
        },
        body: JSON.stringify({ body, username: nameToSend, client_key: CLIENT_KEY })
      });
      const j = await r.json();
      if (!j.ok) { hint.textContent = j.error || 'Fehler beim Senden'; return; }

      // Falls Server client_key/username nicht zurückgibt, lokal setzen
      if (!j.message.client_key) j.message.client_key = CLIENT_KEY;
      if (!j.message.username) j.message.username = nameToSend;
      j.message._fromMe = true;

      renderMessage(j.message);
      state.lastId = Math.max(state.lastId || 0, j.message.id);
      store.set({ lastId: state.lastId });
      scrollToBottom();
    } catch {
      hint.textContent = 'Netzwerkfehler beim Senden';
    }
  }

  sendBtn.addEventListener('click', send);
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });

  // --------------- Boot ---------------
  window.addEventListener('load', () => {
    loadInitial();
    setInterval(poll, 4000);
document.addEventListener('click', (e) => {
 const t = e.target.closest('[data-toggle-global-chat],[data-chat-toggle]');
 if (!t) return;
 e.preventDefault();
 window.toggleGlobalChat();
 });
  });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();

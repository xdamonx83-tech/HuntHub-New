// /assets/js/global-chat.js
(function () {
  const L = (window.L && typeof window.L === 'object') ? window.L : {};
  const APP_BASE = window.APP_BASE || '';
  const CSRF =
    document.querySelector('meta[name="csrf"]')?.getAttribute('content') ||
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    window.CSRF || '';

  const POS_KEY      = 'hhGlobalChat.pos';
  const MIN_KEY      = 'hhGlobalChat.minimized';
  const LAST_ID_KEY  = 'hhGlobalChat.lastId';
  const OPEN_KEY     = 'hhGlobalChat.open';
  const CACHE_KEY    = 'hhGlobalChat.cache';
  const MAX_CACHE    = 200;

  // NEU: Einheitlicher Selector für alle externen Trigger
  const TOGGLE_SELECTOR = '[data-toggle-global-chat],[data-chat-toggle]';

  let lastId    = parseInt(localStorage.getItem(LAST_ID_KEY) || '0', 10) || 0;
  let minimized = localStorage.getItem(MIN_KEY) === '1';
  let isOpen    = localStorage.getItem(OPEN_KEY) === '1';
  let pollTimer = null;
  let ME = null;

  const EMOJI_CATEGORIES = {
    'Smileys': ['😀','😁','😂','🤣','😊','😅','😉','😍','😘','😜','🤪','😎','🤩','🥳','🥲','😭','😡','🤔','😴','😮','😳','😬'],
    'Gesten': ['👍','👎','👏','🙏','🙌','👌','✌️','🤞','👊','🤝','🤙','💪','🫶','☝️','👇','👈','👉'],
    'Herzen': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','💕','💖','💘','💝','💞','💓','💗'],
    'Symbole': ['🔥','🎉','✨','⚡','⭐','🌟','💯','🚀','🎁','📌','☕','🍻','🥇','🎯','🧠'],
    'Faces+Fun': ['🤯','🤓','🤗','😇','🥰','😈','👻','💩','🙈','🙉','🙊','🤖']
  };

  // ========== UI ==========
 const root = document.createElement('div');
  root.id = 'hh-global-chat';
  root.style.display = 'none';
  root.innerHTML = `
    <div class="hhgc-wrap">
      <div class="hhgc-header">
        <span class="hhgc-title">${L.globalchat ?? 'Global Chat'}</span>
        <div class="hhgc-actions">
          <button class="hhgc-min" aria-label="${L.minimize ?? 'Minimieren'}">–</button>
          <button class="hhgc-close" aria-label="${L.close ?? 'Schließen'}">×</button>
        </div>
      </div>
      <div class="hhgc-body">
        <div class="hhgc-messages" id="hhgc-messages"></div>

        <form class="hhgc-form">
          <div class="hhgc-tools">
            <button class="hhgc-emoji-btn" type="button" title="${L.emoji ?? 'Emoji'}">😊</button>
          </div>

          <textarea class="hhgc-input" rows="2" placeholder="${L.send_message ?? 'Nachricht schreiben…'}"></textarea>
          <button class="hhgc-send" type="submit">${L.send ?? 'Senden'}</button>

          <div class="hhgc-picker" aria-hidden="true" hidden>
            <div class="hhgc-tabs">
              <button type="button" data-tab="emoji" class="is-active">${L.emoji ?? 'Emoji'}</button>
              <button type="button" data-tab="gifs">${L.gifs ?? 'GIFs'}</button>
              <button type="button" data-tab="stickers">${L.stickers ?? 'Sticker'}</button>
            </div>
            <div class="hhgc-tab-panels">
              <div class="hhgc-panel" data-panel="emoji"></div>
              <div class="hhgc-panel" data-panel="gifs"></div>
              <div class="hhgc-panel" data-panel="stickers"></div>
            </div>
          </div>
        </form>
      </div>
    </div>
  `;
  document.body.appendChild(root);

  // --- WEG: single externalToggle (war Ursache) ---
  // NEU: Helper für alle aktuellen Trigger
  function getExternalToggles() {
    return Array.from(document.querySelectorAll(TOGGLE_SELECTOR));
  }

  const header     = root.querySelector('.hhgc-header');
  const messagesEl = root.querySelector('#hhgc-messages');
  const form       = root.querySelector('.hhgc-form');
  const input      = root.querySelector('.hhgc-input');
  const btnSend    = root.querySelector('.hhgc-send');
  const btnMin     = root.querySelector('.hhgc-min');
  const btnClose   = root.querySelector('.hhgc-close');
  const btnEmoji   = root.querySelector('.hhgc-emoji-btn');
  const picker     = root.querySelector('.hhgc-picker');
  const tabsEl     = root.querySelector('.hhgc-tabs');
  const panelEmoji = root.querySelector('.hhgc-panel[data-panel="emoji"]');
  const panelGifs  = root.querySelector('.hhgc-panel[data-panel="gifs"]');
  const panelStk   = root.querySelector('.hhgc-panel[data-panel="stickers"]');

  // ---------- Position / Drag ----------
  function restorePos() {
    try {
      const pos = JSON.parse(localStorage.getItem(POS_KEY) || '{}');
      if (typeof pos.left === 'number' && typeof pos.top === 'number') {
        root.style.left = pos.left + 'px';
        root.style.top  = pos.top  + 'px';
      } else {
        root.style.right = '20px';
        root.style.bottom= '20px';
      }
    } catch {
      root.style.right = '20px';
      root.style.bottom= '20px';
    }
  }
  function clampToViewport(x, y) {
    const pad = 10;
    const w = root.offsetWidth;
    const h = root.offsetHeight;
    const maxX = (window.innerWidth  - w - pad);
    const maxY = (window.innerHeight - h - pad);
    return [Math.max(pad, Math.min(x, maxX)), Math.max(pad, Math.min(y, maxY))];
  }
  function applyResponsiveLayout() {
    if (window.innerWidth <= 768) {
      root.style.left = '';
      root.style.top = '';
      root.style.right = '';
      root.style.bottom = '';
    } else {
      restorePos();
    }
  }
  let drag = null;
  header.addEventListener('mousedown', (e) => {
    if (window.innerWidth <= 768) return;
    drag = {
      startX: e.clientX,
      startY: e.clientY,
      startLeft: root.offsetLeft,
      startTop: root.offsetTop
    };
    document.body.classList.add('hhgc-dragging');
    e.preventDefault();
  });
  window.addEventListener('mousemove', (e) => {
    if (!drag) return;
    const dx = e.clientX - drag.startX;
    const dy = e.clientY - drag.startY;
    let nx = drag.startLeft + dx;
    let ny = drag.startTop  + dy;
    [nx, ny] = clampToViewport(nx, ny);
    root.style.left = nx + 'px';
    root.style.top  = ny + 'px';
  });
  window.addEventListener('mouseup', () => {
    if (!drag) return;
    localStorage.setItem(POS_KEY, JSON.stringify({ left: root.offsetLeft, top: root.offsetTop }));
    document.body.classList.remove('hhgc-dragging');
    drag = null;
  });

  // ---------- Sichtbarkeit & Polling ----------
  function startPolling() {
    if (pollTimer) return;
    poll(true);
    pollTimer = setInterval(() => poll(false), 2500);
  }
  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function setOpen(open) {
    isOpen = !!open;
    localStorage.setItem(OPEN_KEY, isOpen ? '1' : '0');
    root.style.display = isOpen ? 'block' : 'none';

    // NEU: auf ALLE Trigger anwenden
    getExternalToggles().forEach(t => {
      t.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      t.classList.toggle('is-active', isOpen);
    });

    document.documentElement.classList.toggle('hhgc-open', isOpen);
    document.body.classList.toggle('hhgc-open', isOpen);

    if (isOpen) {
      if (window.innerWidth <= 768) {
        root.style.left = root.style.top = root.style.right = root.style.bottom = '';
      }
      hydrateFromCache();
      startPolling();
    } else {
      stopPolling();
      hidePicker();
    }
  }

  function setMinimized(min) {
    minimized = !!min;
    localStorage.setItem(MIN_KEY, minimized ? '1' : '0');
    root.classList.toggle('is-min', minimized);
  }
  btnMin.addEventListener('click', () => setMinimized(!minimized));
  btnClose.addEventListener('click', () => setOpen(false));

  // NEU: Delegation – funktioniert für Button & Link, mobil robust
  document.addEventListener('click', (e) => {
    const t = e.target.closest?.(TOGGLE_SELECTOR);
    if (!t) return;
    e.preventDefault();
    setOpen(!isOpen);
  }, true); // Capture-Phase

  // ---------- Emoji Picker ----------
  function renderEmojiPanel() {
    panelEmoji.innerHTML = '';
    Object.entries(EMOJI_CATEGORIES).forEach(([title, list]) => {
      const group = document.createElement('div');
      group.className = 'hhgc-emoji-group';
      group.innerHTML = `<div class="hhgc-emoji-title">${title}</div>`;
      const grid = document.createElement('div');
      grid.className = 'hhgc-emoji-grid';
      list.forEach(ch => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'hhgc-emoji-item';
        b.textContent = ch;
        b.addEventListener('click', () => insertAtCursor(ch + ' '));
        grid.appendChild(b);
      });
      group.appendChild(grid);
      panelEmoji.appendChild(group);
    });
  }
  function insertAtCursor(text) {
    const el = input;
    const start = el.selectionStart ?? el.value.length;
    const end   = el.selectionEnd ?? el.value.length;
    const before = el.value.slice(0, start);
    const after  = el.value.slice(end);
    el.value = before + text + after;
    const pos = start + text.length;
    el.selectionStart = el.selectionEnd = pos;
    el.focus();
  }
  function showPicker() { picker.hidden = false; picker.setAttribute('aria-hidden', 'false'); }
  function hidePicker() { picker.hidden = true; picker.setAttribute('aria-hidden', 'true'); }
  btnEmoji.addEventListener('click', () => { picker.hidden ? showPicker() : hidePicker(); });
  document.addEventListener('click', (e) => {
    if (picker.hidden) return;
    if (!picker.contains(e.target) && !btnEmoji.contains(e.target)) hidePicker();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !picker.hidden) hidePicker(); });

  // Tabs
  tabsEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-tab]');
    if (!btn) return;
    tabsEl.querySelectorAll('button').forEach(b => b.classList.toggle('is-active', b === btn));
    const tab = btn.getAttribute('data-tab');
    root.querySelectorAll('.hhgc-panel').forEach(p => {
      p.style.display = (p.getAttribute('data-panel') === tab ? 'block' : 'none');
    });
    if (tab === 'emoji'   && !panelEmoji?.dataset.ready) { renderEmojiPanel(); panelEmoji.dataset.ready = '1'; }
    if (tab === 'gifs'    && !panelGifs?.dataset.ready)  { renderGifPanel();   panelGifs.dataset.ready  = '1'; }
    if (tab === 'stickers'&& !panelStk?.dataset.ready)   { loadStickers();     panelStk.dataset.ready   = '1'; }
  });
  root.querySelectorAll('.hhgc-panel').forEach(p =>
    p.style.display = (p.getAttribute('data-panel') === 'emoji' ? 'block' : 'none')
  );
  renderEmojiPanel(); panelEmoji.dataset.ready = '1';

  // ---------- Cache ----------
  function loadCache() {
    try { return JSON.parse(localStorage.getItem(CACHE_KEY) || '[]'); }
    catch { return []; }
  }
  function saveCache(list) {
    try { localStorage.setItem(CACHE_KEY, JSON.stringify(list.slice(-MAX_CACHE))); } catch {}
  }
  function hydrateFromCache() {
    const list = loadCache();
    if (!Array.isArray(list) || list.length === 0) return;
    messagesEl.innerHTML = '';
    for (const m of list) appendMessage(m, true);
    const maxId = Math.max(...list.map(x => Number(x.id) || 0), 0);
    if (maxId > 0) {
      lastId = maxId;
      localStorage.setItem(LAST_ID_KEY, String(lastId));
    }
  }

  function renderRich(text) {
    const abs = (u) => {
      try { return new URL(u, window.location.origin).toString(); }
      catch { return u; }
    };
    const httpRe = /(https?:\/\/[^\s]+)/g;
    let html = text.split(/\n/).map(line => {
      return line.replace(httpRe, (u) => {
        const low = u.toLowerCase();
        if (/\.(gif|png|jpe?g|webp)$/.test(low)) return `<img class="hhgc-embed" src="${u}" alt="">`;
        if (/\.(mp4|webm)$/.test(low)) return `<video class="hhgc-embed" src="${u}" autoplay muted loop playsinline></video>`;
        return `<a href="${u}" target="_blank" rel="noopener">${u}</a>`;
      });
    }).join('<br>');
    const relImgRe = /(^|[\s>])((\/[^\s"'<>]+\.(?:gif|png|jpe?g|webp)))/gi;
    const relVidRe = /(^|[\s>])((\/[^\s"'<>]+\.(?:mp4|webm)))/gi;
    html = html
      .replace(relImgRe, (m, pre, u) => `${pre}<img class="hhgc-embed" src="${abs(u)}" alt="">`)
      .replace(relVidRe, (m, pre, u) => `${pre}<video class="hhgc-embed" src="${abs(u)}" autoplay muted loop playsinline></video>`);
    return html;
  }

  function appendMessage(m, skipCache = false) {
    const item = document.createElement('div');
    item.className = 'hhgc-msg' + (m.is_me ? ' is-me' : '');
    const time = new Date(m.created_at.replace(' ', 'T') + 'Z');
    const hh = time.getHours().toString().padStart(2,'0');
    const mm = time.getMinutes().toString().padStart(2,'0');
    item.innerHTML = `
      <div class="hhgc-meta">
        <span class="hhgc-user">${escapeHtml(m.username)}</span>
        <span class="hhgc-time">${hh}:${mm}</span>
      </div>
      <div class="hhgc-text">${renderRich(escapeHtml(m.message))}</div>
    `;
    messagesEl.appendChild(item);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    if (!skipCache) {
      const list = loadCache();
      list.push({
        id: Number(m.id),
        user_id: Number(m.user_id),
        username: m.username,
        message: m.message,
        created_at: m.created_at
      });
      saveCache(list);
    }
  }

  async function sendQuick(text) {
    try {
      const res = await fetch(APP_BASE + '/api/chat/global_send.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ message: text, csrf: CSRF }).toString()
      });
      const raw = await res.text();
      let data; try { data = JSON.parse(raw); } catch { data = null; }
      if (data && data.ok && data.message) {
        data.message.is_me = true;
        appendMessage(data.message);
        lastId = Math.max(lastId, Number(data.message.id) || 0);
        localStorage.setItem(LAST_ID_KEY, String(lastId));
        hidePicker();
        return true;
      } else {
        console.error('Quick send failed:', data || raw);
        return false;
      }
    } catch (e) {
      console.error('Quick send error:', e);
      return false;
    }
  }

  function linkify(text) {
    return text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
  }
  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  async function fetchMe() {
    const res = await fetch(APP_BASE + '/api/auth/me.php', { credentials: 'include' });
    const data = await res.json();
    if (!data || !data.ok || !data.user) throw new Error('not logged in');
    return data.user;
  }

  async function poll(initial=false) {
    try {
      const url = new URL(APP_BASE + '/api/chat/global_poll.php', window.location.origin);
      if (!initial && lastId > 0) url.searchParams.set('after_id', String(lastId));
      const res = await fetch(url.toString(), { credentials: 'include' });
      const raw = await res.text();

      let data;
      try { data = JSON.parse(raw); }
      catch { console.error('Poll RAW:', raw); return; }

      if (!data || !data.ok) {
        console.error('Poll failed:', data);
        return;
      }

      const msgs = data.messages || [];
      for (const m of msgs) {
        m.is_me = (ME && Number(m.user_id) === Number(ME.id));
        appendMessage(m);
        lastId = Math.max(lastId, Number(m.id));
      }
      localStorage.setItem(LAST_ID_KEY, String(lastId));

      if (initial && msgs.length === 0 && messagesEl.children.length === 0) {
        messagesEl.innerHTML = `<div class="hhgc-empty">Noch keine Nachrichten. Sag mal hallo! 👋</div>`;
      }
    } catch (e) {
      console.error('Poll error:', e);
    }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    btnSend.disabled = true;
    try {
      const res = await fetch(APP_BASE + '/api/chat/global_send.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ message: text, csrf: CSRF }).toString()
      });
      const raw = await res.text();
      let data = {};
      try { data = JSON.parse(raw); } catch (_) { console.error('Send RAW:', raw); }
      if (data && data.ok && data.message) {
        data.message.is_me = true;
        appendMessage(data.message);
        lastId = Math.max(lastId, Number(data.message.id));
        localStorage.setItem(LAST_ID_KEY, String(lastId));
        input.value = '';
        hidePicker();
      } else {
        console.error('Send failed:', data);
      }
    } catch (e) {
      console.error('Send error:', e);
    } finally {
      btnSend.disabled = false;
      input.focus();
    }
  });

  function renderGifPanel() {
    if (!panelGifs) return;
    panelGifs.innerHTML = `
      <form class="hhgc-gif-search">
        <input type="text" placeholder="GIF suchen …" class="hhgc-gif-q" />
        <button type="submit">Suchen</button>
      </form>
      <div class="hhgc-grid" data-kind="gifs"></div>
      <div class="hhgc-note small"></div>
    `;
    const grid = panelGifs.querySelector('.hhgc-grid');
    const note = panelGifs.querySelector('.hhgc-note');
    const inputQ = panelGifs.querySelector('.hhgc-gif-q');

    async function loadGifs(q='') {
      if (!grid || !note) return;
      grid.innerHTML = '<div class="hhgc-loading">Lade…</div>';
      note.textContent = '';
      try {
        const url = new URL(APP_BASE + '/api/chat/gifs_search.php', window.location.origin);
        if (q) url.searchParams.set('q', q);
        const res = await fetch(url.toString(), { credentials: 'include' });
        const raw = await res.text();
        let data; try { data = JSON.parse(raw); } catch { data = null; }
        grid.innerHTML = '';
        if (!data) { note.textContent = 'Fehler: Ungültige JSON-Antwort.'; return; }
        if (!data.ok) {
          const dbg = data.debug ? ` (${data.debug.err || ('HTTP '+data.debug.http)} via ${data.debug.via})` : '';
          note.textContent = 'Fehler: ' + (data.error || 'Unbekannt') + dbg;
          return;
        }
        const items = Array.isArray(data.items) ? data.items : [];
        if (!items.length) {
          note.textContent = data.hint === 'NO_API_KEY' ? 'Kein GIF-API-Key in config.php.' : 'Keine Ergebnisse.';
          return;
        }
        for (const it of items) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'hhgc-media';
          if (typeof it.thumb === 'string' && it.thumb.endsWith('.mp4')) {
            btn.innerHTML = `<video muted autoplay loop playsinline src="${it.thumb}"></video>`;
          } else {
            btn.innerHTML = `<img loading="lazy" src="${it.thumb || it.url}" alt="gif">`;
          }
          btn.addEventListener('click', async () => {
            const url = (it.url || '').trim();
            if (!url) return;
            await sendQuick(url);
          });
          grid.appendChild(btn);
        }
      } catch (e) {
        grid.innerHTML = '';
        note.textContent = 'Netzwerkfehler.';
      }
    }

    panelGifs.addEventListener('submit', (e) => {
      const formEl = e.target.closest('.hhgc-gif-search');
      if (!formEl) return;
      e.preventDefault();
      const q = (panelGifs.querySelector('.hhgc-gif-q')?.value || '').trim();
      loadGifs(q);
    }, { once: false });

    loadGifs('');
  }

  async function loadStickers() {
    panelStk.innerHTML = `
      <div class="hhgc-stk-head">
        <div class="hhgc-stk-title">Sticker</div>
        <form class="hhgc-stk-upload" hidden>
          <input type="file" accept="image/png,image/webp,image/jpeg,image/gif" class="hhgc-stk-file">
          <input type="text" class="hhgc-stk-name" placeholder="Name (optional)" />
          <button type="submit">Hochladen</button>
        </form>
      </div>
      <div class="hhgc-grid" data-kind="stickers"></div>
      <div class="hhgc-note small"></div>
    `;
    const grid = panelStk.querySelector('.hhgc-grid');
    const note = panelStk.querySelector('.hhgc-note');
    const upForm = panelStk.querySelector('.hhgc-stk-upload');
    const upFile = panelStk.querySelector('.hhgc-stk-file');
    const upName = panelStk.querySelector('.hhgc-stk-name');

    try {
      if (!ME) { ME = await fetchMe(); }
      const canManage = ME && ['moderator','administrator','admin'].includes((ME.role||'').toLowerCase());
      if (canManage) { upForm.hidden = false; }
    } catch {}

    async function refresh() {
      grid.innerHTML = '<div class="hhgc-loading">Lade…</div>';
      note.textContent = '';
      try {
        const res = await fetch(APP_BASE + '/api/chat/stickers_list.php', { credentials: 'include' });
        const data = await res.json();
        grid.innerHTML = '';
        if (!data.ok) { note.textContent = 'Fehler beim Laden der Sticker.'; return; }
        const canManage = ME && ['moderator','administrator','admin'].includes((ME.role||'').toLowerCase());

        (data.items || []).forEach(it => {
          const wrap = document.createElement('div');
          wrap.className = 'hhgc-media hhgc-stk-item';
          wrap.innerHTML = `
            <img loading="lazy" src="${it.url}" alt="${it.name}">
            ${canManage ? '<button class="hhgc-stk-del" title="Löschen">×</button>' : ''}
          `;
          wrap.querySelector('img').addEventListener('click', async () => {
            const url = (it.url || '').trim();
            if (!url) return;
            await sendQuick(url);
          });
          if (canManage) {
            wrap.querySelector('.hhgc-stk-del').addEventListener('click', async (e) => {
              e.stopPropagation();
              if (!confirm('Diesen Sticker löschen?')) return;
              const fd = new FormData(); fd.append('file', it.file);
              const del = await fetch(APP_BASE + '/api/chat/stickers_delete.php', {
                method: 'POST', credentials: 'include', body: fd
              }).then(r=>r.json()).catch(()=>null);
              if (del && del.ok) wrap.remove(); else alert('Löschen fehlgeschlagen');
            });
          }
          grid.appendChild(wrap);
        });
        if (!grid.children.length) {
          grid.innerHTML = '<div class="hhgc-empty-note">Noch keine Sticker im Ordner /uploads/stickers.</div>';
        }
      } catch { note.textContent = 'Netzwerkfehler.'; }
    }

    if (upForm) {
      upForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!upFile.files || !upFile.files[0]) return;
        const fd = new FormData();
        fd.append('file', upFile.files[0]);
        if (upName.value.trim()) fd.append('name', upName.value.trim());
        const res = await fetch(APP_BASE + '/api/chat/stickers_upload.php', {
          method: 'POST', credentials: 'include', body: fd
        });
        const data = await res.json().catch(()=>null);
        if (data && data.ok) {
          upFile.value = ''; upName.value = '';
          refresh();
        } else {
          alert('Upload fehlgeschlagen' + (data?.error ? ': ' + data.error : ''));
        }
      });
    }

    refresh();
  }

  // ========== Init ==========
  async function init() {
    restorePos();
    applyResponsiveLayout();
    window.addEventListener('resize', applyResponsiveLayout);

    setMinimized(minimized);

    try {
      ME = await fetchMe();
    } catch {
      root.style.display = 'none';
      // NEU: alle Trigger stilllegen, wenn nicht eingeloggt
      getExternalToggles().forEach(t => {
        t.setAttribute('aria-disabled', 'true');
        t.classList.add('is-disabled');
      });
      // Klicks auf Trigger abfangen
      document.addEventListener('click', (e) => {
        const t = e.target.closest?.(TOGGLE_SELECTOR);
        if (t) e.preventDefault();
      }, true);
      return;
    }

    setOpen(isOpen);
  }

  window.addEventListener('beforeunload', () => { if (pollTimer) clearInterval(pollTimer); });
  init();
})();

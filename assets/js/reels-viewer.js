(() => {
  // einmalige Initialisierung
  if (window.__HH_REELS_VIEWER__) return;
  window.__HH_REELS_VIEWER__ = true;

  const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const isReelsPage = /\/reels\.php(?:$|\?)/.test(location.pathname + location.search);

  // ---- State ----
  let modal, stack, closeBtn, loader;
  let items = [];
  let cursor = 0;
  let loading = false;
  let after_id = 0;
  let wheelLock = false;
  let swipeLock = false; // verhindert Doppelwechsel

  // --- Reels CSS on-demand laden/entladen ---
  let __hhReelsCssRef = 0;
  function mountReelsCSS() {
    const ex = document.getElementById('hh-reels-css');
    if (ex) { __hhReelsCssRef++; return; }
    const l = document.createElement('link');
    l.id = 'hh-reels-css';
    l.rel = 'stylesheet';
    l.href = '/assets/styles/reels.css?v=1.96';
    document.head.appendChild(l);
    __hhReelsCssRef = 1;
  }
  function unmountReelsCSS() {
    __hhReelsCssRef = Math.max(0, __hhReelsCssRef - 1);
    if (__hhReelsCssRef === 0) {
      const ex = document.getElementById('hh-reels-css');
      if (ex) ex.remove();
    }
  }

  // ------- Scroll-Lock für iOS/Android -------
  let __lock = { y: 0, on: false };
  function lockScroll() {
    if (__lock.on) return;
    __lock.y = window.scrollY || 0;
    document.documentElement.classList.add('hh-lock');
    document.body.classList.add('hh-lock');
    document.body.style.position = 'fixed';
    document.body.style.top = `-${__lock.y}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    __lock.on = true;
  }
  function unlockScroll() {
    if (!__lock.on) return;
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    document.documentElement.classList.remove('hh-lock');
    document.body.classList.remove('hh-lock');
    window.scrollTo(0, __lock.y);
    __lock.on = false;
  }

  // ---- Touchmove-Killer (gegen Scroll-Leaks) ----
  let __killTouchMove;
  function attachTouchKillers(rootEl) {
    __killTouchMove = (e) => e.preventDefault();
    rootEl.addEventListener('touchmove', __killTouchMove, { passive: false });
    window.addEventListener('touchmove', __killTouchMove, { passive: false });
  }
  function detachTouchKillers(rootEl) {
    if (!__killTouchMove) return;
    rootEl.removeEventListener('touchmove', __killTouchMove);
    window.removeEventListener('touchmove', __killTouchMove);
    __killTouchMove = null;
  }

  // ---- Utils ----
  const h = (html) => { const d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstElementChild; };
  const escapeHtml = (s='') => s.replace(/[&<>"']/g, (m)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;' }[m]));
  function fmtHashtags(s) {
    if (!s) return '';
    const tags = s.match(/(^|\s)#([\p{L}0-9_.\-]{2,80})/gu)?.map(t=>t.trim())||[];
    return tags.map(t=>`<a href="/search.php?q=${encodeURIComponent(t)}">${escapeHtml(t)}</a>`).join(' ');
  }

  // Composer on-demand nachladen (JS/CSS), dann öffnen
  async function ensureComposerAssets() {
    mountReelsCSS(); // CSS aktiv solange offen
    if (window.hhreelsComposerReady) return;
    await new Promise((resolve) => {
      const s = document.createElement('script');
      s.src = '/assets/js/reels-composer.js?v=1';
      s.defer = true;
      s.onload = () => { window.hhreelsComposerReady = true; resolve(); };
      document.head.appendChild(s);
    });
  }
  async function openComposer() {
    try {
      await ensureComposerAssets();
      window.dispatchEvent(new CustomEvent('hh:reels:openComposer'));
    } catch (e) {
      location.href = '/reels.php?compose=1';
    }
  }

  // ---- API ----
  async function loadMore() {
    if (loading) return 0;
    loading = true;
    if (loader) loader.hidden = false;
    try {
      const res = await fetch(`/api/reels/feed.php?after_id=${after_id}`);
      const j = await res.json();
      if (!j || !j.items) return 0;
      const list = j.items;
      if (list.length) after_id = list[list.length - 1].id;
      items = items.concat(list);
      if (items.length && !stack?.childElementCount) render(0);
      return list.length;
    } catch(_) {
      return 0;
    } finally {
      if (loader) loader.hidden = true;
      loading = false;
    }
  }
  async function ensureHasNext() {
    if (cursor < items.length - 1) return true;
    const added = await loadMore();
    return cursor < items.length - 1 && added > 0;
  }
  async function ensureHasPrev() {
    return cursor > 0; // Feed lädt nach unten
  }

  function setLikeState(btn, liked, count) {
    btn.classList.toggle('is-liked', !!liked);
    btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
    const cnt = btn.querySelector('.cnt');
    if (cnt) cnt.textContent = String(Math.max(0, count||0));
  }

  function spawnHearts(layer, n=6) {
    if (!layer) return;
    for (let i=0;i<n;i++) {
      const el = document.createElement('div');
      el.className = 'fx-heart';
      el.textContent = '❤';
      el.style.left = `${12 + Math.random()*76}%`;
      el.style.setProperty('--dx', `${-20 + Math.random()*40}px`);
      el.style.animationDelay = `${i * 0.04}s`;
      layer.appendChild(el);
      el.addEventListener('animationend', ()=> el.remove());
    }
  }

  async function toggleLike(btn, reelId, fxLayer) {
    const wasLiked = btn.classList.contains('is-liked');
    let count = parseInt(btn.querySelector('.cnt')?.textContent || '0', 10) || 0;
    const nowLiked = !wasLiked;
    count = Math.max(0, count + (nowLiked ? 1 : -1));
    setLikeState(btn, nowLiked, count);
    if (nowLiked) spawnHearts(fxLayer, 10);

    try {
      const fd = new FormData();
      fd.append('id', reelId);
      fd.append('csrf', csrf);
      const r = await fetch('/api/reels/like_toggle.php', { method:'POST', body:fd });
      const j = await r.json();
      if (j && j.ok) setLikeState(btn, !!j.liked, parseInt(j.count,10)||0);
    } catch(_) {}
  }

  // --- Vollbild-Modal per data-reels-open ---
  function bindReelsOpeners(root = document) {
    root.querySelectorAll('[data-reels-open]').forEach((btn) => {
      if (btn.__hhBound) return;
      btn.__hhBound = true;
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        const a = btn.closest('a[href^="#"], a[href*="#reels"]');
        if (a) a.removeAttribute('href');
        openModal(0);
        loadMore();
        return false;
      });
    });
  }
  bindReelsOpeners();
  const moOpeners = new MutationObserver((ms) => {
    for (const m of ms) m.addedNodes?.forEach((n) => { if (n.nodeType === 1) bindReelsOpeners(n); });
  });
  moOpeners.observe(document.documentElement, { childList: true, subtree: true });

  // optionale globale API
  window.HHReels = { open(i=0){ openModal(i); loadMore(); } };

  // ---- Share-Sheet ----
  function openShareSheet(reel){
    const url = location.origin + '/reels.php?id=' + reel.id;
    const text = (reel.description || '').slice(0,140);

    // Native Share wenn verfügbar
    if (navigator.share) {
      navigator.share({ title: 'Hunthub Reel', text, url }).catch(()=>{});
      return;
    }

    const el = h(`
      <div class="hh-share" role="dialog" aria-modal="true">
        <div class="sheet">
          <h4>Teilen</h4>
          <div class="grid">
            <a class="opt" href="https://api.whatsapp.com/send?text=${encodeURIComponent(text + ' ' + url)}" target="_blank" rel="noreferrer noopener">📱 WhatsApp</a>
            <a class="opt" href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}" target="_blank" rel="noreferrer noopener">📘 Facebook</a>
            <a class="opt" href="https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}" target="_blank" rel="noreferrer noopener">𝕏 Twitter</a>
            <a class="opt" href="https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}" target="_blank" rel="noreferrer noopener">✈️ Telegram</a>
            <a class="opt" href="https://www.reddit.com/submit?url=${encodeURIComponent(url)}&title=${encodeURIComponent(text)}" target="_blank" rel="noreferrer noopener">👽 Reddit</a>
            <a class="opt" href="#" data-discord>💬 Discord</a>
            <a class="opt" href="#" data-copy>🔗 Link kopieren</a>
          </div>
          <div class="row">
            <div class="muted" data-info></div>
            <button class="btn" data-close>Schließen</button>
          </div>
        </div>
      </div>
    `);
    document.body.appendChild(el);

    const close = ()=> el.remove();
    el.addEventListener('click', (e)=>{ if (e.target === el) close(); });
    el.querySelector('[data-close]').addEventListener('click', close);

    el.querySelector('[data-copy]').addEventListener('click', async (e)=>{
      e.preventDefault();
      try { await navigator.clipboard.writeText(url); el.querySelector('[data-info]').textContent = 'Link kopiert ✔'; }
      catch { el.querySelector('[data-info]').textContent = 'Konnte nicht kopieren'; }
    });

    el.querySelector('[data-discord]').addEventListener('click', async (e)=>{
      e.preventDefault();
      try { await navigator.clipboard.writeText(url); } catch {}
      // Best-Effort: versucht die App zu öffnen (mobil); sonst passiert einfach nix.
      location.href = 'discord://';
    });
  }

  // ---- Modal ----
  function openModal(startIndex = 0) {
    mountReelsCSS();
    ensureReelsStyles(); // Baseline-CSS (falls reels.css noch lädt)
    if (modal) modal.remove();

    // Body wirklich festsetzen (mobil)
    lockScroll();

    modal = h(`
      <div id="reels-modal" role="dialog" aria-modal="true">
        <button class="reels-close" aria-label="Schließen">✕</button>
        <button class="reels-upload" aria-label="Neues Reel">＋</button>
        <div class="reels-stack" id="reels-stack"></div>
        <div class="reels-loader" hidden>Loading…</div>
      </div>
    `);

    document.body.appendChild(modal);
    document.body.classList.add('hh-modal-open');

    // Touch-Killer aktivieren (kein Scroll-Leak)
    attachTouchKillers(modal);

    stack = modal.querySelector('#reels-stack');
    closeBtn = modal.querySelector('.reels-close');
    const uploadBtn = modal.querySelector('.reels-upload');
    uploadBtn.addEventListener('click', (e) => { e.preventDefault(); openComposer(); });

    loader = modal.querySelector('.reels-loader');

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeModal();
      if (e.key === 'ArrowUp')   { e.preventDefault(); prev(); }
      if (e.key === 'ArrowDown') { e.preventDefault(); next(); }
    });
    modal.tabIndex = -1;
    modal.focus();

    // Wheel → nur wenn nicht auf Buttons/Inputs
    modal.addEventListener('wheel', (e) => {
      const targetIsUI = !!(e.target.closest?.('.reel-actions, .reels-close, button, a, input, textarea'));
      if (targetIsUI) return;
      e.preventDefault();
      if (wheelLock) return;
      wheelLock = true;
      (e.deltaY > 0) ? next() : prev();
      setTimeout(()=> wheelLock = false, 380);
    }, { passive:false });

    if (!items.length) loadMore();
    else render(startIndex);
  }

  function closeModal() {
    if (!modal) return;
    // Touch-Killer entfernen
    detachTouchKillers(modal);
    modal.remove();
    modal = null;
    document.body.classList.remove('hh-modal-open');
    unlockScroll();
    unmountReelsCSS();
  }

  // ---- Render ----
  function render(idx) {
    // Kein Re-Render des gleichen Index
    const clamped = Math.max(0, Math.min(idx, items.length - 1));
    if (clamped === cursor && stack?.firstChild) return;

    cursor = clamped;
    const it = items[cursor];
    stack.innerHTML = '';
    if (!it) return;

    const el = h(`
      <div class="reel-frame">
        <video
          playsinline
          webkit-playsinline
          autoplay
          loop
          preload="auto"
          disablepictureinpicture
          controlslist="nodownload noplaybackrate nofullscreen"
        ></video>

        <div class="reel-like-fx" aria-hidden="true"></div>

        <div class="reel-overlay">
          <div class="reel-meta">
            <div class="reel-user">
              <img class="reel-avatar" src="${it.avatar || '/assets/images/default-avatar.png'}" alt="">
              <span>@${escapeHtml(it.username||'user')}</span>
            </div>
            <div class="reel-desc">${escapeHtml(it.description || '')}</div>
            <div class="reel-hashtags">${fmtHashtags(it.hashtags_cache || it.description || '')}</div>
          </div>
        </div>

        <div class="reel-actions">
          <button class="reel-icon sound" title="Ton an/aus" aria-pressed="true">
            <span class="ico unmuted">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5l-4 4H4v6h3l4 4z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
            </span>
            <span class="ico muted">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5l-4 4H4v6h3l4 4z"/><path d="M22 9l-6 6"/><path d="M16 9l6 6"/></svg>
            </span>
          </button>

          <button class="reel-icon like${it.liked ? ' is-liked' : ''}" data-id="${it.id}" aria-pressed="${it.liked?'true':'false'}">
            <span class="heart">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                   class="icon icon-tabler icons-tabler-outline icon-tabler-heart">
                   <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                   <path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" />
              </svg>
            </span>
            <span class="cnt">${it.likes_count || 0}</span>
          </button>

          <button class="reel-icon comment" data-id="${it.id}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-bubble-text">
                 <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                 <path d="M7 10h10" />
                 <path d="M9 14h5" />
                 <path d="M12.4 3a5.34 5.34 0 0 1 4.906 3.239a5.333 5.333 0 0 1 -1.195 10.6a4.26 4.26 0 0 1 -5.28 1.863l-3.831 2.298v-3.134a2.668 2.668 0 0 1 -1.795 -3.773a4.8 4.8 0 0 1 2.908 -8.933a5.33 5.33 0 0 1 4.287 -2.16" />
            </svg>
            <span class="ccnt">${it.comments_count || 0}</span>
          </button>

          <button class="reel-icon share" data-id="${it.id}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-share-3">
                 <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                 <path d="M13 4v4c-6.575 1.028 -9.02 6.788 -10 12c-.037 .206 5.384 -5.962 10 -6v4l8 -7l-8 -7z" />
            </svg>
          </button>
        </div>

        <div class="reel-sound-hint" hidden>Tippe für Ton 🔊</div>
      </div>
    `);

    stack.appendChild(el);

    // --- Mehr lesen / Weniger ---
    const descEl = el.querySelector('.reel-desc');
    const tagsEl = el.querySelector('.reel-hashtags');

    const moreBtn = document.createElement('button');
    moreBtn.className = 'reel-more';
    moreBtn.textContent = 'Mehr lesen';
    moreBtn.style.display = 'none';
    (el.querySelector('.reel-meta') || el).appendChild(moreBtn);

    requestAnimationFrame(() => {
      const needsDesc = !!descEl && (descEl.scrollHeight - descEl.clientHeight > 4);
      const needsTags = !!tagsEl && (tagsEl.scrollHeight - tagsEl.clientHeight > 4);
      if (needsDesc) descEl.classList.add('moreable');
      if (needsTags) tagsEl.classList.add('moreable');
      if (needsDesc || needsTags) moreBtn.style.display = 'inline';
    });
    moreBtn.addEventListener('click', () => {
      const expanded = !(descEl?.classList.contains('expanded') || tagsEl?.classList.contains('expanded'));
      if (descEl){
        descEl.classList.toggle('expanded', expanded);
        descEl.classList.toggle('moreable', !expanded);
      }
      if (tagsEl){
        tagsEl.classList.toggle('expanded', expanded);
        tagsEl.classList.toggle('moreable', !expanded);
      }
      moreBtn.textContent = expanded ? 'Weniger' : 'Mehr lesen';
    });

    const frame    = el; // Swipe auf dem gesamten Frame
    const video    = el.querySelector('video');
    const likeBtn  = el.querySelector('.like');
    const fx       = el.querySelector('.reel-like-fx');
    const cmtBtn   = el.querySelector('.comment');
    const shareBtn = el.querySelector('.share');
    const soundBtn = el.querySelector('.reel-icon.sound');
    const soundHint= el.querySelector('.reel-sound-hint');

    // Autoplay (Ton bevorzugt)
    video.src = it.src;

    function setMuted(m){
      video.muted = !!m;
      if (m) video.setAttribute('muted',''); else video.removeAttribute('muted');
      soundBtn.classList.toggle('is-off', !!m);
      soundBtn.setAttribute('aria-pressed', (!m).toString());
    }
    async function autoplayWithSoundPreferred(){
      setMuted(false); // Standard: Ton an
      try {
        await video.play();
        soundHint.hidden = true;
      } catch (e) {
        // Browser blockt Autoplay mit Ton
        setMuted(true);
        try { await video.play(); } catch(_) {}
        soundHint.hidden = false;
      }
    }
    // erstes Nutzer-Gesture im Modal → Ton freischalten
    const unlockOnce = () => {
      if (!video) return;
      if (video.muted) {
        setMuted(false);
        video.play().catch(()=>{});
      }
      soundHint.hidden = true;
      modal?.removeEventListener('pointerdown', unlockOnce);
    };
    modal?.addEventListener('pointerdown', unlockOnce, { once:true });

    video.addEventListener('loadedmetadata', () => {
      video.loop = true;
      // Falls Autoplay noch nicht gelaufen ist
      if (video.paused) autoplayWithSoundPreferred();
    });
    // Direkt versuchen (falls metadata schon gecached)
    autoplayWithSoundPreferred();

    // Preload nächste Items
    if (cursor >= items.length - 3) loadMore();

    // --- Tap-Logik auf dem Video: Single = Play/Pause, Double = Like (kein Fullscreen)
    {
      let lastTap = 0;
      let singleTimer = null;
      let downX = 0, downY = 0;

      // leichte Bewegung tolerieren (kein Swipe)
      const TAP_MOVE_MAX = 10;
      const DBL_TAP_MS = 280;
      const SINGLE_DELAY = 240;

      video.addEventListener('pointerdown', (e) => {
        downX = e.clientX; downY = e.clientY;
      }, {passive:true});

      video.addEventListener('pointerup', (e) => {
        const moved = Math.hypot(e.clientX - downX, e.clientY - downY);
        if (moved > TAP_MOVE_MAX) return;         // war ein Swipe
        e.preventDefault();                       // verhindert native dblclick/FS

        const now = e.timeStamp || Date.now();
        const isDouble = (now - lastTap) < DBL_TAP_MS;

        if (isDouble) {
          if (singleTimer) { clearTimeout(singleTimer); singleTimer = null; }
          toggleLike(likeBtn, it.id, fx);
        } else {
          singleTimer = setTimeout(() => {
            if (video.paused) { video.play().catch(()=>{}); }
            else { video.pause(); }
            singleTimer = null;
          }, SINGLE_DELAY);
        }
        lastTap = now;
      }, {passive:false});

      // Fallback: etwaige dblclick-Events sicher unterdrücken
      video.addEventListener('dblclick', (e) => e.preventDefault(), {passive:false});
    }

    // --- Buttons: Klicks/Touch dürfen NICHT das Swipe auslösen
    [likeBtn, cmtBtn, shareBtn].forEach(btn=>{
      const stop = (ev)=>{ ev.stopPropagation(); };
      btn.addEventListener('click', stop, {passive:true});
      btn.addEventListener('pointerdown', stop, {passive:true});
      btn.addEventListener('touchstart', stop, {passive:true});
    });

    likeBtn.addEventListener('click', () => toggleLike(likeBtn, it.id, fx));
    cmtBtn.addEventListener('click', () => openComments(it.id));
    shareBtn.addEventListener('click', () => openShareSheet(it));

    soundBtn.addEventListener('click', () => {
      setMuted(!video.muted ? true : false);
      if (video.paused) video.play().catch(()=>{});
      soundHint.hidden = true;
    });

    // Swipe auf dem gesamten Frame
    addSwipeHandlers(frame);
  }

  // Vor/Zurück mit Guards + Nachladen
  async function next(){
    if (swipeLock) return;
    swipeLock = true;
    try {
      const can = await ensureHasNext();
      if (!can) return;
      render(cursor + 1);
    } finally {
      setTimeout(()=> swipeLock = false, 220);
    }
  }
  async function prev(){
    if (swipeLock) return;
    swipeLock = true;
    try {
      const can = await ensureHasPrev();
      if (!can) return;
      render(cursor - 1);
    } finally {
      setTimeout(()=> swipeLock = false, 220);
    }
  }

  // ---- Swipe helpers (ersetzt deine addSwipeHandlers komplett)
  function addSwipeHandlers(element) {
    let startY = null;
    let activeTouchId = null;

    const isUiTarget = (node) =>
      !!(node && node.closest('.reel-actions, .reels-close, .reel-more, .reel-overlay a, button, a, input, textarea'));

    // Pointer (Android/neuere iOS)
    element.addEventListener('pointerdown', (e) => {
      if (isUiTarget(e.target)) return;
      startY = e.clientY;
      activeTouchId = e.pointerId;
      element.setPointerCapture?.(e.pointerId);
    });

    element.addEventListener('pointermove', (e) => {
      if (startY == null || activeTouchId !== e.pointerId) return;
      const dy = e.clientY - startY;
      if (Math.abs(dy) > 8) e.preventDefault(); // Scroll unterbinden
    }, { passive:false });

    element.addEventListener('pointerup', (e) => {
      if (startY == null || activeTouchId !== e.pointerId) return;
      const dy = e.clientY - startY;
      if (Math.abs(dy) > 50) { dy < 0 ? next() : prev(); }
      startY = null; activeTouchId = null;
    });

    element.addEventListener('pointercancel', () => { startY = null; activeTouchId = null; });

    // Touch-Fallback (älteres iOS Safari)
    element.addEventListener('touchstart', (e) => {
      if (isUiTarget(e.target)) return;
      const t = e.touches[0]; if (!t) return;
      startY = t.clientY; activeTouchId = t.identifier;
    }, { passive:true });

    element.addEventListener('touchmove', (e) => {
      if (startY == null) return;
      e.preventDefault(); // verhindert Scroll-Leak
    }, { passive:false });

    element.addEventListener('touchend', (e) => {
      if (startY == null) return;
      // passender Finger?
      const t = e.changedTouches[0];
      if (!t || (activeTouchId != null && t.identifier !== activeTouchId)) { startY = null; return; }
      const dy = t.clientY - startY;
      if (Math.abs(dy) > 50) { dy < 0 ? next() : prev(); }
      startY = null; activeTouchId = null;
    }, { passive:true });

    element.addEventListener('touchcancel', () => { startY = null; activeTouchId = null; });
  }

  // ---- Comments Modal (mit Live-Count Update) ----
  async function openComments(id) {
    mountReelsCSS(); // Styles sicher da
    try {
      const res = await fetch('/api/reels/comments.php?id=' + id);
      const j = await res.json();
      if (!j.ok) { alert('Kommentare gerade nicht verfügbar.'); return; }
      const list = j.items || [];

      const wrap = h(`
        <div class="hh-reels-comments">
          <div class="panel-xl">
            <div class="modal-hd"><div>Kommentare</div><button data-close>✕</button></div>
            <div class="modal-bd"><div class="space-y-4" data-list>
              ${list.map(c => `<div class="text-sm"><b>@${escapeHtml(c.username||'user')}</b> ${escapeHtml(c.body||'')}</div>`).join('')}
            </div></div>
            <div class="modal-ft">
              <form class="flex gap-2 w-full" data-form>
                <input name="body" class="reel-input" placeholder="Kommentar schreiben" autocomplete="off"/>
                <button class="reel-btn reel-btn-primary" type="submit">Senden</button>
              </form>
            </div>
          </div>
        </div>
      `);
      document.body.appendChild(wrap);
      document.body.classList.add('hh-modal-open');

      const close = () => {
        wrap.remove();
        document.body.classList.remove('hh-modal-open');
        unmountReelsCSS();
      };
      wrap.querySelector('[data-close]').addEventListener('click', close);

      const form = wrap.querySelector('[data-form]');
      const listEl = wrap.querySelector('[data-list]');

      form.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(form);
        const body = (fd.get('body')||'').toString().trim();
        if (!body) return;
        fd.append('id', id);
        fd.append('csrf', csrf);
        try {
          const r = await fetch('/api/reels/comment_create.php', { method:'POST', body:fd });
          const jj = await r.json();
          if (jj.ok) {
            const cntEl = stack.querySelector(`.reel-frame .reel-actions .comment[data-id="${id}"] .ccnt`);
            if (cntEl) {
              const newCnt = typeof jj.count === 'number' ? jj.count : (parseInt(cntEl.textContent,10)||0)+1;
              cntEl.textContent = String(newCnt);
              cntEl.classList.add('bump'); setTimeout(()=>cntEl.classList.remove('bump'),250);
            }
            listEl.insertAdjacentHTML('afterbegin', `<div class="text-sm"><b>@du</b> ${escapeHtml(body)}</div>`);
            form.reset();
          }
        } catch(_) {}
      });
    } catch (_) {
      alert('Kommentare gerade nicht verfügbar.');
    }
  }

  // Nach Upload: Modal offen lassen und direkt neues Reel anzeigen
  window.addEventListener('hh:reels:uploaded', (e) => {
    try {
      const it = normalizeItem(e.detail || {});
      if (!modal) openModal(0);
      items = [it, ...items];
      cursor = 0;
      render(0);
    } catch (_) {}
  });

  // ---- Auto-Open nur auf reels.php ----
  if (isReelsPage) {
    openModal(0);
    loadMore();
  }

  // ---- Compose trigger (falls auf der Seite vorhanden) ----
  function bindCompose(root=document){
    root.querySelectorAll('[data-reels-compose]').forEach((btn)=>{
      if (btn.__hhBound) return;
      btn.__hhBound = true;
      btn.addEventListener('click', (e)=>{
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('hh:reels:openComposer'));
      });
    });
  }
  bindCompose();
  const moCompose = new MutationObserver((ms)=>{
    for (const m of ms) m.addedNodes?.forEach(n=>{ if (n.nodeType===1) bindCompose(n); });
  });
  moCompose.observe(document.documentElement, { childList:true, subtree:true });

  // Hilfsfunktion: Formate normalisieren
  function normalizeItem(it) {
    return {
      id: it.id,
      src: it.src,
      poster: it.poster || null,
      description: it.description || '',
      username: it.username || (it.user_id ? 'u' + it.user_id : 'user'),
      avatar: it.avatar || null,
      likes_count: it.likes_count || 0,
      comments_count: it.comments_count || 0,
      liked: !!it.liked,
    };
  }

  // ---- Inline-Baseline-CSS (Safe-Area, Share, Sound) ----
  function ensureReelsStyles() {
    if (document.getElementById('hh-reels-inline-css')) return;
    const css = `
    :root{
      --safe-t: env(safe-area-inset-top, 0px);
      --safe-r: env(safe-area-inset-right, 0px);
      --safe-b: env(safe-area-inset-bottom, 0px);
      --safe-l: env(safe-area-inset-left, 0px);
    }
    .hh-modal-open{overflow:hidden}
    html.hh-lock, body.hh-lock{overflow:hidden !important; height:100% !important}
    #reels-modal{
      position:fixed; inset:0; z-index:9999; background:#000;
      touch-action:none; overscroll-behavior:contain;
      padding: var(--safe-t) var(--safe-r) var(--safe-b) var(--safe-l);
    }
    #reels-modal .reels-stack{position:relative; width:100vw; height:100dvh}
    #reels-modal .reels-loader{position:absolute; left:0; right:0; bottom:calc(16px + var(--safe-b)); text-align:center; color:#aaa}
    #reels-modal .reels-close{
      position:absolute; top:calc(8px + var(--safe-t)); right:calc(8px + var(--safe-r)); z-index:2;
      width:44px; height:44px; border-radius:999px; background:rgba(255,255,255,.08);
      border:1px solid rgba(255,255,255,.15); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center
    }
    .reel-frame{position:absolute; inset:0; display:grid; place-items:center}
    .reel-frame video{
      width:100vw; height:100dvh; object-fit:cover; background:#000; outline:none;
      -webkit-user-select:none; user-select:none; touch-action:manipulation;
      -webkit-tap-highlight-color: transparent;
    }

    /* Overlay klickbar + sicher über Safe Area */
    #reels-modal .reel-overlay{
      position:absolute; left:calc(16px + var(--safe-l)); right:calc(96px + var(--safe-r));
      bottom:calc(18px + var(--safe-b)); color:#fff; text-shadow:0 1px 2px rgba(0,0,0,.6);
      pointer-events:none;
    }
    #reels-modal .reel-meta, #reels-modal .reel-more{ pointer-events:auto; }

    .reel-actions{
      position:absolute; right:calc(14px + var(--safe-r));
      /* nie zu tief – clamp zwischen 72px und 18% + Safe Area */
      bottom: clamp(72px, 18%, calc(24px + var(--safe-b)));
      display:flex; flex-direction:column; gap:14px
    }
    .reel-icon{appearance:none; border:0; background:rgba(0,0,0,.25); color:#fff;
      padding:8px 10px; border-radius:12px; display:flex; align-items:center; gap:8px;
      cursor:pointer; backdrop-filter:blur(2px)}
    .reel-icon.is-liked{color:#ef4444}
    .reel-like-fx{position:absolute; inset:0; pointer-events:none}
    .fx-heart{position:absolute; bottom:18%; left:50%; transform:translateX(-50%); font-size:22px; opacity:0;
      filter:drop-shadow(0 2px 2px rgba(0,0,0,.25)); animation:hh-float-heart 1.2s ease-out forwards}
    @keyframes hh-float-heart{
      0%{transform:translate(-50%,0) scale(1);opacity:0}
      10%{transform:translate(calc(-50% + var(--dx,0px)),-10px) scale(1.05);opacity:.95}
      50%{transform:translate(calc(-50% + var(--dx,0px)),-120px) scale(1.3);opacity:.95}
      100%{transform:translate(calc(-50% + var(--dx,0px)),-220px) scale(1.6);opacity:0}
    }

    /* Sound-Button Zustände + Hinweis */
    .reel-icon.sound .ico.muted{ display:none; }
    .reel-icon.sound.is-off .ico.muted{ display:inline; }
    .reel-icon.sound.is-off .ico.unmuted{ display:none; }
    .reel-sound-hint{
      position:absolute; left:50%; transform:translateX(-50%);
      bottom:calc(86px + var(--safe-b)); padding:6px 10px; border-radius:10px;
      background:rgba(0,0,0,.45); color:#fff; font-size:13px;
      border:1px solid rgba(255,255,255,.15); pointer-events:none;
    }

    /* Share-Sheet */
    .hh-share{position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,.55); backdrop-filter:blur(2px);
      display:flex; align-items:flex-end; padding:0 var(--safe-r) calc(4px + var(--safe-b)) var(--safe-l);}
    .hh-share .sheet{width:100%; background:#0e0e0e; border:1px solid rgba(255,255,255,.1);
      border-top-left-radius:16px; border-top-right-radius:16px; padding:14px 16px;}
    .hh-share .sheet h4{margin:0 0 10px 0}
    .hh-share .grid{display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px}
    .hh-share .opt{display:flex; flex-direction:column; align-items:center; gap:6px;
      background:#151515; border:1px solid rgba(255,255,255,.1); border-radius:12px; padding:10px;
      color:#fff; text-decoration:none; font-size:12px;}
    .hh-share .row{display:flex; gap:8px; margin-top:10px; justify-content:flex-end}
    .hh-share .btn{background:#1b1b1b; border:1px solid rgba(255,255,255,.15); color:#e5e7eb;
      padding:.5rem .8rem; border-radius:10px; cursor:pointer}
    .hh-share .muted{color:#a8a8a8; font-size:12px; margin-right:auto}

    `;
    const style = document.createElement('style');
    style.id = 'hh-reels-inline-css';
    style.textContent = css;
    document.head.appendChild(style);
  }

})();

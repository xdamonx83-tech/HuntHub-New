(function () {
  const BASE = window.APP_BASE || '';
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

  // PNG-Map: bevorzugt globale Map, sonst Fallback-Pfade
  const ICONS = (window.REACTIONS_PNG && Object.keys(window.REACTIONS_PNG).length)
    ? window.REACTIONS_PNG
    : {
        like:  '/assets/images/reactions/like.webp',
        love:  '/assets/images/reactions/love.webp',
        haha:  '/assets/images/reactions/haha.webp',
        wow:   '/assets/images/reactions/wow.webp',
        sad:   '/assets/images/reactions/sad.webp',
        angry: '/assets/images/reactions/angry.webp'
      };

  // Anzeige-Texte
  const LABEL = {
    like:  'Gefällt mir',
    love:  'Love',
    haha:  'Haha',
    wow:   'Wow',
    sad:   'Traurig',
    angry: 'Wütend'
  };

  // helpers
  const q  = (s, el=document) => el.querySelector(s);
  const qa = (s, el=document) => Array.from(el.querySelectorAll(s));
  const isEl = (x) => x instanceof Element;
  const safeJson = (t)=>{ try{ return JSON.parse(t); }catch(_){ return null; } };
  const fmtLabel = (r) => LABEL[r] || (r ? (r[0].toUpperCase() + r.slice(1)) : LABEL.like);
  const iconHTML = (r) => ICONS[r] ? `<img src="${ICONS[r]}" alt="${r}" class="inline-block w-4 h-4">` : '';

  async function sendReaction(btn, reaction) {
    if (!btn) return null;
    const fd = new FormData();
    fd.set('csrf', CSRF);
    fd.set('type', btn.dataset.entityType || '');
    fd.set('id', btn.dataset.entityId || '');
    fd.set('reaction', reaction || ''); // leer = entfernen

    const res = await fetch(BASE + '/api/wall/react.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });

    const text = await res.text();
    const j = safeJson(text);
    if (!res.ok || !j || j.ok === false) {
      console.error('Reaction error:', j?.error || text);
      return null;
    }
    return j; // erwartet: { ok:true, user_reaction:'', counts:{...}, total:n }
  }

  function sumCounts(counts){
    let n = 0;
    if (counts && typeof counts === 'object') {
      for (const k in counts) n += +counts[k] || 0;
    }
    return n;
  }

  function updateMainButtonUI(mainBtn, reaction, counts){
    if (!mainBtn) return;

    // Datenzustand
    mainBtn.dataset.reaction = reaction || '';
    mainBtn.classList.toggle('is-reacted', !!reaction);
    mainBtn.setAttribute('aria-pressed', reaction ? 'true' : 'false');

    // Label/Icon im Button
    const labelSpan = q('.like-label', mainBtn);
    const iconSpan  = q('.like-icon-slot', mainBtn) || q(':scope > .like-icon', mainBtn);

    if (labelSpan) labelSpan.textContent = fmtLabel(reaction || 'like');
    if (iconSpan)  iconSpan.innerHTML   = iconHTML(reaction || 'like');

    // Zähler (Button/Bar)
    const bar       = mainBtn.closest('.like-bar');
    const countSpan = bar ? (q('.like-count', bar) || q('.like-count-total', bar)) : null;
    if (countSpan && counts) countSpan.textContent = String(sumCounts(counts));

    // Top-Icons-Zusammenfassung (optional)
    if (bar && counts) {
      const summary = q('.reaction-summary', bar);
      if (summary) {
        summary.innerHTML = '';
        const top = Object.entries(counts).sort((a,b)=>b[1]-a[1]).slice(0,3).map(e=>e[0]);
        top.forEach(k=>{
          if (ICONS[k]) summary.insertAdjacentHTML('beforeend', `<img class="rs-icon" src="${ICONS[k]}" alt="${k}">`);
        });
      }
    }
  }
async function hydrateFromServer(container){
  const scope = container || document;
  const btns = Array.from(scope.querySelectorAll('.btn-like'));
  if (!btns.length) return;

  // Paare einsammeln
  const seen = new Set();
  const list = [];
  btns.forEach(b => {
    const t = b.dataset.entityType || '';
    const i = b.dataset.entityId || '';
    if (t && i) {
      const k = t + ':' + i;
      if (!seen.has(k)) { seen.add(k); list.push(k); }
    }
  });
  if (!list.length) return;

  try {
    const url = BASE + '/api/wall/reactions_bulk.php?pairs=' + encodeURIComponent(list.join(','));
    const res = await fetch(url, { credentials: 'same-origin' });
    const text = await res.text();
    const j = safeJson(text);
    if (!j || j.ok === false) return;
const map = j.reactions || {};

// UI setzen
btns.forEach(b => {
  const key = (b.dataset.entityType || '') + ':' + (b.dataset.entityId || '');
  const r = map[key] || '';
  if (r) updateMainButtonUI(b, r, null);
});

  } catch (_) {}
}

// Initial + on-demand
document.addEventListener('DOMContentLoaded', () => {
  hydrateFromServer(document); // gesamte Seite
});
document.addEventListener('hh-hydrate', (e) => {
  hydrateFromServer(e.detail?.root || document);
});
// === Einfach-Hydrierung: pro Button aktuellen Status holen ===
async function hydrateOneButton(btn){
  const t = btn.dataset.entityType || '';
  const i = btn.dataset.entityId || '';
  if (!t || !i) return;

  try {
    const url = `${BASE}/api/wall/react.php?_action=get&type=${encodeURIComponent(t)}&id=${encodeURIComponent(i)}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    const text = await res.text();
    const j = (()=>{ try{return JSON.parse(text);}catch{ return null; } })();
    if (j && j.ok) {
      updateMainButtonUI(btn, j.reaction || '', j.counts || null);
	  
    }
	// „Hard set“ – verhindert spätere Überschreibungen
btn.dataset.reaction = j.reaction || '';
const hardLbl = btn.querySelector('.like-label');
if (hardLbl) hardLbl.textContent = (LABEL[j.reaction] || LABEL.like);
const hardIcon = btn.querySelector('.like-icon-slot');
if (hardIcon) hardIcon.innerHTML = iconHTML(j.reaction || 'like');

  } catch(_){}
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn-like').forEach(hydrateOneButton);
});

// optional: von außen nach dynamischen Inserts aufrufen
window.WALL_HYDRATE = (root) => {
  (root || document).querySelectorAll('.btn-like').forEach(hydrateOneButton);
};

  // --- Likers-Modal ---
  async function openLikers(type, id){
    if (!type || !id) return;

    let modal = document.getElementById('likers-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'likers-modal';
      modal.className = 'modal-mask';
      modal.innerHTML = `
        <div class="modal-card">
          <div class="modal-hd">
            <strong>Reaktionen</strong>
            <button type="button" id="likers-close" aria-label="Schließen">×</button>
          </div>
          <div class="modal-bd" id="likersList">Lade …</div>
        </div>`;
      document.body.appendChild(modal);

      modal.addEventListener('click', (e)=>{
        if (e.target.id === 'likers-close' || e.target.id === 'likers-modal') modal.style.display = 'none';
      });
    }

    modal.style.display = 'flex';
    const list = document.getElementById('likersList');
    list.textContent = 'Lade …';

    try {
      const res  = await fetch(`${BASE}/api/wall/likers.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`, { credentials:'same-origin' });
      const text = await res.text();
      const j    = safeJson(text);
      if (!j || !j.ok) { list.textContent = j?.error || 'Fehler beim Laden'; return; }

      const users = Array.isArray(j.users) ? j.users : [];
      if (!users.length) { list.textContent = 'Noch keine Reaktionen.'; return; }

      list.innerHTML = users.map(u => {
        const r = u.reaction || '';
        const rIcon = r && ICONS[r] ? `<img src="${ICONS[r]}" alt="${r}" class="w-4 h-4 ml-auto">` : '';
        const avatar = u.avatar || '/assets/images/avatar-default.png';
        const slug = encodeURIComponent(u.slug || '');
        const name = u.username || 'User';
        return `
          <div class="liker-row">
            <img src="${avatar}" class="avatar" alt="">
            <a href="${BASE}/user.php?u=${slug}">${name}</a>
            ${rIcon}
          </div>`;
      }).join('');
    } catch(err){
      list.textContent = 'Fehler beim Laden';
    }
  }

  // --- Haupt-Button: Klick ---
  document.addEventListener('click', async (e) => {
    if (!isEl(e.target)) return;
    const mainBtn = e.target.closest('.btn-like');
    if (!mainBtn || mainBtn.closest('.reactions-popup')) return; // nur echter Hauptbutton

    e.preventDefault();

    const bar   = mainBtn.closest('.like-bar');
    const popup = bar ? q('.reactions-popup', bar) : null;

    // Wenn Popup sichtbar ist, nicht toggeln (Nutzer will Popup nutzen)
    if (popup && popup.classList.contains('show')) return;

    const current = mainBtn.dataset.reaction || '';
    const next    = current ? '' : 'like';   // Toggle: vorhandene Reaktion entfernen, sonst like setzen

    const res = await sendReaction(mainBtn, next);
    if (!res) return;

    updateMainButtonUI(mainBtn, (typeof res.reaction !== 'undefined' ? res.reaction : next), res.counts || null);
  });

  // --- Popup-Reaktionen: Klick ---
  document.addEventListener('click', async (e) => {
    if (!isEl(e.target)) return;
    const rBtn = e.target.closest('.reactions-popup button');
    if (!rBtn) return;

    e.preventDefault();
    const bar      = rBtn.closest('.like-bar');
    const mainBtn  = bar ? q('.btn-like', bar) : null;
    const chosen   = rBtn.dataset.reaction || 'like';
    const current  = mainBtn?.dataset.reaction || '';

    // Gleiche Reaktion erneut -> entfernen; sonst wechseln
    const toSend = (current && current === chosen) ? '' : chosen;

    const res = await sendReaction(mainBtn, toSend);
    if (!res) return;

    updateMainButtonUI(mainBtn, (typeof res.reaction !== 'undefined' ? res.reaction : toSend), res.counts || null);

    // Popup schließen
    q('.reactions-popup', bar)?.classList.remove('show');
  });

  // --- Likers-Button (öffnet Modal) ---
  document.addEventListener('click', (e) => {
    if (!isEl(e.target)) return;
    const trigger = e.target.closest('.btn-likers, .likers-link'); // passe Klassen ggf. an
    if (!trigger) return;

    e.preventDefault();

    const bar     = trigger.closest('.like-bar');
    // type/id aus Trigger > sonst aus btn-like im gleichen Block > sonst aus Bar-Data
    const type = trigger.dataset.entityType
              || q('.btn-like', bar)?.dataset.entityType
              || bar?.dataset.entityType
              || '';
    const id   = trigger.dataset.entityId
              || q('.btn-like', bar)?.dataset.entityId
              || bar?.dataset.entityId
              || '';

    openLikers(type, id);
  });

  // --- Long-Press (mobil) öffnet Popup ---
  document.addEventListener('pointerdown', (e) => {
    if (!isEl(e.target)) return;
    const mainBtn = e.target.closest('.btn-like');
    if (!mainBtn || mainBtn.closest('.reactions-popup')) return;
    mainBtn._pressTimer = setTimeout(() => {
      const bar = mainBtn.closest('.like-bar');
      q('.reactions-popup', bar)?.classList.add('show');
    }, 450);
  });
  document.addEventListener('pointerup', (e) => {
    if (!isEl(e.target)) return;
    const mainBtn = e.target.closest('.btn-like');
    if (mainBtn && mainBtn._pressTimer) clearTimeout(mainBtn._pressTimer);
  });

  // --- Hover (Desktop) öffnet Popup ---
  let hoverTimer=null;
  document.addEventListener('mouseenter', (e) => {
    if (!isEl(e.target)) return;
    const bar = e.target.closest('.like-bar');
    if (!bar || !matchMedia('(hover:hover)').matches) return;
    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => q('.reactions-popup', bar)?.classList.add('show'), 180);
  }, true);
  document.addEventListener('mouseleave', (e) => {
    if (!isEl(e.target)) return;
    const bar = e.target.closest('.like-bar');
    if (!bar || !matchMedia('(hover:hover)').matches) return;
    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => q('.reactions-popup', bar)?.classList.remove('show'), 200);
  }, true);

  // --- Initiale Hydrierung: Label/Icon anhand data-reaction setzen ---
  document.addEventListener('DOMContentLoaded', () => {
    qa('.btn-like').forEach((b) => {
      updateMainButtonUI(b, b.dataset.reaction || '', null);
    });
  });
})();

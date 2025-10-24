// /theme/js/ratings.js — HHR v20250829h (Popup, kein Reload)
(function () {
  const APP_BASE = window.APP_BASE || '';
  const CSRF =
    document.querySelector('meta[name="csrf"]')?.getAttribute('content') ||
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const $ = (s, r = document) => r.querySelector(s);
  const $all = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (t) => String(t).replaceAll('"', '&quot;');

  // ---- Sterne (Inline-SVG, Farbe via CSS .hhr-star { color: #e1c17a }) ----
  function starHTML(stars, title) {
    const s = Math.max(0, Math.min(6, parseInt(stars || 0, 10)));
    let html = '<div class="hhr-stars"' + (title ? ' title="' + esc(title) + '"' : '') + '>';
    for (let i = 1; i <= 6; i++) {
      html +=
        '<svg class="hhr-star' + (i <= s ? ' is-on' : '') +
        '" viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="currentColor">' +
        '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>' +
        '</svg>';
    }
    html += '</div>';
    return html;
  }

  // ---- Interaktive Sterne im Formular ----
  function initStarsWidget(scope) {
    const groups = ['play', 'friendly', 'helpful'];
    groups.forEach((name) => {
      const inputs = $all(`input[name="${name}"]`, scope);
      if (!inputs.length) return;
      const labels = inputs.map((i) => i.closest('label')).filter(Boolean);
      const icons = labels.map((l) => l.querySelector('.hhr-star, svg.hhr-star')).filter(Boolean);
      const wrap = labels[0]?.parentElement;

      const current = () => Math.max(0, inputs.findIndex((i) => i.checked) + 1);
      const paint = (n) => icons.forEach((ic, i) => ic.classList.toggle('is-on', i < n));

      paint(current());
      labels.forEach((lbl, idx) => {
        const input = inputs[idx];
        lbl.setAttribute('tabindex', '0');
        const commit = () => { input.checked = true; paint(idx + 1); };
        lbl.addEventListener('click', (e) => { e.preventDefault(); commit(); });
        lbl.addEventListener('keydown', (e) => { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); commit(); } });
        lbl.addEventListener('mouseenter', () => paint(idx + 1));
      });
      if (wrap) wrap.addEventListener('mouseleave', () => paint(current()));
      inputs.forEach((inp) => inp.addEventListener('change', () => paint(current())));
    });
  }

  // ---- Daten laden ----
  function loadSummary(userId) {
    return fetch(`${APP_BASE}/api/ratings/summary.php?user_id=${userId}`)
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        const s = data.summary;
        const rounded = s.stars_rounded || 0;
        const exact = s.avg_overall_exact ? Number(s.avg_overall_exact).toFixed(2) : '–';
        const count = s.count || 0;
        const box = `
          <div class="hhr-summary-box">
            <div class="hhr-summary-stars">${starHTML(rounded, `Ø ${exact} / 6.0 aus ${count} Bewertungen`)}</div>
            <div class="hhr-summary-meta" style="margin-bottom: 20px;">
              <span class="hhr-score">Ø ${exact} / 6.0 HHR</span>
              <span class="hhr-count">(${count} Bewertungen)</span>
            </div>
          </div>`;
        $all('.hhr-summary').forEach((el) => { el.innerHTML = box; });
      })
      .catch(() => {});
  }

  function loadList(userId, limit = 10) {
    const list = $('#hhr-list');
    if (!list) return Promise.resolve();
    return fetch(`${APP_BASE}/api/ratings/list.php?ratee_id=${userId}&limit=${limit}`)
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) { list.innerHTML = `<div class="hhr-empty">Fehler: ${data.error || 'Unbekannt'}</div>`; return; }
        if (!data.items.length) { list.innerHTML = '<div class="hhr-empty">Noch keine Bewertungen.</div>'; return; }
        list.innerHTML = data.items.map((item) => {
          const exact = Number(item.score_exact).toFixed(2);
          const title = `Spielweise ${item.play}/6 · Freundlichkeit ${item.friendly}/6 · Hilfsbereitschaft ${item.helpful}/6 (Ø ${exact})`;
          const avatar = item.avatar_path || '/assets/images/avatars/placeholder.png';
          return `
            <article class="hhr-item">
              <a class="hhr-rater" href="${APP_BASE}/user.php?id=${encodeURIComponent(item.rater_id)}${item.slug ? `&slug=${encodeURIComponent(item.slug)}` : ''}">
                <img class="hhr-avatar" src="${avatar}" alt="Avatar">
                <span class="hhr-name">${(item.display_name || 'Spieler')}</span>
              </a>
              <div class="hhr-score">${starHTML(item.score_stars, title)} <span class="hhr-exact">(${exact})</span></div>
              ${item.comment ? `<p class="hhr-comment"></p>` : ''}
              <time class="hhr-time" datetime="${item.updated_at}">${item.updated_at}</time>
            </article>`;
        }).join('');
        $all('.hhr-item').forEach((el, idx) => {
          const it = data.items[idx];
          if (it.comment) el.querySelector('.hhr-comment').textContent = it.comment;
        });
      })
      .catch(() => {});
  }

  // ---- Popup ----
  function openModal(innerHTML) {
    const wrap = document.createElement('div');
    wrap.setAttribute('role', 'dialog'); wrap.setAttribute('aria-modal', 'true');
    wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center';
    wrap.innerHTML = `
      <div style="position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(2px)"></div>
      <div style="position:relative;width:min(92vw,720px);max-width:720px;background:#1a1c1f;border:1px solid #2c2f33;border-radius:14px;box-shadow:0 20px 80px rgba(0,0,0,.55);padding:22px">
        <button type="button" aria-label="Schließen" style="position:absolute;top:10px;right:10px;background:transparent;border:0;color:#cbd0d6;font-size:18px;cursor:pointer">✕</button>
        ${innerHTML}
      </div>`;
    document.body.appendChild(wrap);
    const panel = wrap.children[1];
    const close = () => wrap.remove();
    panel.querySelector('button[aria-label="Schließen"]').addEventListener('click', close);
    wrap.firstElementChild.addEventListener('click', close);
    document.addEventListener('keydown', function onEsc(e){ if(e.key==='Escape'){ close(); document.removeEventListener('keydown', onEsc); }});
    return { panel, close };
  }

  function modalFormHTML(userId) {
    return `
      <h3 style="color:#fff;margin:0 0 14px;font-weight:800;font-size:22px;">Bewertung abgeben</h3>
      <form id="hhr-form" novalidate autocomplete="off">
        <input type="hidden" name="ratee_id" value="${userId}">
        <div class="hhr-field" style="display:grid;grid-template-columns:160px 1fr;gap:16px;align-items:center;margin-bottom:14px;">
          <label style="color:#e5e7eb">Spielweise</label>
          <div class="hhr-stars-input">
            ${[1,2,3,4,5,6].map(i=>`
              <label><input type="radio" name="play" value="${i}" ${i===6?'checked':''}><i class="hhr-star" aria-hidden="true"></i></label>`).join('')}
          </div>
        </div>
        <div class="hhr-field" style="display:grid;grid-template-columns:160px 1fr;gap:16px;align-items:center;margin-bottom:14px;">
          <label style="color:#e5e7eb">Freundlichkeit</label>
          <div class="hhr-stars-input">
            ${[1,2,3,4,5,6].map(i=>`
              <label><input type="radio" name="friendly" value="${i}" ${i===6?'checked':''}><i class="hhr-star" aria-hidden="true"></i></label>`).join('')}
          </div>
        </div>
        <div class="hhr-field" style="display:grid;grid-template-columns:160px 1fr;gap:16px;align-items:center;margin-bottom:14px;">
          <label style="color:#e5e7eb">Hilfsbereitschaft</label>
          <div class="hhr-stars-input">
            ${[1,2,3,4,5,6].map(i=>`
              <label><input type="radio" name="helpful" value="${i}" ${i===6?'checked':''}><i class="hhr-star" aria-hidden="true"></i></label>`).join('')}
          </div>
        </div>
        <div class="hhr-field" style="display:grid;grid-template-columns:160px 1fr;gap:16px;align-items:start;margin:16px 0;">
          <label style="color:#e5e7eb">Kommentar</label>
          <textarea name="comment" maxlength="800" placeholder="Wie war dein Eindruck von diesem Spieler?" style="width:100%;min-height:140px;resize:vertical;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);color:#e5e7eb;outline:none;"></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;">
          <button type="button" class="btn btn-neutral-2 rounded-12" data-cancel>Abbrechen</button>
          <button type="submit" class="btn btn-primary rounded-12">Bewertung speichern</button>
        </div>
      </form>`;
  }

  function wireOpenButton(userId) {
    const btn = document.getElementById('hhr-open');
    if (!btn || btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', () => {
      const { panel, close } = openModal(modalFormHTML(userId));
      const form = panel.querySelector('#hhr-form');

      initStarsWidget(form);
      panel.querySelector('[data-cancel]')?.addEventListener('click', close);

      form.addEventListener('submit', async (e) => {
        e.preventDefault(); e.stopPropagation();           // ← kein Reload
        if (form.dataset.submitting === '1') return;
        form.dataset.submitting = '1';

        const submitBtn = form.querySelector('[type="submit"]');
        const oldText = submitBtn.textContent;
        submitBtn.disabled = true; submitBtn.textContent = 'Speichere…';

        const payload = {
          ratee_id: userId,
          play: Number(form.play?.value || 0),
          friendly: Number(form.friendly?.value || 0),
          helpful: Number(form.helpful?.value || 0),
          comment: (form.comment?.value || '').trim()
        };

        try {
          const r = await fetch(`${APP_BASE}/api/ratings/submit.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify(payload)
          });
          const j = await r.json();
          if (!j.ok) throw new Error(j.error || 'Fehler beim Speichern');

          close();                              // Popup zu
          await loadSummary(userId);            // UI live aktualisieren
          await loadList(userId);
          if (window.showToast) showToast('Bewertung gespeichert');
        } catch (err) {
          alert(err.message || 'Fehler beim Speichern');
        } finally {
          form.dataset.submitting = '0';
          submitBtn.disabled = false; submitBtn.textContent = oldText;
        }
      }, { capture: true }); // sicher intercepten
    });
  }

  // ---- Public API ----
  window.HHR = {
    init(userId) {
      loadSummary(userId).then(() => loadList(userId));
      wireOpenButton(userId);
    }
  };
})();

(function(){
  const BASE = window.APP_BASE || "";
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || "";

  // Mini-helpers
  const q  = (s, el=document) => el.querySelector(s);
  const qa = (s, el=document) => Array.from(el.querySelectorAll(s));

  async function postForm(url, data){
    const fd = new FormData();
    for (const [k,v] of Object.entries(data)) fd.append(k, v);
    if (CSRF) fd.append('csrf', CSRF);
    const res = await fetch(url, { method:'POST', body: fd, credentials:'same-origin' });
    const txt = await res.text();
    let j=null; try { j = JSON.parse(txt); } catch(_) {}
    if (!res.ok || !j) throw new Error(`HTTP ${res.status}: ${txt.slice(0,300)}`);
    if (!j.ok) throw new Error(j.error || 'error');
    return j;
  }

  async function getJSON(url){
    const res = await fetch(url, { credentials:'same-origin' });
    const txt = await res.text();
    let j=null; try { j = JSON.parse(txt); } catch(_) {}
    if (!res.ok || !j) throw new Error(`HTTP ${res.status}: ${txt.slice(0,300)}`);
    if (!j.ok) throw new Error(j.error || 'error');
    return j;
  }

  // Simple Modal
  function openModal({title, content, buttons=[]}){
    const wrap = document.createElement('div');
    wrap.className = 'hh-modal-wrap';
    wrap.innerHTML = `
      <div class="hh-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:10000;"></div>
      <div class="hh-modal" role="dialog" aria-modal="true"
           style="position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);
                  background:#111;color:#eee;max-width:680px;width:calc(100% - 24px);
                  border-radius:12px;box-shadow:0 10px 35px rgba(0,0,0,.5);z-index:10001;">
        <div style="padding:14px 16px;border-bottom:1px solid #222;font-weight:600;">${title||'Bearbeiten'}</div>
        <div class="hh-modal-body" style="padding:14px 16px;max-height:60vh;overflow:auto;"></div>
        <div class="hh-modal-actions" style="padding:12px 16px;border-top:1px solid #222;text-align:right;"></div>
      </div>
    `;
    const body = wrap.querySelector('.hh-modal-body');
    const acts = wrap.querySelector('.hh-modal-actions');
    if (content instanceof Node) body.appendChild(content); else body.innerHTML = content;

    const close = () => wrap.remove();
    wrap.querySelector('.hh-modal-backdrop').addEventListener('click', close);

    const btns = buttons.length ? buttons : [{label:'Schließen', variant:'secondary', onClick: close}];
    btns.forEach(b=>{
      const btn = document.createElement('button');
      btn.textContent = b.label || 'OK';
      btn.type = 'button';
      btn.style.cssText = 'margin-left:8px;padding:8px 12px;border-radius:8px;border:1px solid #333;background:#1b1b1b;color:#eee;cursor:pointer;';
      if (b.variant === 'primary') btn.style.background = '#2a2a2a';
      btn.addEventListener('click', async ()=>{
        try {
          if (typeof b.onClick === 'function') {
            const res = await b.onClick({close});
            if (res !== false) close();
          } else {
            close();
          }
        } catch (e) {
          console.error(e);
          alert(e.message || e);
        }
      });
      acts.appendChild(btn);
    });

    document.body.appendChild(wrap);
    return { close, el: wrap };
  }

  function findPostContentEl(postId){
    return q(`[data-post-id="${postId}"] .hh-post-content`) ||
           q(`.hh-post[data-post-id="${postId}"] .hh-post-content`);
  }
  function findCommentContentEl(commentId){
    return q(`[data-comment-id="${commentId}"] .hh-comment-content`) ||
           q(`.hh-comment[data-comment-id="${commentId}"] .hh-comment-content`);
  }

  function ensureEditedBadge(elMetaContainer, type, id){
    if (!elMetaContainer) return;
    if (elMetaContainer.querySelector('.hh-edited-badge')) return;
    const a = document.createElement('a');
    a.href = '#';
    a.className = 'hh-edited-badge';
    a.dataset.type = type;
    a.dataset.id = String(id);
    a.textContent = 'Bearbeitet';
    a.style.marginLeft = '8px';
    a.title = 'Bearbeitet – Verlauf ansehen';
    elMetaContainer.appendChild(a);
  }
function openReportModal(type, id){
  const reasons = [
    ['harassment','Mobbing, Belästigung oder Missbrauch'],
    ['selfharm','Suizid oder Selbstverletzung'],
    ['violence','Gewaltdarstellung, Hass oder verstörender Inhalt'],
    ['scam','Scam, Betrug oder Fehlinformationen'],
    ['copyright','Urheberrechtsverletzung'],
    ['adult','Nicht jugendfreier Inhalt'],
    ['under18','Problem involviert Minderjährige'],
    ['illegal','Als rechtswidrig melden'],
    ['dislike','Gefällt mir einfach nicht']
  ];
  const box = document.createElement('div');
  box.innerHTML = `
    <p class="text-sm opacity-80" style="margin-bottom:10px">Warum meldest du diesen ${type==='post'?'Beitrag':'Kommentar'}?</p>
    <div style="display:flex;flex-direction:column;gap:8px;max-height:45vh;overflow:auto">
      ${reasons.map(([v,l])=>`<label style="display:flex;gap:8px;align-items:center"><input type="radio" name="hh-reason" value="${v}"><span>${l}</span></label>`).join('')}
    </div>
    <div style="margin-top:12px"><textarea class="hh-note" rows="3" placeholder="Optionale Nachricht (max. 500) " style="width:100%;background:#0e0e0e;border:1px solid #222;border-radius:8px;color:#eee;padding:10px"></textarea></div>
  `;
  openModal({
    title:'Melden',
    content: box,
    buttons: [
      {label:'Abbrechen'},
      {label:'Senden', variant:'primary', onClick: async ()=>{
        const reason = box.querySelector('input[name="hh-reason"]:checked')?.value;
        const note   = box.querySelector('.hh-note')?.value?.trim() || '';
        if (!reason) { alert('Bitte einen Grund auswählen.'); return false; }
        const fd = new FormData();
        fd.set('csrf', CSRF);
        fd.set('type', type);
        fd.set('id', String(id));
        fd.set('reason', reason);
        if (note) fd.set('note', note);
const j = await postForm(`${BASE}/api/wall/report.php`, {
  type, id: String(id), reason, note
});
if (j.under_review) {
  if (type==='post') {
    const art = document.querySelector(`.hh-post[data-post-id="${id}"]`);
    if (art) art.outerHTML = `<article class="hh-card" data-post-id="${id}" style="padding:16px;text-align:center;opacity:.8">Beitrag wird geprüft.</article>`;
  } else {
    const el = document.querySelector(`[data-comment-id="${id}"] .hh-comment-content`);
    if (el) el.innerHTML = '<p class="hh-text text-sm italic opacity-70">Kommentar wird geprüft.</p>';
  }
}

      } }
    ]
  });
}
document.addEventListener('click', e=>{
  const p = e.target.closest('[data-report-post]');
  if (p){ e.preventDefault(); openReportModal('post', parseInt(p.dataset.postId||'0',10)); }
});
document.addEventListener('click', e=>{
  const c = e.target.closest('[data-report-comment]');
  if (c){ e.preventDefault(); openReportModal('comment', parseInt(c.dataset.commentId||'0',10)); }
});

  // Open "Vorher vs. Aktuell" viewer
  async function openEditHistory(type, id){
    const j = await getJSON(`${BASE}/api/wall/edits.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
    const before = String(j.before || '');
    const after  = String(j.after || '');

    // aktuelle Anzeige aus DOM holen (robust)
    let currentText = after;
    if (type === 'post') {
      currentText = (findPostContentEl(id)?.textContent || after || '').trim();
    } else {
      currentText = (findCommentContentEl(id)?.textContent || after || '').trim();
    }

    const content = document.createElement('div');
    content.innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <div style="font-weight:600;margin-bottom:6px;">Vorher</div>
          <pre style="white-space:pre-wrap;background:#0e0e0e;border:1px solid #222;border-radius:8px;padding:10px;min-height:80px;">${before.replace(/[&<>]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]))}</pre>
        </div>
        <div>
          <div style="font-weight:600;margin-bottom:6px;">Aktuell</div>
          <pre style="white-space:pre-wrap;background:#0e0e0e;border:1px solid #222;border-radius:8px;padding:10px;min-height:80px;">${currentText.replace(/[&<>]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]))}</pre>
        </div>
      </div>
      <div style="margin-top:10px;font-size:12px;opacity:.8;">Zuletzt bearbeitet: ${j.edited_at || ''} — von ${j.editor?.name || ('ID '+(j.editor?.id||''))}</div>
    `;
    openModal({ title: 'Änderungsverlauf', content });
  }

  // Click: "Bearbeitet" Badge
  document.addEventListener('click', (e)=>{
    const a = e.target.closest('.hh-edited-badge');
    if (!a) return;
    e.preventDefault();
    const type = a.dataset.type;
    const id   = parseInt(a.dataset.id||'0',10);
    if (!type || !id) return;
    openEditHistory(type, id).catch(err=>alert(err.message||String(err)));
  });

  // Click: Post bearbeiten
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.hh-btn-edit-post');
    if (!btn) return;
    e.preventDefault();
    const postId = parseInt(btn.dataset.postId || btn.getAttribute('data-post-id') || '0', 10);
    if (!postId) return;

    const el = findPostContentEl(postId);
    const current = (el?.textContent || '').trim();

    const ta = document.createElement('textarea');
    ta.value = current;
    ta.style.cssText = 'width:100%;min-height:160px;background:#0e0e0e;border:1px solid #222;border-radius:8px;color:#eee;padding:10px;';

    openModal({
      title: 'Post bearbeiten',
      content: ta,
      buttons: [
        { label: 'Abbrechen', variant:'secondary' },
        { label: 'Speichern', variant:'primary', onClick: async ({close})=>{
            const body = ta.value.trim();
            const j = await postForm(`${BASE}/api/wall/edit_post.php`, { post_id: String(postId), body });
            // Nur Text-Absatz aktualisieren (Media bleibt)
            if (el) {
              let p = el.querySelector('.hh-text');
              if (!p) { p = document.createElement('p'); p.className = 'hh-text'; el.prepend(p); }
              p.innerHTML = (j.body || body)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/\n/g,'<br>');
            }
            const meta = el?.closest('[data-post-id]')?.querySelector('.hh-post-meta') || el?.parentElement;
            if (j.has_edits) ensureEditedBadge(meta, 'post', postId);
          } }
      ]
    });
  });

  // Click: Kommentar bearbeiten
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.hh-btn-edit-comment');
    if (!btn) return;
    e.preventDefault();
    const commentId = parseInt(btn.dataset.commentId || btn.getAttribute('data-comment-id') || '0', 10);
    if (!commentId) return;

    const el = findCommentContentEl(commentId);
    const current = (el?.textContent || '').trim();

    const ta = document.createElement('textarea');
    ta.value = current;
    ta.style.cssText = 'width:100%;min-height:140px;background:#0e0e0e;border:1px solid #222;border-radius:8px;color:#eee;padding:10px;';

    openModal({
      title: 'Kommentar bearbeiten',
      content: ta,
      buttons: [
        { label: 'Abbrechen', variant:'secondary' },
        { label: 'Speichern', variant:'primary', onClick: async ({close})=>{
            const body = ta.value.trim();
            const j = await postForm(`${BASE}/api/wall/edit_comment.php`, { comment_id: String(commentId), body });
            if (el) {
              let p = el.querySelector('.hh-text');
              if (!p) { p = document.createElement('p'); p.className = 'hh-text text-sm text-w-neutral-3'; el.prepend(p); }
              p.innerHTML = (j.body || body)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/\n/g,'<br>');
            }
            const meta = el?.closest('[data-comment-id]')?.querySelector('.hh-comment-meta') || el?.parentElement;
            if (j.has_edits) ensureEditedBadge(meta, 'comment', commentId);
          } }
      ]
    });
  });

  // --- DELETE COMMENT (Inhalt leeren, Platzhalter anzeigen) ---
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.hh-btn-delete-comment');
    if (!btn) return;
    e.preventDefault();
    const commentId = parseInt(btn.dataset.commentId || '0', 10);
    if (!commentId) return;

    openModal({
      title: 'Kommentar löschen?',
      content: 'Der Inhalt wird entfernt und durch einen Hinweis ersetzt. Fortfahren?',
      buttons: [
        { label: 'Abbrechen', variant: 'secondary' },
        { label: 'Löschen', variant: 'primary', onClick: async ({close})=>{
            await postForm(`${BASE}/api/wall/comment_delete.php`, { comment_id: String(commentId) });
            const wrap = btn.closest('[data-comment-id]');
            if (wrap) {
              const content = wrap.querySelector('.hh-comment-content');
              if (content) {
                content.innerHTML = '<p class="hh-text text-sm italic opacity-70">Dieses Kommentar wurde entfernt.</p>';
              }
              wrap.querySelectorAll('.hh-btn-edit-comment, .hh-btn-delete-comment').forEach(b=>{
                b.setAttribute('disabled','disabled');
                b.classList.add('opacity-60','pointer-events-none');
              });
            }
          } }
      ]
    });
  });

  // --- DELETE POST (Post + zugehörige Kommentare soft löschen) ---
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.hh-btn-delete-post');
    if (!btn) return;
    e.preventDefault();
    const postId = parseInt(btn.dataset.postId || '0', 10);
    if (!postId) return;

    openModal({
      title: 'Post löschen?',
      content: 'Der Post wird gelöscht. Alle zugehörigen Kommentare werden ebenfalls gelöscht.',
      buttons: [
        { label: 'Abbrechen', variant: 'secondary' },
        { label: 'Löschen', variant: 'primary', onClick: async ({close})=>{
            await postForm(`${BASE}/api/wall/post_delete.php`, { post_id: String(postId) });
            const art = document.querySelector(`.hh-post[data-post-id="${postId}"]`);
            if (art) art.remove();
          } }
      ]
    });
  });

})();

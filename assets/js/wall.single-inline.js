// /assets/js/wall.single-inline.js
(function () {
  const root = document.querySelector('[data-wall-single="1"]');
  if (!root) return;

  const postId = +root.dataset.postId;
  const listEl = root.querySelector('.hh-comments-list');
  const csrf   = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const ME     = +window.CURRENT_USER_ID || 0;

  // ---------- Utils
  const esc = (s) => (s == null ? '' : String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;'));

  const fmtTime = (iso) => {
    if (!iso) return '';
    try { return new Date(iso.replace(' ', 'T') + 'Z').toLocaleString(); }
    catch { return iso; }
  };

  const api = (url, data) => fetch(url, {
    method: 'POST',
    body: data instanceof FormData ? data : (() => { const fd = new FormData(); for (const k in data) fd.append(k, data[k]); return fd; })(),
    headers: csrf ? {'X-CSRF-Token': csrf} : {}
  }).then(r => r.json());

  // ---------- Rendering
  const renderLikeBar = (c) => {
    const btn = (c.like_button_html || '').trim();
    const sum = (c.like_summary_html || '').trim();
    return `<div class="mt-1 flex items-center gap-2 like-bar">
      ${btn}
      ${sum ? `<div class="reaction-summary">${sum}</div>` : ''}
    </div>`;
  };

  const canEdit = (c) => (+c.user_id === ME) || root.dataset.canModerate === '1';

  const renderOne = (c, depth = 0) => {
    const avatar = esc(c.avatar || '/assets/images/avatar-default.png');
    const user   = esc(c.username || 'Mitglied');
    const when   = fmtTime(c.created_at);
    const contentHtml = (c.content_html && c.content_html.trim())
      ? c.content_html
      : `<p class="whitespace-pre-wrap">${esc(c.content_plain || '')}</p>`;

    const deleted = !!c.content_deleted_at;
    const underReview = !!c.under_review;

    const tools = deleted ? '' : `
      <div class="text-xs text-w-neutral-4 flex gap-3 mt-1">
        <button class="hh-btn-comment-reply" data-cid="${c.id}">Antworten</button>
        ${canEdit(c)
          ? `<button class="hh-btn-comment-edit" data-cid="${c.id}">Bearbeiten</button>
             <button class="hh-btn-comment-delete text-red-400" data-cid="${c.id}">Löschen</button>`
          : ''}
      </div>`;

    const badge = deleted
      ? `<div class="text-sm text-w-neutral-4 italic">Kommentar gelöscht</div>`
      : (underReview ? `<div class="text-sm text-yellow-400">In Prüfung</div>` : '');

    const replies = Array.isArray(c.children) && c.children.length
      ? `<div class="mt-2 space-y-2">${c.children.map(ch => renderOne(ch, depth + 1)).join('')}</div>`
      : '';

    return `<article class="hh-comment flex gap-3 ${depth ? 'ml-10' : ''}" data-comment-id="${c.id}">
      <img class="w-9 h-9 rounded-full object-cover" src="${avatar}" alt="">
      <div class="flex-1 min-w-0">
        <div class="bg-w-neutral-8 rounded-xl px-3 py-2">
          <div class="text-sm font-medium">${user}</div>
          <div class="prose prose-invert max-w-none break-words">${contentHtml}</div>
        </div>
        <div class="text-xs text-w-neutral-4 mt-1">${esc(when)}</div>
        ${renderLikeBar(c)}
        ${badge}
        ${tools}
        <div class="hh-reply-slot"></div>
      </div>
    </article>`;
  };

  const draw = (comments) => {
    listEl.innerHTML = comments.map(c => renderOne(c, 0)).join('') || `<div class="text-w-neutral-4">Noch keine Kommentare.</div>`;
  };

  // ---------- Load
  const loadComments = () => {
    fetch(`/api/wall/comments.php?post_id=${postId}`)
      .then(r => r.json())
      .then(j => { if (j && j.ok) draw(j.comments || []); })
      .catch(() => {});
  };

  // ---------- Top-Level Composer
  const topForm = root.querySelector('.hh-comment-form--inline');
  if (topForm) {
    topForm.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const fd = new FormData(topForm);
      api('/api/wall/comment_create.php', fd).then(j => {
        if (j && j.ok) { topForm.reset(); loadComments(); }
        else { alert((j && (j.error || j.msg)) || 'Fehler beim Senden.'); }
      }).catch(() => alert('Netzwerkfehler.'));
    });
  }

  // ---------- Delegation: Reply / Edit / Delete
  root.addEventListener('click', (ev) => {
    const btnReply  = ev.target.closest('.hh-btn-comment-reply');
    const btnEdit   = ev.target.closest('.hh-btn-comment-edit');
    const btnDelete = ev.target.closest('.hh-btn-comment-delete');

    if (btnReply) {
      const cid  = +btnReply.dataset.cid;
      const node = root.querySelector(`[data-comment-id="${cid}"] .hh-reply-slot`);
      if (!node) return;

      if (node.firstElementChild && node.firstElementChild.matches('form.hh-reply-form')) {
        node.innerHTML = ''; return;
      }
      node.innerHTML = `
        <form class="hh-reply-form mt-2 flex gap-2 items-start"
              action="/api/wall/comment_create.php" method="post">
          <input type="hidden" name="post_id" value="${postId}">
          <input type="hidden" name="parent_comment_id" value="${cid}">
          <textarea name="content" rows="2" required
            class="w-full rounded-lg bg-w-neutral-8 text-w-neutral-1 p-2"
            placeholder="Antwort schreiben …"></textarea>
          <button type="submit" class="hh-btn px-3 py-2 rounded-lg bg-w-primary-6 text-white shrink-0">Senden</button>
        </form>`;
    }

    if (btnEdit) {
      const cid = +btnEdit.dataset.cid;
      const article = root.querySelector(`[data-comment-id="${cid}"]`);
      if (!article) return;
      const body = article.querySelector('.prose');
      const text = body ? body.textContent : '';
      const slot = article.querySelector('.hh-reply-slot');
      if (!slot) return;
      slot.innerHTML = `
        <form class="hh-edit-form mt-2 flex gap-2 items-start"
              action="/api/wall/comment_update.php" method="post">
          <input type="hidden" name="comment_id" value="${cid}">
          <textarea name="content" rows="3" required
            class="w-full rounded-lg bg-w-neutral-8 text-w-neutral-1 p-2">${esc(text)}</textarea>
          <button type="submit" class="hh-btn px-3 py-2 rounded-lg bg-w-primary-6 text-white shrink-0">Speichern</button>
          <button type="button" class="hh-edit-cancel px-3 py-2 rounded-lg bg-w-neutral-7 text-w-neutral-2 shrink-0">Abbrechen</button>
        </form>`;
    }

    if (btnDelete) {
      const cid = +btnDelete.dataset.cid;
      if (!confirm('Kommentar löschen?')) return;
      const fd = new FormData();
      fd.append('comment_id', String(cid));
      api('/api/wall/comment_delete.php', fd).then(j => {
        if (j && j.ok) loadComments();
        else alert((j && (j.error || j.msg)) || 'Fehler beim Löschen.');
      }).catch(() => alert('Netzwerkfehler.'));
    }

    const cancel = ev.target.closest('.hh-edit-cancel');
    if (cancel) {
      const form = cancel.closest('.hh-edit-form');
      if (form && form.parentElement) form.parentElement.innerHTML = '';
    }
  });

  // Submit (Delegation) für Reply/Edit
  root.addEventListener('submit', (ev) => {
    const form = ev.target.closest('form.hh-reply-form, form.hh-edit-form');
    if (!form) return;
    ev.preventDefault();
    api(form.action, new FormData(form)).then(j => {
      if (j && j.ok) loadComments();
      else alert((j && (j.error || j.msg)) || 'Fehler beim Senden.');
    }).catch(() => alert('Netzwerkfehler.'));
  });

  // initial laden
  loadComments();
})();

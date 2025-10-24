/**
 * assets/js/wall.comments.js
 * Inline-Kommentar-Uploads (Bilder) mit Preview + Senden
 * - funktioniert für alle .hh-comment-form-Container
 * - schickt NUR files[] (keine Duplikate)
 * - lädt nach Erfolg die Kommentar-Liste des Posts neu
 */
(function(){
  'use strict';

  const BASE = window.APP_BASE || '';
  const getCSRF = () =>
    document.querySelector('meta[name="csrf"]')?.content ||
    document.querySelector('meta[name="csrf-token"]')?.content ||
    document.querySelector('meta[name="x-csrf-token"]')?.content || '';

  // Delegation für alle Kommentar-Formulare
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.hh-comment-form [data-action="submit"]');
    if (!btn) return;

    const form = btn.closest('.hh-comment-form');
    if (!form) return;

    e.preventDefault();

    const ta   = form.querySelector('textarea');
    const text = (ta?.value || '').trim();
    const fileInput = form.querySelector('.hh-file-image');
    const files = Array.from(fileInput?.files || []);
    const endpoint = form.getAttribute('data-endpoint') || `${BASE}/api/wall/comment_create.php`;
    const postId   = form.getAttribute('data-post-id');

    if (!text && files.length === 0) { ta?.focus(); return; }

    btn.disabled = true;

    try {
      const fd = new FormData();
      fd.set('post_id', postId);
      fd.set('text', text);
      fd.set('content', text);
      fd.set('message', text);
      fd.set('body', text);
      const CSRF = getCSRF();
      if (CSRF) fd.set('csrf', CSRF);

      // nur files[] (keine 'file'-Duplikate)
      files.forEach(f => fd.append('files[]', f, f.name));

      const res = await fetch(endpoint, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF': CSRF || '' }
      });
      const j = await res.json().catch(()=> ({}));
      if (!res.ok || j?.ok === false) throw new Error(j?.error || `http_${res.status}`);

      // UI zurücksetzen
      ta.value = '';
      fileInput.value = '';
      renderCommentPreview(form, []); // leer

      // Kommentar-Liste neu laden
      const wrap = form.closest('.hh-comments');
      if (wrap) {
        const list  = wrap.querySelector('.hh-comment-list');
        const ep    = wrap.getAttribute('data-endpoint');
        if (list && ep) {
          const r = await fetch(ep, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const html = await r.text();
          list.innerHTML = html;
        }
      }

    } catch (err) {
      console.error('comment submit failed:', err);
      alert('Kommentar konnte nicht gesendet werden.');
    } finally {
      btn.disabled = false;
    }
  });

  // Datei-Pick -> Preview
  document.addEventListener('change', (e) => {
    const inp = e.target.closest('.hh-comment-form .hh-file-image');
    if (!inp) return;
    const form = inp.closest('.hh-comment-form');
    const files = Array.from(inp.files || []);
    renderCommentPreview(form, files);
  });

  function renderCommentPreview(form, files){
    const prevBox  = form.querySelector('.hh-previews'); // vorhandener Container
    if (!prevBox) return;
    prevBox.innerHTML = '';

    if (!files || files.length === 0) { prevBox.classList.add('hidden'); return; }
    prevBox.classList.remove('hidden');

    // Single: hero
    if (files.length === 1) {
      const url = URL.createObjectURL(files[0]);
      prevBox.innerHTML =
        `<div id="hh-com-preview" class="single">
           <div id="hh-com-preview-grid">
             <div class="hh-wall-media">
               <div class="hh-wall-media-1">
                 <img class="hh-media-img" src="${url}" data-full="${url}" alt="">
               </div>
             </div>
           </div>
         </div>`;
      return;
    }

    // Multi-Grid
    let html = `<div id="hh-com-preview" class="multi"><div id="hh-com-preview-grid"><div class="hh-wall-media"><div class="hh-wall-media-grid">`;
    files.forEach(f => {
      const url = URL.createObjectURL(f);
      html += `<div class="hh-wall-media-item"><img class="hh-media-img" src="${url}" data-full="${url}" alt=""></div>`;
    });
    html += `</div></div></div></div>`;
    prevBox.innerHTML = html;
  }

})();

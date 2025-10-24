/**
 * assets/js/wall.composer-modal.js
 * Facebook-ähnlicher Composer: Fake-Input -> Modal mit echter Textarea
 * - Öffnen/Schließen mit Animation
 * - CSRF robust (Meta + Hidden + Header), Cookies via credentials
 * - Mehrfach-Anhänge (Bilder/Videos) mit Preview + Entfernen
 * - Sendet redundante Feldnamen (text|content|message|body, visibility|audience|scope)
 * - WICHTIG: Dateien nur als files[] (kein 'file' mehr, um Doppel-Uploads zu vermeiden)
 * - Events: 'hh:wall:post:html' und 'hh:wall:post:created'
 */
 (function(){
  function setHHVh(){
    // echte Höhe des Viewports in px (gegen Mobile-URL-Bar)
    document.documentElement.style.setProperty('--hh100vh', window.innerHeight + 'px');
  }
  setHHVh();
  window.addEventListener('resize', setHHVh);
})();

(function () {
  'use strict';

  // ---- Config --------------------------------------------------------------
  const BASE = window.APP_BASE || '';
  const ENDPOINT_CREATE = `${BASE}/api/wall/post_create.php`;
  const MAX_FILES = 10;

  // ---- Helpers -------------------------------------------------------------
  const $ = (sel, root = document) => root.querySelector(sel);

  function getCSRF() {
    return document.querySelector('meta[name="csrf"]')?.content
        || document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('meta[name="x-csrf-token"]')?.content
        || document.querySelector('#hh-composer-form input[name="csrf"]')?.value
        || '';
  }

  function dispatch(name, detail) {
    try { window.dispatchEvent(new CustomEvent(name, { detail })); }
    catch (_) { /* ignore */ }
  }

  function escHtml(str){
    return (str ?? '').replace(/[&<>"']/g, s => (
      { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[s]
    ));
  }

  // ---- Elements ------------------------------------------------------------
  const modal       = $('#hh-composer-modal');
  const backdrop    = $('#hh-composer-backdrop');
  const btnOpen     = $('#hh-open-composer');
  const btnClose    = $('#hh-composer-close');
  const form        = $('#hh-composer-form');
  const textArea    = $('#hh-composer-text');
  const count       = $('#hh-composer-count');
  const btnSubmit   = $('#hh-composer-submit');
  const fileInput   = $('#hh-composer-file');
  const previewBox  = $('#hh-composer-preview');
  const previewGrid = $('#hh-composer-preview-grid');
  const clearMedia  = $('#hh-composer-clear-media');
  const visibility  = $('#hh-composer-visibility');

  if (!btnOpen || !modal || !form || !textArea) return;

  // ---- State ---------------------------------------------------------------
  let currentFiles = [];  // Array<File>

  // ---- Modal open/close ----------------------------------------------------
  function openModal(prefill = '') {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (prefill) textArea.value = prefill;
    updateCount();
    setTimeout(() => textArea?.focus(), 40);
  }

  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    form.reset();
    clearPreview();
    updateCount();
  }

  btnOpen.addEventListener('click', () => openModal(''));
  btnClose?.addEventListener('click', closeModal);
  backdrop?.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });

  // ---- Counter -------------------------------------------------------------
  function updateCount() {
    if (!count) return;
    count.textContent = (textArea.value || '').length.toString();
  }
  textArea.addEventListener('input', updateCount);

  // ---- Media preview -------------------------------------------------------
  function setHidden(el, hide){ if (el) el.classList.toggle('hidden', !!hide); }

  function syncInputFromState(){
    if (!fileInput) return;
    const dt = new DataTransfer();
    currentFiles.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
  }

  function removeAt(index){
    currentFiles.splice(index, 1);
    syncInputFromState();
    renderPreview();
  }

  function clearPreview() {
    currentFiles = [];
    syncInputFromState();
    if (previewGrid) previewGrid.innerHTML = '';
    setHidden(previewBox, true);
  }

  // Baut ein Close-Button-Overlay
  function buildCloseBtn(idx){
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'absolute top-2 right-2 bg-black/60 text-white rounded-full w-7 h-7 grid place-items-center text-sm';
    btn.setAttribute('aria-label', 'Entfernen');
    btn.textContent = '×';
    btn.addEventListener('click', (e) => { e.preventDefault(); removeAt(idx); });
    return btn;
  }

function renderPreview() {
  if (!previewGrid) return;

  previewGrid.innerHTML = '';

  if (!currentFiles.length) {
    previewBox?.classList.add('hidden');
    previewBox?.classList.remove('single','multi');
    return;
  }
  previewBox?.classList.remove('hidden');

  // SINGLE --------------------------------------------------------------
  if (currentFiles.length === 1) {
    previewBox?.classList.add('single');
    previewBox?.classList.remove('multi');

    // remove evtl. Tailwind grid-Klassen vom Markup
    previewGrid.className = ''; // wichtig für volle Breite

    const f   = currentFiles[0];
    const url = URL.createObjectURL(f);

    previewGrid.innerHTML =
      `<div class="hh-wall-media">
         <div class="hh-wall-media-1">
           <img class="hh-media-img" src="${url}" data-full="${url}" alt="${(f.name||'').replace(/["&<>]/g,s=>({'"':'&#39;','"':'&quot;','&':'&amp;','<':'&lt;','>':'&gt;'}[s]))}">
         </div>
       </div>`;
    return;
  }

  // MULTI (>=2) ---------------------------------------------------------
  previewBox?.classList.add('multi');
  previewBox?.classList.remove('single');

  // setze bewusst KEINE Tailwind grid-classes – CSS oben steuert display:grid
  previewGrid.className = '';

  let html = '<div class="hh-wall-media"><div class="hh-wall-media-grid">';
  currentFiles.forEach(f => {
    const url = URL.createObjectURL(f);
    html += `<div class="hh-wall-media-item">
               <img class="hh-media-img" src="${url}" data-full="${url}" alt="${(f.name||'').replace(/["&<>]/g,s=>({'"':'&#39;','"':'&quot;','&':'&amp;','<':'&lt;','>':'&gt;'}[s]))}">
             </div>`;
  });
  html += '</div></div>';
  previewGrid.innerHTML = html;
}


  fileInput?.addEventListener('change', () => {
    const files = Array.from(fileInput.files || []);
    // einfache Begrenzung + nur Bilder/Videos
    currentFiles = files
      .filter(f => /^image\//.test(f.type) || /^video\//.test(f.type))
      .slice(0, MAX_FILES);
    renderPreview();
  });

  clearMedia?.addEventListener('click', (e) => {
    e.preventDefault();
    clearPreview();
  });

  // ---- Submit --------------------------------------------------------------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const text = (textArea.value || '').trim();
    const vis  = visibility?.value || 'public';

    if (!text && currentFiles.length === 0) {
      textArea.focus();
      return;
    }

    btnSubmit.disabled = true;
    const CSRF = getCSRF();

    try {
      const fd = new FormData();

      // Text in ALLEN üblichen Feldern setzen
      fd.set('text', text);
      fd.set('content', text);
      fd.set('message', text);
      fd.set('body', text);

      // Sichtbarkeit + Aliase
      fd.set('visibility', vis);
      fd.set('audience', vis);
      fd.set('scope', vis);

      // Typ (manche Endpoints nutzen das)
      fd.set('type', 'post');

      // CSRF (Body)
      if (CSRF) fd.set('csrf', CSRF);

      // IMPORTANT: Nur files[] schicken (KEIN 'file' -> sonst Duplikate auf Server)
      currentFiles.forEach((f) => {
        fd.append('files[]', f, f.name);
      });

      const res = await fetch(ENDPOINT_CREATE, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF': CSRF || ''
        }
      });

      const j = await res.json().catch(() => ({}));
      if (!res.ok || j?.ok === false) {
        const errCode = j?.error || `http_${res.status}`;
        throw new Error(errCode);
      }

      // Erfolgreich: UI informieren
      const html = j?.html || j?.post_html || j?.post?.html || null;
      if (html) dispatch('hh:wall:post:html', { html, data: j });
      dispatch('hh:wall:post:created', j);

      closeModal();

      // Fallback, falls kein JS-Listener vorhanden:
      setTimeout(() => {
        if (!window.WALL || typeof window.WALL.prependPost !== 'function') location.reload();
      }, 80);

    } catch (err) {
      console.error('Composer error:', err);
      alert(`Konnte den Beitrag nicht senden.\nError: ${err.message || err}`);
    } finally {
      btnSubmit.disabled = false;
    }
  });

})();

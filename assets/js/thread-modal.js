// assets/js/thread-modal.js
// Saubere Initialisierung für das "Neues Thema"-Modal.
// Verhindert doppelte Filepicker-Öffnung, zeigt einfache Previews,
// bindet KEINE click()s an Labels (Label öffnet nativ).

(function () {
  if (window.__threadModalInit) return;
  window.__threadModalInit = true;

  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  const modal   = $('#threadModal');
  if (!modal) return;

  const form    = $('#newThreadForm', modal);
  const close   = $('#closeThreadModal', modal);
  const cancel  = $('#cancelThreadModal', modal);
  const preview = $('#threadPreview', modal);

  const inputImage = $('#threadImage', modal);
  const inputVideo = $('#threadVideo', modal);

  // --- Modal open/close helpers ---
  function openModal()  { modal.setAttribute('aria-hidden', 'false'); modal.style.display = 'block'; }
  function closeModal() { modal.setAttribute('aria-hidden', 'true');  modal.style.display = 'none';  }

  // Optional: Wenn du irgendwo einen "Neues Thema" Button hast, gib ihm data-open-thread
  document.addEventListener('click', (ev) => {
    const trg = ev.target.closest('[data-open-thread]');
    if (!trg) return;
    ev.preventDefault();
    openModal();
  });

  [close, cancel].forEach(btn => btn?.addEventListener('click', (e) => {
    e.preventDefault();
    closeModal();
  }));

  // --- Preview helpers ---
  function clearPreview() {
    if (preview) preview.innerHTML = '';
  }
  function renderImage(file) {
    if (!preview) return;
    const url = URL.createObjectURL(file);
    const wrap = document.createElement('div');
    wrap.className = 'rounded-12 overflow-hidden border border-[rgba(255,255,255,.08)]';
    const img = document.createElement('img');
    img.src = url;
    img.loading = 'lazy';
    img.style.maxWidth = '100%';
    wrap.appendChild(img);
    preview.appendChild(wrap);
  }
  function renderVideo(file) {
    if (!preview) return;
    const url = URL.createObjectURL(file);
    const wrap = document.createElement('div');
    wrap.className = 'rounded-12 overflow-hidden border border-[rgba(255,255,255,.08)]';
    const v = document.createElement('video');
    v.setAttribute('controls', '');
    v.setAttribute('preload', 'metadata');
    v.src = url;
    v.style.maxWidth = '100%';
    wrap.appendChild(v);
    preview.appendChild(wrap);
  }

  // --- WICHTIG: Keine click()/pointerup Handler auf Labels! ---
  // Nur auf Auswahl reagieren.
  inputImage?.addEventListener('change', () => {
    clearPreview();
    const f = inputImage.files && inputImage.files[0];
    if (f) renderImage(f);
  });

  inputVideo?.addEventListener('change', () => {
    clearPreview();
    const f = inputVideo.files && inputVideo.files[0];
    if (f) {
      renderVideo(f);
      // Falls du ein Trim-Modal hast, starte es HIER – nicht auf Label-Klick.
      // window.openTrimModal && window.openTrimModal(f, { input: inputVideo });
    }
  });

  // Optional: nach Submit Previews leeren und Modal schließen, wenn gewünscht
  form?.addEventListener('submit', () => {
    // keine zusätzliche Manipulation – der Upload läuft klassisch via POST multipart/form-data
    // clearPreview(); // optional
    // closeModal();   // optional
  });

  // Safety: falls irgendwo alte Handler doppelt gebunden wurden (legacy-Code),
  // ersetze die Label-Knoten einmal durch Klone, um Listener zu löschen.
  ['openTrim'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      const clone = el.cloneNode(true);
      el.parentNode.replaceChild(clone, el);
    }
  });

})();

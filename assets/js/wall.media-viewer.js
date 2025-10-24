(function(){
  // Viewer-Root sicherstellen (bei Bedarf dynamisch erstellen)
  let root = document.getElementById('hh-media-viewer');
  if (!root) {
    root = document.createElement('div');
    root.id = 'hh-media-viewer';
    root.innerHTML = `
      <div class="hhmv-backdrop"></div>
      <div class="hhmv-panel">
        <img id="hh-media-viewer-img" alt="">
        <button type="button" class="hhmv-close" aria-label="Schließen">✕</button>
      </div>`;
    document.body.appendChild(root);
  }

  const imgEl    = root.querySelector('#hh-media-viewer-img');
  const closeBtn = root.querySelector('.hhmv-close');
  const backdrop = root.querySelector('.hhmv-backdrop');

  function openViewer(src){
    if (!src) return;
    imgEl.src = src;
    root.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeViewer(){
    root.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(()=>{ imgEl.removeAttribute('src'); }, 150);
  }

  // Klick-Delegation (Capture: true, damit es immer ankommt)
  document.addEventListener('click', function(e){
    const t = e.target;
    if (!(t instanceof Element)) return;

    // Direkt auf <img>?
    let img = null;
    if (t.matches('.hh-wall-media img')) {
      img = t;
    } else {
      // Oder ein Wrapper geklickt?
      const wrap = t.closest('.hh-wall-media-item, .hh-wall-media-1, .hh-wall-media');
      if (wrap) img = wrap.querySelector('img');
    }

    if (img) {
      // Standardverhalten verhindern (z. B. innerhalb eines Buttons/Links)
      e.preventDefault();
      const src = img.getAttribute('data-full') || img.currentSrc || img.getAttribute('src');
      openViewer(src);
    }
  }, true); //  <-- Capture!

  backdrop && backdrop.addEventListener('click', closeViewer);
  closeBtn && closeBtn.addEventListener('click', closeViewer);
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && root.classList.contains('open')) closeViewer(); });
})();

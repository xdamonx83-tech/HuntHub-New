// --- Lightbox für Bilder in Foren-Posts (figure.image > img) ---
(function(){
  if (window.__hhLightboxInit) return;
  window.__hhLightboxInit = true;

  // Overlay einmalig erzeugen
  const overlay = document.createElement('div');
  overlay.className = 'hh-lightbox-overlay';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center;z-index:10000';
  overlay.innerHTML = `
    <div class="hh-lightbox-inner" role="dialog" aria-modal="true" aria-label="Bildanzeige" style="position:relative;max-width:95vw;max-height:95vh">
      <button class="hh-lightbox-btn hh-lightbox-close" aria-label="Schließen" style="position:absolute;top:12px;right:14px;padding:.4rem .6rem;background:rgba(0,0,0,.5);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:.55rem;cursor:pointer">✕</button>
      <button class="hh-lightbox-btn hh-lightbox-prev"  aria-label="Vorheriges Bild" style="position:absolute;top:50%;left:14px;transform:translateY(-50%);padding:.4rem .6rem;background:rgba(0,0,0,.5);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:.55rem;cursor:pointer">←</button>
      <button class="hh-lightbox-btn hh-lightbox-next"  aria-label="Nächstes Bild" style="position:absolute;top:50%;right:14px;transform:translateY(-50%);padding:.4rem .6rem;background:rgba(0,0,0,.5);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:.55rem;cursor:pointer">→</button>
      <img class="hh-lightbox-img" alt="" style="max-width:95vw;max-height:95vh;display:block">
      <div class="hh-lightbox-caption" style="position:absolute;left:0;right:0;bottom:-2.2rem;color:#e5e7eb;text-align:center;font-size:.95rem;opacity:.9"></div>
    </div>`;
  document.body.appendChild(overlay);

  const imgEl = overlay.querySelector('.hh-lightbox-img');
  const capEl = overlay.querySelector('.hh-lightbox-caption');
  const btnX  = overlay.querySelector('.hh-lightbox-close');
  const btnP  = overlay.querySelector('.hh-lightbox-prev');
  const btnN  = overlay.querySelector('.hh-lightbox-next');

  let gallery = []; // [{el, src, full, alt, title}]
  let idx = 0; let scrollLocked = false;

  function lock(){ if (scrollLocked) return; document.documentElement.style.overflow='hidden'; scrollLocked=true; }
  function unlock(){ if (!scrollLocked) return; document.documentElement.style.overflow=''; scrollLocked=false; }
  function openAt(i){
    if (!gallery.length) return;
    idx = (i + gallery.length) % gallery.length;
    const it = gallery[idx];
    const url = it.full || it.src;
    imgEl.src = url;
    imgEl.alt = it.alt || '';
    capEl.textContent = it.title || it.alt || '';
    overlay.style.display = 'flex';
    lock();
    // einfache Vorladung Nachbarn
    const n1 = new Image(); n1.src = (gallery[(idx+1)%gallery.length].full || gallery[(idx+1)%gallery.length].src);
    const p1 = new Image(); p1.src = (gallery[(idx-1+gallery.length)%gallery.length].full || gallery[(idx-1+gallery.length)%gallery.length].src);
  }
  function close(){ overlay.style.display = 'none'; unlock(); imgEl.src=''; }
  function next(){ openAt(idx+1); }
  function prev(){ openAt(idx-1); }

  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  btnX.addEventListener('click', e => { e.stopPropagation(); close(); });
  btnN.addEventListener('click', e => { e.stopPropagation(); next(); });
  btnP.addEventListener('click', e => { e.stopPropagation(); prev(); });
  document.addEventListener('keydown', e => {
    if (overlay.style.display !== 'flex') return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft')  prev();
  });

  function buildGalleryFrom(container){
    const nodes = container.querySelectorAll('figure.image > img, .post-content img, .post-body img, .comment-body img');
    return Array.from(nodes)
      .filter(img => !img.closest('.avatar, .emoji, .user-badge'))
      .map(img => ({
        el: img,
        src: img.currentSrc || img.src,
        full: img.dataset.full || img.getAttribute('data-full') || img.src, // optionales Original
        alt: img.getAttribute('alt') || '',
        title: img.getAttribute('title') || ''
      }));
  }

  // Delegation: Klick direkt auf <img>
  document.addEventListener('click', e => {
    const img = e.target.closest('figure.image > img, .post-content img, .post-body img, .comment-body img');
    if (!img) return;

    // Container des jeweiligen Posts/Kommentars (Galerie-Kontext)
    const post = img.closest('.post-content, .post-body, .comment-body') || img.parentElement || document.body;
    gallery = buildGalleryFrom(post);

    const i = gallery.findIndex(g => g.el === img);
    if (i === -1) return;

    e.preventDefault(); // ggf. Link-Navigation verhindern
    openAt(i);
  });
})();

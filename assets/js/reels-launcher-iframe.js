(() => {
  if (window.__HH_REELS_IFRAME__) return;
  window.__HH_REELS_IFRAME__ = true;

  function openReelsIframe(src = '/reels.php?embed=1') {
    // ggf. vorhandenes Modal entfernen
    const old = document.getElementById('reels-iframe-modal');
    if (old) old.remove();

    const modal = document.createElement('div');
    modal.id = 'reels-iframe-modal';
    modal.innerHTML = `
      <div class="hh-reels-overlay" aria-hidden="true"></div>
      <div class="hh-reels-frame">
        <button class="hh-reels-close" aria-label="Schließen">✕</button>
        <iframe
          src="${src}"
          allow="autoplay; clipboard-write"
          allowfullscreen
          referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
      </div>
    `;
    document.body.appendChild(modal);
    document.body.classList.add('hh-modal-open');

    const close = () => {
      modal.remove();
      document.body.classList.remove('hh-modal-open');
    };
    modal.querySelector('.hh-reels-overlay').addEventListener('click', close);
    modal.querySelector('.hh-reels-close').addEventListener('click', close);
    // ESC schließt
    document.addEventListener('keydown', function onKey(e){
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); }
    });
  }

  function bind(root = document) {
    root.querySelectorAll('[data-reels-open]').forEach((el) => {
      if (el.__hhBound) return;
      el.__hhBound = true;
      el.addEventListener('click', (e) => {
        e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
        // Falls der Trigger in einem <a href="#..."> steckt → href neutralisieren, damit kein Scroll passiert
        const a = el.closest('a[href^="#"], a[href*="#reels"]');
        if (a) a.removeAttribute('href');
        openReelsIframe('/reels.php?embed=1');
        return false;
      });
    });
  }

  bind();
  new MutationObserver((ms) => {
    for (const m of ms) m.addedNodes?.forEach((n) => n.nodeType === 1 && bind(n));
  }).observe(document.documentElement, { childList: true, subtree: true });

  // Optional globale API
  window.HHReels = { open: () => openReelsIframe('/reels.php?embed=1') };
})();

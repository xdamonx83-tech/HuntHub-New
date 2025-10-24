(() => {
  if (window.__HH_REELS_LAUNCHER__) return;
  window.__HH_REELS_LAUNCHER__ = true;

  // Lädt CSS/JS nur falls noch nicht da – keine Duplikate.
  function ensureReelsAssets() {
    return new Promise((resolve) => {
      if (window.HHReels?.open) return resolve();

      // CSS einmalig anhängen (falls dein reels.css schon global geladen wird -> diesen Block weglassen)
      if (!document.querySelector('link[data-reels-css]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = '/assets/styles/reels.css?v=1.96';
        l.setAttribute('data-reels-css', '');
        document.head.appendChild(l);
      }

      // Viewer JS nachladen
      const s = document.createElement('script');
      s.src = '/assets/js/reels-viewer.js?v=1.94';
      s.defer = true;
      s.onload = () => resolve();
      document.head.appendChild(s);
    });
  }

  async function openReels() {
    await ensureReelsAssets();
    // ruft die globale API aus reels-viewer.js auf
    if (window.HHReels?.open) window.HHReels.open(0);
  }

  // Ein Handler für ALLE Klicks – im Capture-Modus, damit wir Anker-Scroll zuverlässig verhindern
  function onClick(e) {
    const trigger = e.target.closest?.('[data-reels-open]');
    if (!trigger) return;

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    // Falls der Trigger in einem <a href="#..."> steckt → href neutralisieren
    const a = trigger.closest('a[href^="#"], a[href*="#reels"]');
    if (a) a.removeAttribute('href');

    openReels();
    return false;
  }

  document.addEventListener('click', onClick, true); // <<< capture
})();

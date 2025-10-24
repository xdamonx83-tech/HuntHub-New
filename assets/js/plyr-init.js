// Standard-Plyr ohne Portrait-/Fullscreen-Hacks
(function(){
  function init(el){
    // NIE Plyr auf unsere Reel-Videos anwenden
    if (el.hasAttribute('data-hh-reel')) return;

    // bereits initialisiert?
    if (el.dataset.plyrReady === '1') return;
    el.dataset.plyrReady = '1'; // setzt data-plyr-ready="1"

    new Plyr(el, {
      fullscreen: { enabled: true, fallback: true, iosNative: true },
      controls: [
        'play','progress','current-time','mute','volume','settings','pip','airplay','fullscreen'
      ]
    });
  }

  function scan(root=document){
    // WICHTIG:
    //  - data-plyr-ready (mit Bindestrich) statt data-plyrReady
    //  - Reel-Videos ausschließen
    root.querySelectorAll('video:not([data-plyr-ready]):not([data-hh-reel])').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }

  // Für dynamische Inhalte
  new MutationObserver(m => {
    m.forEach(r => r.addedNodes.forEach(n => {
      if (n.nodeType !== 1) return;
      if (n.tagName === 'VIDEO') {
        if (!n.hasAttribute('data-hh-reel')) init(n);
      } else {
        scan(n);
      }
    }));
  }).observe(document.documentElement, { childList:true, subtree:true });
})();

(() => {
  const content = document.getElementById('profile-content');
  if (!content) return;

  const nav = document.querySelector('.profile-tabs');
  const userId = content.dataset.userId;

  // kleine Ladeanzeige
  const showLoading = () => { content.classList.add('is-loading'); };
  const hideLoading = () => { content.classList.remove('is-loading'); };

  async function loadTab(url, push = true) {
    try {
      showLoading();
      // wir liefern nur den Partial-Block
      const u = new URL(url, window.location.origin);
      u.searchParams.set('partial', '1');
      const res = await fetch(u.toString(), {
        headers: {
          'X-Partial': '1',
          'X-Requested-With': 'fetch'
        },
        credentials: 'include'
      });
      const html = await res.text();
      content.innerHTML = html;
      if (push) history.pushState({ url: u.pathname + u.search }, '', url);
      // optional: nachladen/initialisieren von Widgets im Partial …
    } catch (e) {
      console.error('Tab load failed:', e);
      content.innerHTML = `<div class="error">Konnte Inhalt nicht laden.</div>`;
    } finally {
      hideLoading();
    }
  }

  // Klicks auf Tabs abfangen
  if (nav) {
    nav.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-profile-tab]');
      if (!a) return;
      e.preventDefault();
      nav.querySelectorAll('a[data-profile-tab]').forEach(el => el.classList.remove('is-active'));
      a.classList.add('is-active');
      loadTab(a.href, /*push*/true);
    });
  }

  // Browser zurück/vor
  window.addEventListener('popstate', (e) => {
    const url = (e.state && e.state.url) ? e.state.url : window.location.href;
    loadTab(url, /*push*/false);
    // Tab-Button aktiv setzen
    if (nav) {
      const current = new URL(url, window.location.origin).searchParams.get('tab') || 'posts';
      nav.querySelectorAll('a[data-profile-tab]').forEach(el => {
        el.classList.toggle('is-active', el.dataset.profileTab === current);
      });
    }
  });
})();

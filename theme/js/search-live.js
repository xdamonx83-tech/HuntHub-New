(function(){
  const input = document.getElementById('global-search');
  if (!input) return;

  let box, aborter, idx = -1, items = [];
  const maxItems = 8;

  const ensureBox = () => {
    if (box) return box;
    box = document.createElement('div');
    box.id = 'search-live-box';
    box.className = 'absolute z-50 mt-2 w-full max-w-xl rounded-2xl overflow-hidden border border-[#2a2a2a] bg-[#0f0f0f] shadow-xl';
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(box);
    return box;
  };

  const hide = () => { if (box) box.style.display = 'none'; idx = -1; items = []; };
  const show = () => { ensureBox().style.display = 'block'; };

  const render = (data, q) => {
    const threads = (data.threads || []).slice(0, maxItems);
    const posts   = (data.posts   || []).slice(0, maxItems);
    let html = '';
    const sec = (title, arr, map) => {
      if (arr.length === 0) return;
      html += `<div class="px-3 py-2 text-xs uppercase tracking-wider opacity-60">${title}</div>`;
      arr.forEach(it => html += map(it));
    };
    sec('Threads', threads, it => `<a class="search-item block px-4 py-3 hover:bg-[#161616]" href="${it.url}">${escapeHtml(it.title)}</a>`);
    sec('Beiträge', posts, it => `<a class="search-item block px-4 py-3 hover:bg-[#161616]" href="${it.url}"><div class="text-sm opacity-80">${it.snippet}</div></a>`);
    if (!html) html = `<div class="px-4 py-3 opacity-60">Keine Treffer</div>`;
    ensureBox().innerHTML = html;
    items = Array.from(ensureBox().querySelectorAll('.search-item'));
    idx = -1;
    show();
  };

  const fetchSearch = debounce(async (q) => {
    if (aborter) aborter.abort();
    aborter = new AbortController();
    const res = await fetch(`/api/forum/search.php?q=${encodeURIComponent(q)}&limit=6`, {signal: aborter.signal});
    if (!res.ok) return;
    const data = await res.json();
    render(data, q);
  }, 140);

  input.addEventListener('input', (e) => {
    const q = input.value.trim();
    if (q.length < 2) { hide(); return; }
    fetchSearch(q);
  });

  input.addEventListener('keydown', (e) => {
    if (!box || box.style.display === 'none') return;
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
    else if (e.key === 'Enter') {
      if (idx >= 0 && items[idx]) { e.preventDefault(); items[idx].click(); }
      else window.location.href = `/forum/search.php?q=${encodeURIComponent(input.value.trim())}`;
    } else if (e.key === 'Escape') { hide(); }
  });

  document.addEventListener('click', (e) => {
    if (!box) return;
    if (e.target === input || box.contains(e.target)) return;
    hide();
  });

  function move(d){
    if (!items.length) return;
    idx = (idx + d + items.length) % items.length;
    items.forEach((el,i) => el.classList.toggle('bg-[#161616]', i === idx));
  }

  function debounce(fn, ms){
    let t; return (...a)=>{ clearTimeout(t); t = setTimeout(()=>fn(...a), ms); };
  }

  function escapeHtml(s){
    return s.replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }
})();

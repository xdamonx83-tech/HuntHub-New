(async () => {
  const wrap = document.getElementById('search-results');
  if (!wrap) return;
  const q = wrap.dataset.q || '';
  const url = `/api/forum/search.php?q=${encodeURIComponent(q)}&limit=20`;
  const res = await fetch(url);
  const data = await res.json();

  const el = document.createElement('div');

  const sec = (title, items, render) => {
    const s = document.createElement('section');
    const h = document.createElement('h2');
    h.className = 'text-xl font-semibold mt-6 mb-3';
    h.textContent = title;
    s.appendChild(h);

    if (!items || items.length === 0) {
      const p = document.createElement('p');
      p.className = 'opacity-60';
      p.textContent = 'Keine Treffer.';
      s.appendChild(p);
    } else {
      const ul = document.createElement('ul');
      ul.className = 'space-y-3';
      items.forEach(it => {
        const li = document.createElement('li');
        li.innerHTML = render(it);
        ul.appendChild(li);
      });
      s.appendChild(ul);
    }
    return s;
  };

  el.appendChild(
    sec('Thread-Titel', data.threads, it =>
      `<a class="block p-3 rounded-xl bg-[#141414] border border-[#2a2a2a] hover:border-[#3a3a3a]"
          href="${it.url}">
         <div class="font-medium">${escapeHtml(it.title)}</div>
       </a>`
    )
  );

  el.appendChild(
    sec('Beiträge', data.posts, it =>
      `<a class="block p-3 rounded-xl bg-[#141414] border border-[#2a2a2a] hover:border-[#3a3a3a]"
          href="${it.url}">
         <div class="text-sm opacity-80">${it.snippet}</div>
       </a>`
    )
  );

  wrap.replaceChildren(el);

  function escapeHtml(s){
    return s.replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }
})();

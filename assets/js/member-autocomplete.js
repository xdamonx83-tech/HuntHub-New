// /assets/js/member-autocomplete.js
(function () {
  'use strict';
  const BASE = window.APP_BASE || '';

  // Ziel-Eingabefeld finden (am besten: data-members-autocomplete ans Input hängen)
  const INPUT =
    document.querySelector('[data-members-autocomplete]') ||
    document.querySelector('#hh-search-input') ||
    document.querySelector('header input[type="search"], .header input[type="search"], .topbar input[type="search"]') ||
    document.querySelector('input[type="search"]');

  if (!INPUT) return;

  // Dropdown-Container
  const DROP = document.createElement('div');
  DROP.id = 'hh-member-drop';
  Object.assign(DROP.style, {
    position: 'absolute',
    background: '#0d0d0d',
    border: '1px solid rgba(255,255,255,.10)',
    borderRadius: '12px',
    boxShadow: '0 10px 30px rgba(0,0,0,.45)',
    zIndex: '9999',
    padding: '.25rem',
    minWidth: '280px',
    display: 'none'
  });
  document.body.appendChild(DROP);

  // Minimal-Styles
  const style = document.createElement('style');
  style.textContent = `
    #hh-member-drop .title{font-size:.75rem;opacity:.7;padding:.25rem .5rem}
    #hh-member-drop .item{display:flex;align-items:center;gap:.6rem;padding:.5rem .6rem;border-radius:10px;cursor:pointer;color:#e5e7eb}
    #hh-member-drop .item:hover,#hh-member-drop .item.active{background:rgba(255,255,255,.06)}
    #hh-member-drop img{width:28px;height:28px;border-radius:50%;object-fit:cover;background:#222}
    #hh-member-drop .name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #hh-member-drop .empty{padding:.6rem .75rem;opacity:.6}
  `;
  document.head.appendChild(style);

  let items = [];
  let active = -1;
  let closeTimer = null;

  function esc(s){ return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;'}[c])); }

  function pos() {
    const r = INPUT.getBoundingClientRect();
    DROP.style.left = (window.scrollX + r.left) + 'px';
    DROP.style.top  = (window.scrollY + r.bottom + 6) + 'px';
    DROP.style.width = r.width + 'px';
  }
  function open() { pos(); DROP.style.display = 'block'; }
  function close(){ DROP.style.display = 'none'; active = -1; highlight(); }

  function highlight(){
    DROP.querySelectorAll('.item').forEach((el,i)=>{
      if (i===active){ el.classList.add('active'); el.setAttribute('aria-selected','true'); }
      else { el.classList.remove('active'); el.removeAttribute('aria-selected'); }
    });
  }

  function render(){
    DROP.innerHTML = '';
    if (!items.length){ close(); return; }
    const title = document.createElement('div');
    title.className = 'title';
    title.textContent = 'Mitglieder';
    DROP.appendChild(title);

    items.forEach((it,i)=>{
      const row = document.createElement('div');
      row.className = 'item';
      row.setAttribute('role','option');
      row.dataset.index = String(i);
      row.innerHTML = `<img src="${it.avatar}" alt=""><div class="name">${esc(it.name)}</div>`;
      row.addEventListener('mousedown', e => { e.preventDefault(); goto(it.url); });
      DROP.appendChild(row);
    });
    open(); highlight();
  }

  function goto(url){ window.location.href = url; }

  async function search(q){
    const url = `${BASE}/api/search/users.php?q=${encodeURIComponent(q)}&limit=8`;
    try {
      const r = await fetch(url, { credentials: 'same-origin' });
      if (!r.ok) return [];
      const data = await r.json();
      return (data && Array.isArray(data.items)) ? data.items : [];
    } catch(e) { console.error('member search failed', e); return []; }
  }

  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

  const onInput = debounce(async ()=>{
    const q = INPUT.value.trim();
    if (q.length < 2){ items=[]; render(); return; }
    items = await search(q);
    render();
  }, 160);

  INPUT.setAttribute('autocomplete','off');
  INPUT.setAttribute('spellcheck','false');
  INPUT.addEventListener('input', onInput);
  INPUT.addEventListener('focus', ()=>{ if (items.length) open(); });
  INPUT.addEventListener('blur',  ()=>{ closeTimer = setTimeout(close, 120); });
  document.addEventListener('mousedown', ()=>{ if (closeTimer){ clearTimeout(closeTimer); closeTimer = null; } });

  window.addEventListener('scroll', ()=>{ if (DROP.style.display==='block') pos(); }, true);
  window.addEventListener('resize', ()=>{ if (DROP.style.display==='block') pos(); });

  INPUT.addEventListener('keydown', (e)=>{
    if (DROP.style.display !== 'block') return;
    const max = items.length - 1;
    if (e.key === 'ArrowDown'){ e.preventDefault(); active = (active < max) ? active+1 : 0; highlight(); }
    else if (e.key === 'ArrowUp'){ e.preventDefault(); active = (active > 0) ? active-1 : max; highlight(); }
    else if (e.key === 'Enter'){
      if (items.length > 0 && active < 0){ e.preventDefault(); goto(items[0].url); }
      else if (active >= 0 && items[active]){ e.preventDefault(); goto(items[active].url); }
    } else if (e.key === 'Escape'){ close(); }
  });
})();

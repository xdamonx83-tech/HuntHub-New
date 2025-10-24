// /assets/js/header-multisearch.js – v1.1 (fixed dropdown + close on scroll)
(function () {
  'use strict';
  const BASE = window.APP_BASE || '';

  // Ziel-Eingabefeld – das data-Attribut ist optional
  const INPUT =
    document.querySelector('[data-members-autocomplete]') ||
    document.querySelector('#hh-search-input') ||
    document.querySelector('header input[type="search"], .header input[type="search"], .topbar input[type="search"]') ||
    document.querySelector('input[type="search"]');
  if (!INPUT) return;

  // Dropdown (FIXED statt absolute)
  const DROP = document.createElement('div');
  DROP.id = 'hh-search-drop';
  Object.assign(DROP.style, {
    position:'fixed', // <- wichtig
    background:'#0d0d0d',
    border:'1px solid rgba(255,255,255,.10)',
    borderRadius:'12px',
    boxShadow:'0 10px 30px rgba(0,0,0,.45)',
    zIndex:'9999',
    padding:'.25rem',
    minWidth:'280px',
    display:'none'
  });
  document.body.appendChild(DROP);

  const style = document.createElement('style');
  style.textContent = `
    #hh-search-drop .section{padding:.35rem .35rem 0}
    #hh-search-drop .title{font-size:.75rem;opacity:.7;padding:.1rem .4rem .3rem;text-transform:uppercase;letter-spacing:.06em}
    #hh-search-drop .item{display:flex;align-items:center;gap:.6rem;padding:.5rem .6rem;border-radius:10px;cursor:pointer;color:#e5e7eb}
    #hh-search-drop .item:hover,#hh-search-drop .item.active{background:rgba(255,255,255,.06)}
    #hh-search-drop img{width:28px;height:28px;border-radius:50%;object-fit:cover;background:#222}
    #hh-search-drop .name,.thread{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #hh-search-drop .empty{padding:.6rem .75rem;opacity:.6}
  `;
  document.head.appendChild(style);

  let flat = [];
  let active = -1;
  let closeTimer = null;

  function esc(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // Position relativ zur Viewport-Position des Inputs berechnen (fixed)
  function pos() {
    const r = INPUT.getBoundingClientRect();
    DROP.style.left = Math.round(r.left) + 'px';
    DROP.style.top  = Math.round(r.bottom + 6) + 'px';
    DROP.style.width = Math.round(r.width) + 'px';
  }
  function open(){ pos(); DROP.style.display='block'; }
  function close(){ DROP.style.display='none'; active=-1; highlight(); }

  function highlight(){
    DROP.querySelectorAll('.item').forEach((el,i)=>{
      if (i===active){ el.classList.add('active'); el.setAttribute('aria-selected','true'); }
      else { el.classList.remove('active'); el.removeAttribute('aria-selected'); }
    });
  }
  function goto(url){ window.location.href = url; }

  async function search(q){
    const url = `${BASE}/api/search/quick.php?q=${encodeURIComponent(q)}&limit_members=6&limit_threads=6`;
    try {
      const r = await fetch(url, { credentials:'same-origin' });
      if (!r.ok) return {members:[], threads:[]};
      const json = await r.json();
      const items = (json && json.items) ? json.items : {};
      return {members: items.members || [], threads: items.threads || []};
    } catch(e){ console.error('multisearch failed', e); return {members:[],threads:[]}; }
  }

  function render(data){
    DROP.innerHTML = '';
    flat = [];
    const hasMembers = data.members && data.members.length;
    const hasThreads = data.threads && data.threads.length;
    if (!hasMembers && !hasThreads){ close(); return; }

    if (hasMembers){
      const sec = document.createElement('div');
      sec.className = 'section';
      sec.innerHTML = `<div class="title">Mitglieder</div>`;
      data.members.forEach((m)=>{
        const row = document.createElement('div');
        row.className = 'item';
        row.innerHTML = `<img src="${m.avatar}" alt=""><div class="name">${esc(m.name)}</div>`;
        row.addEventListener('mousedown', e => { e.preventDefault(); goto(m.url); });
        sec.appendChild(row);
        flat.push({url:m.url});
      });
      DROP.appendChild(sec);
    }

    if (hasThreads){
      const sec = document.createElement('div');
      sec.className = 'section';
      sec.innerHTML = `<div class="title">Forum</div>`;
      data.threads.forEach((t)=>{
        const row = document.createElement('div');
        row.className = 'item';
        row.innerHTML = `<div class="thread">🧵 ${esc(t.title || 'Thread')}</div>`;
        row.addEventListener('mousedown', e => { e.preventDefault(); goto(t.url); });
        sec.appendChild(row);
        flat.push({url:t.url});
      });
      DROP.appendChild(sec);
    }

    open(); active = (flat.length?0:-1); highlight();
  }

  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
  const onInput = debounce(async ()=>{
    const q = INPUT.value.trim();
    if (q.length < 2){ close(); return; }
    render(await search(q));
  }, 160);

  INPUT.setAttribute('autocomplete','off');
  INPUT.setAttribute('spellcheck','false');
  INPUT.addEventListener('input', onInput);
  INPUT.addEventListener('focus', ()=>{ if (DROP.style.display==='none') onInput(); });
  INPUT.addEventListener('blur',  ()=>{ closeTimer = setTimeout(close, 120); });

  // WICHTIG: statt reposition -> bei Scroll einfach schließen (passiv!)
  window.addEventListener('scroll', ()=>{ if (DROP.style.display==='block') close(); }, { passive: true });
  window.addEventListener('resize', ()=>{ if (DROP.style.display==='block') pos(); }, { passive: true });

  document.addEventListener('mousedown', ()=>{ if (closeTimer){ clearTimeout(closeTimer); closeTimer = null; } });

  INPUT.addEventListener('keydown', (e)=>{
    if (DROP.style.display !== 'block') return;
    const max = flat.length - 1; if (max < 0) return;
    if (e.key === 'ArrowDown'){ e.preventDefault(); active = (active < max) ? active+1 : 0; highlight(); }
    else if (e.key === 'ArrowUp'){ e.preventDefault(); active = (active > 0) ? active-1 : max; highlight(); }
    else if (e.key === 'Enter'){
      if (flat.length > 0 && active < 0){ e.preventDefault(); goto(flat[0].url); }
      else if (active >= 0 && flat[active]){ e.preventDefault(); goto(flat[active].url); }
    } else if (e.key === 'Escape'){ close(); }
  });
})();

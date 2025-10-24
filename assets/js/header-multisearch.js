// Header Multisearch v1.6 – mit "Zuletzt gesucht" (3 Einträge, Desktop+Mobile)
(function () {
  'use strict';
  const BASE = window.APP_BASE || '';
  const RECENT_KEY = 'hh.search.recent.v1';
  const RECENT_MAX = 3;

  // sichtbares Input wählen
  function isVisible(el){
    if (!el) return false;
    if (el.offsetParent === null) return false;
    const cs = getComputedStyle(el);
    return cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0';
  }
  const candidates = [
    document.querySelector('#hh-search-input'),      // Desktop
    document.querySelector('#global-search-input')   // Mobile
  ].filter(Boolean);
  let INPUT = candidates.find(isVisible)
         || document.querySelector('header input[type="search"]')
         || document.querySelector('input[type="search"]');
  if (!INPUT) return;

  const ANCHOR = INPUT.closest('.hh-search, .search, .topbar, header, .header') || INPUT.parentElement;

  // Dropdown
  const DROP = document.createElement('div');
  DROP.id = 'hh-search-drop';
  Object.assign(DROP.style, {
    position:'absolute',
    background:'#0d0d0d',
    border:'1px solid rgba(255,255,255,.10)',
    borderRadius:'12px',
    boxShadow:'0 10px 30px rgba(0,0,0,.45)',
    zIndex:'9999',
    padding:'.25rem',
	minWidth:'100px',
    maxWidth:'220px',
    display:'none'
  });
  ANCHOR.appendChild(DROP);

  // Styles
  const style = document.createElement('style');
  style.textContent = `
    #hh-search-drop .section{padding:.35rem .35rem 0}
    #hh-search-drop .title{font-size:.75rem;opacity:.7;padding:.1rem .4rem .3rem;text-transform:uppercase;letter-spacing:.06em;display:flex;justify-content:space-between;align-items:center;gap:.5rem}
    #hh-search-drop .clear-btn{font-size:.72rem;opacity:.7;border:0;background:transparent;color:#e5e7eb;cursor:pointer}
    #hh-search-drop .item{display:flex;align-items:center;gap:.6rem;padding:.5rem .6rem;border-radius:10px;cursor:pointer;color:#e5e7eb}
    #hh-search-drop .item:hover,#hh-search-drop .item.active{background:rgba(255,255,255,.06)}
    #hh-search-drop img{width:28px;height:28px;border-radius:50%;object-fit:cover;background:#222}
    #hh-search-drop .name,.thread,.q{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #hh-search-drop .q{opacity:.85}
    #hh-search-drop .empty{padding:.6rem .75rem;opacity:.6}
  `;
  document.head.appendChild(style);

  let flat = [];   // für Pfeiltasten
  let active = -1;
  let closeTimer = null;
  let focusValue = ''; // zum Erkennen, ob bei Blur etwas getippt wurde

  // helpers
  function esc(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function pos(){ const ir=INPUT.getBoundingClientRect(), ar=ANCHOR.getBoundingClientRect(); DROP.style.left=(ir.left-ar.left)+'px'; DROP.style.top=(ir.bottom-ar.top+6)+'px'; DROP.style.width=ir.width+'px'; }
  function open(){ pos(); DROP.style.display='block'; }
  function close(){ DROP.style.display='none'; active=-1; highlight(); }
  function highlight(){ DROP.querySelectorAll('.item').forEach((el,i)=>{ if(i===active){ el.classList.add('active'); el.setAttribute('aria-selected','true'); } else { el.classList.remove('active'); el.removeAttribute('aria-selected'); } }); }
  function goto(url){ window.location.href = url; }

  // recent storage
  function getRecent(){ try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch(_) { return []; } }
  function setRecent(list){ try { localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, RECENT_MAX))); } catch(_) {} }
  function addRecent(entry){
    if (!entry) return;
    const list = getRecent();
    const key = entry.url ? 'url' : 'label';
    const filtered = list.filter(x => (x[key] || '') !== (entry[key] || ''));
    filtered.unshift(entry);
    setRecent(filtered);
  }
  function clearRecent(){ try { localStorage.removeItem(RECENT_KEY); } catch(_) {} }

  // fetch
  async function fetchQuick(q){
    const url = `${BASE}/api/search/quick.php?q=${encodeURIComponent(q)}&limit_members=6&limit_threads=6`;
    const r = await fetch(url, { credentials:'same-origin' });
    if (!r.ok) return {members:[], threads:[]};
    const json = await r.json();
    const items = (json && json.items) ? json.items : {};
    return {members: items.members || [], threads: items.threads || []};
  }

  // render
  function renderRecent(){
    const list = getRecent();
    DROP.innerHTML = ''; flat = [];
    const sec = document.createElement('div'); sec.className='section';
    const title = document.createElement('div'); title.className='title';
    title.innerHTML = `Zuletzt gesucht <button class="clear-btn" type="button" id="hh-clear-recent">Leeren</button>`;
    sec.appendChild(title);

    if (!list.length){
      const empty = document.createElement('div'); empty.className='empty'; empty.textContent='Noch keine Suchen';
      sec.appendChild(empty);
    } else {
      list.forEach(item=>{
        const row = document.createElement('div'); row.className='item';
        if (item.type === 'member' && item.avatar){
          row.innerHTML = `<img src="${item.avatar}" alt=""><div class="name">${esc(item.label)}</div>`;
        } else if (item.type === 'thread'){
          row.innerHTML = `<div class="thread">🧵 ${esc(item.label)}</div>`;
        } else {
          row.innerHTML = `<div class="q">🔎 ${esc(item.label)}</div>`;
        }
        row.addEventListener('mousedown', e=>{
          e.preventDefault();
          if (item.url){ goto(item.url); }
          else { INPUT.value = item.label; onInput.now(); }
        });
        sec.appendChild(row);
        flat.push({url: item.url || null, q: item.label});
      });
    }

    DROP.appendChild(sec);
    open(); active=(flat.length?0:-1); highlight();

    const btn = DROP.querySelector('#hh-clear-recent');
    if (btn) btn.addEventListener('mousedown', e=>{ e.preventDefault(); clearRecent(); renderRecent(); });
  }

  function renderResults(data){
    DROP.innerHTML = ''; flat = [];
    const hasMembers = data.members && data.members.length;
    const hasThreads = data.threads && data.threads.length;
    if (!hasMembers && !hasThreads){ close(); return; }

    if (hasMembers){
      const sec = document.createElement('div'); sec.className='section';
      sec.innerHTML = `<div class="title">Mitglieder</div>`;
      data.members.forEach(m=>{
        const row = document.createElement('div'); row.className='item';
        row.innerHTML = `<img src="${m.avatar}" alt=""><div class="name">${esc(m.name)}</div>`;
        row.addEventListener('mousedown', e=>{ e.preventDefault(); addRecent({type:'member', label:m.name, url:m.url, avatar:m.avatar}); goto(m.url); });
        sec.appendChild(row); flat.push({url:m.url});
      });
      DROP.appendChild(sec);
    }
    if (hasThreads){
      const sec = document.createElement('div'); sec.className='section';
      sec.innerHTML = `<div class="title">Forum</div>`;
      data.threads.forEach(t=>{
        const row = document.createElement('div'); row.className='item';
        row.innerHTML = `<div class="thread">🧵 ${esc(t.title || 'Thread')}</div>`;
        row.addEventListener('mousedown', e=>{ e.preventDefault(); addRecent({type:'thread', label:t.title || 'Thread', url:t.url}); goto(t.url); });
        sec.appendChild(row); flat.push({url:t.url});
      });
      DROP.appendChild(sec);
    }
    open(); active=(flat.length?0:-1); highlight();
  }

  // debounce helper
  function debounce(fn, ms){ let t; function w(...a){ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); } w.now=(...a)=>{ clearTimeout(t); fn(...a); }; return w; }

  const onInput = debounce(async ()=>{
    const q = INPUT.value.trim();
    if (q.length < 2){ renderRecent(); return; }
    try { renderResults(await fetchQuick(q)); } catch(_){ renderRecent(); }
  }, 160);

  // Events
  INPUT.setAttribute('autocomplete','off');
  INPUT.addEventListener('focus', ()=>{ focusValue = INPUT.value; if (focusValue.trim().length === 0) renderRecent(); else onInput.now(); });
  INPUT.addEventListener('input', onInput);
  INPUT.addEventListener('blur', ()=>{
    // Optional: Query merken, wenn getippt wurde, ohne Enter zu drücken
    const q = INPUT.value.trim();
    if (q.length >= 2 && q !== focusValue) addRecent({type:'query', label:q});
    closeTimer = setTimeout(close, 120);
  });
  document.addEventListener('mousedown', ()=>{ if (closeTimer){ clearTimeout(closeTimer); closeTimer=null; } });

  // Keyboard: Enter speichert IMMER die aktuelle Query
  INPUT.addEventListener('keydown', (e)=>{
    if (e.key === 'Enter'){
      const q = INPUT.value.trim();
      if (q.length >= 2) addRecent({type:'query', label:q});
    }
    if (DROP.style.display !== 'block') return;
    const max = flat.length - 1;
    if (e.key === 'ArrowDown'){ e.preventDefault(); active = (active < max) ? active+1 : 0; highlight(); }
    else if (e.key === 'ArrowUp'){ e.preventDefault(); active = (active > 0) ? active-1 : max; highlight(); }
    else if (e.key === 'Enter'){
      if (flat.length === 0) return; // oben schon gespeichert
      e.preventDefault();
      const sel = flat[Math.max(0, active)];
      if (sel && sel.url) goto(sel.url);
      else if (sel && sel.q){ INPUT.value = sel.q; onInput.now(); }
    } else if (e.key === 'Escape'){ close(); }
  });
})();

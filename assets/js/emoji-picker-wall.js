(function(){
  const RECENT_KEY = 'hh_recent_emojis_v1';
  const MAX_RECENT = 28;

  // ---------- CSS-Font-Fix injizieren (gegen Webfont-Overrides) ----------
  (function injectEmojiCSS(){
    const styleId = 'hh-emoji-picker-fontfix';
    if (document.getElementById(styleId)) return;
    const css = `
:root{ --hh-emoji-font: "Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji","EmojiOne Color","Twemoji Mozilla","Segoe UI Symbol"; }
.hh-emoji-picker, .hh-emoji-picker *{ font-family: var(--hh-emoji-font), system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif !important; font-weight:400 !important; font-style:normal !important; }
.hh-emoji-item{ font-size:22px; line-height:36px; }
.hh-emoji-item img.emoji{ width:24px; height:24px; display:block; margin:6px auto; }
`;
    const st = document.createElement('style');
    st.id = styleId; st.textContent = css;
    document.head.appendChild(st);
  })();

  // ---------- Twemoji (SVG) nur bei Bedarf laden ----------
  let twemojiReady = null;
  function ensureTwemoji(){
    if (window.twemoji) return Promise.resolve();
    if (twemojiReady)   return twemojiReady;
    twemojiReady = new Promise((resolve, reject)=>{
      const s = document.createElement('script');
      s.src   = 'https://cdn.jsdelivr.net/npm/twemoji@14.0.2/dist/twemoji.min.js';
      s.async = true;
      s.onload = ()=> resolve();
      s.onerror = ()=> reject(new Error('twemoji load failed'));
      document.head.appendChild(s);
    });
    return twemojiReady;
  }

  // ---------- Helper: Hex → Emoji ----------
  // Unterstützt auch Sequenzen mit '-' (z. B. 1F6B4-200D-2642-FE0F)
  const HEX2EMOJI = (hexList) =>
    hexList.trim().split(/\s+/).filter(Boolean).map(token => {
      const cps = token.split('-').map(h => parseInt(h, 16));
      return String.fromCodePoint.apply(null, cps);
    });

  // ---------- Kategorien (encoding-sicher via Hex) ----------
  const CATS = [
    { id:'recent',  icon:'\u{1F559}', label:'Kürzlich verwendet', emojis:[] }, // 🕙
    { id:'smileys', icon:'\u{1F642}', label:'Smileys & Personen', emojis: HEX2EMOJI(`
      1F600 1F603 1F604 1F601 1F606 1F605 1F923 1F602 1F642 1F643 1F609 1F60A 1F607 1F970 1F60D
      1F618 1F617 1F61A 1F60B 1F61B 1F61C 1F92A 1F61D 1F914 1F928 1F610 1F611 1F636 1FAE5 1F60F
      1F612 1F644 1F62C 1F973 1F60E 1F9D0 1F62E 1F62F 1F632 1F633 1F97A 1F622 1F62D 1F620 1F621
      1F92C 1F92F 1F631 1F628 1F630 1F625 1F613 1F974 1F637 1F912 1F915 1F922 1F92E 1F920 1F978
      1F921 1F47B 1F4A9 1F44B 1F91A 270B 1F590 1F44C 1F90C 1F90F 270C 1F91E 1F918 1F919 1F44D
      1F44E 1F44F 1F64C 1F450 1F932 1F64F
    `)},
    { id:'people',  icon:'\u{1F464}', label:'Menschen', emojis: HEX2EMOJI(`
      1F476 1F9D2 1F466 1F467 1F9D1 1F468 1F469 1F9D3 1F474 1F475 1F9D4 1F471-200D-2642-FE0F
      1F471-200D-2640-FE0F 1F468-200D-1F9B0 1F469-200D-1F9B0 1F468-200D-1F9B1 1F469-200D-1F9B1
      1F468-200D-1F9B3 1F469-200D-1F9B3 1F468-200D-1F9B2 1F469-200D-1F9B2 1F9D1-200D-2695-FE0F
      1F468-200D-2695-FE0F 1F469-200D-2695-FE0F 1F9D1-200D-1F4BB 1F468-200D-1F4BB 1F469-200D-1F4BB
      1F9D1-200D-1F3EB 1F468-200D-1F3EB 1F469-200D-1F3EB 1F9D1-200D-1F373 1F468-200D-1F373
      1F469-200D-1F373 1F9D1-200D-1F680 1F468-200D-1F680 1F469-200D-1F680
    `)},
    { id:'animals', icon:'\u{1F43E}', label:'Tiere & Natur', emojis: HEX2EMOJI(`
      1F436 1F431 1F42D 1F439 1F430 1F98A 1F43B 1F43C 1F428 1F42F 1F981 1F42E 1F437 1F438 1F435
      1F984 1F414 1F427 1F426 1F424 1F423 1F986 1F985 1F989 1F987 1F43A 1F417 1F434 1F41D 1FAB2
      1F98B 1F41E 1F422 1F40D 1F98E 1F996 1F338 1F33C 1F33B 1F337 1F332 1F333 1F334 1F340 1F343
      1F341 1F342
    `)},
    { id:'food',    icon:'\u{1F34E}', label:'Essen & Trinken', emojis: HEX2EMOJI(`
      1F34F 1F34E 1F350 1F34A 1F34B 1F34C 1F349 1F347 1F353 1FAD0 1F352 1F351 1F34D 1F96D 1F95D
      1F345 1F346 1F951 1F966 1F955 1F33D 1F336-FE0F 1F954 1F35E 1F950 1F968 1F9C0 1F355 1F354
      1F32D 1F32E 1F32F 1F959 1F957 1F363 1F364 1F35C 1F35D 1F366 1F370 1F36A 1F369 1F36B 1F36C
      2615 FE0F 1F37A 1F37B 1F377 1F378 1F964
    `)},
    { id:'activity',icon:'\u{26BD}', label:'Aktivitäten', emojis: HEX2EMOJI(`
      26BD 1F3C0 1F3C8 26BE 1F3BE 1F3D0 1F3D3 1F3F9 26F3 1F94A 1F94B 1F3B1 1F3F7 1F3A3 1F93F
      1F3BF 26F7 FE0F 1F3C2 1F6B4-200D-2642-FE0F 1F6B5-200D-2640-FE0F 1F3C3-200D-2642-FE0F
      1F3CA-200D-2642-FE0F 1F9D7-200D-2642-FE0F 1F938-200D-2640-FE0F 1F9D8-200D-2642-FE0F
      1F3AE 1F3B2 1F3AF 1F3B3 1F3BB 1F3B8 1F3A7 1F3A4 1F3AC 1F3AA
    `)},
    { id:'objects', icon:'\u{1F4A1}', label:'Objekte', emojis: HEX2EMOJI(`
      1F4A1 1F526 1F56F-FE0F 1F4F1 1F4BB 2328 FE0F 1F5B1-FE0F 1F5A5-FE0F 1F5A8-FE0F 1F579-FE0F
      1F4F7 1F3A5 1F4FA 23F0 23F1 FE0F 1F4E6 1F9F0 1F527 1F528 2699 FE0F 1F529 1F517 1F9F2 1F48E
      1FA99 1F4B0 1F4CC 1F4CE 2702 FE0F 1F9EA 1F52C 1F9EF 1F9F9 1F9FB 1FA91
    `)},
    { id:'symbols', icon:'\u{1F3F3}', label:'Symbole', emojis: HEX2EMOJI(`
      2764 FE0F 1F9E1 1F7E1 1F49A 1F499 1F49C 1F5A4 1F90D 1F90E 1F494 2763 FE0F 1F495 1F49E 1F493
      1F497 1F496 1F498 1F49D 1F4A4 1F4A2 1F4A5 1F4AB 1F4A6 1F4A8 1F573-FE0F 1F3B6 2728 2744 FE0F
      1F525 2B50 1F31F 26A1 2600 FE0F 2614 FE0F 1F308 2714 FE0F 2716 FE0F 274C 2B55 2705 2757 2753
      2049 FE0F 1F51E
    `)},
  ];

  // ---------- Mini-DOM helpers ----------
  const $  = (sel, root=document)=>root.querySelector(sel);
  const $$ = (sel, root=document)=>Array.from(root.querySelectorAll(sel));
  const on = (el,ev,fn,opts)=>el.addEventListener(ev,fn,opts);

  function loadRecent(){
    try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); }
    catch(e){ return []; }
  }
  function pushRecent(emoji){
    let arr = loadRecent().filter(e => e!==emoji);
    arr.unshift(emoji);
    if (arr.length>MAX_RECENT) arr = arr.slice(0,MAX_RECENT);
    localStorage.setItem(RECENT_KEY, JSON.stringify(arr));
  }

  function insertAtCursor(target, text){
    target.focus();
    const start = target.selectionStart ?? target.value.length;
    const end   = target.selectionEnd   ?? target.value.length;
    const val   = target.value;
    target.value = val.slice(0,start) + text + val.slice(end);
    const pos = start + text.length;
    target.setSelectionRange(pos,pos);
    target.dispatchEvent(new Event('input', {bubbles:true}));
    target.dispatchEvent(new CustomEvent('hh:emoji:inserted', {detail:{emoji:text}}));
  }

  function createEl(tag, cls, html){
    const el = document.createElement(tag);
    if(cls) el.className=cls;
    if(html!=null) el.innerHTML=html;
    return el;
  }

  function buildPicker(targetInput, anchorBtn){
    const picker = createEl('div','hh-emoji-picker'); picker.setAttribute('role','dialog'); picker.setAttribute('aria-modal','true');
	const placement = (anchorBtn.getAttribute('data-emoji-placement') || 'top').toLowerCase();

    // Head (search)
    const head = createEl('div','hh-emoji-head');
    const search = createEl('input','hh-emoji-search'); search.type='search'; search.placeholder='Suche Emojis…';
    head.appendChild(search);
    picker.appendChild(head);

    // Scroll area
    const scroller = createEl('div','hh-emoji-scroll');
    picker.appendChild(scroller);

    // Tabs
    const tabs = createEl('div','hh-emoji-tabs');
    CATS.forEach((c,idx)=>{
      const t = createEl('button','hh-emoji-tab');
      t.type='button'; t.setAttribute('data-tab',c.id);
      t.setAttribute('aria-selected', idx===0 ? 'true' : 'false');
      t.title = c.label; t.textContent = c.icon;
      tabs.appendChild(t);
    });
    picker.appendChild(tabs);
    document.body.appendChild(picker);

    // Sections render
    function render(catId, query){
      scroller.innerHTML = '';
      let items = [];
      if (catId==='recent') {
        const rec = loadRecent();
        items.push(rec.length ? {title:'Kürzlich verwendet', emojis:rec}
                              : {title:'Noch keine zuletzt verwendeten Emojis', emojis:[]});
      } else {
        const cat = CATS.find(c=>c.id===catId) || CATS[1];
        items.push({title:cat.label, emojis:cat.emojis});
      }

      items.forEach(group=>{
        const sec  = createEl('section','hh-emoji-section');
        const h    = createEl('div','hh-emoji-title', group.title);
        const grid = createEl('div','hh-emoji-grid');

        let ems = group.emojis;
        if (query && query.trim()){
          const q = query.trim().toLowerCase();
          ems = ems.filter(e => e && e.toString().toLowerCase().includes(q));
        }

        if (!ems.length && group.title!=='Noch keine zuletzt verwendeten Emojis') {
          const p = createEl('div', '', '<div style="padding:8px;color:#888;font-size:12px;">Keine Treffer</div>');
          sec.appendChild(h); sec.appendChild(p); scroller.appendChild(sec); return;
        }

        ems.forEach(e=>{
          const b = createEl('button','hh-emoji-item'); b.type='button'; b.textContent=e;
          on(b,'click',()=>{ insertAtCursor(targetInput, e); pushRecent(e); closePicker(); });
          grid.appendChild(b);
        });

        sec.appendChild(h); sec.appendChild(grid); scroller.appendChild(sec);

        // Twemoji-Fallback (ersetzt Text im Grid durch <img>, unabhängig von Fonts/Encoding)
        ensureTwemoji().then(()=>{
          if (window.twemoji) {
            window.twemoji.parse(grid, {
              folder: 'svg',
              ext: '.svg',
              base: 'https://cdn.jsdelivr.net/npm/twemoji@14.0.2/assets/'
            });
          }
        }).catch(()=>{/* ignore */});
      });
    }

    function selectTab(catId){
      $$('.hh-emoji-tab', picker).forEach(t=>t.setAttribute('aria-selected', t.getAttribute('data-tab')===catId ? 'true':'false'));
      render(catId, search.value);
    }

    // Positionierung (unten/seitlich flippen, im Viewport halten)
function position(){
  const r = anchorBtn.getBoundingClientRect();
  const p = picker; const margin = 8;
  const vw = Math.max(document.documentElement.clientWidth,  window.innerWidth  || 0);
  const vh = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);

  // Erst unsichtbar messen
  p.style.visibility = 'hidden'; p.style.left = '0px'; p.style.top = '0px';
  const pw = p.offsetWidth || 344;
  const ph = p.offsetHeight || 380;

  // X-Position clampen
  let left = Math.min(Math.max(margin, r.left), vw - pw - margin);

  // bevorzugte Richtung = oben
  const wantTop = (placement === 'top');
  const above = r.top - ph - margin;      // Y wenn oben
  const below = r.bottom + margin;        // Y wenn unten

  let top;
  if (wantTop) {
    // Wenn genug Platz über dem Button → oben, sonst fallback unten
    top = (above >= margin) ? above : Math.min(below, vh - ph - margin);
  } else {
    // (falls jemand bottom setzt)
    top = (below + ph <= vh - margin) ? below : Math.max(above, margin);
  }

  p.style.left = (left + window.scrollX) + 'px';
  p.style.top  = (top  + window.scrollY)  + 'px';
  p.dataset.side = (top < r.top) ? 'top' : 'bottom';
p.style.setProperty('--arrow-x', Math.min(Math.max(24, r.width/2), pw-24) + 'px');

  p.style.visibility = 'visible';
}


    function closePicker(){
      window.removeEventListener('resize', position);
      document.removeEventListener('scroll', position, true);
      document.removeEventListener('mousedown', onDocDown, true);
      document.removeEventListener('keydown', onKey, true);
      picker.remove();
    }

    function onDocDown(e){ if (!picker.contains(e.target) && e.target!==anchorBtn) closePicker(); }
    function onKey(e){ if (e.key==='Escape') closePicker(); }

    on(search,'input', ()=> render(getActiveTab(), search.value));
    on(tabs,'click', (ev)=>{ const b = ev.target.closest('.hh-emoji-tab'); if (b) selectTab(b.getAttribute('data-tab')); });

    function getActiveTab(){ const t=$('.hh-emoji-tab[aria-selected="true"]',picker); return t?t.getAttribute('data-tab'):'smileys'; }

    // Init
    selectTab('recent');
    position();
    window.addEventListener('resize', position);
    document.addEventListener('scroll', position, true);
    document.addEventListener('mousedown', onDocDown, true);
    document.addEventListener('keydown', onKey, true);

    setTimeout(()=> search.focus(), 0);
    return {close: closePicker};
  }

  // ---------- Launcher ----------
  let openInstance = null;
  function openForButton(btn){
    const sel = btn.getAttribute('data-emoji-target') || '#hh-composer-text';
    const target = document.querySelector(sel);
    if (!target) return;
    if (openInstance) { openInstance.close(); openInstance = null; }
    openInstance = buildPicker(target, btn);
  }

  function init(){
    document.addEventListener('click', (e)=>{
      const b = e.target.closest('.hh-emoji-btn');
      if (b) { e.preventDefault(); openForButton(b); }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// /js/emoji-picker.js
(function () {
  const BTN   = document.getElementById('emojiBtn');
  const INPUT = document.getElementById('replyText');
  if (!BTN || !INPUT) return;

  // Mögliches inline onclick entfernen, das direkt ein Emoji einfügt
  if (BTN.getAttribute('onclick')) BTN.removeAttribute('onclick');

  // Popup einmalig bauen (mit Inline-Styles, keine externen CSS nötig)
  let POP = null;
  function buildPopup(){
    if (POP) return POP;
    POP = document.createElement('div');
    POP.id = 'hh-emoji-popup';
    Object.assign(POP.style, { position:'fixed', zIndex:'10000', display:'none' });

    const box = document.createElement('div');
    Object.assign(box.style, {
      background:'rgba(24,24,24,.98)',
      border:'1px solid rgba(255,255,255,.08)',
      borderRadius:'12px', padding:'8px',
      boxShadow:'0 12px 32px rgba(0,0,0,.5)',
      maxHeight:'50vh', overflow:'auto',
      width:'min(360px, 92vw)'
    });

    const head = document.createElement('div');
    Object.assign(head.style, { display:'flex', alignItems:'center', justifyContent:'space-between', gap:'8px', padding:'2px 4px 8px', marginBottom:'4px' });
    const title = document.createElement('div');
    title.textContent = 'Emojis';
    Object.assign(title.style, { color:'#e5e7eb', fontSize:'13px', opacity:'0.9' });
    const x = document.createElement('button');
    x.type='button'; x.textContent='×'; x.setAttribute('aria-label','Schließen');
    Object.assign(x.style, { background:'transparent', border:0, color:'#9ca3af', fontSize:'18px', cursor:'pointer' });
    x.addEventListener('click', hide);

    const grid = document.createElement('div');
    function setCols(){ grid.style.gridTemplateColumns = (window.innerWidth<=520) ? 'repeat(6,1fr)' : 'repeat(8,1fr)'; }
    Object.assign(grid.style, { display:'grid', gap:'6px' });
    setCols();
    window.addEventListener('resize', setCols);

    const EMOJIS = [
      "😀","😃","😄","😁","😆","😅","😂","🤣",
      "😊","🙂","😉","😍","😘","😜","😎","🤔",
      "😴","😇","😢","😭","😡","🤯","😱","🤗",
      "👍","👎","👏","🙏","🙌","💪","🔥","💯",
      "🎉","✨","🌟","💡","❤️","💚","💙","💛"
    ];
    EMOJIS.forEach(ch=>{
      const b=document.createElement('button');
      b.type='button'; b.textContent=ch; b.setAttribute('aria-label','Emoji '+ch);
      Object.assign(b.style, { fontSize:'20px', lineHeight:'1', padding:'8px', background:'transparent', border:0, borderRadius:'8px', cursor:'pointer', color:'#e5e7eb' });
      b.addEventListener('click', ()=>{ insertAtCaret(INPUT, ch); hide(); INPUT.focus(); });
      grid.appendChild(b);
    });

    head.appendChild(title); head.appendChild(x);
    box.appendChild(head); box.appendChild(grid);
    POP.appendChild(box);
    POP._box = box;
    document.body.appendChild(POP);
    return POP;
  }

  function positionPopup(){
    const r = BTN.getBoundingClientRect();
    const pw = Math.min(360, window.innerWidth*0.92);
    const ph = Math.min(POP._box.scrollHeight||0, window.innerHeight*0.5);
    const m = 8;
    const spaceBelow = window.innerHeight - r.bottom;
    const top = (spaceBelow > ph + m) ? (r.bottom + m) : Math.max(m, r.top - ph - m);
    let left = r.right - pw;
    left = Math.min(left, window.innerWidth - pw - m);
    left = Math.max(left, m);
    POP.style.top  = Math.round(top + window.scrollY) + 'px';
    POP.style.left = Math.round(left + window.scrollX) + 'px';
    POP.style.width= pw + 'px';
  }

  function show(){ buildPopup(); positionPopup(); POP.style.display='block';
    document.addEventListener('click', onDocClick, true);
    document.addEventListener('keydown', onKey, true);
  }
  function hide(){ if (!POP) return; POP.style.display='none';
    document.removeEventListener('click', onDocClick, true);
    document.removeEventListener('keydown', onKey, true);
  }
  function toggle(){ (POP && POP.style.display==='block') ? hide() : show(); }

  function onDocClick(e){
    if (e.target === BTN || BTN.contains(e.target)) return;
    if (POP && POP.contains(e.target)) return;
    hide();
  }
  function onKey(e){ if (e.key === 'Escape') hide(); }

  // Einfügen ins <input> an der Cursor-Position
  function insertAtCaret(el, text){
    const start = el.selectionStart ?? el.value.length;
    const end   = el.selectionEnd ?? start;
    el.value = el.value.slice(0,start) + text + el.value.slice(end);
    const pos = start + Array.from(text).length;
    try { el.setSelectionRange(pos, pos); } catch(_) {}
    el.dispatchEvent(new Event('input', { bubbles:true }));
  }

  // WICHTIG: Früh abfangen, damit andere Listener den Klick nicht „verbrauchen“
  function openHandler(e){
    if (e.target === BTN || BTN.contains(e.target)) {
      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      toggle();
    }
  }
  // Dokument-weit im Capture-Modus → wir kommen vor fast allen anderen Listeners dran
  document.addEventListener('pointerdown', openHandler, true);
  document.addEventListener('click',        openHandler, true);

})();

// /js/emoji-popup.js
(function(){
  const btn   = document.getElementById('emojiBtn');
  const input = document.getElementById('replyText');
  const popup = document.getElementById('emojiPopup');
  const close = popup?.querySelector('.emoji-close');
  if (!btn || !input || !popup) return;

  // Inline onclicks zuverlässig entfernen
  if (btn.getAttribute('onclick')) btn.removeAttribute('onclick');

  // Caret-Tracking, damit Einfügen an der letzten Cursorposition funktioniert
  let caret = { start: input.value.length, end: input.value.length };
  function rememberCaret(){
    caret.start = input.selectionStart ?? input.value.length;
    caret.end   = input.selectionEnd ?? caret.start;
  }
  ['keyup','click','input','focus'].forEach(ev => input.addEventListener(ev, rememberCaret, {passive:true}));
  document.addEventListener('selectionchange', ()=>{
    if (document.activeElement === input) rememberCaret();
  });

  function toggle(open){
    const willOpen = (typeof open === 'boolean') ? open : popup.hasAttribute('hidden');
    if (willOpen){
      popup.removeAttribute('hidden');
      popup.setAttribute('aria-hidden','false');
      // Fokus nicht verschieben, damit Caret erhalten bleibt
    }else{
      popup.setAttribute('hidden','');
      popup.setAttribute('aria-hidden','true');
    }
  }

  // Buttonklick – früh abfangen, andere Handler stoppen
btn.addEventListener('click', (e)=>{
  e.preventDefault();
  e.stopPropagation();
  if (e.stopImmediatePropagation) e.stopImmediatePropagation();
  toggle();   // oder: show(); wenn du kein Toggle möchtest
}, { capture:true });

  // Emoji anklicken → einfügen
  popup.addEventListener('click', (e)=>{
    const b = e.target.closest('button.emo');
    if (!b) return;
    insertAtCaret(input, b.dataset.emo || b.textContent || '');
    toggle(false);
    input.focus();
  });

  close?.addEventListener('click', (e)=>{ e.preventDefault(); toggle(false); });

  // Außenklick/ESC schließt
  document.addEventListener('click', (e)=>{
    if (popup.hasAttribute('hidden')) return;
    if (e.target === btn || btn.contains(e.target)) return;
    if (popup.contains(e.target)) return;
    toggle(false);
  }, true);
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape' && !popup.hasAttribute('hidden')) toggle(false); });

  // Einfügen an gemerkter Caret-Position
  function insertAtCaret(el, text){
    const start = caret.start ?? el.value.length;
    const end   = caret.end   ?? start;
    el.value = el.value.slice(0, start) + text + el.value.slice(end);

    const pos = start + Array.from(text).length;
    try { el.setSelectionRange(pos, pos); } catch(_){}
    rememberCaret();

    el.dispatchEvent(new Event('input',  { bubbles:true }));
    el.dispatchEvent(new Event('change', { bubbles:true }));
  }
})();

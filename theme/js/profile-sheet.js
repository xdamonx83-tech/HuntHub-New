(function(){
  const btn   = document.getElementById('mbnavProfile');
  const sheet = document.getElementById('profileSheet');

  if (!btn || !sheet) return;

  function openSheet(){
    sheet.hidden = false;
    sheet.classList.add('open');
    btn.setAttribute('aria-expanded','true');
    btn.classList.add('active');          // zeigt den dunklen „Profil“-Chip
    document.body.style.overflow = 'hidden';
  }
  function closeSheet(){
    sheet.classList.remove('open');
    setTimeout(()=>{ sheet.hidden = true; }, 200);
    btn.setAttribute('aria-expanded','false');
    btn.classList.remove('active');
    document.body.style.overflow = '';
  }

  btn.addEventListener('click', (e)=>{
    e.preventDefault();
    if (sheet.classList.contains('open')) closeSheet(); else openSheet();
  });

  const closeBtn = sheet.querySelector('[data-close]');
  closeBtn?.addEventListener('click', (e)=>{ e.preventDefault(); closeSheet(); });

  // Schließen per ESC/Backdrop
  sheet.addEventListener('click', (e)=>{
    if (e.target === sheet) closeSheet();
  });
  window.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape' && sheet.classList.contains('open')) closeSheet();
  });
})();


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
    btn.setAttribute('aria-expanded','false');
    btn.classList.remove('active');
    document.body.style.overflow = '';
    setTimeout(()=>{ sheet.hidden = true; }, 200);
  }

  btn.addEventListener('click', openSheet);
  sheet.addEventListener('click', (e)=>{
    if (e.target.dataset.close === '1' || e.target.closest('[data-close]')) closeSheet();
  });
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape' && sheet.classList.contains('open')) closeSheet(); });
})();

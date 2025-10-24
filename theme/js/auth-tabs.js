(function(){
  const sheet = document.getElementById('profileSheet');
  if (!sheet) return;

  const titleEl = sheet.querySelector('#psTitle') || sheet.querySelector('.ps-hd strong');
  const tabs    = sheet.querySelectorAll('[data-auth-tab]');
  const loginF  = sheet.querySelector('#loginForm');
  const regF    = sheet.querySelector('#regForm');

  if (!tabs.length || !loginF || !regF) return; // eingeloggter Zustand o.ä.

  function setMode(mode){ // 'login' | 'register'
    const isLogin = mode === 'login';
    tabs.forEach(btn=>{
      const active = btn.dataset.authTab === mode;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    loginF.hidden = !isLogin;
    regF.hidden   =  isLogin;
    if (titleEl) titleEl.textContent = isLogin ? 'Login' : 'Registrieren';
    (isLogin ? loginF : regF).querySelector('input')?.focus();
  }

  tabs.forEach(btn=>{
    btn.addEventListener('click', ()=> setMode(btn.dataset.authTab));
  });

  // Standard: Login aktiv
  setMode('login');
})();

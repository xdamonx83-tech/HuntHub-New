async function postJSON(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  const json = await res.json();
  if (!res.ok) throw json;
  return json;
}

const loginForm = document.getElementById('loginForm');
if (loginForm) loginForm.onsubmit = async (e)=>{
  e.preventDefault();
  const fd = new FormData(loginForm);
  const msg = document.getElementById('loginMsg');
  msg.textContent = '…';
  try {
    await postJSON('/api/auth/login.php', {
      email: fd.get('email'),
      password: fd.get('password')
    });
    location.reload();
  } catch(err){ msg.textContent = err?.error||'Fehler'; }
};

const registerForm = document.getElementById('regForm');
if (registerForm) registerForm.onsubmit = async (e)=>{
  e.preventDefault();
  const fd = new FormData(registerForm);
  const msg = document.getElementById('regMsg');
  msg.textContent = '…';
  try {
    await postJSON('/api/auth/register.php', {
      display_name: fd.get('display_name'),
      email: fd.get('email'),
      password: fd.get('password')
    });
    msg.textContent = 'Konto erstellt. Du bist jetzt eingeloggt.';
    location.reload();
  } catch(err){ msg.textContent = err?.error||'Fehler'; }
};

const logoutBtn = document.getElementById('logout');
if (logoutBtn) logoutBtn.onclick = async (e)=>{
  e.preventDefault();
  await fetch('/api/auth/logout.php',{method:'POST'});
  location.reload();
};

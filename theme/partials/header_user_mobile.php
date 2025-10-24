<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth/guards.php';

$me = function_exists('optional_auth') ? optional_auth() : current_user();

$displayName = $me['display_name'] ?? 'Gast';
$avatar      = !empty($me['avatar_path']) ? $me['avatar_path'] : '/assets/images/avatars/placeholder.png';
?>

      <div class="sheet-bd ">
      <?php if ($me): ?>
        <div class="ps-user">
          <img class="ps-ava" src="<?= htmlspecialchars($meAvatar) ?>" alt="">
          <div>
            <div class="ps-name"><?= htmlspecialchars($me['display_name'] ?? 'Profil') ?></div>
            <a class="ps-link" href="<?= htmlspecialchars($meProfile) ?>">Profil ansehen</a>
          </div>
        </div>

        <div class="ps-actions">
          <a class="ps-btn" href="<?= htmlspecialchars($meProfile) ?>"><i class="ti ti-user"></i><span>Profil</span></a>
          <a class="ps-btn" href="<?= $APP_BASE ?>/user-settings.php"><i class="ti ti-settings"></i><span>Profil bearbeiten</span></a>
          <button class="ps-btn danger" id="logout"><i class="ti ti-logout-2"></i><span>Logout</span></button>
        </div>
      <?php else: ?>


  <div class="ps-tabs" role="tablist" aria-label="Anmeldung">
    <button type="button" class="ps-tab is-active" data-auth-tab="login" aria-selected="true">Login</button>
    <button type="button" class="ps-tab" data-auth-tab="register" aria-selected="false">Registrieren</button>
  </div>
  


  <form data-auth="login" class="ps-authform">
    <div class="ps-field">
      <label for="loginEmail">E-Mail</label>
      <input id="loginEmail" name="email" type="email" class="ps-input" required>
    </div>
    <div class="ps-field">
      <label for="loginPass">Passwort</label>
      <input id="loginPass" type="password" name="password" class="ps-input" required>
    </div>
    <button class="ps-submit" type="submit">
      <i class="ti ti-login-2"></i><span>Einloggen</span>
    </button>
   <div data-auth-msg class="text-xs mt-2"></div>
  </form>
    <form id="regForm" autocomplete="on" class="ps-authform" hidden>
    <div class="ps-field">
      <label for="regName">Anzeigename</label>
      <input id="regName" name="display_name" class="ps-input" required>
    </div>
    <div class="ps-field">
      <label for="regEmail">E-Mail</label>
      <input id="regEmail" name="email" type="email" class="ps-input" required>
    </div>
    <div class="ps-field">
      <label for="regPass">Passwort</label>
      <input id="regPass" name="password" type="password" class="ps-input" required>
    </div>
    <button class="ps-submit" type="submit">
      <i class="ti ti-user-plus"></i><span>Konto erstellen</span>
    </button>
   
	<div id="loginMsg" class="msg err"></div>
  </form>
        <?php endif; ?>
    </div>


<script>
(() => {
  const SHEET = document.getElementById('profileSheet'); // dein Fullscreen-Popup
  if (!SHEET) return;

  async function postJSON(url, data) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'Accept':'application/json' },
      body: JSON.stringify(data),
      credentials: 'include' // wichtig, damit die Session-Cookies sicher gesetzt/mitgesendet werden
    });
    const out = await res.json().catch(() => ({}));
    if (!res.ok || out.ok === false) throw new Error(out?.error || `HTTP ${res.status}`);
    return out;
  }

  // Login binden (nur im Sheet):
  SHEET.querySelectorAll('form[data-auth="login"]').forEach((form) => {
    const msg = form.querySelector('[data-auth-msg]');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg && (msg.textContent = 'Bitte warten …');
      const fd = new FormData(form);
      try {
        await postJSON('/api/auth/login.php', {
          email: String(fd.get('email') || '').trim(),
          password: fd.get('password')
        });
        msg && (msg.textContent = 'Eingeloggt.');
        location.reload();
      } catch (err) {
        msg && (msg.textContent = err.message || 'Login fehlgeschlagen.');
      }
    });
  });

  // Registrierung binden (nur im Sheet):
  SHEET.querySelectorAll('form[data-auth="register"]').forEach((form) => {
    const msg = form.querySelector('[data-auth-msg]');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg && (msg.textContent = 'Bitte warten …');
      const fd = new FormData(form);
      try {
        await postJSON('/api/auth/register.php', {
          display_name: fd.get('display_name'),
          email: fd.get('email'),
          password: fd.get('password')
        });
        msg && (msg.textContent = 'Konto erstellt. Du wirst eingeloggt …');
        location.reload();
      } catch (err) {
        msg && (msg.textContent = err.message || 'Registrierung fehlgeschlagen.');
      }
    });
  });
})();
</script>


<script>
(function(){
  async function postJSON(url, data) {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'include', // Cookies mitnehmen/annehmen
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(data)
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw json;
    return json;
  }
  function hardReload() {
    location.assign(location.pathname + location.search);
  }

  const loginForm = document.getElementById('loginForm');
  if (loginForm) loginForm.onsubmit = async (e)=>{
    e.preventDefault();
    const fd  = new FormData(loginForm);
    const msg = document.getElementById('loginMsg');
    msg.classList.remove('ok'); msg.classList.add('err'); msg.textContent = '…';
    try {
      const out = await postJSON('/api/auth/login.php', {
        email: fd.get('email'),
        password: fd.get('password')
      });
      msg.classList.remove('err'); msg.classList.add('ok'); msg.textContent = 'Eingeloggt.';
      if (out.redirect) { location.assign(out.redirect); return; }
      hardReload();
    } catch (err) {
      msg.classList.remove('ok'); msg.classList.add('err');
      msg.textContent = (err && err.error) ? err.error : 'Login fehlgeschlagen.';
    }
  };

  const logoutBtn = document.getElementById('logout');
  if (logoutBtn) logoutBtn.onclick = async (e)=>{
    e.preventDefault();
    try {
      await fetch('/api/auth/logout.php', { method:'POST', credentials:'include' });
    } finally {
      hardReload();
    }
  };
})();
</script>
<?php
declare(strict_types=1);

/**
 * Benutzer-Menü mit Login (Gast) / Profil-Aktionen (eingeloggt).
 * Einbinden an gewünschter Stelle, z. B. im Header:
 *   include __DIR__ . '/partials/user_menu.php';
 */

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

// Basis-Konfig laden (für Pfade, Cookies)
$cfg      = require __DIR__ . '/../../auth/config.php';
$APP_BASE = rtrim((string)($cfg['app_base'] ?? ''), '/');

$pdo = db();
$me  = function_exists('optional_auth') ? optional_auth() : null;

// CSRF-Token für Formular(e)
$sessionCookie = $_COOKIE[$cfg['cookies']['session_name']] ?? '';
$csrf = issue_csrf($pdo, $sessionCookie);

// Fallback-Avatar
$avatarFallback = $APP_BASE . '/assets/images/avatars/placeholder.png';

// Hilfswerte
$redirectBack = $_SERVER['REQUEST_URI'] ?? ($APP_BASE ?: '/');
$isLoggedIn   = !empty($me) && isset($me['id']);
$displayName  = $isLoggedIn ? (string)($me['display_name'] ?? 'User') : 'Gast';
$role         = $isLoggedIn ? (string)($me['role'] ?? 'user') : 'guest';
$avatar       = $isLoggedIn && !empty($me['avatar_path']) ? $me['avatar_path'] : $avatarFallback;

// Optionale Routen (falls vorhanden, sonst zeigen wir sie nicht an)
$profileUrl   = $isLoggedIn ? ($APP_BASE . '/user.php?id=' . (int)$me['id']) : null;
$settingsUrl  = $APP_BASE . '/settings/index.php';   // ggf. anpassen
$helpUrl      = $APP_BASE . '/help.php';             // ggf. anpassen
$logoutUrl    = $APP_BASE . '/auth/logout.php?next=' . urlencode($redirectBack);
$loginAction  = $APP_BASE . '/auth/login.php';       // deine bestehende Login-Route
$registerUrl  = $APP_BASE . '/auth/register.php';    // ggf. anpassen
?>

<style>
  /* Minimaler, unaufdringlicher Stil – nutzt deine bestehenden Farben/Abstände */
  .umenu { position: relative; }
  .umenu-toggle { display:flex; align-items:center; gap:.75rem; background:transparent; border:0; color:#fff; cursor:pointer; }
  .umenu-toggle .ava { width:40px; height:40px; border-radius:999px; object-fit:cover; border:1px solid rgba(255,255,255,.1); }
  .umenu-toggle .name { font-weight:600; line-height:1.1; }
  .umenu-toggle .caret { font-size:14px; opacity:.7; transform: translateY(1px); }
  .umenu-menu { position:absolute; top:120%; right:0; min-width:240px; background:#1a1a1a; color:#e5e7eb;
                border:1px solid rgba(255,255,255,.08); border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,.4);
                padding:12px; z-index:10000; display:none; }
  .umenu-menu.open { display:block; }
  .umenu-item { display:block; padding:10px 12px; border-radius:10px; color:#e5e7eb; text-decoration:none; }
  .umenu-item:hover { background:#242424; }
  .umenu-sep { height:1px; background:rgba(255,255,255,.08); margin:8px 4px; border:0; }
  .umenu-subtle { color:#9aa5b1; font-size:.9rem; margin-top:-6px; margin-bottom:6px; }

  /* Login-Form im Dropdown */
  .ulogin { display:flex; flex-direction:column; gap:10px; }
  .ulogin input { width:100%; background:#121212; border:1px solid rgba(255,255,255,.12); border-radius:10px; color:#e5e7eb; padding:10px; }
  .ulogin .row { display:flex; gap:10px; }
  .btn { padding:.55rem .9rem; border-radius:10px; border:1px solid rgba(255,255,255,.15); background:#1f4f99; color:#fff; cursor:pointer; }
  .btn:hover { filter:brightness(1.07); }
  .btn-ghost { background:#232323; }
  .uerror { color:#fca5a5; font-size:.9rem; display:none; }
  .uok    { color:#86efac; font-size:.9rem; display:none; }
</style>

<div class="umenu" id="umenu-root">
  <button class="umenu-toggle" id="umenu-toggle" aria-expanded="false" aria-haspopup="menu" type="button">
    <img class="ava" src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
    <div>
      <div class="name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
      <?php if ($isLoggedIn): ?>
        <div class="umenu-subtle"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></div>
      <?php else: ?>
        <div class="umenu-subtle">Nicht angemeldet</div>
      <?php endif; ?>
    </div>
    <span class="caret">▾</span>
  </button>

  <div class="umenu-menu" id="umenu-menu" role="menu" aria-hidden="true">
    <?php if ($isLoggedIn): ?>
      <?php if ($profileUrl): ?>
        <a class="umenu-item" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" role="menuitem">Profil</a>
      <?php endif; ?>
      <?php if ($settingsUrl): ?>
        <a class="umenu-item" href="<?= htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8') ?>" role="menuitem">Einstellungen</a>
      <?php endif; ?>
      <?php if (in_array($role, ['administrator','moderator'], true)): ?>
        <a class="umenu-item" href="<?= htmlspecialchars($APP_BASE . '/admin/index.php', ENT_QUOTES, 'UTF-8') ?>" role="menuitem">Admin</a>
      <?php endif; ?>
      <hr class="umenu-sep">
      <a class="umenu-item" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>" role="menuitem">Logout</a>
      <?php if ($helpUrl): ?>
        <a class="umenu-item" href="<?= htmlspecialchars($helpUrl, ENT_QUOTES, 'UTF-8') ?>" role="menuitem">Hilfe</a>
      <?php endif; ?>
    <?php else: ?>
      <form class="ulogin" id="umenu-login" action="<?= htmlspecialchars($loginAction, ENT_QUOTES, 'UTF-8') ?>" method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="next" value="<?= htmlspecialchars($redirectBack, ENT_QUOTES, 'UTF-8') ?>">
        <input type="email"    name="email"    placeholder="E-Mail"    autocomplete="email" required>
        <input type="password" name="password" placeholder="Passwort"  autocomplete="current-password" required>
        <div class="row">
          <button type="submit" class="btn">Einloggen</button>
          <?php if ($registerUrl): ?>
            <a class="btn btn-ghost" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Registrieren</a>
          <?php endif; ?>
        </div>
        <div class="uerror" id="umenu-error"></div>
        <div class="uok" id="umenu-ok"></div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const root   = document.getElementById('umenu-root');
  const toggle = document.getElementById('umenu-toggle');
  const menu   = document.getElementById('umenu-menu');
  if (!root || !toggle || !menu) return;

  function openMenu(){ menu.classList.add('open'); toggle.setAttribute('aria-expanded','true'); }
  function closeMenu(){ menu.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); }
  function isOpen(){ return menu.classList.contains('open'); }

  toggle.addEventListener('click', (e) => {
    e.preventDefault();
    isOpen() ? closeMenu() : openMenu();
  });

  document.addEventListener('click', (e) => {
    if (!root.contains(e.target)) closeMenu();
  });

  // Progressive-Enhancement: AJAX-Login, wenn möglich (sonst normal absenden)
  const form = document.getElementById('umenu-login');
  if (form) {
    form.addEventListener('submit', async (e) => {
      // Versuche per fetch; bei Fehler -> normales Submit
      e.preventDefault();
      const err = document.getElementById('umenu-error');
      const ok  = document.getElementById('umenu-ok');
      if (err) { err.style.display='none'; err.textContent=''; }
      if (ok)  { ok.style.display='none';  ok.textContent='';  }

      try {
        const body = new URLSearchParams(new FormData(form));
        const res  = await fetch(form.action, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8', 'Accept':'application/json,text/html' },
          body
        });

        // Wenn der Server weiterleitet (klassischer Login), Seite neu laden
        if (res.redirected) { window.location.href = res.url; return; }

        const ctype = res.headers.get('Content-Type') || '';
        if (ctype.includes('application/json')) {
          const out = await res.json().catch(() => ({}));
          if (!res.ok || out.ok === false) {
            if (err) { err.textContent = out.error || 'Login fehlgeschlagen.'; err.style.display='block'; }
            return;
          }
          // Erfolg (JSON): Seite neu laden / weiterleiten
          window.location.reload();
          return;
        }

        // Fallback: keine JSON-Antwort – einfach neu laden
        window.location.reload();
      } catch (ex) {
        // Fallback auf echtes Submit
        form.removeEventListener('submit', arguments.callee);
        form.submit();
      }
    });
  }
})();
</script>

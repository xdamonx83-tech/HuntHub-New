<?php
declare(strict_types=1);

/**
 * /register.php – Eigenständige Registrierungsseite für Hunthub3
 * Nutzt: lib/layout.php (Meta/CSRF/i18n), api/auth/register.php (POST)
 */

require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/layout.php';

$pdo  = $pdo ?? db();
$cfg  = require __DIR__ . '/auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/'); // Root => ''

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$L = $GLOBALS['L'];

// Wenn bereits eingeloggt -> nach Startseite
if (function_exists('current_user')) {
    $me = current_user();
    if (!empty($me['id'])) {
        header('Location: ' . ($APP_BASE ?: '/'));
        exit;
    }
}

$title = ($lang === 'en') ? 'Register' : 'Registrieren';
start_layout($title, [
  'body_class' => 'page-register',
  'noindex'    => true, // Suchmaschinen sollen die Seite nicht indexieren
]);

// kleine Übersetzungs-Fallbacks
$t_email        = $L['email']         ?? ($lang === 'en' ? 'Email' : 'E-Mail');
$t_display_name = $L['display_name']  ?? ($lang === 'en' ? 'Display name' : 'Anzeigename');
$t_password     = $L['password']      ?? ($lang === 'en' ? 'Password' : 'Passwort');
$t_password2    = $L['password2']     ?? ($lang === 'en' ? 'Repeat password' : 'Passwort (Wiederholung)');
$t_terms        = $L['terms_accept']  ?? ($lang === 'en' ? 'I accept the terms.' : 'Ich akzeptiere die Bedingungen.');
$t_btn_reg      = $L['register']      ?? ($lang === 'en' ? 'Create account' : 'Account erstellen');
$t_or           = $L['or']            ?? ($lang === 'en' ? 'or' : 'oder');
$t_oauth        = $L['signin_with']   ?? ($lang === 'en' ? 'Sign up with' : 'Registrieren mit');
$t_has_account  = $L['has_account']   ?? ($lang === 'en' ? 'Already have an account?' : 'Schon ein Konto?');
$t_login        = $L['login']         ?? ($lang === 'en' ? 'Log in' : 'Einloggen');
$t_headline     = $L['register_head'] ?? ($lang === 'en' ? 'Create your Hunthub account' : 'Erstelle deinen Hunthub-Account');
$t_privacy_hint = $L['privacy_hint']  ?? ($lang === 'en' ? 'By registering you agree to our Privacy Policy.' : 'Mit der Registrierung stimmst du unserer Datenschutzerklärung zu.');
$t_terms_link   = $L['terms']         ?? ($lang === 'en' ? 'Terms' : 'Nutzungsbedingungen');
$t_privacy      = $L['privacy']       ?? ($lang === 'en' ? 'Privacy Policy' : 'Datenschutzerklärung');

// ggf. vorhandene Seiten
$privacyUrl = $APP_BASE . '/privacy.php';
$loginAnchor = '#login'; // Deine Modalseite hat meist #login; passe ggf. an

?>
<style>
  .auth-wrap {
    min-height: calc(100dvh - 120px);
    display:flex; align-items:center; justify-content:center;
    padding: 2rem 1rem;
  }
  .auth-card {
    width: 100%; max-width: 560px;
    background: #0f0f0f; color:#e5e7eb;
    border:1px solid rgba(255,255,255,.08);
    border-radius: 16px; box-shadow: 0 20px 70px rgba(0,0,0,.5);
    overflow:hidden;
  }
  .auth-head {
    padding: 1.25rem 1.25rem 0.5rem;
    border-bottom:1px solid rgba(255,255,255,.06);
  }
  .auth-head h1 {
    margin:0; font-size:1.4rem; line-height:1.2; font-weight:700;
  }
  .auth-body { padding: 1.25rem; }
  .field {
    display:flex; flex-direction:column; gap:.4rem; margin-bottom:.9rem;
  }
  .field label { font-size:.95rem; color:#cbd5e1; }
  .input {
    background:#141414; border:1px solid rgba(255,255,255,.14);
    color:#e5e7eb; border-radius:10px; padding:.7rem .85rem; outline:none;
  }
  .input:focus { border-color:#8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,.15); }
  .row { display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
  @media (max-width: 640px){ .row { grid-template-columns:1fr; } }

  .terms { display:flex; align-items:flex-start; gap:.6rem; font-size:.95rem; color:#cbd5e1; margin-top:.2rem; }
  .terms input { margin-top:.15rem; }
  .btn {
    display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
    background:#8b5cf6; color:white; border:none; border-radius:10px;
    padding:.8rem 1rem; cursor:pointer; font-weight:600; width:100%;
  }
  .btn[disabled] { opacity:.6; cursor:not-allowed; }
  .oauth {
    display:flex; gap:.6rem; margin-top:.8rem; flex-wrap:wrap;
  }
  .btn-ghost {
    background: transparent; border:1px solid rgba(255,255,255,.16);
    color:#e5e7eb; border-radius:10px; padding:.7rem .9rem; flex:1;
    min-width: 46%;
  }
  .sep { display:flex; align-items:center; gap:.8rem; margin:1rem 0; color:#9ca3af; }
  .sep::before, .sep::after { content:""; height:1px; flex:1; background:rgba(255,255,255,.12); }

  .hint { color:#a1a1aa; font-size:.9rem; margin-top:.6rem; }
  .error { color:#fca5a5; background: rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); padding:.6rem .7rem; border-radius:10px; margin-bottom:.8rem; display:none; }
  .success { color:#86efac; background: rgba(22,163,74,.1); border:1px solid rgba(22,163,74,.35); padding:.6rem .7rem; border-radius:10px; margin-bottom:.8rem; display:none; }
  .foot {
    padding: .9rem 1.25rem; border-top:1px solid rgba(255,255,255,.06); display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap;
    font-size:.95rem; color:#cbd5e1;
  }
  .foot a { color:#e5e7eb; text-decoration: underline dotted; }
</style>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-head">
      <h1><?= htmlspecialchars($t_headline) ?></h1>
    </div>
    <div class="auth-body">
      <div id="msgError" class="error"></div>
      <div id="msgSuccess" class="success"></div>

      <form id="regForm" autocomplete="on" novalidate>
        <div class="field">
          <label for="display_name"><?= htmlspecialchars($t_display_name) ?></label>
          <input class="input" id="display_name" name="display_name" type="text" maxlength="40" required placeholder="z. B. NightRunner">
        </div>

        <div class="field">
          <label for="email"><?= htmlspecialchars($t_email) ?></label>
          <input class="input" id="email" name="email" type="email" required placeholder="you@example.com">
        </div>

        <div class="row">
          <div class="field">
            <label for="password"><?= htmlspecialchars($t_password) ?></label>
            <input class="input" id="password" name="password" type="password" minlength="6" required placeholder="••••••••">
          </div>
          <div class="field">
            <label for="password2"><?= htmlspecialchars($t_password2) ?></label>
            <input class="input" id="password2" name="password2" type="password" minlength="6" required placeholder="••••••••">
          </div>
        </div>

        <label class="terms">
          <input type="checkbox" id="accept" required>
          <span>
            <?= htmlspecialchars($t_terms) ?>
            (<a href="<?= htmlspecialchars($privacyUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($t_privacy) ?></a>)
          </span>
        </label>

        <button id="btnSubmit" class="btn" type="submit"><?= htmlspecialchars($t_btn_reg) ?></button>

        <div class="sep"><?= htmlspecialchars($t_or) ?></div>

        <div class="oauth">
          <!-- Optional: OAuth-Knöpfe – nur anzeigen, wenn konfiguriert -->
          <a class="btn-ghost" href="<?= $APP_BASE ?>/api/auth/oauth_start.php?provider=google">Google</a>
          <a class="btn-ghost" href="<?= $APP_BASE ?>/api/auth/oauth_start.php?provider=discord">Discord</a>
          <!-- Füge weitere Provider hinzu, die du in /api/auth/oauth_* konfiguriert hast -->
        </div>

        <p class="hint"><?= htmlspecialchars($t_privacy_hint) ?></p>
      </form>
    </div>
    <div class="foot">
      <div><?= htmlspecialchars($t_has_account) ?></div>
      <div><a href="<?= $APP_BASE ?>/<?= ltrim($loginAnchor, '/') ?>"><?= htmlspecialchars($t_login) ?></a></div>
    </div>
  </div>
</div>

<script>
(function(){
  const BASE = "<?= $APP_BASE ?>";
  const API  = (p) => `${BASE}/api/auth/${p}`;
  const csrf =
    document.querySelector('meta[name="csrf"]')?.getAttribute('content') ||
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    '';

  const el = (id) => document.getElementById(id);
  const $error = el('msgError');
  const $success = el('msgSuccess');
  const $form = el('regForm');
  const $btn = el('btnSubmit');

  function showError(msg){
    $error.textContent = msg || 'Unbekannter Fehler.';
    $error.style.display = 'block';
    $success.style.display = 'none';
  }
  function showSuccess(msg){
    $success.textContent = msg || 'Registrierung erfolgreich.';
    $success.style.display = 'block';
    $error.style.display = 'none';
  }

  function samePwd(){
    const p1 = el('password').value.trim();
    const p2 = el('password2').value.trim();
    return p1 && p2 && p1 === p2;
  }

  $form.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    $error.style.display = 'none';
    $success.style.display = 'none';

    if (!samePwd()){
      showError('Passwörter stimmen nicht überein.');
      return;
    }
    if (!el('accept').checked){
      showError('Bitte Bedingungen/Datenschutz akzeptieren.');
      return;
    }

    const fd = new FormData($form);
    // Viele deiner APIs akzeptieren beides: Header + Feld.
    if (csrf) fd.append('csrf', csrf);

    // Minimalvalidierung
    const email = (fd.get('email')||'').toString().trim();
    const display = (fd.get('display_name')||'').toString().trim();
    const pass = (fd.get('password')||'').toString();
    if (!email || !display || !pass) {
      showError('Bitte alle Felder ausfüllen.');
      return;
    }

    $btn.disabled = true;
    $btn.textContent = 'Wird erstellt…';

    try {
      const res = await fetch(API('register.php'), {
        method: 'POST',
        headers: {
          'X-CSRF': csrf || ''
        },
        body: fd,
        credentials: 'include'
      });

      let data = null;
      try { data = await res.json(); } catch(_){}

      if (!res.ok) {
        const msg = (data && (data.error || data.message)) || ('HTTP ' + res.status);
        showError(msg);
      } else {
        if (data && (data.ok || data.success)) {
          showSuccess('Account erstellt. Du wirst weitergeleitet…');
          // Falls API bereits ein Ziel liefert, nutzen:
          const goto = (data.redirect || '').toString() || (BASE || '/') + '/profile.php';
          setTimeout(()=>{ location.href = goto; }, 600);
        } else {
          // Manche Responses liefern nur message:
          showSuccess(data && data.message ? data.message : 'Account erstellt. Weiterleiten…');
          setTimeout(()=>{ location.href = (BASE || '/') + '/profile.php'; }, 600);
        }
      }
    } catch (err){
      showError('Netzwerk-/Serverfehler. Bitte später erneut versuchen.');
    } finally {
      $btn.disabled = false;
      $btn.textContent = '<?= htmlspecialchars($t_btn_reg) ?>';
    }
  });
})();
</script>

<?php end_layout(); ?>

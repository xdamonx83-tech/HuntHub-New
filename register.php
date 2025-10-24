<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/auth/db.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$pdo = db();

$me = current_user();
if (!empty($me['id'])) {
  header('Location: /'); // Eingeloggt? -> Startseite
  exit;
}

ob_start();
?>
<main>

  <!-- breadcrumb start -->
  <section class="pt-30p">
    <div class="section-pt">
      <div class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
        <div class="container">
          <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
            <div class="lg:col-start-2 lg:col-end-12 col-span-12">
              <h2 class="heading-2 text-w-neutral-1 mb-3">
                <?= t('register_head', $lang === 'en' ? 'Create your Hunthub account' : 'Erstelle deinen Hunthub-Account') ?>
              </h2>
              <ul class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="/" class="breadcrumb-link">
                    <?= t('home', $lang === 'en' ? 'Home' : 'Startseite') ?>
                  </a>
                </li>
                <li class="breadcrumb-item">
                  <span class="breadcrumb-icon">
                    <i class="ti ti-chevrons-right"></i>
                  </span>
                </li>
                <li class="breadcrumb-item">
                  <span class="breadcrumb-current"><?= t('register_sign_up', $lang === 'en' ? 'Sign Up' : 'Registrieren') ?></span>
                </li>
              </ul>
            </div>
          </div>
        </div>
        <div class="overlay-11"></div>
      </div>
    </div>
  </section>
  <!-- breadcrumb end -->

  <!-- sign up section start -->
  <section class="section-py">
    <div class="container"><div id="refInfo" class="hidden mb-20p p-12p rounded-12 bg-primary/10 border border-primary/30 text-primary"></div>

      <div class="flex-c">
        <div class="max-w-[530px] w-full p-40p bg-b-neutral-3 rounded-12">
          <h2 class="heading-2 text-w-neutral-1 mb-16p text-center">
            <?= t('register_sign_up', $lang === 'en' ? 'Sign Up' : 'Registrieren') ?>
          </h2>
          <p class="text-m-medium text-w-neutral-3 text-center">
            <?= t('has_account', $lang === 'en' ? 'Already have an account?' : 'Schon ein Konto?') ?>
            <a href="/login.php" class="inline text-primary"><?= t('login', $lang === 'en' ? 'Log in' : 'Einloggen') ?></a>
          </p>

          <div class="grid grid-cols-1 gap-3 py-32p text-center">
            <a href="/api/auth/oauth_start.php?provider=google" class="btn btn-md bg-[#434DE4] hover:bg-[#434DE4]/80 w-full">
              <i class="ti ti-brand-google icon-24"></i>
              <?= t('register_log_in_with_google', $lang === 'en' ? 'Log In With Google' : 'Mit Google einloggen') ?>
            </a>
            <a href="/api/auth/oauth_start.php?provider=twitch" class="btn btn-md bg-[#6E31DF] hover:bg-[#6E31DF]/80 w-full">
              <i class="ti ti-brand-twitch icon-24"></i>
              <?= t('register_log_in_with_twitch', $lang === 'en' ? 'Log In with Twitch' : 'Mit Twitch einloggen') ?>
            </a>
            <a href="/api/auth/oauth_start.php?provider=facebook" class="btn btn-md bg-[#1876F2] hover:bg-[#1876F2]/80 w-full">
              <i class="ti ti-brand-facebook icon-24"></i>
              <?= t('register_log_in_with_facebook', $lang === 'en' ? 'Log In With Facebook' : 'Mit Facebook einloggen') ?>
            </a>

            <div class="flex items-center gap-3">
              <div class="w-full h-1px bg-shap"></div>
              <span class="text-m-medium text-w-neutral-1"><?= t('or', $lang === 'en' ? 'or' : 'oder') ?></span>
              <div class="w-full h-1px bg-shap"></div>
            </div>
          </div>

          <!-- ... bleibt alles wie gehabt ... -->

<form id="regForm" novalidate autocomplete="on">
  <div class="grid grid-cols-1 gap-30p mb-40p">
    <!-- Username -->
    <div>
      <label for="display_name" class="label label-xl text-w-neutral-1 font-borda mb-3">
        <?= t('username', $lang === 'en' ? 'Username' : 'Benutzername') ?>
      </label>
      <input class="border-input-1" type="text" name="display_name" id="display_name"
             placeholder="<?= t('username', $lang === 'en' ? 'Username' : 'Benutzername') ?>" />
    </div>

    <!-- Password -->
    <div>
      <label for="password" class="label label-xl text-w-neutral-1 font-borda mb-3">
        <?= t('password', $lang === 'en' ? 'Password' : 'Passwort') ?>
      </label>
      <input class="border-input-1" type="password" name="password" id="password"
             placeholder="<?= t('password', $lang === 'en' ? 'Password' : 'Passwort') ?>" />
    </div>

    <!-- Password repeat -->
    <div>
      <label for="password2" class="label label-xl text-w-neutral-1 font-borda mb-3">
        <?= t('password2', $lang === 'en' ? 'Repeat password' : 'Passwort wiederholen') ?>
      </label>
      <input class="border-input-1" type="password" name="password2" id="password2"
             placeholder="<?= t('password2', $lang === 'en' ? 'Repeat password' : 'Passwort wiederholen') ?>" />
    </div>

    <!-- Email -->
    <div>
      <label for="email" class="label label-xl text-w-neutral-1 font-borda mb-3">
        <?= t('email', $lang === 'en' ? 'Email Address' : 'E-Mail Adresse') ?>
      </label>
      <input class="border-input-1" type="email" name="email" id="email"
             placeholder="<?= t('email', $lang === 'en' ? 'Email Address' : 'E-Mail Adresse') ?>" />
    </div>

    <!-- ✅ Referral Code -->
<div>
  <label for="ref_code" class="label label-xl text-w-neutral-1 font-borda mb-3">
    Einladungscode
  </label>
  <input class="border-input-1" type="text" name="ref_code" id="ref_code"
         placeholder="Einladungscode (optional)" />
</div>

    <!-- Accept terms -->
    <div>
      <label class="label label-md text-w-neutral-1 inline-flex items-center cursor-pointer gap-3">
        <input type="checkbox" id="accept" value="1" checked class="sr-only peer togglePricing">
        <span
          class="relative w-11 h-6 bg-w-neutral-1 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:bg-w-neutral-1 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-b-neutral-3 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary shrink-0">
        </span>
        <?= t('terms_accept', $lang === 'en' ? 'I accept the terms.' : 'Ich akzeptiere die Bedingungen.') ?>
        ( <a href="/privacy.php" class="link-1"><?= t('privacy', $lang === 'en' ? 'Privacy Policy' : 'Datenschutzerklärung') ?></a> )
      </label>
    </div>
  </div>

  <button id="btnSubmit" class="btn btn-md btn-primary rounded-12 w-full mb-16p">
    <?= t('register_btn_submit', $lang === 'en' ? 'Sign up for free' : 'Konto erstellen') ?>
  </button>

  <div id="msgError" class="hidden text-danger bg-danger/10 border border-danger/30 rounded-12 p-12p mb-16p"></div>
  <div id="msgSuccess" class="hidden text-success bg-success/10 border border-success/30 rounded-12 p-12p mb-16p"></div>

  <a href="/privacy.php" class="text-m-medium text-primary underline text-center">
    <?= t('privacy', $lang === 'en' ? 'Privacy Policy' : 'Datenschutzerklärung') ?>
  </a>
</form>

        </div>
      </div>
    </div>
  </section>
  <!-- sign up section end -->

</main>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const ref = params.get('ref');
  if (ref) {
    const input = document.querySelector('input[name="ref_code"]');
    if (input) {
      input.value = ref;
      input.readOnly = true;
    }

    // 🔹 Lookup anzeigen
    try {
      const res = await fetch('/api/auth/ref_lookup.php?ref=' + encodeURIComponent(ref));
      const data = await res.json();
      if (data.ok && data.display_name) {
        const box = document.getElementById('refInfo');
        if (box) {
          box.textContent = "Du wurdest eingeladen von " + data.display_name;
          box.classList.remove('hidden');
        }
      }
    } catch (e) {
      console.warn("ref_lookup fehlgeschlagen", e);
    }
  }
});

</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = (s, c=document)=>c.querySelector(s);
  const form = $('#regForm');
  const btn  = $('#btnSubmit') || (form && form.querySelector('[type="submit"]'));
  const err  = $('#msgError');
  const ok   = $('#msgSuccess');

  const MSG_FILL_PASSWORDS   = <?= json_encode(t('reg_err_fill_passwords', 'Bitte beide Passwortfelder ausfüllen.')) ?>;
  const MSG_MISMATCH         = <?= json_encode(t('reg_err_password_mismatch', 'Passwörter stimmen nicht überein.')) ?>;
  const MSG_ACCEPT           = <?= json_encode(t('reg_err_accept_terms', 'Bitte Bedingungen/Datenschutz akzeptieren.')) ?>;
  const MSG_FILL_ALL         = <?= json_encode(t('reg_err_fill_all', 'Bitte alle Felder ausfüllen.')) ?>;
  const MSG_UNKNOWN          = <?= json_encode(t('reg_unknown_error', 'Unbekannter Fehler.')) ?>;
  const MSG_CREATING         = <?= json_encode(t('reg_btn_creating', 'Wird erstellt…')) ?>;
  const MSG_SUCCESS_REDIRECT = <?= json_encode(t('reg_success_redirect', 'Account erstellt. Weiterleiten…')) ?>;
  const MSG_NO_FORM          = <?= json_encode(t('reg_debug_form_missing', 'Formular #regForm nicht gefunden – prüfe id="regForm".')) ?>;
  const MSG_NO_BUTTON        = <?= json_encode(t('reg_debug_button_missing', 'Submit-Button nicht gefunden – gib ihm id="btnSubmit" oder type="submit".')) ?>;

  const show = (el)=> el && el.classList.remove('hidden');
  const hide = (el)=> el && el.classList.add('hidden');
  function showErr(m){ if (err){ err.textContent = m || MSG_UNKNOWN; show(err); } console.error(m); hide(ok); }
  function showOk(m){ if (ok){ ok.textContent  = m || 'OK'; show(ok); } hide(err); }

  if (!form) { showErr(MSG_NO_FORM); return; }
  if (!btn)  { showErr(MSG_NO_BUTTON); return; }

  // Alte doppelte Felder in Overlays neutralisieren (falls vorhanden)
  document.querySelectorAll('.auth-modal input[name="password"], .auth-modal input[name="password2"]').forEach(el=>{
    el.setAttribute('name','_old_'+el.getAttribute('name'));
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    hide(err); hide(ok);

    try {
      const p1 = ($('#password')?.value ?? '').trim();
      const p2 = ($('#password2')?.value ?? '').trim();
      const accept = $('#accept');

      if (!p1 || !p2) return showErr(MSG_FILL_PASSWORDS);
      if (p1 !== p2)   return showErr(MSG_MISMATCH);
      if (!accept || !accept.checked) return showErr(MSG_ACCEPT);

      const fd = new FormData(form);
      const csrf = document.querySelector('meta[name="csrf"]')?.content
                || document.querySelector('meta[name="csrf-token"]')?.content || '';
      if (csrf) fd.append('csrf', csrf);

      const email = (fd.get('email')||'').toString().trim();
      const name  = (fd.get('display_name')||'').toString().trim();
      const pw    = (fd.get('password')||'').toString();
      if (!email || !name || !pw) return showErr(MSG_FILL_ALL);

      const oldTxt = btn.textContent; btn.disabled = true; btn.textContent = MSG_CREATING;

      const res = await fetch('/api/auth/register.php', {
        method: 'POST',
        headers: csrf ? { 'X-CSRF': csrf } : {},
        body: fd,
        credentials: 'include'
      });

      let data = null; try { data = await res.json(); } catch(_) {}

      if (!res.ok) {
        showErr((data && (data.error || data.message)) || ('HTTP ' + res.status));
      } else {
        showOk((data && data.message) || MSG_SUCCESS_REDIRECT);
        const goto = (data && data.redirect) || '/profile.php';
        setTimeout(()=>location.href = goto, 600);
      }

      btn.disabled = false; btn.textContent = oldTxt;
    } catch (ex) {
      console.error(ex);
      showErr(<?= json_encode(t('reg_unknown_error_console', 'Ein Fehler ist aufgetreten. Details in der Konsole.')) ?>);
      btn.disabled = false;
    }
  });

  // Falls ein Overlay den Button blockt
  btn.style.pointerEvents = 'auto';
});
</script>

<?php
$content = ob_get_clean();

$title = empty($me['id'])
  ? ($lang === 'en' ? 'Register – Hunthub Community' : 'Registrieren – Hunthub Community')
  : 'Hunthub';

render_theme_page($content, $title);

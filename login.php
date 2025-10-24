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
  $next = isset($_GET['next']) ? (string)$_GET['next'] : '/';
  header('Location: ' . $next);
  exit;
}

$next = isset($_GET['next']) ? (string)$_GET['next'] : '';
$csrf = function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf'] ?? bin2hex(random_bytes(16)));
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = $csrf;

// Übersetzungen / Fallbacks
$t_headline   = t('login_head',   $lang === 'en' ? 'Sign in to your account' : 'Melde dich an');
$t_email      = t('email',        $lang === 'en' ? 'Email' : 'E-Mail');
$t_password   = t('password',     $lang === 'en' ? 'Password' : 'Passwort');
$t_remember   = t('remember_me',  $lang === 'en' ? 'Remember me' : 'Angemeldet bleiben');
$t_btn_login  = t('login',        $lang === 'en' ? 'Log in' : 'Einloggen');
$t_or         = t('or',           $lang === 'en' ? 'or' : 'oder');
$t_no_acc     = t('no_account',   $lang === 'en' ? "Don't have an account?" : 'Noch kein Konto?');
$t_register   = t('register',     $lang === 'en' ? 'Create account' : 'Account erstellen');
$t_forgot     = t('forgot_pw',    $lang === 'en' ? 'Forgot password?' : 'Passwort vergessen?');

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
              <h2 class="heading-2 text-w-neutral-1 mb-3"><?= t('login', 'Login') ?></h2>
              <ul class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="/" class="breadcrumb-link"><?= t('home', 'Home') ?></a>
                </li>
                <li class="breadcrumb-item"><span class="breadcrumb-icon"><i class="ti ti-chevrons-right"></i></span></li>
                <li class="breadcrumb-item"><span class="breadcrumb-current"><?= t('login', 'Login') ?></span></li>
              </ul>
            </div>
          </div>
        </div>
        <div class="overlay-11"></div>
      </div>
    </div>
  </section>
  <!-- breadcrumb end -->

  <!-- login section start -->
  <section class="section-py">
    <div class="container">
      <div class="flex-c">
        <div class="max-w-[530px] w-full p-40p bg-b-neutral-3 rounded-12">
          <h2 class="heading-2 text-w-neutral-1 mb-16p text-center"><?= t('login', 'Login') ?></h2>
          <p class="text-m-medium text-w-neutral-3 text-center">
            <?= t('login_don_t_have_an_account_73c427e7', "Don't have an account?") ?>
            <a href="/register.php" class="inline text-primary"><?= t('register_sign_up', 'Sign Up') ?></a>
          </p>

          <div class="grid grid-cols-1 gap-3 py-32p text-center">
            <a href="/api/auth/oauth_start.php?provider=google" class="btn btn-md bg-[#434DE4] hover:bg-[#434DE4]/80 w-full">
              <i class="ti ti-brand-google icon-24"></i> <?= t('register_log_in_with_google', 'Log In With Google') ?>
            </a>
            <a href="/api/auth/oauth_start.php?provider=twitch" class="btn btn-md bg-[#6E31DF] hover:bg-[#6E31DF]/80 w-full">
              <i class="ti ti-brand-twitch icon-24"></i> <?= t('register_log_in_with_twitch', 'Log In with Twitch') ?>
            </a>
            <a href="/api/auth/oauth_start.php?provider=facebook" class="btn btn-md bg-[#1876F2] hover:bg-[#1876F2]/80 w-full">
              <i class="ti ti-brand-facebook icon-24"></i> <?= t('register_log_in_with_facebook', 'Log In With Facebook') ?>
            </a>

            <div class="flex items-center gap-3">
              <div class="w-full h-1px bg-shap"></div>
              <span class="text-m-medium text-w-neutral-1"><?= t('or', 'or') ?></span>
              <div class="w-full h-1px bg-shap"></div>
            </div>
          </div>

          <!-- Error Box -->
          <div id="msgError" class="hidden mb-4 p-3 rounded-8 text-red-200 bg-red-900/30"></div>

          <form id="loginForm" novalidate autocomplete="on" method="post" action="/api/auth/login.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($next !== ''): ?>
              <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <div class="grid grid-cols-1 gap-30p mb-40p">
              <div>
                <label for="email" class="label label-xl text-w-neutral-1 font-borda mb-3"><?= htmlspecialchars($t_email, ENT_QUOTES, 'UTF-8') ?></label>
                <input class="border-input-1" type="email" name="email" id="email" placeholder="<?= htmlspecialchars($t_email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required />
              </div>
              <div>
                <label for="password" class="label label-xl text-w-neutral-1 font-borda mb-3"><?= htmlspecialchars($t_password, ENT_QUOTES, 'UTF-8') ?></label>
                <input class="border-input-1" type="password" name="password" id="password" placeholder="<?= htmlspecialchars($t_password, ENT_QUOTES, 'UTF-8') ?>" autocomplete="current-password" required />
              </div>
              <div>
                <label class="label label-md text-w-neutral-1 inline-flex items-center cursor-pointer gap-3">
                  <input type="checkbox" name="remember" id="remember" value="1" checked class="sr-only peer">
                  <span class="relative w-11 h-6 bg-w-neutral-1 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:bg-w-neutral-1 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-b-neutral-3 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary shrink-0"></span>
                  <?= htmlspecialchars($t_remember, ENT_QUOTES, 'UTF-8') ?>
                </label>
              </div>
            </div>

            <button id="btnLogin" class="btn btn-md btn-primary rounded-12 w-full mb-16p" type="submit">
              <?= t('login', 'Log In') ?>
            </button>
            <a href="/forgot.php" class="text-m-medium text-primary text-center block"><?= t('forgot', 'Passwort vergessen?') ?></a>
          </form>
        </div>
      </div>
    </div>
  </section>
  <!-- login section end -->
</main>

<script>
(() => {
  const $   = (s,c=document)=>c.querySelector(s);
  const frm = $('#loginForm');
  const btn = $('#btnLogin');
  const err = $('#msgError');

  if (!frm || !btn) return;

  const showErr = (m)=>{
    if(!err) return;
    err.textContent = m || 'Login fehlgeschlagen.';
    err.classList.remove('hidden');
  };
  const hideErr = ()=>{ if(err) err.classList.add('hidden'); };

  frm.addEventListener('submit', async (e) => {
    e.preventDefault(); // AJAX statt klassischem POST
    hideErr();

    const email = $('#email')?.value.trim() || '';
    const pw    = $('#password')?.value || '';
    const remember = $('#remember')?.checked ? 1 : 0;
    if (!email || !pw) { showErr('Bitte E-Mail und Passwort ausfüllen.'); return; }

    const csrf = frm.querySelector('input[name="csrf"]')?.value
              || document.querySelector('meta[name="csrf"]')?.content || '';

    const payload = { email, password: pw, remember, ...(csrf ? {csrf} : {}) };
    const old = btn.textContent; btn.disabled = true; btn.textContent = 'Wird eingeloggt…';

    try {
      const res = await fetch('/api/auth/login.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(csrf ? {'X-CSRF': csrf} : {})
        },
        body: JSON.stringify(payload),
        credentials: 'include'
      });

      const data = await res.json().catch(()=>null);

      if (!res.ok || !data?.ok) {
        showErr((data && (data.error || data.message)) || ('HTTP ' + res.status));
        return;
      }

      const nextParam = (new URLSearchParams(location.search)).get('next') || '';
      const target = data.redirect || nextParam || '/profile.php';
      location.replace(target);
    } catch (ex) {
      console.error(ex);
      showErr('Netzwerk-/Serverfehler. Bitte später erneut versuchen.');
    } finally {
      btn.disabled = false; btn.textContent = old;
    }
  });
})();
</script>
<?php
$content = ob_get_clean();
$title = ($lang === 'en' ? 'Login – Hunthub Community' : 'Login – Hunthub Community');
render_theme_page($content, $title);

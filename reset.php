<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/auth/db.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$pdo = db();

$me = current_user();
if (!empty($me['id'])) { header('Location: /'); exit; }

$t_head   = ($lang==='en') ? 'Set a new password' : 'Neues Passwort setzen';
$t_pass   = ($lang==='en') ? 'New password' : 'Neues Passwort';
$t_pass2  = ($lang==='en') ? 'Repeat password' : 'Passwort wiederholen';
$t_btn    = ($lang==='en') ? 'Save password' : 'Passwort speichern';

$token = (string)($_GET['token'] ?? '');

ob_start();
?>
<main>
  <section class="pt-30p">
    <div class="container max-w-[720px]">
      <div class="bg-b-neutral-3 rounded-24 p-30p md:p-25 sm:p-20 border border-white/10">
        <h1 class="heading-3 text-w-neutral-1 mb-10p"><?= htmlspecialchars($t_head) ?></h1>

        <div id="msgError" class="hidden text-danger bg-danger/10 border border-danger/30 rounded-12 p-12p mb-16p"></div>
        <div id="msgOk" class="hidden text-success bg-success/10 border border-success/30 rounded-12 p-12p mb-16p"></div>

        <?php if ($token===''): ?>
          <div class="text-danger bg-danger/10 border border-danger/30 rounded-12 p-12p mb-16p">
            <?= $lang==='en' ? 'Missing or invalid link.' : 'Ungültiger Link.' ?>
          </div>
        <?php else: ?>
          <form id="resetForm" method="post" action="/api/auth/reset.php" class="grid gap-16p" autocomplete="off">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <label class="grid gap-6p">
              <span class="text-w-neutral-2"><?= htmlspecialchars($t_pass) ?></span>
              <input type="password" id="password" name="password" minlength="6" required class="field box-input-3" placeholder="••••••••">
            </label>
            <label class="grid gap-6p">
              <span class="text-w-neutral-2"><?= htmlspecialchars($t_pass2) ?></span>
              <input type="password" id="password2" name="password2" minlength="6" required class="field box-input-3" placeholder="••••••••">
            </label>
            <button id="btnSave" type="submit" class="btn btn-primary rounded-12 py-12p"><?= htmlspecialchars($t_btn) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const $ = (s,c=document)=>c.querySelector(s);
  const f = $('#resetForm'), b=$('#btnSave'), e=$('#msgError'), ok=$('#msgOk');
  if(!f||!b) return;

  f.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    e.classList.add('hidden'); ok.classList.add('hidden');

    const p1 = ($('#password')?.value||'').trim();
    const p2 = ($('#password2')?.value||'').trim();
    if (!p1 || p1.length < 6) { e.textContent='Passwort zu kurz (min. 6 Zeichen).'; e.classList.remove('hidden'); return; }
    if (p1 !== p2) { e.textContent='Passwörter stimmen nicht überein.'; e.classList.remove('hidden'); return; }

    const fd = new FormData(f);
    const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
    if (csrf) fd.append('csrf', csrf);

    const old = b.textContent; b.disabled=true; b.textContent='Wird gespeichert…';
    try{
      const res = await fetch('/api/auth/reset.php', {
        method:'POST',
        headers: csrf ? {'X-CSRF': csrf, 'Accept':'application/json'} : {'Accept':'application/json'},
        body: fd, credentials:'include'
      });
      const data = await res.json().catch(()=>null);
      if (!res.ok || !data?.ok){
        e.textContent = (data && (data.error||data.message)) || ('Fehler ('+res.status+')');
        e.classList.remove('hidden');
      } else {
        ok.textContent = 'Passwort gesetzt. Weiterleiten…';
        ok.classList.remove('hidden');
        setTimeout(()=>location.replace(data.redirect || '/profile.php'), 600);
      }
    } catch(ex){
      e.textContent='Netzwerk-/Serverfehler. Bitte später erneut versuchen.'; e.classList.remove('hidden');
    } finally { b.disabled=false; b.textContent=old; }
  });
});
</script>
<?php
$content = ob_get_clean();
render_theme_page($content, $t_head);

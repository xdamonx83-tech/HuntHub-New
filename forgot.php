<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/auth/db.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$pdo = db();
$me  = current_user();
if (!empty($me['id'])) { header('Location: /'); exit; }

$t_head  = $lang==='en' ? 'Forgot your password?' : 'Passwort vergessen?';
$t_hint  = $lang==='en' ? 'Enter your email and we’ll send you a reset link.' : 'Gib deine E-Mail ein, wir senden dir einen Link.';
$t_email = $lang==='en' ? 'Email' : 'E-Mail';
$t_send  = $lang==='en' ? 'Send reset link' : 'Link senden';

ob_start();
?>













<main>

            <!-- breadcrumb start -->
            <section class="pt-30p">
                <div class="section-pt">
                    <div
                        class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
                        <div class="container">
                            <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
                                <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                                    <h2 class="heading-2 text-w-neutral-1 mb-3">
                                        <?= t('forgot_passwort_vergessen_75e3614d') ?>
                                    </h2>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="#" class="breadcrumb-link">
                                                <?= t('home') ?>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-current"><?= t('forgot_passwort_vergessen_75e3614d') ?></span>
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

            <!-- login section start -->
            <section class="section-py">
                <div class="container">
                    <div class="flex-c">
                        <div class="max-w-[530px] w-full p-40p bg-b-neutral-3 rounded-12">
                            <h2 class="heading-2 text-w-neutral-1 mb-16p text-center">
                                <?= t('forgot_passwort_vergessen_75e3614d') ?>?
                            </h2>
           
                            <form id="forgotForm" method="post" action="/api/auth/forgot.php" autocomplete="on" novalidate>
                                <div class="grid grid-cols-1 gap-30p mb-40p">
                                    <div>
                                        <label for="userEmail" class="label label-xl text-w-neutral-1 font-borda mb-3">
                                            <?= htmlspecialchars($t_email) ?>
                                        </label>
                                        <input class="border-input-1" type="email" name="email" id="email"
                                            placeholder="you@example.com" />
                                    </div>
                    
                                </div>
                                <button id="btnSend" type="submit" class="btn btn-md btn-primary rounded-12 w-full mb-16p">
                                    <?= htmlspecialchars($t_send) ?>
                                </button>
                         
                            </form>
                        </div>
                    </div>
                </div>
            </section>
            <!-- login section end -->

        </main>





















<!-- Toasts -->
<div id="toast-root" class="fixed top-6 left-1/2 -translate-x-1/2 z-[99999]" style="max-width:520px;width:92%"></div>
<style>
@keyframes toast-pop{0%{transform:scale(.85);opacity:0}60%{transform:scale(1.03);opacity:1}100%{transform:scale(1)}}
@keyframes toast-out{to{transform:translateY(-6px);opacity:0}}
.toast{margin:10px 0;padding:12px 14px;border-radius:14px;border:1px solid rgba(255,255,255,.2);
       box-shadow:0 20px 70px rgba(0,0,0,.45);display:flex;gap:12px;align-items:flex-start;font-size:15px;
       animation:toast-pop .22s cubic-bezier(.2,.8,.2,1)}
.toast.hide{animation:toast-out .26s ease forwards}
.toast.ok{background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.3);color:#10b981}
.toast.err{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3);color:#ef4444}
.toast .x{opacity:.65;cursor:pointer;border:0;background:transparent;font-size:18px;line-height:1}
</style>

<script>
(function(){
  const root = document.getElementById('toast-root');
  function ensureRoot(){ if(root) return root; alert('Hinweis: Toast-Container fehlt.'); return null; }
  window.notify = function(type, msg){
    const r = ensureRoot(); if(!r) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (type==='success'?'ok':'err');
    el.innerHTML = '<div style="flex:1">'+(msg||'')+'</div><button class="x" aria-label="Schließen">×</button>';
    el.querySelector('.x').onclick = ()=>{ el.classList.add('hide'); setTimeout(()=>el.remove(), 280); };
    r.appendChild(el);
    setTimeout(()=>{ if(el.isConnected){ el.classList.add('hide'); setTimeout(()=>el.remove(), 280); } }, 10000);
  };
  // Falls irgendwo JS crasht, zeig es an:
  window.addEventListener('error', e => notify('error', 'JS-Fehler: '+(e.message||e.error)));
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const form = document.querySelector('#forgotForm') 
            || document.querySelector('form[action$="/api/auth/forgot.php"]')
            || document.querySelector('form[action*="api/auth/forgot.php"]');
  const btn  = document.querySelector('#btnSend') || (form && form.querySelector('button[type="submit"]'));
  if(!form){ notify('error','Fehler: Formular nicht gefunden.'); return; }

  form.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const fd = new FormData(form);
    const email = (fd.get('email')||'').toString().trim();
    if(!email){ notify('error','Bitte E-Mail eingeben.'); return; }

    const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
    const headers = { 'Accept':'application/json' };
    if (csrf) headers['X-CSRF'] = csrf;

    const oldTxt = btn ? btn.textContent : '';
    if (btn){ btn.disabled = true; btn.textContent = 'Wird gesendet…'; }

    try{
      const res  = await fetch('/api/auth/forgot.php', { method:'POST', headers, body: fd, credentials:'include' });
      const body = await res.text();           // robust: erst Text holen
      let data   = null; try { data = JSON.parse(body); } catch {}

      if (res.status === 404){
        notify('error', (data && data.message) || 'User/E-Mail Adresse existiert nicht.');
      } else if (!res.ok || (data && data.ok===false)){
        notify('error', (data && data.message) || ('Fehler ('+res.status+')'));
      } else {
        notify('success', (data && data.message) || 'Wir haben dir eine E-Mail mit dem Link zum Zurücksetzen gesendet.');
        try { form.reset(); } catch {}
      }
    } catch (err){
      console.error(err);
      notify('error','Netzwerk-/Serverfehler. Bitte später erneut versuchen.');
    } finally {
      if (btn){ btn.disabled = false; btn.textContent = oldTxt; }
    }
  });
});
</script>
<script>
(function(){
  // --- ZENTRIERTER TOAST (mit Plopp, 10s Auto-Close) ---
  function toastCenter(msg, ok){
    try{
      var el = document.createElement('div');
      el.setAttribute('role', ok ? 'status' : 'alert');
      el.textContent = msg || '';
      el.style.cssText = [
        // Position: genau mittig
        'position:fixed','top:50%','left:50%','transform:translate(-50%,-50%) scale(0.85)',
        'z-index:2147483647','opacity:0',
        // Box
        'max-width:560px','width:92vw','padding:16px 18px','text-align:center',
        'border-radius:14px','box-shadow:0 20px 70px rgba(0,0,0,.45)',
        'font:500 15px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif',
        // Farben je nach Typ
        (ok
          ? 'background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981'
          : 'background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#ef4444'),
        // Animation
        'transition:transform .22s cubic-bezier(.2,.8,.2,1),opacity .22s'
      ].join(';');

      // Klick schließt sofort
      el.style.cursor = 'pointer';
      el.onclick = function(){
        el.style.transition = 'transform .26s ease,opacity .26s ease';
        el.style.opacity = '0';
        el.style.transform = 'translate(-50%,-52%) scale(0.98)';
        setTimeout(function(){ if(el.parentNode) el.parentNode.removeChild(el); }, 280);
      };

      document.body.appendChild(el);
      // Plopp aktivieren
      requestAnimationFrame(function(){
        el.style.opacity = '1';
        el.style.transform = 'translate(-50%,-50%) scale(1)';
      });
      // Auto-Close nach 10 s
      setTimeout(function(){
        if(!el.isConnected) return;
        el.onclick();
      }, 3000);
    }catch(e){ alert(msg); }
  }
  // global verfügbar machen
  window.toast = toastCenter;

  // --- Robuster Submit-Handler für /api/auth/forgot.php ---
  document.addEventListener('DOMContentLoaded', function(){
    var form = document.querySelector('#forgotForm')
           || document.querySelector('form[action$="/api/auth/forgot.php"]')
           || document.querySelector('form[action*="/api/auth/forgot.php"]');
    var btn  = document.querySelector('#btnSend') || (form && form.querySelector('button[type="submit"]'));
    if(!form){ toast('Fehler: Reset-Formular nicht gefunden.', false); return; }

    form.addEventListener('submit', async function(ev){
      ev.preventDefault();
      var fd = new FormData(form);
      var email = (fd.get('email')||'').toString().trim();
      if(!email){ toast('Bitte E-Mail eingeben.', false); return; }

      var old = btn ? btn.textContent : '';
      if (btn){ btn.disabled = true; btn.textContent = 'Wird gesendet…'; }

      try{
        var res  = await fetch('/api/auth/forgot.php', {
          method:'POST', body: fd, credentials:'include',
          headers:{ 'Accept':'application/json' }
        });
        var txt  = await res.text();
        var data = null; try{ data = JSON.parse(txt); }catch(_){}
        if (res.status === 404){
          toast((data && data.message) || 'User/E-Mail Adresse existiert nicht.', false);
        } else if (!res.ok || (data && data.ok===false)){
          toast((data && data.message) || ('Fehler ('+res.status+')'), false);
        } else {
          toast((data && data.message) || 'Wir haben dir eine E-Mail mit dem Link zum Zurücksetzen gesendet.', true);
          try{ form.reset(); }catch(_){}
        }
      }catch(err){
        console.error(err);
        toast('Netzwerk-/Serverfehler. Bitte später erneut versuchen.', false);
      }finally{
        if (btn){ btn.disabled = false; btn.textContent = old; }
      }
    });
  });
})();
</script>


<?php
$content = ob_get_clean();
render_theme_page($content, $t_head);

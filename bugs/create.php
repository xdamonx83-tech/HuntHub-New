<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/layout.php';

require_auth();
$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$sessionCookie = $_COOKIE[$cfg['cookies']['session_name']] ?? '';
$csrf = issue_csrf($pdo, $sessionCookie);

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
                                        Ticketsystem
                                    </h2>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="/" class="breadcrumb-link">
                                                Home
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-current">Ticketsystem</span>
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

            <!-- contact us section start -->
            <section class="section-py">
                <div class="container">
                    <div class="grid grid-cols-12 gap-30p">
                        <div class="3xl:col-start-3 xxl:col-start-2 3xl:col-end-11 xxl:col-end-12 col-span-12">
                            <h2 class="heading-2 text-center text-w-neutral-1 mb-48p">
                                Fehler übermitteln
                            </h2>

                            
<meta name="csrf" content="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="bg-b-neutral-3 rounded-4 p-40p">
                                <form id="bug-form" onsubmit="return false">
                                    <div class="grid grid-cols-8 gap-30p mb-48p">
                         
                        
                                        <div class="sm:col-span-4 col-span-8">
                                            <label for="phone" class="label label-md font-normal text-white mb-3">
                                                Screenshot
                                            </label>
                                           <input id="bug-files" type="file" multiple accept="image/*,video/*">
      <div id="bug-previews" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:8px"></div>
                                        </div>
                                        <div class="sm:col-span-4 col-span-8">
                                            <label for="subject" class="label label-md font-normal text-white mb-3">
                                                Titel
                                            </label>
                                            <input class="box-input-4" name="title" maxlength="200" required />
                                        </div>
                                        <div class="col-span-8">
                                            <label for="subject" class="label label-md font-normal text-white mb-3">
                                                Beschreibung
                                            </label>
                                            <textarea class="box-input-4 h-[156px]" name="description" rows="6" required placeholder="Schritte zur Reproduktion …"></textarea>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-primary rounded-12 w-full" id="btn-send">
                                        Absenden
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- contact us section end -->

        </main>










<script>
const BASE = "<?= $APP_BASE ?>";
const CSRF = document.querySelector('meta[name=csrf]')?.content||'';

document.getElementById('btn-send').addEventListener('click', async ()=>{
  const f = document.getElementById('bug-form');
  const fd = new FormData(f);
  const r = await fetch(`${BASE}/api/bugs/create.php`, { method:'POST', headers:{'X-CSRF':CSRF}, body:fd });
  const j = await r.json().catch(()=>null); if(!j||!j.ok){ alert('Fehler'); return; }
  const bugId=j.id;
  const files = Array.from(document.getElementById('bug-files').files||[]);
  for(const file of files){
    const uf = new FormData(); uf.append('bug_id', String(bugId)); uf.append('file', file);
    await fetch(`${BASE}/api/bugs/upload.php`, { method:'POST', headers:{'X-CSRF':CSRF}, body: uf });
  }
  location.href = `${BASE}/bugs/view.php?id=${bugId}`;
});
document.getElementById('bug-files').addEventListener('change', (e)=>{
  const box=document.getElementById('bug-previews'); box.innerHTML='';
  Array.from(e.target.files||[]).slice(0,12).forEach(f=>{
    const w=document.createElement('div'); w.style.border='1px solid rgba(255,255,255,.14)'; w.style.borderRadius='8px'; w.style.padding='4px';
    if (f.type.startsWith('image/')){ const img=new Image(); img.src=URL.createObjectURL(f); img.style.maxWidth='100%'; w.appendChild(img); }
    else if (f.type.startsWith('video/')){ const v=document.createElement('video'); v.src=URL.createObjectURL(f); v.controls=true; v.style.width='100%'; w.appendChild(v); }
    else w.textContent=f.name;
    box.appendChild(w);
  });
});
</script>
<?php
$content = ob_get_clean();
render_theme_page($content, 'Bug melden');

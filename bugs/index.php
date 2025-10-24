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

<style>
/* Basis */
.hh-table { width:100%; }

/* Kopfzeile: nur ab md sichtbar */
.hh-table-head { display:none; }
@media (min-width:768px){
  .hh-table-head{
    display:grid;
    grid-template-columns:110px minmax(220px,1fr) repeat(4, max-content);
    align-items:center;
    column-gap:16px;
  }
  .hh-table-head > div{ padding:8px 12px; }
}

/* ROW: macht den <a> wirklich zum Grid */
.hh-table-row{
  display:grid !important;           /* wichtig: Anchor -> Grid */
  grid-template-columns:1fr;         /* mobil untereinander */
  gap:12px;
  padding:12px 16px;
  border-bottom:1px solid var(--color-shap, #333);
  background:var(--color-b-neutral-3, #1f1f1f);
  text-decoration:none;
  color:inherit;
}

/* Desktop-Spalten */
@media (min-width:768px){
  .hh-table-row{
    grid-template-columns:110px minmax(220px,1fr) repeat(4, max-content);
    align-items:center;
    column-gap:16px;
  }
  .hh-table-row > div{ padding:8px 12px; }
}


</style>
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
                                        Bugreport / Ticket
                                    </h2>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="#" class="breadcrumb-link">
                                                Home
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-current">Bugreport / Ticket</span>
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

            <!-- Leaderboard section start -->
            <section class="section-pb pt-60p">
                <div class="container">
                    <div class="overflow-x-auto scrollbar-sm">
                     
				<a class="btn btn-md btn-primary rounded-12 mt-60p" href="<?= $APP_BASE ?>/bugs/create.php">Neues Ticket</a>	 
					 
					 <div class="hh-table">

  <!-- Kopf -->
  <div class="hh-table-head">
    <div>Placement</div>
    <div>Titel</div>
    <div>Priorität</div>
    <div>Status</div>
    <div>XP erhalten</div>
    <div>Auszeichnung</div>
  </div>

  <!-- Zeile -->
<meta name="csrf" content="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
<section id="my-bugs">Lade…</section>
<script>
(async function(){
  const BASE = "<?= $APP_BASE ?>";
  const CSRF = document.querySelector('meta[name=csrf]')?.content||'';
  const root = document.getElementById('my-bugs');
  const esc=s=>String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
  try{
    const r = await fetch(`${BASE}/api/bugs/list_mine.php`, {headers:{'X-CSRF':CSRF}});
    if (!r.ok) { root.textContent = `API-Fehler ${r.status}: ` + (await r.text()).slice(0,200); return; }
    const j = await r.json().catch(()=>null);
    const items = (j && j.ok && Array.isArray(j.items)) ? j.items : [];
    if (!items.length) { root.textContent='Noch keine Tickets.'; return; }
    root.innerHTML = items.map(it=>`
                                    <a href="${BASE}/bugs/view.php?id=${it.id}" class="hh-table-row">
    <div class="flex-y gap-3">
      <i class="ti ti-chevrons-up icon-24 text-danger"></i>
      <span>#${it.id}</span>
    </div>
    <div class="flex items-center gap-20p">
      <p class="link-1 line-clamp-1">${esc(it.title)}</p>
    </div>
    <div>${(it.priority||'').toUpperCase()}</div>
    <div>Status: ${it.status}</div>
    <div>${it.xp_awarded?`XP: +${it.xp_awarded}`:''}</div>
    <div>${it.badge_code?`Badge: ${esc(it.badge_code)}`:''}</div>
  </a>
    `).join('');
  }catch(e){ root.textContent='Fehler: '+(e?.message||e); }
})();
</script>	

</div>

					 
					 
					 
                    </div>
       
                </div>
            </section>

            <!-- Leaderboard section end -->

        </main>



<?php
$content = ob_get_clean();
render_theme_page($content, 'Meine Tickets');

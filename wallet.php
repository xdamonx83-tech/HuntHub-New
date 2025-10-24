<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/points.php';

$me  = require_auth();
$pdo = db();

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
if (!function_exists('t')) {
  function t(string $key, array $vars=[]): string {
    $L = $GLOBALS['L'] ?? []; $s = $L[$key] ?? $key;
    if ($vars) { $repl=[]; foreach($vars as $k=>$v){ $repl['{'.$k.'}']=(string)$v; } return strtr($s,$repl); }
    return $s;
  }
}

$cfg        = require __DIR__ . '/auth/config.php';
$APP_BASE   = rtrim($cfg['app_base'] ?? '/', '/');
$APP_PUBLIC = rtrim($cfg['app_base_public'] ?? '', '/');
$csrf       = issue_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '');

$balance_ssr = (int) get_user_points($pdo, (int)$me['id']);

$coverFallback  = $APP_BASE . '/assets/images/cover-placeholder.jpg';
$avatarFallback = $APP_BASE . '/assets/images/avatars/placeholder.png';
$coverSrc  = (string)($me['cover_path']  ?? '') ?: $coverFallback;
$avatarSrc = (string)($me['avatar_path'] ?? '') ?: $avatarFallback;

$title = 'Wallet & Shop';
ob_start();
?>
<meta name="csrf" content="<?= htmlspecialchars($csrf) ?>">
<script>window.APP_BASE = <?= json_encode($APP_PUBLIC) ?>;</script>

<main>

  <!-- ===== Header mit Cover, Avatar & Tab-Navi ===== -->
  <section class="section-pb pt-30 overflow-visible">
    <div class="container">
      <div class="grid grid-cols-12 gap-x-30p gap-y-10">
        <!-- ===== Linke Spalte (Inhalt) ===== -->
        <div class="4xl:col-span-9 xxl:col-span-8 col-span-12">
          <div>
            <!-- header card start -->
            <div class="bg-b-neutral-3 mb-30p">
              <div class="glitch-effect rounded-t-12 overflow-hidden">
                <div class="glitch-thumb">
                  <img class="w-full 3xl:h-[428px] lg:h-[400px] md:h-[340px] sm:h-[280px] h-[240px] object-cover"
                       src="<?= htmlspecialchars($coverSrc) ?>" alt="cover" />
                </div>
                <div class="glitch-thumb">
                  <img class="w-full 3xl:h-[428px] lg:h-[400px] md:h-[340px] sm:h-[280px] h-[240px] object-cover"
                       src="<?= htmlspecialchars($coverSrc) ?>" alt="cover" />
                </div>
              </div>

              <div class="px-40p xl:py-[26px] py-24p z-[5]">
                <div class="flex md:flex-row flex-col md:items-end items-center md:text-left text-center gap-3 py-4 xxl:-mt-30 xl:-mt-15 max-md:-mt-20 relative z-[2]">
                  <img class="avatar xxl:size-[160px] xl:size-[140px] size-[120px] border-2 border-secondary rounded-full object-cover"
                       src="<?= htmlspecialchars($avatarSrc) ?>" alt="avatar" />
                  <div>
                    <h4 class="heading-4 text-w-neutral-1 mb-2">
                      <?= htmlspecialchars($me['display_name'] ?? $me['slug'] ?? 'Profil') ?>
                    </h4>
                    <p class="text-sm text-w-neutral-3">
                      Punkte: <b id="pointsBadge" data-points-badge><?= $balance_ssr ?></b>
                    </p>
                  </div>
                </div>

                <!-- Tabbar -->
                <div class="tab-navbar flex items-center flex-wrap gap-x-32p gap-y-24p sm:text-xl text-lg *:font-borda font-medium text-w-neutral-1 whitespace-nowrap pt-16p border-t border-shap">
                  <a href="#shop"         class="tab-link active" data-tab="shop">Shop</a>
                  <a href="#inventory"    class="tab-link" data-tab="inventory">Inventar</a>
                  <a href="#transactions" class="tab-link" data-tab="transactions">Transaktionen</a>
                  <a href="#transfer"     class="tab-link" data-tab="transfer">Punkte senden</a>
                </div>
              </div>
            </div>
            <!-- header card end -->

            <!-- ===== Tab-Content ===== -->
            <div class="bg-b-neutral-3 p-24p rounded-12 mb-24p">
              <!-- SHOP -->
              <section id="tab-shop" class="tab-panel is-active">
                <div id="shopCats" class="cats-nav mb-3"></div>
                <div id="shopItems"></div>
              </section>

              <!-- INVENTAR -->
              <section id="tab-inventory" class="tab-panel">
                <div id="invWrap"><div class="p-4 opacity-70">Lade Inventar…</div></div>
              </section>

              <!-- TRANSAKTIONEN -->
              <section id="tab-transactions" class="tab-panel">
                <div id="txWrap" class="tx-wrap">
                  <header class="tx-head">
                    <div>Datum</div><div>Vorgang</div><div>Delta</div><div>Kontostand</div>
                  </header>
                  <div id="txBody" class="tx-body"></div>
                  <div class="tx-more">
                    <button id="txMoreBtn" class="btn btn-sm btn-secondary rounded-12" hidden>Weitere laden</button>
                  </div>
                </div>
              </section>

              <!-- TRANSFER -->
<section id="tab-transfer" class="tab-panel">
  <form id="transferForm" class="transfer">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="relative">
        <label class="label mb-2">Empfänger</label>
        <input type="text" name="to" class="box-input-3"
               placeholder="z.B. krispie"
               autocomplete="off" required>
      </div>
      <div>
        <label class="label mb-2">Betrag (Punkte)</label>
        <input type="number" name="amount" min="1" step="1" class="box-input-3" required>
      </div>
    </div>
    <div class="mt-4">
      <label class="label mb-2">Nachricht (optional)</label>
      <input type="text" name="note" maxlength="140" class="box-input-3" placeholder="Danke für die Hilfe!">
    </div>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="mt-6 flex gap-3">
      <button class="btn btn-md btn-primary rounded-12">Punkte senden</button>
      <div id="transferInfo" class="opacity-80"></div>
    </div>
  </form>
  <p class="mt-3 text-sm opacity-70">Hinweis: Transfers sind endgültig. Missbrauch führt zur Sperrung.</p>
</section>

            </div>

          </div>
        </div>

        <!-- ===== Rechte Spalte (deine Sidebar) ===== -->
        <div class="4xl:col-span-3 xxl:col-span-4 col-span-12 relative">
          <div class="xxl:sticky xxl:top-30">
            <!-- right side content active members, Latest Blogs, Testimonials, newsletter card start -->
            <div class="grid grid-cols-12 gap-24p *:bg-b-neutral-3 *:rounded-12 *:px-32p *:py-24p">
              <div class="xxl:col-span-12 md:col-span-6 col-span-12 xxl:order-1 order-3">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-40p">
                  <h4 class="heading-4 text-w-neutral-1">Active Members</h4>
                  <a href="#" class="inline-flex items-center gap-3 text-w-neutral-1 link-1">View All <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="swiper active-members-carousel w-full">
                  <div class="swiper-wrapper *:w-fit">
                    <div class="swiper-slide"><div><a href="#" class="avatar relative size-60p mb-3"><img class="size-60p rounded-full" src="assets/images/users/user1.png" alt="avatar" /><span class="status-badge online"><i class="ti ti-circle-check-filled"></i></span></a><span class="text-m-regular text-w-neutral-1 text-center">Courtney</span></div></div>
                    <div class="swiper-slide"><div><a href="#" class="avatar relative size-60p mb-3"><img class="size-60p rounded-full" src="assets/images/users/user2.png" alt="avatar" /><span class="status-badge offline"><i class="ti ti-circle-check-filled"></i></span></a><span class="text-m-regular text-w-neutral-1 text-center">Priscilla</span></div></div>
                    <div class="swiper-slide"><div><a href="#" class="avatar relative size-60p mb-3"><img class="size-60p rounded-full" src="assets/images/users/user3.png" alt="avatar" /><span class="status-badge online"><i class="ti ti-circle-check-filled"></i></span></a><span class="text-m-regular text-w-neutral-1 text-center">Priscilla</span></div></div>
                    <div class="swiper-slide"><div><a href="#" class="avatar relative size-60p mb-3"><img class="size-60p rounded-full" src="assets/images/users/user5.png" alt="avatar" /><div class="status-badge offline"><i class="ti ti-circle-check-filled"></i></div></a><span class="text-m-regular text-w-neutral-1 text-center">Aubrey</span></div></div>
                    <div class="swiper-slide"><div><a href="#" class="avatar relative size-60p mb-3"><img class="size-60p rounded-full" src="assets/images/users/user6.png" alt="avatar" /><div class="status-badge offline"><i class="ti ti-circle-check-filled"></i></div></a><span class="text-m-regular text-w-neutral-1 text-center">jon Smith</span></div></div>
                    <div class="swiper-slide"><div><a href="#" class="avatar relative size-60p mb-3"><img class="size-60p rounded-full" src="assets/images/users/user7.png" alt="avatar" /><div class="status-badge offline"><i class="ti ti-circle-check-filled"></i></div></a><span class="text-m-regular text-w-neutral-1 text-center">jon Doe</span></div></div>
                    <div class="swiper-slide"><div><a href="#" class="avatar relative size-60p mb-3"><img class="size-60p rounded-full" src="assets/images/users/user8.png" alt="avatar" /><div class="status-badge offline"><i class="ti ti-circle-check-filled"></i></div></a><span class="text-m-regular text-w-neutral-1 text-center">Priscilla</span></div></div>
                  </div>
                </div>
              </div>

              <div class="xxl:col-span-12 md:col-span-6 col-span-12 xxl:order-2 order-2">
                <div class="flex items-center justify-between flex-wrap gap-3 mb-24p">
                  <h4 class="heading-4 text-w-neutral-1 ">Latest News</h4>
                  <a href="blogs.html" class="inline-flex items-center gap-3 text-w-neutral-1 link-1">View All <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="group">
                  <div class="overflow-hidden rounded-12">
                    <img class="w-full h-[202px] object-cover group-hover:scale-110 transition-1" src="assets/images/blogs/blog9.png" alt="img" />
                  </div>
                  <div class="flex-y justify-between flex-wrap gap-20px py-3">
                    <div class="flex-y gap-3">
                      <div class="flex-y gap-1"><i class="ti ti-heart icon-20 text-danger"></i><span class="text-sm text-w-neutral-1">02</span></div>
                      <div class="flex-y gap-1"><i class="ti ti-message icon-20 text-primary"></i><span class="text-sm text-w-neutral-1">07</span></div>
                    </div>
                    <div class="flex-y gap-1"><i class="ti ti-share-3 icon-20 text-w-neutral-4"></i><span>07</span></div>
                  </div>
                  <div class="flex-y flex-wrap gap-3 mb-1">
                    <span class="text-m-medium text-w-neutral-1">Collections</span>
                    <p class="text-sm text-w-neutral-2">September 04, 2023</p>
                  </div>
                  <a href="blog-details.html" class="heading-5 text-w-neutral-1 line-clamp-2 link-1">Pros and Cons of Minimal Navigation In Web Design</a>
                </div>
              </div>

              <div class="xxl:col-span-12 md:col-span-6 col-span-12 xxl:order-3 order-2">
                <h4 class="heading-4 text-w-neutral-1 mb-24p">Testimonials</h4>
                <div class="swiper one-card-carousel" data-carousel-name="testimonials-one">
                  <div class="swiper-wrapper pb-15">
                    <div class="swiper-slide">
                      <div class="flex-col-c text-center">
                        <div class="flex-c gap-1 icon-24 text-primary">
                          <i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-half-filled"></i>
                        </div>
                        <p class="text-base text-w-neutral-1 line-clamp-3 my-16p">Stellar Odyssey" is a visually stunning…</p>
                        <a href="#" class="text-l-medium text-w-neutral-1 mb-1">David Malan</a>
                        <span class="text-sm text-w-neutral-2 mb-3">Leader</span>
                        <img class="avatar size-60p" src="assets/images/users/user1.png" alt="user" />
                      </div>
                    </div>
                    <div class="swiper-slide">
                      <div class="flex-col-c text-center">
                        <div class="flex-c gap-1 icon-24 text-primary">
                          <i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i>
                        </div>
                        <p class="text-base text-w-neutral-1 line-clamp-3 my-16p">"Galactic Pursuit" weaves an exciting tale…</p>
                        <a href="#" class="text-l-medium text-w-neutral-1 mb-1">Sarah Johnson</a>
                        <span class="text-sm text-w-neutral-2 mb-3">Co-Founder</span>
                        <img class="avatar size-60p" src="assets/images/users/user2.png" alt="user" />
                      </div>
                    </div>
                    <div class="swiper-slide">
                      <div class="flex-col-c text-center">
                        <div class="flex-c gap-1 icon-24 text-primary">
                          <i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-filled"></i><i class="ti ti-star-half-filled"></i><i class="ti ti-star"></i>
                        </div>
                        <p class="text-base text-w-neutral-1 line-clamp-3 my-16p">"Mystic Realms" offers breathtaking visuals…</p>
                        <a href="#" class="text-l-medium text-w-neutral-1 mb-1">Kevin Peterson</a>
                        <span class="text-sm text-w-neutral-2 mb-3">Senior Developer</span>
                        <img class="avatar size-60p" src="assets/images/users/user3.png" alt="user" />
                      </div>
                    </div>
                  </div>
                  <div class="swiper-pagination pagination-one testimonials-one-carousel-pagination flex-c gap-2.5"></div>
                </div>
              </div>

              <div class="xxl:col-span-12 md:col-span-6 col-span-12 order-4">
                <span class="icon-40 text-primary"><i class="ti ti-mail-opened"></i></span>
                <div class="my-20p">
                  <h1 class="heading-1 text-w-neutral-1 mb-2">Newsletter</h1>
                  <p class="text-base text-w-neutral-3">Check Latest Updates</p>
                </div>
                <form class="flex-y justify-between gap-2.5 bg-b-neutral-2 px-24p pr-3 py-3 rounded-full mb-3">
                  <input class="w-full bg-transparent placeholder:text-sm placeholder:text-w-neutral-4" type="email" name="email" id="newsletterEmail" placeholder="Your Email" />
                  <button class="shrink-0 flex-c size-8 rounded-full bg-accent-4 icon-24 text-b-neutral-4"><i class="ti ti-arrow-right"></i></button>
                </form>
                <div class="shrink-0 flex-y gap-16p">
                  <i class="ti ti-alert-circle text-white icon-24"></i>
                  <p class="text-sm text-w-neutral-2">Important Notification</p>
                </div>
              </div>
            </div>
            <!-- right side content end -->
          </div>
        </div>
        <!-- ===== /rechte Spalte ===== -->

      </div>
    </div>
  </section>

</main>

<!-- Vollbild-Shop (Modal) optional -->
<div id="shopModal" class="shop-modal" aria-hidden="true">
  <div class="card">
    <div class="hd">
      <h4 class="shop-title">Guthabenshop</h4>
      <button class="modal-close shop-close" type="button" aria-label="Schließen">×</button>
    </div>
    <div class="bd">
      <div id="shopCats2" class="cats-nav"></div>
      <div id="shopItems2"></div>
    </div>
  </div>
</div>

<style>
/* Tabs */
.tab-link{opacity:.8}
.tab-link.active{opacity:1;border-bottom:2px solid #f29620}
.tab-panel{display:none}
.tab-panel.is-active{display:block}

/* Shop UI */
.cats-nav{position:sticky;top:0;z-index:2;background:#0b0b0b;border-bottom:1px solid rgba(255,255,255,.08);padding:10px 0;display:flex;gap:8px;flex-wrap:wrap}
.cats-nav a{display:inline-block;padding:6px 10px;border:1px solid rgba(255,255,255,.12);border-radius:999px;color:#e5e7eb;text-decoration:none;font-size:.95rem}
.cats-nav a:hover{background:#151515}
.shop-section{padding:12px 0 6px}
.shop-section h5{margin:0 0 10px;color:#fff;font-weight:700;font-size:1.05rem}
.shop-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px}
.shop-card{background:#111;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px}
.shop-thumb{aspect-ratio:1/1;background:#0f0f0f;border-radius:8px;overflow:hidden}
.shop-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.shop-title{font-weight:700;color:#e5e7eb}
.shop-desc{font-size:.9rem;color:#9ca3af}
.shop-price{color:#fbbf24;font-weight:700}
.shop-buy-btn{background:#f29620;color:#111;border:none;border-radius:10px;padding:8px 10px;cursor:pointer}
.shop-buy-btn[disabled]{opacity:.6;cursor:not-allowed}
.shop-pill{display:inline-block;background:#374151;color:#e5e7eb;border-radius:999px;padding:4px 8px;font-size:.85rem}
/* NEU: Off-Button optisch neutraler */
.shop-off-btn{background:#374151;color:#e5e7eb}
.shop-off-btn:hover{filter:brightness(1.05)}

/* Modal */
.shop-modal{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;z-index:1000}
.shop-modal.is-open{display:flex;align-items:center;justify-content:center}
.shop-modal .card{background:#0b0b0b;width:min(1040px,96vw);max-height:80vh;border-radius:16px;border:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;overflow:hidden}
.shop-modal .hd{padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;justify-content:space-between;align-items:center}
.shop-modal .bd{padding:14px;overflow:auto}

/* Transactions */
.tx-wrap{background:#0b0b0b;border:1px solid rgba(255,255,255,.08);border-radius:12px}
.tx-head,.tx-row{display:grid;grid-template-columns:160px 1fr 120px 140px;gap:10px;padding:10px 12px}
.tx-head{border-bottom:1px solid rgba(255,255,255,.08);color:#cbd5e1;font-weight:700}
.tx-row{border-bottom:1px solid rgba(255,255,255,.04);align-items:center}
.tx-row .neg{color:#ef4444}.tx-row .pos{color:#10b981}
.tx-row small{opacity:.7}
.tx-more{display:flex;justify-content:center;padding:10px}

/* --- Typeahead --- */
.ta-after{position:relative; display:block; width:100%; height:0}
.ta-list{
  position:absolute; left:0; right:0; top:6px;
  background:#101214; border:1px solid rgba(255,255,255,.08);
  border-radius:10px; padding:4px; z-index:2147483000;
  box-shadow:0 10px 30px rgba(0,0,0,.4);
  max-height:260px; overflow:auto; display:none;
}
.ta-open .ta-list{display:block}
.ta-item{display:flex; gap:8px; align-items:center; padding:8px 10px; border-radius:8px; cursor:pointer}
.ta-item:hover,.ta-item.is-active{background:#1b1b1b}
.ta-img{width:28px; height:28px; border-radius:50%; object-fit:cover; background:#2a2d31}
.ta-name{color:#e5e7eb; font-weight:600}
.ta-meta{color:#9ca3af; font-size:.85rem}
</style>

<script>
(() => {
  const APP = window.APP_BASE || '';
  const $  = (s,el=document)=>el.querySelector(s);
  const $$ = (s,el=document)=>Array.from(el.querySelectorAll(s));
  const pointsEl = document.querySelector('[data-points-badge]');

  // ---- kleine Helfer ----
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  async function postJson(url, body) {
    const res = await fetch(url, { method: 'POST', body, credentials: 'include' });
    const ct  = (res.headers.get('content-type') || '').toLowerCase();
    const raw = await res.text(); // immer Rohtext lesen
    let json  = null;
    if (ct.includes('application/json')) {
      try { json = JSON.parse(raw); } catch (_) {}
    }
    if (!json) {
      const short = raw.replace(/<[^>]+>/g,'').trim().slice(0, 300);
      throw new Error(`HTTP ${res.status} – keine JSON-Antwort. ${short}`);
    }
    if (!res.ok || json.ok === false) {
      throw new Error(json.error || 'server_error');
    }
    return json;
  }
  async function refreshPoints(){
    try{
      const r = await fetch(APP + '/api/points/balance.php',{credentials:'include'});
      const j = await r.json();
      if (j && j.ok && pointsEl) pointsEl.textContent = j.balance ?? '0';
    }catch(_){}
  }

  /* ===== Tabs + Hash ===== */
  function activateTab(name){
    $$('.tab-link').forEach(a=>a.classList.toggle('active', a.dataset.tab===name));
    $$('.tab-panel').forEach(p=>p.classList.toggle('is-active', p.id==='tab-'+name));
    if(name==='inventory') renderInventory();
    if(name==='transactions') loadTransactions(true);
  }
  $$('.tab-navbar .tab-link').forEach(a=>{
    a.addEventListener('click',(e)=>{
      e.preventDefault();
      const name=a.dataset.tab;
      history.replaceState(null,'','#'+name);
      activateTab(name);
    });
  });
  (function(){
    const h=(location.hash||'').replace('#','');
    const valid=['shop','inventory','transactions','transfer'];
    activateTab(valid.includes(h)?h:'shop');
  })();

  /* ===== Shop ===== */
  const ORDER=['avatar','cover','frame','badge','vip','status','color','module','other'];
  const LABEL={avatar:'Avatare',cover:'Cover',frame:'Rahmen',badge:'Badges',vip:'VIP',status:'Status',color:'Name-Farbe',module:'Module',other:'Sonstiges'};
  const imgSrc=it=>{
    if(it.image_url) return it.image_url;
    if(it.image) return (APP||'') + (String(it.image).startsWith('/')? it.image : '/'+it.image);
    return (APP||'') + '/assets/images/placeholder-item.png';
  };
  function renderItem(it){
    const owned = !!(it.owned||it.is_owned);
    const active= !!(it.active||it.is_active);
    const price = +it.price||0;
    const type  = String(it.type||'other').toLowerCase();

    let actions = '';
    if (!owned) {
      actions = `<button class="shop-buy-btn js-buy" data-id="${it.id}">Kaufen</button>`;
    } else if (!active) {
      actions = `<button class="shop-buy-btn js-activate" data-id="${it.id}">Aktivieren</button>
                 <span class="shop-pill">Gekauft</span>`;
    } else {
      if (type === 'color') {
        actions = `<button class="shop-buy-btn shop-off-btn js-deactivate" data-type="color">Farbe entfernen</button>
                   <span class="shop-pill">Aktiv</span>`;
      } else {
        actions = `<span class="shop-pill">Aktiv</span>`;
      }
    }

    return `<div class="shop-card">
      <div class="shop-thumb"><img src="${imgSrc(it)}" alt=""></div>
      <div class="shop-title">${esc(it.title||'Ohne Titel')}</div>
      ${it.description? `<div class="shop-desc">${esc(it.description)}</div>`:''}
      <div class="shop-price">${price} Punkte</div>
      ${actions}
    </div>`;
  }
  function groupByType(items){
    const g=Object.fromEntries(ORDER.map(k=>[k,[]]));
    for(const it of (items||[])){ const t=String(it.type||'other').toLowerCase(); (g[ORDER.includes(t)?t:'other']).push(it); }
    return g;
  }
  function renderShop(targetCats, targetItems, items){
    const groups=groupByType(items);
    targetCats.innerHTML = ORDER.filter(k=>groups[k].length).map(k=>`<a href="#cat-${k}">${LABEL[k]}</a>`).join('') || '<span class="opacity-70">Keine Artikel</span>';
    targetItems.innerHTML = ORDER.filter(k=>groups[k].length).map(k=>`
      <section class="shop-section" id="cat-${k}"><h5>${LABEL[k]}</h5>
        <div class="shop-grid">${groups[k].map(renderItem).join('')}</div>
      </section>`).join('') || '<div class="p-4 opacity-70">Keine Shop-Artikel.</div>';
  }
  async function fetchShopItems(){
    const r = await fetch(APP + '/api/shop/list.php', {credentials:'include'});
    const j = await r.json(); if(!j || j.ok===false) throw new Error(j?.error||'server_error');
    return j.items || j.data || [];
  }
  async function loadShop(){
    $('#shopItems').innerHTML = '<div class="p-4 opacity-70">Lade Shop…</div>';
    try{ const items=await fetchShopItems(); renderShop($('#shopCats'), $('#shopItems'), items); }
    catch(e){ $('#shopCats').innerHTML=''; $('#shopItems').innerHTML = '<div class="p-4 text-red-400">Fehler: '+esc(e.message||e)+'</div>'; }
  }
  loadShop();

  // Inventar
  let _cacheShopItems=null;
  async function renderInventory(){
    try{
      _cacheShopItems = _cacheShopItems || await fetchShopItems();
      const owned = _cacheShopItems.filter(it=>!!(it.owned||it.is_owned));
      if(!owned.length){ $('#invWrap').innerHTML = '<div class="p-4 opacity-70">Noch keine Käufe.</div>'; return; }
      $('#invWrap').innerHTML = `<div class="shop-grid">${owned.map(renderItem).join('')}</div>`;
    }catch(e){ $('#invWrap').innerHTML = '<div class="p-4 text-red-400">Fehler: '+esc(e.message||e)+'</div>'; }
  }

  // Delegation – Shop Buttons
  document.addEventListener('click', async e=>{
    const buyBtn = e.target.closest('.js-buy');
    const actBtn = e.target.closest('.js-activate');
    const offBtn = e.target.closest('.js-deactivate');

    if(buyBtn){ buyBtn.disabled=true;
      try{
        const form = new URLSearchParams({ item_id:String(buyBtn.dataset.id||'') });
        const j = await postJson(APP + '/api/shop/buy.php', form);
        await refreshPoints(); _cacheShopItems=null; await loadShop();
        if($('#shopModal')?.classList.contains('is-open')) await loadShopModal();
        if($('#tab-inventory')?.classList.contains('is-active')) await renderInventory();
      }catch(err){ alert('Kauf fehlgeschlagen: ' + (err?.message||err)); } finally{ buyBtn.disabled=false; }
    }

    if(actBtn){ actBtn.disabled=true;
      try{
        const form = new URLSearchParams({ item_id:String(actBtn.dataset.id||'') });
        await postJson(APP + '/api/shop/activate.php', form);
        _cacheShopItems=null; await loadShop();
        if($('#shopModal')?.classList.contains('is-open')) await loadShopModal();
        if($('#tab-inventory')?.classList.contains('is-active')) await renderInventory();
      }catch(err){ alert('Aktivieren fehlgeschlagen: ' + (err?.message||err)); } finally{ actBtn.disabled=false; }
    }

    // Deaktivieren der Schriftfarbe
    if(offBtn){
      offBtn.disabled = true;
      try{
        const type = offBtn.dataset.type || 'color';
        const form = new URLSearchParams({ action:'off', type });
        await postJson(APP + '/api/shop/activate.php', form);
        _cacheShopItems=null; await loadShop();
        if($('#shopModal')?.classList.contains('is-open')) await loadShopModal();
        if($('#tab-inventory')?.classList.contains('is-active')) await renderInventory();
      }catch(err){ alert('Aktion fehlgeschlagen: ' + (err?.message||err)); }
      finally{ offBtn.disabled=false; }
    }
  });

  /* ===== Kontoauszug ===== */
  let txCursor=null, txBusy=false;
  async function loadTransactions(reset=false){
    if(txBusy) return; txBusy=true;
    if(reset){ txCursor=null; $('#txBody').innerHTML=''; }
    try{
      const url=new URL(APP + '/api/points/transactions.php', location.origin);
      if(txCursor) url.searchParams.set('cursor', txCursor);
      url.searchParams.set('limit','25');
      const r=await fetch(url.toString().replace(location.origin,''), {credentials:'include'});
      const j=await r.json(); if(!j||j.ok===false) throw new Error(j?.error||'server_error');
      const rows=j.rows||[]; const body=$('#txBody');
      for(const row of rows){
        const d=esc(row.created_at||row.ts||'—');
        const reason = esc(row.label || row.reason || row.type || '—');
        const delta=+row.delta||+row.amount||+row.value||+row.points_delta||0;
        const bal=row.balance_after!=null? String(row.balance_after) : (row.balance!=null? String(row.balance):'');
        body.insertAdjacentHTML('beforeend', `
          <div class="tx-row">
            <div><div>${d}</div><small>#${row.id||''}</small></div>
            <div>${reason}${row.note?` – <small>${esc(row.note)}</small>`:''}</div>
            <div class="${delta<0?'neg':'pos'}">${delta>0?'+':''}${delta}</div>
            <div>${bal? esc(bal):''}</div>
          </div>`);
      }
      txCursor=j.next_cursor||null;
      $('#txMoreBtn').hidden = !(rows.length && txCursor);
    }catch(e){
      $('#txBody').innerHTML = '<div class="p-4 text-red-400">Fehler: '+esc(e.message||e)+'</div>';
      $('#txMoreBtn').hidden=true;
    }finally{ txBusy=false; }
  }
  $('#txMoreBtn')?.addEventListener('click', ()=>loadTransactions(false));

  /* ===== Transfer ===== */
  const transferForm = document.getElementById('transferForm');
  const transferInfo = document.getElementById('transferInfo');

  transferForm?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    transferInfo.textContent = 'Sende…';
    const fd = new FormData(transferForm);
    try{
      const j = await postJson(APP + '/api/points/transfer.php', fd);
      transferInfo.textContent = `Gesendet: ${j.sent} Punkte an ${j.to?.name || j.to?.slug || ('#'+(j.to?.id||''))}. Neuer Kontostand: ${j.balance}`;
      transferForm.reset();
      await refreshPoints();
      await loadTransactions(true);
    }catch(err){
      transferInfo.textContent = 'Fehler: ' + (err?.message || err);
    }
  });

  /* ===== Optionales Shop-Modal ===== */
  const shopModal = document.getElementById('shopModal');
  document.getElementById('btnOpenShop')?.addEventListener('click', async ()=>{
    shopModal.classList.add('is-open'); document.body.style.overflow='hidden';
    await loadShopModal();
  });
  shopModal?.querySelector('.shop-close')?.addEventListener('click', ()=>{
    shopModal.classList.remove('is-open'); document.body.style.overflow='';
  });
  async function loadShopModal(){
    document.getElementById('shopItems2').innerHTML='<div class="p-4 opacity-70">Lade Shop…</div>';
    try{ const items=await fetchShopItems(); renderShop(document.getElementById('shopCats2'), document.getElementById('shopItems2'), items); }
    catch(e){ document.getElementById('shopCats2').innerHTML=''; document.getElementById('shopItems2').innerHTML='<div class="p-4 text-red-400">Fehler: '+esc(e.message||e)+'</div>'; }
  }

  /* ===== Typeahead für Empfängerfeld ===== */
  (function(){
    const input = document.querySelector('#transferForm input[name="to"]');
    if (!input) return;

    // unsichtbarer "Anker" direkt NACH dem Input, damit die Liste bündig darunter sitzt
    const anchor = document.createElement('div');
    anchor.className = 'ta-after';
    input.insertAdjacentElement('afterend', anchor);

    // Liste
    const list = document.createElement('div');
    list.className = 'ta-list';
    anchor.appendChild(list);

    // Breite der Liste immer an Input angleichen
    function syncWidth() {
      const r = input.getBoundingClientRect();
      anchor.style.width = r.width + 'px';
    }
    syncWidth();
    window.addEventListener('resize', syncWidth);

    let items = [], sel = -1, lastQ = '', t = null, aborter = null;
    const escHtml = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

    function render(){
      anchor.classList.toggle('ta-open', items.length>0);
      list.innerHTML = items.map((u,i)=>`
        <div class="ta-item ${i===sel?'is-active':''}" data-i="${i}">
          ${u.avatar ? `<img class="ta-img" src="${escHtml(u.avatar)}" alt="">` : `<span class="ta-img"></span>`}
          <div>
            <div class="ta-name">${escHtml(u.name)}</div>
            <div class="ta-meta">${escHtml(u.slug || u.email || u.ref || '')}</div>
          </div>
        </div>
      `).join('');
    }
    function choose(i){
      const u = items[i]; if(!u) return;
      input.value = u.slug || u.name || u.email || u.ref || '';
      close();
    }
    function close(){ items=[]; sel=-1; render(); }

    list.addEventListener('mousedown', (e)=>{
      const it = e.target.closest('[data-i]');
      if(!it) return;
      e.preventDefault();
      choose(+it.dataset.i);
    });

    input.addEventListener('keydown', (e)=>{
      if(!items.length) return;
      if(e.key==='ArrowDown'){ sel = (sel+1)%items.length; render(); e.preventDefault(); }
      else if(e.key==='ArrowUp'){ sel = (sel-1+items.length)%items.length; render(); e.preventDefault(); }
      else if(e.key==='Enter'){ if(sel>-1){ choose(sel); e.preventDefault(); } }
      else if(e.key==='Escape'){ close(); }
    });

    input.addEventListener('blur', ()=> setTimeout(close, 150));

    input.addEventListener('input', ()=>{
      const q = input.value.trim();
      if(q === lastQ) return;
      lastQ = q;

      if (t) clearTimeout(t);
      if (aborter) aborter.abort();
      if (q.length < 1) { close(); return; }

      t = setTimeout(async () => {
        try{
          aborter = new AbortController();
          const url = `${APP}/api/users/suggest.php?q=${encodeURIComponent(q)}&limit=7`;
          const r   = await fetch(url, { credentials:'include', signal:aborter.signal });
          const j   = await r.json();
          items = (j && j.ok) ? (j.users || []) : [];
          sel = -1;
          render();
        }catch(_){} // ignore
      }, 200);
    });
  })();

})();
</script>

<?php
$content = ob_get_clean();
render_theme_page($content, $title);

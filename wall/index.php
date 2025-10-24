<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/guards.php';

// Nutzer prüfen
$me = optional_auth();
if (!$me) {
    // Gast → weiterleiten zur Gast-Seite
    header("Location: /wallguest.php");
    exit;
}

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/wall.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$L = $GLOBALS['L'];
$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

// CSRF-Token aus Session
$sessionCookieName = $cfg['cookies']['session_name'] ?? '';
$sessionCookie = $_COOKIE[$sessionCookieName] ?? '';
$csrf = function_exists('issue_csrf') ? issue_csrf($pdo, $sessionCookie) : (function_exists('csrf_token') ? csrf_token() : '');

$avatar = function_exists('current_user_avatar_url') ? current_user_avatar_url() : '/assets/images/avatar-default.png';

ob_start();
?>
















        <main>

            <!-- community page section start -->
            <section class="section-py overflow-visible">
                <div class="container pt-[30px]">
                    <div class="grid grid-cols-12 gap-30p">
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
		<div class="min-[1480px]:col-span-3 xl:col-span-4 xl:block hidden relative">
                            <div class="xl:sticky xl:top-30 h-screen pb-40 overflow-y-auto scrollbar-0">
                                <div class="py-24p px-32p bg-b-neutral-3 rounded-12 mb-30p">
                                    <div class="flex flex-wrap justify-between items-center gap-20p mb-24p">
                                        <div class="flex-y gap-3">
                                            <span class="badge badge-circle size-3 badge-primary"></span>
                                            <h4 class="heading-4 text-w-neutral-1">
                                                Recent Topics
                                            </h4>
                                        </div>
                                        <a href="/forum/boards.php" class="text-s-medium text-w-neutral-1 link-1">
                                            Zeige Alle
                                            <i class="ti ti-arrow-right"></i>
                                        </a>
                                    </div>
                                    <div class="grid grid-cols-1 gap-24p">
                        <?php include __DIR__ . '/sidebar-left.php'; ?>
                          
              
                         
                                    </div>
                                </div>
                                
                            </div>
                        </div>			
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
					
                       
                        <div class="min-[1480px]:col-span-6 xl:col-span-8 col-span-12">
                            <div class="grid grid-cols-1 gap-30p">
              
								<link rel="stylesheet" href="<?= esc($APP_BASE) ?>/assets/styles/wall.css?v=1">
<meta name="csrf" content="<?= esc($csrf) ?>">
 
	<?php require __DIR__ . '/../theme/partials/wall_composer_inline.php'; ?>
<?php require __DIR__ . '/../theme/partials/wall_composer_modal.php'; ?>

 
                                <div>
                                    

                          
                                       

                                        <!-- post card  -->
                                        
										<div id="feed" class="hh-feed " data-aos="fade-up" data-endpoint="<?= esc($APP_BASE) ?>/api/wall/feed.php"></div>
    <div id="feed-sentinel" class="hh-sentinel" aria-hidden="true"></div>

                                        <!-- post card  -->
                                        

                               
                                    <div class="flex-c mt-48p">
                                        <button type="button" class="btn btn-lg btn-neutral-3 rounded-12">
                                            Load more...
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
						
						
						
						
						
						
						
						
						
						
						
						
						
						
						
						
						
						
						<div class="min-[1480px]:col-span-3 relative min-[1480px]:block hidden">
                            <div
                                class="min-[1480px]:sticky min-[1480px]:top-30 h-screen pb-40 overflow-y-auto scrollbar-0">
                                <div class="grid grid-cols-12 gap-24p *:bg-b-neutral-3 *:rounded-12 *:px-32p *:py-24p">
                                    <div class="xxl:col-span-12 md:col-span-6 col-span-12 xxl:order-1 order-3">
                                        <div class="flex flex-wrap items-center justify-between gap-3 mb-40p">
                                            <h4 class="heading-4 text-w-neutral-1 ">
                                                Aktive Mitglieder
                                            </h4>
                                            <a href="#" class="inline-flex items-center gap-3 text-w-neutral-1 link-1">
                                                View All
                                                <i class="ti ti-arrow-right"></i>
                                            </a>
                                        </div>
                                        <!-- active-members-carousel -->
                                        <div class="swiper active-members-carousel w-full">
                                            <div class="swiper-wrapper *:w-fit">
						
                        <?php include __DIR__ . '/sidebar-right.php'; ?>
						
						
						
						
						
						
						
						
						                 </div>
                                        </div>
                                    </div>
									
									
									
									
									
									
									
									
									
									
									
									
									
									
									
									<?php include __DIR__ . '/sidebar-events.php'; ?>

                                    
                                   
                       
                                </div>
                            </div>
                        </div>
						
						
						
						
						
						
						
						
                    </div>
                </div>
            </section>
            <!-- community page section end -->

        </main>





















<script>
  window.REACTIONS_PNG = {
    like: '/assets/images/reactions/like.png',
    love: '/assets/images/reactions/love.png',
    haha: '/assets/images/reactions/haha.png',
    wow: '/assets/images/reactions/wow.png',
    sad: '/assets/images/reactions/sad.png',
    angry: '/assets/images/reactions/angry.png'
  };
</script>


<script>window.APP_BASE = <?= json_encode($APP_BASE) ?>;</script>
<script src="<?= esc($APP_BASE) ?>/assets/js/wall.js?v=1" defer></script>
<script src="<?= esc($APP_BASE) ?>/assets/js/wall.reactions.js" defer></script>
<script src="/assets/js/reels-viewer.js?v=1.93" defer></script>



<script>
(async () => {
  const root = document.querySelector('#reels-stories .swiper-wrapper');
  if (!root) return;

  // Daten holen
  let res, json;
  try {
    res  = await fetch('/api/reels/latest.php?limit=6', { credentials: 'same-origin' });
    json = await res.json();
  } catch (_) { json = { ok:false, items:[] }; }

  const items = (json && json.ok && Array.isArray(json.items)) ? json.items : [];

  // Slides bauen
  root.innerHTML = items.map((it, i) => {
    const title = (it.description || '').trim() || '@' + (it.username || 'user');
    const poster = it.poster || '/assets/images/reels/default-poster.jpg';
    return `
      <div class="swiper-slide">
        <a href="#" class="relative w-full rounded-12 group block" data-open-reel data-id="${it.id}">
          <div class="overflow-hidden rounded-12">
            <img class="w-full md:h-[300px] sm:h-[240px] xsm:h-[220px] h-[200px] object-cover group-hover:scale-110 transition-1"
                 src="${poster}" alt="reel ${it.id}" />
            <span class="absolute top-4 left-4 flex-c size-6 rounded-full bg-accent-4 text-w-neutral-1">${i+1}</span>
          </div>
          <div class="w-full absolute inset-0 flex flex-col justify-end z-[2] p-24p overlay-7">
            <div>
              <h6 class="heading-6 text-w-neutral-1 line-clamp-2 max-w-[90px]">${escapeHtml(title)}</h6>
            </div>
          </div>
        </a>
      </div>`;
  }).join('');

  // Click → Modal direkt bei dieser ID öffnen
  root.addEventListener('click', (e) => {
    const a = e.target.closest('[data-open-reel]');
    if (!a) return;
    e.preventDefault();
    const id = parseInt(a.dataset.id, 10);
    if (window.HHReels?.openId) window.HHReels.openId(id);
    else window.HHReels?.open(0);
  });

  // Swiper initialisieren (falls eingebunden)
  if (window.Swiper) {
    new Swiper('.stories-carousel', {
      slidesPerView: 3,
      spaceBetween: 16,
      breakpoints: {
        1280: { slidesPerView: 3 },
        1024: { slidesPerView: 3 },
        768:  { slidesPerView: 3 },
        480:  { slidesPerView: 2.2 },
        0:    { slidesPerView: 1.2 },
      }
    });
  }

  function escapeHtml(s){return s.replace(/[&<>"]/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m]))}
})();
</script>

<?php
$html = ob_get_clean();
render_theme_page($html, 'Wall');

<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/layout.php';

$me = optional_auth();

/* FIX: Sprache für Header/Footer */
if (!isset($lang) || !$lang) { $lang = detect_lang(); load_lang($lang); }

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
                Reels
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
                  <span class="breadcrumb-current">Reels</span>
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
  <div class="container mx-auto px-4 py-3 flex justify-end">
 
</div>

<div id="reels-root" class="fixed inset-0 bg-black text-white overflow-hidden">
  <div id="reels-stack" class="h-full w-full relative"></div>
  <!-- Composer Button (unten rechts) -->

  
  
</div>
  </section>
  <!-- sign up section end -->

</main>
<button class="hh-fab" data-reels-compose title="Neues Reel" id="reels-compose-btn">+ Reel</button>

<link rel="stylesheet" href="/assets/styles/reels.css?v=1" />
<script src="/assets/js/reels-viewer.js?v=1" defer></script>
<script src="/assets/js/reels-composer.js?v=1" defer></script>
<?php
$content = ob_get_clean();
render_theme_page($content, 'Reels');
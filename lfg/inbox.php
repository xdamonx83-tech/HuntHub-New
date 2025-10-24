<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../auth/guards.php';

$me   = function_exists('optional_auth') ? optional_auth() : current_user();
$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$L = $GLOBALS['L']; // lokale Referenz

$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

ob_start();
?>








<section class="pt-30p">
    <div class="section-pt">
        <div
            class="bg-[url('../images/photos/tournamentBanner.webp')] bg-cover bg-no-repeat rounded-24 overflow-hidden h-[416px]">
            <div class="container">
                <div class="grid grid-cols-12 gap-30p relative xl:py-[110px] md:py-30 sm:py-25 py-20 z-[2]">
                    <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                        <h2 class="heading-2 text-w-neutral-1 mb-3">
                            <?= t('inbox_spielersuche_ea7159bb') ?>
                        </h2>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="#" class="breadcrumb-link">
                                    <?= t('inbox_home_fc8351a4') ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <span class="breadcrumb-icon">
                                    <i class="ti ti-chevrons-right"></i>
                                </span>
                            </li>
                            <li class="breadcrumb-item">
                                <span class="breadcrumb-current"><?= t('inbox_spielersuche_ea7159bb') ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div
                class="pb-30p overflow-visible relative grid 4xl:grid-cols-12 grid-cols-1 gap-30p lg:-mt-30 md:-mt-40 sm:-mt-48 -mt-56">
                <div class="4xl:col-start-2 4xl:col-end-12">
                    <div class="relative z-10 grid 4xl:grid-cols-11 grid-cols-12 items-center gap-30p bg-b-neutral-3 shadow-4 p-40p rounded-24 xl:divide-x divide-shap/70"
                        data-aos="fade-up" data-aos-duration="2000">
                        <div class="3xl:col-span-4 col-span-12">
                            <div class="max-xl:flex-col-c max-xl:text-center">
                                <h3 class="heading-3 text-w-neutral-1 mb-20p">
                                    <?= t('inbox_spielersuche_postfach_c14204bd') ?>
                                </h3>
                                <div class="flex-y flex-wrap max-xl:justify-center gap-16p">
                                
                                         <a href="<?= h($APP_BASE) ?>/lfg/create.php" class="btn btn-md btn-primary rounded-12 mb-16p">
                                        <?= t('inbox_eigenen_eintrag_erstellen_fef04a2f') ?>
                                    </a>
                                
                                 
                                       <a href="<?= h($APP_BASE) ?>/lfg/index.php" class="btn btn-md btn-primary rounded-12 mb-16p">
                                        <?= t('inbox_ubersicht_676fb003') ?>
                                    </a>
                                   
                                </div>

                            </div>
                        </div>
         
            
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
<!-- Tournament header end -->

            <!-- Tournament Prizes section start -->
            <section class="section-pb">
                <div class="container">
                    <div class="overflow-visible relative grid 4xl:grid-cols-12 grid-cols-1 gap-30p">
                        <div class="4xl:col-start-2 4xl:col-end-12">
                            <div class="overflow-x-auto scrollbar-sm">
							
							
		<div class="flex-y flex-wrap gap-x-32p bg-b-neutral-3 px-32p py-24p rounded-12 mb-32p">
   
 
  <div style="display:flex;gap:.5rem;margin:.75rem 0 1rem;">
    <button class="btn" data-inbox-tab="pending"  style="padding:.4rem .7rem;border:1px solid #333;border-radius:10px;"><?= t('inbox_eingang_offen_27c095fc') ?></button>
    <button class="btn" data-inbox-tab="accepted" style="padding:.4rem .7rem;border:1px solid #333;border-radius:10px;"><?= t('inbox_angenommen_deeb42fc') ?></button>
    <button class="btn" data-inbox-tab="declined" style="padding:.4rem .7rem;border:1px solid #333;border-radius:10px;"><?= t('inbox_abgelehnt_bace0bd6') ?></button>
    <button class="btn" data-inbox-tab="all"      style="padding:.4rem .7rem;border:1px solid #333;border-radius:10px;"><?= t('inbox_alle_a27e593d') ?></button>
    <button class="btn" data-outbox="1"           style="padding:.4rem .7rem;border:1px solid #333;border-radius:10px;margin-left:auto;"><?= t('inbox_gesendet_a22e5093') ?></button>
  </div>

  <div id="lfg-inbox-list"></div>

                                        </div>					




         
                            </div>
       
                        </div>
                    </div>
                </div>
            </section>
            <!-- Tournament Prizes section end -->

        </main>





<script src="<?= h($APP_BASE) ?>/assets/js/lfg.js?v=3"></script>
<?php
$content    = ob_get_clean();
$pageTitle  = 'LFG-Postfach | Hunthub';
$pageDesc   = 'Verwalte Spielanfragen: annehmen, ablehnen, blockieren oder direkt antworten. Outbox zeigt deine gesendeten Anfragen.';
$pageImage  = $APP_BASE . '/assets/images/og/lfg.webp';
render_theme_page($content, $pageTitle, $pageDesc, $pageImage);

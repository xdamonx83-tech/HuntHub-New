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


<style>
/* Container trennt Zeilen (ersetzt table tbody divide-y) */
.hh-grid-body { display: grid; gap: 12px; }

/* Desktop: 7 Daten-Spalten + 1 Actions-Spalte (auto) */
@media (min-width: 768px) {
  .hh-grid-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr) auto; /* 3 alt + 4 neu + actions */
    align-items: end;
  }
  .hh-grid-row {
    display: grid;
    grid-template-columns: repeat(7, 1fr) auto;
    align-items: center;
  }
  .hh-grid-row > [data-label]::before { content: none; }
}

/* Mobil: Karten-Layout, Labels vor Werte */
@media (max-width: 767px) {
  .hh-grid-header { display: none; }
  .hh-grid-row {
    display: grid;
    grid-template-columns: 1fr;
    row-gap: 6px;
  }
  .hh-grid-row > [data-label] {
    display: grid;
    grid-template-columns: 40% 60%;
    align-items: center;
    column-gap: .5rem;
  }
  .hh-grid-row > [data-label]::before {
    content: attr(data-label);
    color: var(--w-neutral-3, #9ca3af);
    opacity: .9;
  }
  .hh-grid-row > [data-label=""] { grid-template-columns: 1fr; } /* Actions */
}
.hh-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:1000}
.hh-modal.open{display:flex}
.hh-modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.65)}
.hh-modal .dialog{position:relative;background:#0b0b0b;border:1px solid rgba(255,255,255,.12);
  border-radius:12px;width:min(820px,calc(100% - 2rem));max-height:90vh;overflow:auto;padding:1rem;
  box-shadow:0 20px 80px rgba(0,0,0,.6)}
.hh-modal .close{position:absolute;top:.25rem;right:.5rem;background:transparent;border:0;
  color:#e5e7eb;font-size:28px;cursor:pointer;line-height:1}
/* ==== LFG Details ==== */
.hh-modal .dialog { width:min(900px, calc(100% - 2rem)); padding:1.25rem; }

.lfg-details { display:grid; gap:16px; }
.lfg-header  { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.lfg-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; margin-top:.5rem; }

.lfg-grid    { display:grid; grid-template-columns: 1.15fr .85fr; gap:16px; }
.lfg-card    { border:1px solid rgba(255,255,255,.12); border-radius:12px; background:#0f0f0f; padding:14px; }

.lfg-title   { font-size:1.05rem; font-weight:600; color:#e5e7eb; margin:0 0 .6rem 0; }
.lfg-kv      { display:grid; grid-template-columns: 1fr 1fr; gap:8px 12px; }
.lfg-kv .k   { color:#9ca3af; }
.lfg-kv .v   { color:#e5e7eb; }

.lfg-badges  { display:flex; flex-wrap:wrap; gap:6px; }
.lfg-chip    { display:inline-flex; align-items:center; gap:6px; padding:3px 8px;
               border:1px solid #2a2a2a; border-radius:999px; font-size:.86rem; color:#d1d5db; background:#111; }

.lfg-notes   { color:#c9c9c9; line-height:1.45; }

@media (max-width: 820px){
  .lfg-grid { grid-template-columns: 1fr; }
  .lfg-kv   { grid-template-columns: 1fr; }
}
/* Request-Form Layout */
.lfg-req-form { display:grid; gap:10px; }
.lfg-req-row  { display:grid; gap:6px; }
.lfg-req-row .k { color:#9ca3af; font-size:.95rem; }
.lfg-req-msg  { min-height:1.25rem; font-size:.95rem; }
#lfg-request-submit[disabled]{ opacity:.6; cursor:not-allowed; }
#lfg-modal{z-index:1000}
#lfg-request-modal{z-index:1100}

</style>





       <main>

            <!-- Tournament header start -->
<section class="pt-30p">
    <div class="section-pt">
        <div
            class="bg-[url('../images/photos/searchBanner.webp')] bg-cover bg-no-repeat rounded-24 overflow-hidden h-[416px]">
            <div class="container">
                <div class="grid grid-cols-12 gap-30p relative xl:py-[110px] md:py-30 sm:py-25 py-20 z-[2]">
                    <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                        <h2 class="heading-2 text-w-neutral-1 mb-3">
                            <?= t('index_spielersuche_45f96d87') ?>
                        </h2>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="#" class="breadcrumb-link">
                                    <?= t('index_home_cf041eb3') ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <span class="breadcrumb-icon">
                                    <i class="ti ti-chevrons-right"></i>
                                </span>
                            </li>
                            <li class="breadcrumb-item">
                                <span class="breadcrumb-current"><?= t('index_spielersuche_45f96d87') ?></span>
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
                                    <?= t('index_spielersuche_45f96d87') ?>
                                </h3>
                                <div class="flex-y flex-wrap max-xl:justify-center gap-16p">
                                
                                         <a href="<?= h($APP_BASE) ?>/lfg/create.php" class="btn btn-md btn-primary rounded-12 mb-16p">
                                        <?= t('index_eigenen_eintrag_erstellen_3de33a26') ?>
                                    </a>
                                
                                 
                                       <a href="<?= h($APP_BASE) ?>/lfg/inbox.php" class="btn btn-md btn-primary rounded-12 mb-16p">
                                        <?= t('index_postfach_8aee25ef') ?>
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
							  <div id="lfg-filters" class="card" style="padding:1rem;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:#111;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;max-width: 95%;">
      
      <select id="lfg-platform" class="ss-main select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium"><option value=""><?= t('index_plattform_c7af65ef') ?></option><option><?= t('index_pc_e3e90d6d') ?></option><option><?= t('index_xbox_4783a689') ?></option><option><?= t('index_ps_b9a5f7c2') ?></option></select>
      <select id="lfg-region" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium"><option value=""><?= t('index_region_68106515') ?></option><option><?= t('index_eu_2b052acd') ?></option><option><?= t('index_na_bef00f7d') ?></option><option><?= t('index_sa_fb63ff02') ?></option><option><?= t('index_asia_ec1e118e') ?></option><option><?= t('index_oce_ee67dcf5') ?></option></select>
      <select id="lfg-mode" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium"><option value=""><?= t('index_modus_fb6d7001') ?></option><option value="bounty"><?= t('index_bounty_2e9ecc0a') ?></option><option value="clash"><?= t('index_clash_af3fac5e') ?></option><option value="both"><?= t('index_both_0e1a9218') ?></option></select>
      <select id="lfg-playstyle" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium"><option value=""><?= t('index_spielstil_1c1653a4') ?></option><option><?= t('index_offensive_da7722b3') ?></option><option><?= t('index_defensive_d3a711a8') ?></option><option><?= t('index_balanced_97800693') ?></option></select>
      <select id="lfg-looking" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium"><option value=""><?= t('index_sucht_f737cd75') ?></option><option><?= t('index_solo_cad6be5e') ?></option><option><?= t('index_duo_4198d029') ?></option><option><?= t('index_trio_44189992') ?></option><option><?= t('index_any_1785f4ee') ?></option></select>
      <select id="lfg-headset" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium"><option value=""><?= t('index_headset_2f958297') ?></option><option value="1"><?= t('index_ja_3523ac22') ?></option><option value="0"><?= t('index_nein_5102e9fb') ?></option></select>
   </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-top: 15px;"><input id="lfg-q" class="box-input-3" placeholder="Suche (Notiz/Waffe)">
	  <input id="lfg-lang" class="box-input-3" placeholder="Sprache (de,en)">
      <input id="lfg-min-mmr" class="box-input-3" type="number" placeholder="min MMR">
      <input id="lfg-max-mmr" class="box-input-3" type="number" placeholder="max MMR">
      <input id="lfg-min-kd" class="box-input-3" type="number" step="0.01" placeholder="min KD">
      <input id="lfg-max-kd" class="box-input-3" type="number" step="0.01" placeholder="max KD">
    </div>
  </div>
  
  
  
  
  <!-- Responsive "table" as divs (8 Spalten inkl. Actions) -->
<div class="min-w-full whitespace-nowrap" style="padding-top: 25px;">

  <!-- Header (mobil ausgeblendet) -->
  <div class="text-xl font-borda bg-transparent text-w-neutral-1 hh-grid-header">
    <div class="px-32p pb-20p text-left"><?= t('index_username_29386648') ?></div>
    <div class="px-32p pb-20p text-left"><?= t('index_plattform_c7af65ef') ?></div>
    <div class="px-32p pb-20p text-left"><?= t('index_region_68106515') ?></div>
<div class="px-32p pb-20p text-left"><?= t('index_details_32c85066') ?></div>
    <!-- 4 neue Spalten -->


    <!-- Actions -->
    <div class="px-32p pb-20p text-right"></div>
  </div>

  <!-- Body -->
  <div class="text-base font-medium font-poppins text-w-neutral-3 hh-grid-body">

    <!-- Row -->
<div id="lfg-results"></div>

    <!-- weitere .hh-grid-row … -->
  </div>
</div>



         
                            </div>
         
                        </div>
                    </div>
                </div>
            </section>
            <!-- Tournament Prizes section end -->

        </main>






<!-- LFG Request Modal -->
<div id="lfg-request-modal" class="hh-modal" aria-hidden="true">
  <div class="backdrop" data-close></div>
  <div class="dialog" role="dialog" aria-modal="true" aria-label="Spielanfrage">
    <button class="close" type="button" title="Schließen" data-close>&times;</button>
    <div class="content">
      <h3 class="lfg-title" style="margin:0 0 .75rem 0;"><?= t('index_spielanfrage_senden_5f97325f') ?></h3>

      <form id="lfg-request-form" class="lfg-req-form">
        <input type="hidden" name="post_id" id="lfg-request-post">
        <div class="lfg-req-row">
          <label class="k"><?= t('index_nachricht_optional_943b3008') ?></label>
          <textarea name="message" id="lfg-request-message" class="field" rows="4"
            placeholder="Kurz erklären, wann/was ihr spielen wollt…"></textarea>
        </div>
        <div id="lfg-request-msg" class="lfg-req-msg"></div>

        <div class="lfg-actions" style="margin-top:.75rem;">
          <button type="button" class="btn" data-close><?= t('index_abbrechen_7ad90699') ?></button>
          <button type="submit" class="btn" id="lfg-request-submit"><?= t('index_senden_ceb3339f') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>









<script src="<?= h($APP_BASE) ?>/assets/js/lfg.js?v=3"></script>
<?php
$content    = ob_get_clean();
$pageTitle  = 'Spielersuche | Hunthub';
$pageDesc   = 'Finde Mitspieler für Hunt: Showdown nach MMR, KD, Modus, Spielstil, Sprache und Zeiten. Schicke Spielanfragen direkt und chatte los.';
$pageImage  = $APP_BASE . '/assets/images/og/lfg.webp'; // Ersatzbild, falls vorhanden
render_theme_page($content, $pageTitle, $pageDesc, $pageImage);

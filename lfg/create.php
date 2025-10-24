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

<!-- Hero / Banner -->
<section class="pt-30p">
  <div class="section-pt">
    <div class="rounded-24 overflow-hidden h-[416px] bg-cover bg-no-repeat"
         style="background-image:url('<?= h($APP_BASE) ?>/assets/images/searchBanner.webp');">
            <div class="container">
                <div class="grid grid-cols-12 gap-30p relative xl:py-[110px] md:py-30 sm:py-25 py-20 z-[2]">
                    <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                        <h2 class="heading-2 text-w-neutral-1 mb-3">
                            <?= t('create_spielersuche_erstellen_a125540f') ?>
                        </h2>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="/" class="breadcrumb-link">
                                    <?= t('create_home_7c0be0c3') ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <span class="breadcrumb-icon">
                                    <i class="ti ti-chevrons-right"></i>
                                </span>
                            </li>
                            <li class="breadcrumb-item">
                                <span class="breadcrumb-current"><?= t('create_spielersuche_erstellen_a125540f') ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
    </div>

    <div class="container">
      <div class="pb-30p relative grid 4xl:grid-cols-12 grid-cols-1 gap-30p lg:-mt-30 md:-mt-40 sm:-mt-48 -mt-56">
        <div class="4xl:col-start-2 4xl:col-end-12">
          <div class="relative z-10 grid 4xl:grid-cols-11 grid-cols-12 items-center gap-30p bg-b-neutral-3 shadow-4 p-40p rounded-24 xl:divide-x divide-shap/70"
               data-aos="fade-up" data-aos-duration="2000">
            <div class="3xl:col-span-6 col-span-12">
              <div class="max-xl:flex-col-c max-xl:text-center">
                <h3 class="heading-3 text-w-neutral-1 mb-20p"><?= t('create_eintrag_ausfullen_a8dca98e') ?></h3>
                <p class="text-w-neutral-3"><?= t('create_mmr_kd_modus_spielstil_sprache_und_zeiten_damit_andere_9398120e') ?></p>
              </div>
            </div>
            <div class="3xl:col-span-5 col-span-12">
              <div class="flex-y flex-wrap max-xl:justify-center gap-16p">
                <a href="<?= h($APP_BASE) ?>/lfg/index.php" class="btn btn-md btn-primary rounded-12 mb-16p"><?= t('create_ubersicht_6f78e0ff') ?></a>
                <a href="<?= h($APP_BASE) ?>/lfg/inbox.php" class="btn btn-md btn-secondary rounded-12 mb-16p"><?= t('create_postfach_3209e3ee') ?></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Create Form Section -->
<section class="section-pb">
  <div class="container">
    <div class="grid 4xl:grid-cols-12 grid-cols-1 gap-30p">
      <div class="4xl:col-start-2 4xl:col-end-12">
        <div class="bg-b-neutral-3 rounded-24 p-32p shadow-4">

          <form id="lfg-form" class="grid gap-24p">
            <!-- Grid of fields -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-20p">
              <label class="form-control">
                <span class="label"><?= t('create_plattform_e6256ac2') ?></span>
                <select name="platform" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium">
                  <option value="pc"><?= t('create_pc_3fe667e6') ?></option>
                  <option value="xbox"><?= t('create_xbox_310c833b') ?></option>
                  <option value="ps"><?= t('create_ps_7e18a81f') ?></option>
                </select>
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_region_6744012f') ?></span>
                <select name="region" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium">
                  <option value="eu"><?= t('create_eu_2854b7fa') ?></option>
                  <option value="na"><?= t('create_na_f2b32f94') ?></option>
                  <option value="sa"><?= t('create_sa_67033c1c') ?></option>
                  <option value="asia"><?= t('create_asia_838c6eed') ?></option>
                  <option value="oce"><?= t('create_oce_6ab0d6da') ?></option>
                </select>
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_mmr_514ced9e') ?></span>
                <input type="number" name="mmr" class="field box-input-3" placeholder="z. B. 3000">
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_kd_c622ad5c') ?></span>
                <input type="number" step="0.01" name="kd" class="field box-input-3" placeholder="z. B. 1.20">
              </label>

              <label class="form-control md:col-span-2">
                <span class="label"><?= t('create_primare_waffe_ac911f18') ?></span>
                <input type="text" name="primary_weapon" class="field box-input-3" placeholder="z. B. Sparks">
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_modus_52cf8ea7') ?></span>
                <select name="mode" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium">
                  <option value="bounty"><?= t('create_bounty_7181834a') ?></option>
                  <option value="clash"><?= t('create_clash_120785ba') ?></option>
                  <option value="both"><?= t('create_both_a0e43609') ?></option>
                </select>
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_spielstil_9fddb134') ?></span>
                <select name="playstyle" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium">
                  <option value="balanced"><?= t('create_balanced_871c3d86') ?></option>
                  <option value="offensive"><?= t('create_offensive_b337657f') ?></option>
                  <option value="defensive"><?= t('create_defensive_34514a46') ?></option>
                </select>
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_headset_c53c8aa0') ?></span>
                <select name="headset" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium">
                  <option value="1"><?= t('create_ja_f2ab5655') ?></option>
                  <option value="0"><?= t('create_nein_011dc4d1') ?></option>
                </select>
              </label>

              <label class="form-control md:col-span-2">
                <span class="label"><?= t('create_sprachen_csv_63e597fc') ?></span>
                <input type="text" name="languages" class="field box-input-3" placeholder="de,en">
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_suche_bbb55b1e') ?></span>
                <select name="looking_for" class="select !w-[276px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium">
                  <option value="any"><?= t('create_any_71595f56') ?></option>
                 
                  <option value="duo"><?= t('create_duo_9934dc61') ?></option>
                  <option value="trio"><?= t('create_trio_eeba6f29') ?></option>
                </select>
              </label>

              <label class="form-control md:col-span-2">
                <span class="label"><?= t('create_zeiten_json_2d97c0ce') ?></span>
                <input type="text" name="timeslots" class="field box-input-3" placeholder='{"days":["mo","we"],"from":"19:00","to":"23:00"}'>
              </label>

              <label class="form-control">
                <span class="label"><?= t('create_ablaufdatum_65162e15') ?></span>
                <input type="text" name="expires_at" class="field box-input-3">
              </label>
            </div>

            <!-- Notes -->
            <label class="form-control">
              <span class="label"><?= t('create_notiz_d966f009') ?></span>
              <textarea name="notes" rows="5" class="field box-input-3" placeholder="Kurzbeschreibung, Erwartungen, etc."></textarea>
            </label>

            <div class="flex flex-wrap items-center gap-12p pt-8">
              <button type="submit" class="btn btn-primary rounded-12"><?= t('create_speichern_e5662783') ?></button>
              <a href="<?= h($APP_BASE) ?>/lfg/index.php" class="btn rounded-12"><?= t('create_zuruck_46284882') ?></a>
            </div>
          </form>

          <div id="lfg-form-msg" class="mt-20p"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= h($APP_BASE) ?>/assets/js/lfg.js?v=9"></script>
<?php
$content    = ob_get_clean();
$pageTitle  = 'LFG-Eintrag erstellen | Hunthub';
$pageDesc   = 'Erstelle dein LFG-Profil: MMR, KD, Modus, Spielstil, Sprache und Zeiten. Sichtbar für Spieler-Suche & Anfragen.';
$pageImage  = $APP_BASE . '/assets/images/og/lfg.webp';
render_theme_page($content, $pageTitle, $pageDesc, $pageImage);

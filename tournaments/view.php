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
$tid = (int)($_GET['id'] ?? 1); // ?id=...
$en = <<<HTML
<section class="hh-event-desc" style="font-size:1.1rem; line-height:1.65">
  <header>
    <h3 class="heading-5 text-w-neutral-1 mb-3">Hunthub Event #1 – <em>Gauntlet: Kills • Boss • Bounty</em></h3>
    <p class="text-base text-w-neutral-4">First community event: For one week, rack up as many hunter kills as possible, slay bosses, extract with the bounty — and die as rarely as possible. Gauntlet format: only extractions while alive count.</p>
  </header>
  <h3 class="heading-5 text-w-neutral-1 mb-3">How it works</h3>
  <ul class="list-disc text-base text-w-neutral-4 ml-5 mb-24p">
    <li><strong>Lots of kills:</strong> The more hunter kills, the better.</li>
    <li><strong>Boss hunt:</strong> Boss kills are counted.</li>
    <li><strong>Bounty extract:</strong> Extract with at least one token.</li>
    <li><strong>Gauntlet:</strong> Only <em>survived</em> matches count; a death ends your current streak.</li>
    <li><strong>Fewer deaths:</strong> Dying is bad — play smart and secure the extract.</li>
    <li><strong>Platforms:</strong> PC, Xbox, PlayStation — everything counts.</li>
  </ul>
  <h3 class="heading-5 text-w-neutral-1 mb-3">Prizes</h3>
  <p class="text-base text-w-neutral-4">Each winner receives a <strong>€5 Amazon gift card</strong>.</p>
  <ul class="list-disc text-base text-w-neutral-4 ml-5 mb-24p">
    <li><strong>Team of 3 players:</strong> <em>each</em> player receives €5.</li>
    <li><strong>Solo:</strong> remains €5.</li>
  </ul>
  <h3 class="heading-5 text-w-neutral-1 mb-3">Dates</h3>
  <p class="text-base text-w-neutral-4">The event runs for <strong>1 week</strong> — from
    <time datetime="" data-evt="start">[enter start]</time>
    to
    <time datetime="" data-evt="end">[enter end]</time>.
  </p>
  <h3 class="heading-5 text-w-neutral-1 mb-3">Submission &amp; Review</h3>
  <p class="text-base text-w-neutral-4">Submit one screenshot/clip per run of the end screen (kills, tokens, “Extracted”) or the match ID. <strong>All runs may be reviewed.</strong></p>
  <h3 class="heading-5 text-w-neutral-1 mb-3">Fair play</h3>
  <p class="text-base text-w-neutral-4"><strong>Cheating leads to disqualification.</strong> No cheats, no exploits, no prohibited tools. Admin decisions are final.</p>
  <h3 class="heading-5 text-w-neutral-1 mb-3">Payout &amp; Privacy</h3>
  <p class="text-base text-w-neutral-4">By participating you agree that we may use your <strong>email address</strong> solely to deliver the Amazon voucher code via Amazon for prize fulfillment. <strong>No use for advertising</strong> and <strong>no disclosure to third parties</strong> beyond that.</p>
</section>
HTML;

$st = $pdo->prepare("INSERT INTO tournament_i18n (tournament_id, lang, field, value)
                     VALUES (:id, 'en', 'description', :v)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)");
$st->execute([':id'=>$tid, ':v'=>$en]);

$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

// --- kleiner t()-Helper, falls nicht vorhanden (HTML-escaped) ---------------
if (!function_exists('t')) {
  function t(string $key, string $fallback=''): string {
    $L = $GLOBALS['L'] ?? [];
    return htmlspecialchars((string)($L[$key] ?? $fallback), ENT_QUOTES, 'UTF-8');
  }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); echo 'Tournament ID missing'; exit; }

// -----------------------------------------------------------------------------
// Turnier laden (mit optionalen *_en Feldern, wenn vorhanden)
// -----------------------------------------------------------------------------
$tCols = [];
foreach ($pdo->query('SHOW COLUMNS FROM tournaments', PDO::FETCH_ASSOC) as $row) {
  $tCols[$row['Field']] = true;
}

$selectCols = [
  'id','name','description','status','format','team_size','platform',
  'rules_text','prizes_text','best_runs','starts_at','ends_at','scoring_json','titelbild'
];
// optionale EN-Spalten nur anhängen, wenn sie existieren
if (!empty($tCols['description_en']))   $selectCols[] = 'description_en';
if (!empty($tCols['prizes_text_en']))   $selectCols[] = 'prizes_text_en';
if (!empty($tCols['rules_text_en']))    $selectCols[] = 'rules_text_en';

$sql = 'SELECT '.implode(',', $selectCols).' FROM tournaments WHERE id=? LIMIT 1';
$st = $pdo->prepare($sql);
$st->execute([$id]);
$t = $st->fetch(PDO::FETCH_ASSOC);
if (!$t) { http_response_code(404); echo 'Tournament not found'; exit; }

// i18n-Feldhelfer: $row['<base>_i18n'] mit Fallback auf DE
$mkI18n = function(array &$row, string $base) use ($lang): void {
  $val = (string)($row[$base] ?? '');
  if ($lang === 'en') {
    $kEn = $base.'_en';
    if (array_key_exists($kEn, $row)) {
      $alt = trim((string)$row[$kEn]);
      if ($alt !== '') $val = $alt;
    }
  }
  $row[$base.'_i18n'] = $val;
};

// i18n für relevante Freitexte erzeugen
$mkI18n($t, 'description');
$mkI18n($t, 'prizes_text');
$mkI18n($t, 'rules_text');

// Scoring (nur Anzeige)
$scoring = [];
if (!empty($t['scoring_json'])) {
  $tmp = json_decode((string)$t['scoring_json'], true);
  if (is_array($tmp)) $scoring = $tmp;
}

// ------------------------------------------------------
// Robust: Spalten-Infos anderer Tabellen
// ------------------------------------------------------
$ttCols = [];
foreach ($pdo->query('SHOW COLUMNS FROM tournament_teams', PDO::FETCH_ASSOC) as $row) {
  $ttCols[$row['Field']] = true;
}
$trCols = [];
foreach ($pdo->query('SHOW COLUMNS FROM tournament_runs', PDO::FETCH_ASSOC) as $row) {
  $trCols[$row['Field']] = true;
}

// Sichere ORDER-BY-Spalte für Teams
if (!empty($ttCols['created_at']))      { $orderBy = 'tt.created_at ASC'; }
elseif (!empty($ttCols['id']))          { $orderBy = 'tt.id ASC'; }
elseif (!empty($ttCols['name']))        { $orderBy = 'tt.name ASC'; }
else                                    { $orderBy = '1'; }

// Punkte-/Screenshot-Felder ermitteln
$pointsExpr = !empty($trCols['raw_points'])   ? 'r.raw_points'
            : (!empty($trCols['points_total'])? 'r.points_total'
            : (!empty($trCols['points'])      ? 'r.points'       : 'r.raw_points'));

$shotExpr   = !empty($trCols['screenshot_rel'])  ? 'r.screenshot_rel'
            : (!empty($trCols['screenshot_path'])? 'r.screenshot_path'
            : (!empty($trCols['screenshot'])     ? 'r.screenshot'      : 'NULL'));

// ------------------------------------------------------
// Leaderboard: Best-of-N freigegebene Runs pro Team
// ------------------------------------------------------
$bestN = max(1, (int)($t['best_runs'] ?? 3));

$stL = $pdo->prepare("
  SELECT
    r.id,
    r.tournament_team_id,
    $pointsExpr AS pts,
    $shotExpr   AS shot,
    r.created_at,
    tt.name     AS team_name
  FROM tournament_runs r
  JOIN tournament_teams tt ON tt.id = r.tournament_team_id
  WHERE r.tournament_id = ? AND r.status = 'approved'
  ORDER BY r.tournament_team_id ASC, pts DESC, r.created_at ASC
");
$stL->execute([$id]);
$rows = $stL->fetchAll(PDO::FETCH_ASSOC);

// Gruppieren & Best-of-N summieren
$teams = [];
foreach ($rows as $r) {
  $k = (int)$r['tournament_team_id'];
  if (!isset($teams[$k])) $teams[$k] = ['team_id'=>$k, 'team_name'=>$r['team_name'], 'runs'=>[]];
  $teams[$k]['runs'][] = $r;
}

$board = [];
foreach ($teams as $trow) {
  $best = array_slice($trow['runs'], 0, $bestN);
  $total = 0; $bestSingle = 0; $bestRuns = [];
  foreach ($best as $b) {
    $p = (int)$b['pts'];
    $total += $p;
    if ($p > $bestSingle) $bestSingle = $p;
    $bestRuns[] = ['id'=>(int)$b['id'], 'p'=>$p, 'shot'=>$b['shot'] ?: ''];
  }
  $board[] = [
    'team_id'     => $trow['team_id'],
    'team_name'   => $trow['team_name'],
    'total'       => $total,
    'best_single' => $bestSingle,
    'runs'        => $bestRuns,
    'count'       => count($bestRuns),
  ];
}
$overlay = [];
$stI18n = $pdo->prepare("SELECT field, value FROM tournament_i18n WHERE tournament_id = ? AND lang = ?");
$stI18n->execute([$id, $lang]);
foreach ($stI18n->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $overlay[$row['field']] = $row['value'];
}

// Felder mergen (Overlay gewinnt, Fallback = Original aus tournaments)
foreach (['description','rules_text','prizes_text'] as $fld) {
  if (isset($overlay[$fld]) && $overlay[$fld] !== '') {
    $t[$fld] = $overlay[$fld];
  }
}
// Sortierung: Total desc, beste Einzelrunde desc, Name
usort($board, function($a,$b){
  if ($b['total'] !== $a['total']) return $b['total'] <=> $a['total'];
  if ($b['best_single'] !== $a['best_single']) return $b['best_single'] <=> $a['best_single'];
  return strnatcasecmp($a['team_name'], $b['team_name']);
});

// ------------------------------------------------------
// Teams (mit Captain)
// ------------------------------------------------------
$sqlTeams = "SELECT tt.*, u.display_name AS captain_name
             FROM tournament_teams tt
             JOIN users u ON u.id = tt.captain_user_id
             WHERE tt.tournament_id = ?
             ORDER BY $orderBy";
$stT = $pdo->prepare($sqlTeams);
$stT->execute([$id]);
$teamsList = $stT->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------
// Helpers: Safe HTML + Safe partial include
// ------------------------------------------------------
function safe_html(string $html): string {
  $html = preg_replace('#<script\\b[^>]*>(.*?)</script>#is', '', $html);
  $html = preg_replace('#\\son\\w+\\s*=\\s*([\'\"]).*?\\1#is', '', $html);
  $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><h1><h2><h3><a><code><pre><blockquote>';
  $html = strip_tags($html, $allowed);
  $html = preg_replace_callback('#<a\\s+[^>]*href=([\'\"])(.*?)\\1[^>]*>#i', function($m){
    $href = htmlspecialchars($m[2], ENT_QUOTES);
    return '<a href="'.$href.'" target="_blank" rel="noopener nofollow ugc">';
  }, $html);
  return $html;
}

// --- Pfade sauber auflösen ---------------------------------------------------
$CANDIDATE_DIRS = [
  __DIR__ . '/partials/tournament',
  dirname(__DIR__) . '/partials/tournament',
  __DIR__ . '/partials',
  dirname(__DIR__) . '/partials',
];

$PARTIALS_DIR = null;
foreach ($CANDIDATE_DIRS as $d) {
  if (is_dir($d)) { $PARTIALS_DIR = realpath($d) ?: $d; break; }
}
if (!$PARTIALS_DIR) { $PARTIALS_DIR = __DIR__; }

// --- Mapping für erlaubte Partials ------------------------------------------
$TAB_MAP = [
  'overview'     => 'overview.php',
  'prizes'       => 'prizes.php',
  'participants' => 'participants.php',
  'matches'      => 'matches.php',
  'brackets'     => 'brackets.php',
  'rules'        => 'rules.php',
  'leaderboard'  => 'leaderboard.php',
];

// --- Tabs + Active bestimmen -------------------------------------------------
$tabs = [
  ['key' => 'overview',     'label' => t('tab_overview','Overview')],
  ['key' => 'participants', 'label' => t('tab_leaderboard','Leaderboard')],
  ['key' => 'matches',      'label' => t('tab_winner','Winner')],
];
$active = $_GET['tab'] ?? 'overview';
if (!isset($TAB_MAP[$active])) { $active = 'overview'; }

// --- Sichere Include-Funktion ------------------------------------------------
function render_partial(string $key, array $vars = []): void {
  global $PARTIALS_DIR, $TAB_MAP, $L;
  $fileName = $TAB_MAP[$key] ?? 'overview.php';
  $path = rtrim($PARTIALS_DIR, '/\\') . DIRECTORY_SEPARATOR . basename($fileName);
  if (!is_file($path)) { echo '<div class="mut">Partial nicht gefunden.</div>'; return; }
  // $L in Partials verfügbar machen
  if (!isset($vars['L']) && isset($GLOBALS['L'])) { $vars['L'] = $GLOBALS['L']; }
  extract($vars, EXTR_SKIP);
  include $path;
}
$tzApp = $cfg['app_tz'] ?? 'Europe/Berlin'; // Optional aus Config

function fmt_dt(?string $val, string $fmt = 'd.m.Y H:i', string $tz = 'Europe/Berlin'): string {
  if (!$val) return '–';
  try {
    // Falls die DB-Zeit in UTC gespeichert ist:
    $dt = new DateTime($val, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone($tz));

    // --- Wenn deine DB bereits lokale Zeit ohne TZ speichert, nimm stattdessen:
    // $dt = new DateTime($val, new DateTimeZone($tz));

    return $dt->format($fmt);
  } catch (Throwable) {
    return '–';
  }
}
ob_start();
?>


<main>
  <!-- Tournament header start -->
  <section class="pt-30p">
    <div class="section-pt">
      <div class="bg-[url('../images/photos/tournamentBanner.webp')] bg-cover bg-no-repeat rounded-24 overflow-hidden h-[416px]">
        <div class="container">
          <div class="grid grid-cols-12 gap-30p relative xl:py-[110px] md:py-30 sm:py-25 py-20 z-[2]">
            <div class="lg:col-start-2 lg:col-end-12 col-span-12">
              <h2 class="heading-2 text-w-neutral-1 mb-3">
                <?= htmlspecialchars((string)$t['name']) ?>
              </h2>
              <ul class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="<?= htmlspecialchars($APP_BASE ?: '') ?>/" class="breadcrumb-link">
                    <?= t('home','Home') ?>
                  </a>
                </li>
                <li class="breadcrumb-item">
                  <span class="breadcrumb-icon"><i class="ti ti-chevrons-right"></i></span>
                </li>
                <li class="breadcrumb-item">
                  <span class="breadcrumb-current"><?= htmlspecialchars((string)$t['name']) ?></span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="pb-30p overflow-visible relative grid 4xl:grid-cols-12 grid-cols-1 gap-30p lg:-mt-30 md:-mt-40 sm:-mt-48 -mt-56">
          <div class="4xl:col-start-2 4xl:col-end-12">
            <div class="relative z-10 grid 4xl:grid-cols-11 grid-cols-12 items-center gap-30p bg-b-neutral-3 shadow-4 p-40p rounded-24 xl:divide-x divide-shap/70" data-aos="fade-up" data-aos-duration="2000">
              <div class="3xl:col-span-4 col-span-12">
                <div class="max-xl:flex-col-c max-xl:text-center">
                  <h3 class="heading-3 text-w-neutral-1 mb-20p"><?= htmlspecialchars((string)$t['name']) ?></h3>
                  <div class="flex-y flex-wrap max-xl:justify-center gap-16p">
                    <span class="badge badge-lg badge-primary"><?= htmlspecialchars(fmt_dt($t['starts_at'], 'd.m.Y H:i', $tzApp), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge badge-lg badge-secondary"><?= htmlspecialchars(fmt_dt($t['ends_at'], 'd.m.Y H:i', $tzApp), ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                </div>
              </div>
              <div class="3xl:col-span-4 xl:col-span-7 col-span-12 grid xl:grid-cols-2 grid-cols-1 gap-y-30p xl:divide-x divide-shap/70">
                <div class="flex-col-c text-center">
                  <p class="text-m-medium text-w-neutral-1"><?= t('prize','Prize') ?></p>
                  <span class="text-xl-medium text-center text-secondary"><?= htmlspecialchars((string)$t['prizes_text_i18n']) ?></span>
                </div>
                <div class="flex-col-c text-center">
                  <p class="text-m-medium text-w-neutral-1"><?= t('platform','Platform') ?></p>
                  <span class="text-xl-medium text-center text-primary"><?= htmlspecialchars((string)$t['platform']) ?></span>
                </div>
              </div>
              <div class="3xl:col-span-3 xl:col-span-5 col-span-12">
                <div class="flex xl:justify-end justify-center">
                  <div class="flex-col-c text-center">
                    <?php if (!empty($me)): ?>
                      <a href="/tournaments/play.php?id=<?= (int)$id ?>" class="btn btn-md btn-primary rounded-12 mb-16p"><?= t('btn_join','Join') ?></a>
                    <?php else: ?>
                      <a href="#" class="btn btn-md btn-danger rounded-12 mb-16p"><?= t('btn_login_first',$lang==='en'?'Please log in':'Bitte einloggen') ?></a>
                    <?php endif; ?>
                    <div class="flex items-center gap-3 py-16p">
                      <?php if ($scoring): ?>
                        <div><span class="text-m-medium text-white">1</span><span class="text-xs text-white"><?= t('kills','Kills') ?></span></div>
                        <span class="text-primary icon-24">:</span>
                        <div><span class="text-m-medium text-white">3</span><span class="text-xs text-white"><?= t('boss','Boss') ?></span></div>
                        <span class="text-primary icon-24">:</span>
                        <div><span class="text-m-medium text-white">5</span><span class="text-xs text-white"><?= t('bounty','Bounty') ?></span></div>
                        <span class="text-primary icon-24">:</span>
                        <div><span class="text-m-medium text-white">-5</span><span class="text-xs text-white"><?= t('death','Deaths') ?></span></div>
                      <?php endif; ?>
                    </div>
                    <p class="text-s-medium text-w-neutral-4"><?= t('hint_points','Scoring') ?></p>
                  </div>
                </div>
              </div>
            </div>

            <!-- partial menu begin -->
            <div class="tab-navbar flex items-center flex-wrap gap-x-32p gap-y-24p sm:text-xl text-lg *:font-borda font-medium text-w-neutral-1 whitespace-nowrap pt-30p">
              <?php foreach ($tabs as $tab):
                $isActive = ($tab['key'] === $active);
                $href = htmlspecialchars("?id={$id}&tab={$tab['key']}");
              ?>
                <a href="<?= $href ?>" class="<?= $isActive ? 'active' : 'false' ?>">
                  <?= htmlspecialchars($tab['label']) ?>
                </a>
              <?php endforeach; ?>
            </div>
            <!-- partial menu end -->

          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Tournament header end -->

  <!-- Tournament overview section start -->
  <section class="section-pb relative overflow-visible">
    <div class="container">
      <div class="grid 4xl:grid-cols-12 grid-cols-1 gap-30p ">
        <div class="4xl:col-start-2 4xl:col-end-12">
          <div class="grid grid-cols-10 gap-30p items-start">

            <!-- partial begin -->
            <div class="3xl:col-span-7 xl:col-span-6 col-span-10">
              <?php
                // Übergibt sinnvolle Variablen an die Partials
                // Achtung: In Partials bitte description_i18n/rules_text_i18n verwenden!
                render_partial($active, [
                  't'          => $t,
                  'board'      => $board,
                  'bestN'      => $bestN,
                  'teamsList'  => $teamsList,
                  'APP_BASE'   => $APP_BASE,
                  'scoring'    => $scoring,
                  'safe_html'  => 'safe_html', // falls Partial es nutzen will
                ]);
              ?>
            </div>
            <!-- partial end -->

            <div class="3xl:col-span-3 xl:col-span-4 col-span-10 relative">
              <div class="xl:sticky xl:top-30">
                <div class="grid grid-cols-1 gap-y-30p *:bg-b-neutral-3 *:p-32p *:rounded-12">
                  <div class="flex-y flex-wrap justify-between gap-20p">
                    <div>
                      <span class="text-sm text-w-neutral-4 mb-2"><?= t('organized_by','Organized by') ?></span>
                      <h4 class="heading-4 text-white">HuntHub</h4>
                    </div>

                    <div class="flex items-center gap-3">
                      <a href="#" class="btn-socal-primary"><i class="ti ti-brand-facebook"></i></a>
                      <a href="#" class="btn-socal-primary"><i class="ti ti-brand-twitch"></i></a>
                      <a href="#" class="btn-socal-primary"><i class="ti ti-brand-instagram"></i></a>
                      <a href="#" class="btn-socal-primary"><i class="ti ti-brand-discord"></i></a>
                    </div>
                  </div>

                  <div>
                    <div class="flex-y gap-3 mb-12p">
                      <span class="icon-24 text-primary"><i class="ti ti-bolt-filled"></i></span>
                      <h5 class="heading-5 text-w-neutral-1"><?= t('view_hinweis','Hinweis') ?></h5>
                    </div>
                    <p class="text-s-regular text-w-neutral-3">
                      <?= t('view_stelle_sicher_dass_dein_screenshot_die_post_match_ubersicht_8ea908c5',
                           $lang==='en'
                           ? 'Make sure your screenshot clearly shows the post-match summary with points.'
                           : 'Stelle sicher, dass dein Screenshot die Post-Match-Übersicht mit Punkten eindeutig zeigt.') ?>
                    </p>
                  </div>

                  <div>
                    <div class="flex-y gap-3 mb-12p">
                      <span class="icon-24 text-primary"><i class="ti ti-bolt-filled"></i></span>
                      <h5 class="heading-5 text-w-neutral-1"><?= t('view_regeln','Regeln') ?></h5>
                    </div>
                    <p class="text-s-regular text-w-neutral-3">
                      <?= t('view_betrug_fuhrt_zur_disqualifizierung_uploads_werden_manuell_g_a38801fd',
                           $lang==='en'
                           ? 'Cheating leads to disqualification. Uploads are reviewed manually.'
                           : 'Betrug führt zur Disqualifizierung. Uploads werden manuell geprüft.') ?>
                    </p>
                  </div>

                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Tournament overview section end -->
</main>
<?php
$content = ob_get_clean();

$pageTitle = h($t['name'] ?? 'Unbekanntes Turnier') . " | Hunthub Turnier";
$pageDesc  = ($lang==='en' ? 'Join the tournament “' : 'Jetzt teilnehmen am Turnier „')
           . h($t['name'] ?? 'Unbekannt')
           . ($lang==='en' ? '” – Platform: ' : '“ – Plattform: ') . h($t['platform'] ?? '–')
           . ($lang==='en' ? ', Team size: ' : ', Teamgröße: ') . (int)($t['team_size'] ?? 0);

$pageImage = "https://hunthub.online/assets/images/tournamentBanner.webp";
$schemaJson = hh_schema_event($t, $APP_BASE);
render_theme_page($content, $pageTitle, $pageDesc, $pageImage);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_once __DIR__ . '/../lib/layout.php';

$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$title = 'Forum – Boards';

/** i18n früh booten (damit $L / t() im Content-Aufbau verfügbar ist) */
$lang     = function_exists('detect_lang') ? detect_lang() : 'de';
$fallback = 'de';
if (empty($GLOBALS['L'])) { $GLOBALS['L'] = load_lang($lang); }
$L = $GLOBALS['L'];

/** kleine Helpers */
function has_table(PDO $pdo, string $table): bool {
  try { $list = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN, 0); return in_array($table, $list, true); }
  catch (\Throwable) { return false; }
}

/** Kategorien laden (optional mit category_i18n, falls vorhanden) */
$useCatI18n = has_table($pdo, 'category_i18n');

if ($useCatI18n) {
  $cat_stmt = $pdo->prepare("
    SELECT c.id,
           COALESCE(i.title, f.title) AS name
    FROM categories c
    LEFT JOIN category_i18n i ON i.category_id = c.id AND i.lang = ?
    LEFT JOIN category_i18n f ON f.category_id = c.id AND f.lang = ?
    ORDER BY c.position ASC, c.id ASC
  ");
  $cat_stmt->execute([$lang, $fallback]);
} else {
  $cat_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY position ASC, id ASC");
}
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/** Boards + Übersetzungen mit Fallback laden */
$useBoardI18n = has_table($pdo, 'board_i18n');

if ($useBoardI18n) {
$board_stmt = $pdo->prepare("
  SELECT b.id, b.category_id,
         COALESCE(i.name,        f.name,        b.name)        AS name,
         COALESCE(i.slug,        f.slug,        b.slug)        AS slug,
         COALESCE(i.description, f.description, b.description) AS description,
         b.threads_count, b.posts_count
  FROM boards b
  LEFT JOIN board_i18n i ON i.board_id = b.id AND i.lang = ?
  LEFT JOIN board_i18n f ON f.board_id = b.id AND f.lang = ?
  ORDER BY b.position ASC, b.id ASC
");
$board_stmt->execute([$lang, $fallback]);   // $fallback='de'
} else {
  // Fallback: alte einsprachige Struktur
  $board_stmt = $pdo->query("
    SELECT id, category_id, name, slug, description, threads_count, posts_count
    FROM boards
    ORDER BY position ASC, id ASC
  ");
}

$boards_by_cat = [];
while ($b = $board_stmt->fetch(PDO::FETCH_ASSOC)) {
  $boards_by_cat[(int)$b['category_id']][] = $b;
}

ob_start(); ?>
<main>

  <!-- breadcrumb start -->
  <section class="pt-30p">
    <div class="section-pt">
      <div class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
        <div class="container">
          <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
            <div class="lg:col-start-2 lg:col-end-12 col-span-12">
              <h2 class="heading-2 text-w-neutral-1 mb-3">
                <?= t('forum_overview') ?>
              </h2>
              <ul class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="<?= htmlspecialchars($APP_BASE ?: '/') ?>" class="breadcrumb-link">
                    <?= t('home') ?>
                  </a>
                </li>
                <li class="breadcrumb-item">
                  <span class="breadcrumb-icon"><i class="ti ti-chevrons-right"></i></span>
                </li>
                <li class="breadcrumb-item">
                  <span class="breadcrumb-current"><?= t('forum') ?></span>
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

  <?php foreach ($categories as $category): ?>
    <?php
      $cid = (int)$category['id'];
      if (empty($boards_by_cat[$cid])) continue;
    ?>

    <!-- section start -->
    <section class="section-pb pt-60p">
      <div class="container">
        <div class="flex items-center justify-between flex-wrap gap-24p" style="padding-bottom: 25px;">
          <h2 class="heading-2"><?= htmlspecialchars($category['name']) ?></h2>
        </div>

        <div class="grid 4xl:grid-cols-2 xxl:grid-cols-3 lg:grid-cols-2 grid-cols-1 gap-30p">
          <?php foreach ($boards_by_cat[$cid] as $b): ?>
            <?php
              $bid   = (int)$b['id'];
              $name  = (string)$b['name'];
              $slug  = (string)$b['slug'];
              $desc  = trim((string)($b['description'] ?? ''));
              $threads = (int)$b['threads_count'];
              $posts   = (int)$b['posts_count'];
              $urlBoard = $APP_BASE . '/forum/board.php?b=' . $bid . '&slug=' . rawurlencode($slug);
              $imgPath  = $APP_BASE . '/assets/images/forum/' . $bid . '.webp';
            ?>
            <div class="w-full bg-b-neutral-3 grid 4xl:grid-cols-2 grid-cols-1 items-center rounded-24 overflow-hidden group" data-aos="zoom-in">
              <div class="overflow-hidden">
                <img class="w-full 4xl:h-[370px] 3xl:h-[340px] xl:h-[320px] sm:h-[280px] h-[240px] object-cover object-top group-hover:scale-110 transition-1"
                     src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($name) ?>"
                     onerror="this.onerror=null;this.src='<?= htmlspecialchars($APP_BASE) ?>/assets/images/forum/default.webp';" />
              </div>
              <div class="px-30p py-16p">
                <a href="<?= htmlspecialchars($urlBoard) ?>"
                   class="heading-3 text-w-neutral-1 link-1 line-clamp-1 mb-16p text-split-bottom">
                  <?= htmlspecialchars($name) ?>
                </a>

                <!-- Status-Badge (optional): wenn du ein Feld is_locked hast -->
                <?php if (isset($b['is_locked']) && (int)$b['is_locked'] === 0): ?>
                  <span class="badge badge-neutral-2 group-hover:badge-secondary mb-24p">
                    <i class="ti ti-world icon-24"></i> <?= t('board_open') ?>
                  </span>
                <?php elseif (isset($b['is_locked']) && (int)$b['is_locked'] === 1): ?>
                  <span class="badge badge-neutral-2 group-hover:badge-secondary mb-24p">
                    <i class="ti ti-lock icon-24"></i> <?= t('board_closed') ?>
                  </span>
                <?php endif; ?>

                <div class="flex-y flex-wrap gap-x-60p gap-y-24p mb-20p">
                  <div>
                    <span class="text-lead-medium text-w-neutral-1 mb-1"><?= $threads ?></span>
                    <span class="text-base text-w-neutral-3"><?= t('threads') ?></span>
                  </div>
                  <div>
                    <span class="text-lead-medium text-w-neutral-1 mb-1"><?= $posts ?></span>
                    <span class="text-base text-w-neutral-3"><?= t('posts') ?></span>
                  </div>
                </div>

                <!-- Beschreibung: einzeilig, Klick öffnet Modal -->
                <?php if ($desc !== ''): ?>
                  <div class="flex-y gap-2 pt-20p mb-24p border-t border-w-neutral-4/20">
                    <i class="ti ti-alert-circle icon-20 text-w-neutral-3"></i>
                    <button type="button"
                            class="text-left text-base text-w-neutral-3 cursor-pointer js-desc-trunc"
                            data-full="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>">
                      <span class="line-clamp-1"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                  </div>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($urlBoard) ?>" class="btn btn-sm flex btn-neutral-2 group-hover:btn-primary">
                  <?= t('read_more') ?>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <!-- section end -->
  <?php endforeach; ?>
</main>

<!-- Einmaliges, sprachfähiges Modal für Beschreibungen -->
<script>
(function(){
  function ensureModal(){
    let m = document.getElementById('hh-desc-modal');
    if (m) return m;
    m = document.createElement('div');
    m.id = 'hh-desc-modal';
    m.className = 'fixed inset-0 hidden z-[99999]';
    m.innerHTML =
      '<div class="absolute inset-0 bg-black/60" data-close="1"></div>' +
      '<div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 '+ 
      'bg-b-neutral-3 text-white rounded-12 shadow-xl max-w-[560px] w-[92vw] p-20p border border-w-neutral-4/20">' +
      '  <div class="flex items-center justify-between mb-10p">' +
      '    <strong class="text-w-neutral-1"><?= t('description') ?></strong>' +
      '    <button class="icon-24 text-w-neutral-3 hover:text-white" data-close="1" aria-label="<?= t('close') ?>">' +
      '      <i class="ti ti-x"></i>' +
      '    </button>' +
      '  </div>' +
      '  <div id="hh-desc-body" class="text-base text-w-neutral-3 whitespace-pre-line"></div>' +
      '</div>';
    document.body.appendChild(m);
    return m;
  }
  const modal = ensureModal();
  const body  = modal.querySelector('#hh-desc-body');

  function open(text){ body.textContent = text || ''; modal.classList.remove('hidden'); document.body.style.overflow='hidden'; }
  function close(){ modal.classList.add('hidden'); document.body.style.overflow=''; body.textContent=''; }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-desc-trunc');
    if (btn) { open(btn.dataset.full || btn.textContent.trim()); return; }
    if (e.target.closest('#hh-desc-modal [data-close]')) close();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
})();
</script>
<?php
$content = ob_get_clean();
render_theme_page($content, $title);

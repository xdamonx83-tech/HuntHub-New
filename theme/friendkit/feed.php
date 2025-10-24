<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/layout.php';

/**
 * lib/wall.php ruft standardmäßig require_auth() auf,
 * außer die laufende Datei heißt "wallguest.php".
 * Für öffentliche Permalinks wollen wir optional_auth().
 * Workaround wie du ihn schon nutzt:
 */
$__orig = $_SERVER['SCRIPT_FILENAME'] ?? '';
$_SERVER['SCRIPT_FILENAME'] = 'wallguest.php';
require_once __DIR__ . '/../../lib/wall.php';
$_SERVER['SCRIPT_FILENAME'] = $__orig;

$me = optional_auth();

// Hole Posts mit deiner vorhandenen Funktion (Passe ggf. Parameter an)
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
[$posts, $total] = wall_list_posts($pdo, $page, $limit); // <- falls dein Helper anders heißt, hier anpassen

ob_start(); ?>
<div id="app" class="is-app-grey" style="padding-top:64px">
  <div class="container">
    <!-- Composer oben (deiner, unverändert) -->
    <?php include __DIR__ . '/../partials/wall_composer.php'; ?>

    <!-- Friendkit Feed Wrapper -->
    <div class="columns">
      <div class="column is-8 is-offset-2">
        <?php foreach ($posts as $post): ?>
          <div class="card is-rounded mb-4">
            <div class="card-content">
              <?php include __DIR__ . '/../partials/post_item.php'; // dein vorhandenes Post-Template ?>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Pagination simpel -->
        <nav class="pagination is-centered" role="navigation" aria-label="pagination">
          <?php if ($page > 1): ?>
            <a class="pagination-previous" href="?page=<?= $page-1 ?>&fk=1">Zurück</a>
          <?php endif; ?>
          <?php if ($page * $limit < $total): ?>
            <a class="pagination-next" href="?page=<?= $page+1 ?>&fk=1">Weiter</a>
          <?php endif; ?>
        </nav>
      </div>
    </div>
  </div>
</div>
<?php
$CONTENT = ob_get_clean();

$USE_FRIENDKIT = true;
require __DIR__ . '/../page-template.php';

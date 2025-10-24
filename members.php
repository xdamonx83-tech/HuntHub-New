<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/auth/config.php';
require_once __DIR__ . '/lib/layout.php';




$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

// User laden – hier sortieren wir mal nach XP (= aktivste zuerst)
$sql = "SELECT id, slug, username, avatar, xp, level, created_at
        FROM users
        ORDER BY xp DESC, created_at DESC
        LIMIT 100";
$users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<main class="section-py">
  <div class="container">
    <h1 class="heading-2 text-w-neutral-1 mb-6">Mitglieder</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-24p">
      <?php foreach ($users as $u): ?>
        <div class="bg-b-neutral-3 rounded-12 p-24p flex items-center gap-3">
          <img class="avatar size-60p rounded-full object-cover"
               src="<?= $u['avatar'] ? esc($u['avatar']) : '/assets/images/avatar-default.png' ?>"
               alt="<?= esc($u['username']) ?>" />
          <div class="flex-1">
            <a href="<?= esc($APP_BASE) ?>/user.php?u=<?= esc($u['slug'] ?: $u['id']) ?>"
               class="heading-5 text-w-neutral-1 link-1 mb-1 block">
               <?= esc($u['username'] ?: 'User #'.$u['id']) ?>
            </a>
            <p class="text-sm text-w-neutral-4">
              Level <?= (int)$u['level'] ?> • XP <?= (int)$u['xp'] ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<?php
$html = ob_get_clean();
render_theme_page($html, 'Mitglieder');

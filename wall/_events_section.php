<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/layout.php'; // falls esc() genutzt wird

$pdo = db();

// Aktive Turniere holen
$stmt = $pdo->query("
    SELECT id, title, slug, cover_url, start_at, end_at
    FROM tournaments
    WHERE deleted_at IS NULL
      AND start_at <= NOW()
      AND end_at >= NOW()
    ORDER BY start_at ASC
    LIMIT 5
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="py-24p px-32p bg-b-neutral-3 rounded-12 mb-30p">
  <div class="flex flex-wrap justify-between items-center gap-20p mb-24p">
    <div class="flex-y gap-3">
      <span class="badge badge-circle size-3 badge-primary"></span>
      <h4 class="heading-4 text-w-neutral-1">
        Aktive Events
      </h4>
    </div>
    <a href="<?= esc($APP_BASE) ?>/tournaments/index.php"
       class="text-s-medium text-w-neutral-1 link-1">
      View All
      <i class="ti ti-arrow-right"></i>
    </a>
  </div>

  <div class="grid grid-cols-1 gap-24p">
    <?php if (empty($events)): ?>
      <p class="text-w-neutral-4">Zurzeit keine aktiven Events</p>
    <?php else: ?>
      <?php foreach ($events as $ev): 
        $cover = $ev['cover_url'] ?: '/assets/images/event-placeholder.png';
        $title = htmlspecialchars($ev['title']);
        $slug  = urlencode($ev['slug']);
        $start = date('d.m.Y', strtotime($ev['start_at']));
        $end   = date('d.m.Y', strtotime($ev['end_at']));
      ?>
      <div class="flex items-center gap-x-20p">
        <img class="w-16 h-16 rounded-12 object-cover"
             src="<?= esc($cover) ?>"
             alt="<?= $title ?>">
        <div>
          <a href="<?= esc($APP_BASE) ?>/tournaments/view.php?id=<?= (int)$ev['id'] ?>"
             class="heading-5 text-w-neutral-1 line-clamp-2 link-1 mb-1">
            <?= $title ?>
          </a>
          <p class="text-sm text-w-neutral-4">
            <?= $start ?> – <?= $end ?>
          </p>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
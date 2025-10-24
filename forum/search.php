<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/layout.php';

$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$avatarFallback = $APP_BASE . '/assets/images/avatars/placeholder.png';

$q = trim($_GET['q'] ?? '');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$title = 'Suchergebnisse für "' . htmlspecialchars($q) . '"';
$results = [];
$total = 0;

if (strlen($q) >= 3) {
    $searchTerm = '%' . $q . '%';

    // Zuerst die Gesamtzahl der Treffer für die Paginierung ermitteln
    $count_sql = "
        SELECT COUNT(DISTINCT t.id)
        FROM threads t
        WHERE t.deleted_at IS NULL AND (
            t.title LIKE :q1
            OR EXISTS (
                SELECT 1 FROM posts p
                WHERE p.thread_id = t.id AND p.deleted_at IS NULL AND p.content LIKE :q2
            )
        )";
    $count_st = $pdo->prepare($count_sql);
    $count_st->execute([':q1' => $searchTerm, ':q2' => $searchTerm]);
    $total = (int)$count_st->fetchColumn();

    // Dann die Threads für die aktuelle Seite holen
    $sql = "
        SELECT
            t.id, t.title, t.slug, t.last_post_at, t.posts_count,
            b.name AS board_name, b.id AS board_id,
            ua.display_name AS author_name, ua.avatar_path AS author_avatar, ua.id AS author_id
        FROM threads t
        JOIN users ua ON ua.id = t.author_id
        JOIN boards b ON b.id = t.board_id
        WHERE t.deleted_at IS NULL AND (
            t.title LIKE :q1
            OR EXISTS (
                SELECT 1 FROM posts p
                WHERE p.thread_id = t.id AND p.deleted_at IS NULL AND p.content LIKE :q2
            )
        )
        ORDER BY t.last_post_at DESC
        LIMIT :limit OFFSET :offset";
    
    $st = $pdo->prepare($sql);
    $st->bindValue(':q1', $searchTerm);
    $st->bindValue(':q2', $searchTerm);
    $st->bindValue(':limit', $limit, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();
    $results = $st->fetchAll(PDO::FETCH_ASSOC);
}

ob_start();
?>

<main>
    <section class="pt-30p">
        <div class="section-pt">
            <div class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
                <div class="container">
                    <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
                        <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                            <h2 class="heading-2 text-w-neutral-1 mb-3">
                                Suche
                            </h2>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/" class="breadcrumb-link">Home</a></li>
                                <li class="breadcrumb-item"><span class="breadcrumb-icon"><i class="ti ti-chevrons-right"></i></span></li>
                                <li class="breadcrumb-item"><a href="/forum/boards.php" class="breadcrumb-link">Forum</a></li>
                                <li class="breadcrumb-item"><span class="breadcrumb-icon"><i class="ti ti-chevrons-right"></i></span></li>
                                <li class="breadcrumb-item"><span class="breadcrumb-current">Suchergebnisse</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="overlay-11"></div>
            </div>
        </div>
    </section>

    <section class="section-pb pt-60p">
        <div class="container">
            <h3 class="heading-3 mb-4">
                <?php if ($total > 0): ?>
                    <?= $total ?> Ergebnisse für "<?= htmlspecialchars($q) ?>"
                <?php else: ?>
                    Keine Ergebnisse für "<?= htmlspecialchars($q) ?>"
                <?php endif; ?>
            </h3>

            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($results as $t):
                    $threadUrl = $APP_BASE . '/forum/thread.php?t=' . (int)$t['id']
                                 . (!empty($t['slug']) ? '&slug=' . urlencode($t['slug']) : '');
                    $authorAvatar = $t['author_avatar'] ?: $avatarFallback;
                ?>
                <div class="bg-b-neutral-3 p-4 rounded-lg flex items-center gap-4 hover:bg-b-neutral-2 transition-colors">
                    <img src="<?= htmlspecialchars($authorAvatar) ?>" class="avatar size-48p" alt="<?= htmlspecialchars($t['author_name']) ?>">
                    <div class="flex-grow">
                        <a href="<?= $threadUrl ?>" class="text-lg font-semibold text-w-neutral-1 link-1"><?= htmlspecialchars($t['title']) ?></a>
                        <div class="text-sm text-w-neutral-3">
                            von <?= htmlspecialchars($t['author_name']) ?> in <a href="/forum/board.php?b=<?= (int)$t['board_id'] ?>" class="link-1"><?= htmlspecialchars($t['board_name']) ?></a>
                            <span class="mx-2">·</span>
                            <span><?= (int)$t['posts_count'] ?> Beiträge</span>
                        </div>
                    </div>
                    <div class="text-right text-sm text-w-neutral-4">
                        Letzte Aktivität:<br>
                        <?= !empty($t['last_post_at']) ? date('d.m.Y H:i', strtotime($t['last_post_at'])) : '' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total > $limit): ?>
                <nav class="flex justify-center items-center gap-4 mt-8">
                    <?php $totalPages = ceil($total / $limit); ?>
                    <?php if ($page > 1): ?>
                        <a href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-outline-secondary">&laquo; Zurück</a>
                    <?php endif; ?>

                    <span class="text-w-neutral-2">Seite <?= $page ?> von <?= $totalPages ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-outline-secondary">Weiter &raquo;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        </div>
    </section>
</main>

<?php
$content = ob_get_clean();
render_theme_page($content, $title);
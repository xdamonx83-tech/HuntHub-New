<?php
/** Verfügbare Variablen (von user.php gesetzt):
 * $__user    array   – Profil des Users
 * $__posts   array   – Liste der Beiträge/Threads
 * $__errTab  ?string – Fehlermeldung nur für diesen Tab (optional)
 */
?>
<?php if (!empty($__errTab)): ?>
  <div class="note-error"><?= htmlspecialchars($__errTab) ?></div>
<?php endif; ?>

<?php if (!empty($userThreads)): ?>
    <?php foreach ($userThreads as $t): ?>
    <div data-aos="fade-up">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <img class="avatar size-60p" src="<?= htmlspecialchars($avatar) ?>" alt="user" />
                <div>
                    <a href="<?= $APP_BASE ?>/user.php?id=<?= (int)$user['id'] ?>&slug=<?= urlencode((string)$user['slug']) ?>"
                       class="text-xl-medium text-w-neutral-1 link-1 line-clamp-1 mb-1">
                        <?= htmlspecialchars($user['display_name']) ?>
                    </a>
                    <span class="text-s-medium text-w-neutral-4">
                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$t['created_at']))) ?>
                    </span>
                </div>
            </div>

            <div x-data="dropdown" class="dropdown">
                <button @click="toggle()" class="dropdown-toggle w-fit text-white icon-32">
                    <i class="ti ti-dots"></i>
                </button>
                <div x-show="isOpen" @click.away="close()" class="dropdown-content">
                    <a href="<?= $APP_BASE ?>/forum/thread.php?t=<?= (int)$t['id'] ?>&slug=<?= urlencode((string)($t['slug'] ?? '')) ?>" class="dropdown-item">
                        <?php echo $L['open']; ?>
                    </a>
         
                </div>
            </div>
        </div>

        <div class="py-20p">
            <div class="overflow-hidden">
                <a class="heading-5 text-w-neutral-1 link-1"
                   href="<?= $APP_BASE ?>/forum/thread.php?t=<?= (int)$t['id'] ?>&slug=<?= urlencode((string)($t['slug'] ?? '')) ?>">
                    <?= htmlspecialchars($t['title'] ?: 'Ohne Titel') ?>
                </a>

                <?php $preview = thread_preview_from_html($t['first_content'] ?? ''); ?>
                <?php if ($preview !== ''): ?>
                    <div class="text-w-neutral-3 mt-2">
                        <?= htmlspecialchars($preview) ?>
                    </div>
                <?php endif; ?>

                <div class="text-w-neutral-4 text-sm mt-2">
                    <?php echo $L['posts']; ?>: <?= (int)$t['posts_count'] ?> · <?php echo $L['likes']; ?>: <?= (int)$t['likes_count'] ?>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between flex-wrap gap-24p mb-20p">
            <div class="flex items-center gap-32p">
                <a href="<?= $APP_BASE ?>/forum/thread.php?t=<?= (int)$t['id'] ?>&slug=<?= urlencode((string)($t['slug'] ?? '')) ?>"
                   class="flex items-center gap-2 text-base text-w-neutral-1">
                    <i class="ti ti-message icon-24 text-w-neutral-4"></i>
                    <?php echo $L['go_to_thread']; ?>
                </a>
            </div>
            <button type="button" class="flex items-center gap-2 text-base text-w-neutral-1">
                <i class="ti ti-share-3 icon-24 text-w-neutral-4"></i>
                <?php echo $L['share']; ?>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div data-aos="fade-up">
        <div class="py-20p">
            <div class="text-w-neutral-4"><?php echo $L['no_threads_found']; ?></div>
        </div>
    </div>
<?php endif; ?>

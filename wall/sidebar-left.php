<?php
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/wall.php'; // für wall_user_select_sql() + wall_avatar_url()

$pdo = db();
$userSelect = wall_user_select_sql($pdo);

$stmt = $pdo->query("
    SELECT t.id, t.title, t.created_at, $userSelect
    FROM threads t
    JOIN users u ON u.id = t.author_id
    WHERE t.deleted_at IS NULL
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recentTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($recentTopics as $topic):
    $avatar = wall_avatar_url($topic['avatar'] ?? '');
    $title  = htmlspecialchars($topic['title'] ?? 'Untitled');
    $user   = htmlspecialchars($topic['username'] ?? 'Unknown');
    $slug   = urlencode($topic['slug'] ?? '');
?>
               <div
                                            class="flex 3xl:flex-nowrap xl:flex-wrap flex-nowrap items-center gap-x-28p gap-y-3">
                                            <img class="shrink-0 avatar size-60p" src="<?= esc($avatar) ?>"
                                                alt="user" />
                                            <div>
                                                <a href="<?= esc($APP_BASE) ?>/forum/thread.php?t=<?= (int)$topic['id'] ?>" class="heading-5 text-w-neutral-1 line-clamp-2 link-1 mb-1">
                                                    <?= $title ?>
                                                </a>
                                                <div class="flex-y gap-2">
                                                    <p class="text-sm text-w-neutral-4">
                                                        By <a href="<?= esc($APP_BASE) ?>/user.php?u=<?= $slug ?>"
                                                            class="text-w-neutral-1 underline link-1 span"><?= $user ?></a>
                                                    </p>
                                          
                                                </div>
                                            </div>
                                        </div>

<?php endforeach; ?>


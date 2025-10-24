<?php
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/wall.php'; // für wall_user_select_sql + wall_avatar_url

$pdo = db();
$userSelect = wall_user_select_sql($pdo);

// Zählt Aktivität = threads + forum_posts + wall_posts + wall_comments
$sql = "
    SELECT u.id, $userSelect,
           (
             (SELECT COUNT(*) FROM threads t WHERE t.author_id = u.id AND t.deleted_at IS NULL)
             +
             (SELECT COUNT(*) FROM posts p WHERE p.author_id = u.id AND p.deleted_at IS NULL)
             +
             (SELECT COUNT(*) FROM wall_posts wp WHERE wp.user_id = u.id AND wp.deleted_at IS NULL)
             +
             (SELECT COUNT(*) FROM wall_comments wc WHERE wc.user_id = u.id AND wc.deleted_at IS NULL)
           ) AS activity
    FROM users u
    ORDER BY activity DESC
    LIMIT 5
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Render
foreach ($rows as $user):
    $avatar = wall_avatar_url($user['avatar'] ?? '');
    $name   = htmlspecialchars($user['username'] ?? 'Unknown');
    $slug   = urlencode($user['slug'] ?? '');
?>

      <div class="swiper-slide">
                                                    <div>
                                                        <a href="<?= esc($APP_BASE) ?>/user.php?u=<?= $slug ?>" class="avatar relative size-60p mb-3">
                                                            <img class="size-60p rounded-full"
                                                                src="<?= esc($avatar) ?>" alt="avatar" />
                                                            <span class="status-badge online">
                                                                <i class="ti ti-circle-check-filled"></i>
                                                            </span>
                                                        </a>
                                                        <span class="text-m-regular text-w-neutral-1 text-center">
                                                            <?= $name ?>
                                                        </span>
                                                    </div>
                                                </div>
<?php endforeach; ?>




                                            
											
											
											
											
											
											
											
											
											
											
											
											
					
                           
<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/layout.php';
$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);

// Minimaler t()-Helper, falls noch nicht vorhanden
if (!function_exists('t')) {
    function t(string $key, array $vars = []): string {
        $L = $GLOBALS['L'] ?? [];
        $s = $L[$key] ?? $key;
        if ($vars) {
            $repl = [];
            foreach ($vars as $k => $v) $repl['{' . $k . '}'] = (string)$v;
            return strtr($s, $repl);
        }
        return $s;
    }
}

$pdo = db();

$me = current_user();
session_set_cookie_params([
  'path'     => '/',      // wichtig
  'httponly' => true,
  'samesite' => 'Lax',    // oder 'Strict' je nach Bedarf
  'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
$st = $pdo->query(
  "
  SELECT
    p.id,
    p.thread_id,
    p.content,
    p.created_at,
    u.display_name AS author_name,
    u.avatar_path,
    t.title        AS thread_title,
    t.posts_count  AS comments_count,   -- alle Posts im Thread
    p.likes_count  AS likes_count       -- Likes für den Post selbst
  FROM posts p
  JOIN users u   ON u.id = p.author_id
  JOIN threads t ON t.id = p.thread_id
  WHERE p.deleted_at IS NULL
  ORDER BY p.created_at DESC
  LIMIT 5
  "
);
$latestPosts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
session_start();

ob_start();
?>
    
    <meta property="og:description" content="<?= htmlspecialchars(t('home.og_desc')) ?>">
<main>

            <!-- breadcrumb start -->
            <section class="pt-30p">
                <div class="section-pt">
                    <div
                        class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
                        <div class="container">
                            <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
                                <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                                    <h2 class="heading-2 text-w-neutral-1 mb-3">
                                       <?= htmlspecialchars(t('home.hero_title')) ?>
                                    </h2>
                      
                                </div>
                            </div>
                        </div>
                        <div class="overlay-11"></div>
                    </div>
                </div>
            </section>

        <?php if ($latestPosts): ?>
            <section class="section-pb pt-60p ">
                <div class="container">
                    <div class="grid 3xl:grid-cols-4 xl:grid-cols-3 sm:grid-cols-2 grid-cols-1 gap-30p">
                        <?php foreach ($latestPosts as $p): ?>
                        <div class="bg-b-neutral-3 py-24p px-30p rounded-12 group" data-aos="zoom-in">
                            <div class="overflow-hidden rounded-12">
                                <img class="w-full h-[202px] object-cover group-hover:scale-110 transition-1"
                                    src="<?= htmlspecialchars($p['avatar_path'] ?: '/assets/images/avatars/placeholder.png') ?>" alt="<?= htmlspecialchars(t('home.post_image_alt')) ?>" />
                            </div>
                            <div class="flex-y justify-between flex-wrap gap-20px py-3">
                                <div class="flex-y gap-3">
                                    <div class="flex-y gap-1">
                                        <i class="ti ti-heart icon-20 text-danger"></i>
                                        <span class="text-sm text-w-neutral-1">
                                            <?= (int)$p['likes_count'] ?> <?= t('likes') ?>
                                        </span>
                                    </div>
                                    <div class="flex-y gap-1">
                                        <i class="ti ti-message icon-20 text-primary"></i>
                                        <span class="text-sm text-w-neutral-1">
                                            <?= (int)$p['comments_count'] ?> <?= t('comments') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-y flex-wrap gap-3 mb-1">
                                <span class="text-m-medium text-w-neutral-1">
                                  <?= htmlspecialchars($p['author_name']) ?>
                                </span>
                                <p class="text-sm text-w-neutral-2">
                                    <?= date('d.m.Y H:i', strtotime($p['created_at'])) ?>
                                </p>
                            </div>
                            <a href="<?= $APP_BASE ?>/forum/thread.php?t=<?= (int)$p['thread_id'] ?>#comment-<?= (int)$p['id'] ?>"
                                class="heading-5 text-w-neutral-1 leading-[130%] line-clamp-2 link-1">
                                 <?= htmlspecialchars($p['thread_title']) ?>
                                 <p class="text-s-medium text-w-neutral-3"><?= htmlspecialchars(mb_strimwidth(strip_tags($p['content']), 0, 140, '…', 'UTF-8')) ?></p>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex-c mt-48p">
                        <a href="#" class="btn btn-xl py-3 btn-primary rounded-12">
                            <?= htmlspecialchars(t('common.load_more')) ?>
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php // /partials/tournament_leaderboard_widget.php
        $tid = (int)($tid ?? 0);
        if ($tid>0): ?>
        <div id="lb-widget-<?= $tid ?>">
        <script>fetch('/api/tournaments/leaderboard.php?tournament_id=<?= $tid ?>').then(r=>r.json()).then(j=>{ if(j.ok){ const n = document.getElementById('lb-widget-<?= $tid ?>'); n.innerHTML = '<ol>'+ (j.items||[]).slice(0,5).map(it=>`<li><b>${it.total_points}</b> — ${it.team_name}</li>`).join('') +'</ol>'; } });</script>
        </div>
        <?php endif; ?>

            <!-- library section start -->
            <section class="section-pb pt-60p">
                <div class="container">
                    <div class="grid xxl:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-30p">
                        <!-- card 1 -->
                        <div class="library-card group" data-aos="fade-up">
                            <div class="flex flex-col justify-between h-full relative z-[2]">
                                <div class="flex-y justify-between flex-wrap gap-16p"></div>
                                <div>
                                    <a href="https://htda.de"
                                        class="heading-4 text-w-neutral-1 line-clamp-1 mb-1 link-1">
                                        HTDA.de
                                    </a>
                                    <div class="flex-y gap-3 text-l-regular text-w-neutral-2">
                                        <span><?= htmlspecialchars(t('home.card_htda_desc')) ?></span>
                                    </div>
                                </div>
                            </div>
                            <img class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-110 transition-1"
                                src="assets/images/library/library1.png" alt="<?= htmlspecialchars(t('home.library_image_alt')) ?>" />
                        </div>
                        <!-- card 2 -->
                        <div class="library-card group" data-aos="fade-up">
                            <div class="flex flex-col justify-between h-full relative z-[2]">
                                <div class="flex-y justify-between flex-wrap gap-16p"></div>
                                <div>
                                    <a href="https://huntmaps.online"
                                        class="heading-4 text-w-neutral-1 line-clamp-1 mb-1 link-1">
                                        HuntMaps.online
                                    </a>
                                    <div class="flex-y gap-3 text-l-regular text-w-neutral-2">
                                        <span><?= htmlspecialchars(t('home.card_maps_desc')) ?></span>
                                    </div>
                                </div>
                            </div>
                            <img class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-110 transition-1"
                                src="assets/images/library/library2.png" alt="<?= htmlspecialchars(t('home.library_image_alt')) ?>" />
                        </div>
                        <!-- card 3 -->
                        <div class="library-card group" data-aos="fade-up">
                            <div class="flex flex-col justify-between h-full relative z-[2]">
                                <div class="flex-y justify-between flex-wrap gap-16p"></div>
                                <div>
                                    <a href="/"
                                        class="heading-4 text-w-neutral-1 line-clamp-1 mb-1 link-1">
                                        HuntHub.online
                                    </a>
                                    <div class="flex-y gap-3 text-l-regular text-w-neutral-2">
                                        <span><?= htmlspecialchars(t('home.card_hunthub_desc')) ?></span>
                                    </div>
                                </div>
                            </div>
                            <img class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-110 transition-1"
                                src="assets/images/library/library3.png" alt="<?= htmlspecialchars(t('home.library_image_alt')) ?>" />
                        </div>
                    </div>
                </div>
            </section>
            <!-- library section end -->

        </main>
    <?php

$content = ob_get_clean();

render_theme_page($content, $me ? t('site.title_short') : t('site.title_full'));
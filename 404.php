<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/layout.php';
$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$pdo = db();
$me = current_user();
session_set_cookie_params([
  'path'     => '/',      // wichtig
  'httponly' => true,
  'samesite' => 'Lax',    // oder 'Strict' je nach Bedarf
  'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
$st = $pdo->query("
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
");
$latestPosts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
session_start();

ob_start();

    ?>

        <!-- main start -->
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
                                        Error 404
                                    </h2>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="#" class="breadcrumb-link">
                                                Home
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-current">Error 404</span>
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

            <!-- not found 404 section start -->
            <section class="section-py">
                <div class="container">
                    <div class="flex-col-c text-center">
                        <h1
                            class="lg:text-[160px] md:text-[140px] sm:text-[120px] text-7xl font-borda text-w-neutral-1 mb-3">
                            404
                        </h1>
                        <h1 class="heading-1 text-w-neutral-1 mb-24p">
                            Looks Like You are Lost
                        </h1>
                        <p class="text-l-medium text-w-neutral-4 mb-40p">
                            We can’t seem to find the page you’re looking for.
                        </p>
                        <a href="/" class="btn btn-xl py-3 btn-primary rounded-12">
                            BACK TO HOME
                        </a>
                    </div>
                </div>
            </section>
            <!-- not found 404 section end -->

        </main>
        <!-- main end -->

    <?php

$content = ob_get_clean();

render_theme_page($content, $me ? 'HuntHub.online' : '404');
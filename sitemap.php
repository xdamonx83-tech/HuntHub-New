<?php
declare(strict_types=1);

// /sitemap.php  — dynamische Sitemap (Index + Teil-Sitemaps)

require_once __DIR__ . '/auth/db.php';

$cfg = require __DIR__ . '/auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/'); // im Root: ''
$pdo = db();

header('Content-Type: application/xml; charset=UTF-8');

// -------------------------------------------------------------
// Helper
// -------------------------------------------------------------
function abs_base(): string {
    // baue absolute Basis-URL (Scheme + Host + evtl. Port) + APP_BASE
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    global $APP_BASE;
    return $scheme . '://' . $host . $APP_BASE;
}
function esc(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function w3c_date(?string $ts): string {
    if (!$ts) return gmdate('c');
    $t = strtotime($ts);
    if ($t <= 0) return gmdate('c');
    return gmdate('c', $t);
}

// Limits: Google erlaubt 50.000 URLs / 50 MB pro Datei (uncompressed).
// Wir bleiben konservativ:
const THREADS_PER_PAGE = 5000;

// Routen-Parameter
$t = $_GET['t'] ?? '';       // '', 'static', 'boards', 'threads'
$p = max(1, (int)($_GET['p'] ?? 1));

// -------------------------------------------------------------
// 0) Sitemap-Index
// -------------------------------------------------------------
if ($t === '') {
    // Zähle Boards
    $boardCount = 0;
    try {
        $boardCount = (int)$pdo->query("SELECT COUNT(*) FROM boards")->fetchColumn();
    } catch (Throwable) {}

    // Zähle Threads
    $threadCount = 0;
    try {
        $threadCount = (int)$pdo->query("SELECT COUNT(*) FROM threads")->fetchColumn();
    } catch (Throwable) {}

    $threadPages = max(1, (int)ceil($threadCount / THREADS_PER_PAGE));
    $base = abs_base();

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><?= t('sitemap_n_static_echo_5ca0a8ae') ?>  <sitemap><?= t('sitemap_n_echo_2b2190da') ?>    <loc><?= t('sitemap_esc_base_sitemap_static_xml_57499d2f') ?></loc><?= t('sitemap_n_echo_2b2190da') ?>    <lastmod><?= t('sitemap_esc_gmdate_c_8e7ec28a') ?></lastmod><?= t('sitemap_n_echo_2b2190da') ?>  </sitemap><?= t('sitemap_n_boards_if_boardcount_0_144f6bb1') ?>  <sitemap><?= t('sitemap_n_echo_c5a8f808') ?>    <loc><?= t('sitemap_esc_base_sitemap_boards_1_xml_00993904') ?></loc><?= t('sitemap_n_echo_c5a8f808') ?>    <lastmod><?= t('sitemap_esc_gmdate_c_8e7ec28a') ?></lastmod><?= t('sitemap_n_echo_c5a8f808') ?>  </sitemap><?= t('sitemap_n_threads_paginiert_for_i_8d8de65e') ?> <= $threadPages; $i++) {
        echo '  <sitemap><?= t('sitemap_n_echo_c5a8f808') ?>    <loc><?= t('sitemap_esc_base_sitemap_threads_i_xml_800d7aa9') ?></loc><?= t('sitemap_n_echo_c5a8f808') ?>    <lastmod><?= t('sitemap_esc_gmdate_c_8e7ec28a') ?></lastmod><?= t('sitemap_n_echo_c5a8f808') ?>  </sitemap><?= t('sitemap_n_echo_628886ab') ?></sitemapindex><?= t('sitemap_exit_8b5d093c') ?><?xml version="1.0" encoding="UTF-8"?><?= t('sitemap_n_echo_2b2190da') ?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><?= t('sitemap_n_foreach_static_as_loc_freq_prio_las_0ef53a6d') ?>  <url><?= t('sitemap_n_echo_57982b36') ?>    <loc><?= t('sitemap_esc_loc_0ba531f1') ?></loc><?= t('sitemap_n_echo_57982b36') ?>    <lastmod><?= t('sitemap_esc_last_2882c998') ?></lastmod><?= t('sitemap_n_echo_57982b36') ?>    <changefreq><?= t('sitemap_esc_freq_5d2f931e') ?></changefreq><?= t('sitemap_n_echo_57982b36') ?>    <priority><?= t('sitemap_esc_prio_81576b3a') ?></priority><?= t('sitemap_n_echo_fcac21ea') ?>  </url><?= t('sitemap_n_echo_e429e0cb') ?></urlset><?= t('sitemap_exit_e19dae82') ?><?xml version="1.0" encoding="UTF-8"?><?= t('sitemap_n_echo_2b2190da') ?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><?= t('sitemap_n_foreach_rows_as_r_loc_ba_495aefc0') ?>  <url><?= t('sitemap_n_echo_57982b36') ?>    <loc><?= t('sitemap_esc_loc_0ba531f1') ?></loc><?= t('sitemap_n_echo_57982b36') ?>    <lastmod><?= t('sitemap_esc_last_2882c998') ?></lastmod><?= t('sitemap_n_echo_57982b36') ?>    <changefreq><?= t('sitemap_hourly_7d2919c9') ?></changefreq><?= t('sitemap_n_echo_c5a8f808') ?>    <priority><?= t('sitemap_0_8_395eff9b') ?></priority><?= t('sitemap_n_echo_951c2668') ?>  </url><?= t('sitemap_n_echo_91c8301c') ?></urlset><?= t('sitemap_exit_963d2482') ?><?xml version="1.0" encoding="UTF-8"?><?= t('sitemap_n_echo_2b2190da') ?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><?= t('sitemap_n_foreach_rows_as_r_loc_b_31e65dd6') ?>  <url><?= t('sitemap_n_echo_57982b36') ?>    <loc><?= t('sitemap_esc_loc_0ba531f1') ?></loc><?= t('sitemap_n_echo_57982b36') ?>    <lastmod><?= t('sitemap_esc_last_2882c998') ?></lastmod><?= t('sitemap_n_echo_57982b36') ?>    <changefreq><?= t('sitemap_hourly_7d2919c9') ?></changefreq><?= t('sitemap_n_echo_c5a8f808') ?>    <priority><?= t('sitemap_0_6_fe717b44') ?></priority><?= t('sitemap_n_echo_951c2668') ?>  </url><?= t('sitemap_n_echo_91c8301c') ?></urlset>';
    exit;
}

// Fallback: Index
header('Location: ' . (abs_base() . '/sitemap.xml'), true, 302);

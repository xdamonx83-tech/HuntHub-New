<?php
declare(strict_types=1);

/**
 * /lib/layout.php
 * Bootstrap, CSRF, i18n, Theme-Rendering und kompatible start_layout()/end_layout()-Wrapper.
 */

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/csrf.php';

$pdo = $pdo ?? db();
$cfg = require __DIR__ . '/../auth/config.php';

$APP_BASE     = rtrim($cfg['app_base'] ?? '', '/');          // Root => ''
$sessionName  = $cfg['cookies']['session_name'] ?? '';
$csrf         = issue_csrf($pdo, $_COOKIE[$sessionName] ?? ''); // stellt Meta-Token etc. bereit

// -------------------------------------------------------------
// Logger früh aktivieren (Errors/Exceptions ins eigene Log schreiben)
// -------------------------------------------------------------
require_once __DIR__ . '/../lib/logger.php';

hh_setup_error_handlers(function () {
    if (function_exists('current_user')) {
        $u = current_user();
        if (!empty($u['id'])) return $u;
    }
    if (function_exists('optional_auth')) {
        $u = optional_auth();
        if (!empty($u['id'])) return $u;
    }
    return [];
});

// Testeintrag (nur zum Prüfen, danach wieder rausnehmen)
hh_log('info', 'logger test from layout', ['uri'=>$_SERVER['REQUEST_URI'] ?? '']);


// -------------------------------------------------------------
// i18n – erlaubte Sprachen & Defaults
// -------------------------------------------------------------
const LANGS_ALLOWED = ['de','en'];
const LANG_DEFAULT  = 'de';

/**
 * Ermittelt aktuelle Sprache aus ?lang=…, Cookie oder Default.
 * Setzt bei ?lang=… zusätzlich ein Cookie (1 Jahr).
 */
function detect_lang(): string {
    $get    = isset($_GET['lang'])    ? strtolower(trim((string)$_GET['lang']))    : null;
    $cookie = isset($_COOKIE['lang']) ? strtolower(trim((string)$_COOKIE['lang'])) : null;

    $lang = $get ?: $cookie ?: LANG_DEFAULT;
    if (!in_array($lang, LANGS_ALLOWED, true)) {
        $lang = LANG_DEFAULT;
    }

    if ($get) {
        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $sameSite = 'Lax';
        setcookie('lang', $lang, [
            'expires'  => time() + 60*60*24*365,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
        // für diesen Request direkt verfügbar machen
        $_COOKIE['lang'] = $lang;
    }

    return $lang;
}

/**
 * Lädt Sprachdatei als Array $L. Fallback auf Default, falls Datei fehlt.
 */
function load_lang(string $lang): array {
    $path = __DIR__ . "/../lang/{$lang}.php";
    if (is_file($path)) {
        /** @var array $L */
        $L = require $path;
        if (is_array($L)) {
            return $L;
        }
    }
    $fallback = __DIR__ . "/../lang/" . LANG_DEFAULT . ".php";
    return is_file($fallback) ? (require $fallback) : [];
}

/**
 * Übersetzer: t('key', ...$args) → vsprintf-Unterstützung: "Hi %s"
 */
if (!function_exists('t')) {
    function t(string $key, mixed ...$args): string {
        /** @var array $L */
        $L = $GLOBALS['L'] ?? [];
        $val = $L[$key] ?? $key;
        if ($args) {
            try { $val = @vsprintf($val, $args) ?: $val; } catch (\Throwable) {}
        }
        return $val;
    }
}

/**
 * Baut die aktuelle URL mit geänderter Sprache (?lang=en/de) und
 * behält andere Query-Parameter bei.
 */
function build_lang_url(string $toLang): string {
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $u    = parse_url($uri);
    $path = $u['path'] ?? '/';
    parse_str($u['query'] ?? '', $qs);

    unset($qs['lang']);
    $qs['lang'] = $toLang;

    $query = http_build_query($qs);
    return $path . ($query ? ('?' . $query) : '');
}

/**
 * Generiert HTML-Buttons für den Sprachwechsel.
 * Nutze im Template: <?= $LANG_SWITCH_HTML ?>
 */
function render_lang_switch_html(string $current): string {
    $urlDe = build_lang_url('de');
    $urlEn = build_lang_url('en');

    $currDe = ($current === 'de') ? ' aria-current="true"' : '';
    $currEn = ($current === 'en') ? ' aria-current="true"' : '';

    // Tailwind/Utility-Klassen nur beispielhaft – gerne anpassen
    return
      '<div class="lang-switch flex items-center gap-2">' .
        '<a href="' . htmlspecialchars($urlDe) . '" class="px-2 py-1 rounded border text-sm hover:opacity-80"' . $currDe . '>🇩🇪 Deutsch</a>' .
        '<a href="' . htmlspecialchars($urlEn) . '" class="px-2 py-1 rounded border text-sm hover:opacity-80"' . $currEn . '>🇬🇧 English</a>' .
      '</div>';
}

/**
 * Optional: hreflang-Tags für SEO in <head> injizieren.
 * Nutze im Template: <?= $HREFLANG_LINKS ?>
 */
function build_hreflang_links(): string {
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $u    = parse_url($uri);
    $path = $u['path'] ?? '/';
    parse_str($u['query'] ?? '', $qs);
    unset($qs['lang']);

    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $links = [];
    foreach (LANGS_ALLOWED as $code) {
        $qs2 = $qs;
        $qs2['lang'] = $code;
        $url = $proto . '://' . $host . $path . (count($qs2) ? ('?' . http_build_query($qs2)) : '');
        $links[] = '<link rel="alternate" hreflang="' . htmlspecialchars($code) . '" href="' . htmlspecialchars($url) . '">';
    }
    // x-default
    $qsDefault = $qs;
    $qsDefault['lang'] = LANG_DEFAULT;
    $urlDefault = $proto . '://' . $host . $path . (count($qsDefault) ? ('?' . http_build_query($qsDefault)) : '');
    $links[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($urlDefault) . '">';

    return implode("\n", $links);
}

// -------------------------------------------------------------
// Theme-Rendering – neuer Helfer für "einfachen" Seiten-Output
// -------------------------------------------------------------

/**
 * Rendert eine Seite im Theme mit i18n-Unterstützung.
 *
 * @param string $contentHtml HTML-Inhalt für den Content-Slot.
 * @param string $title       <title>-Text.
 */
/**
 * Rendert eine Seite im Theme mit i18n- und SEO-Unterstützung.
 *
 * @param string $contentHtml HTML-Inhalt für den Content-Slot
 * @param string $title       <title>-Text
 * @param string $desc        Meta-Description
 * @param string $image       URL zu einem OG/Twitter-Bild (optional)
 */
function render_theme_page(
    string $contentHtml,
    string $title = 'Hunthub',
    string $desc = 'Hunthub – Community Forum für Hunt: Showdown mit Events, Chat und Gamification.',
    string $image = '',
    ?string $schemaJson = null
): void {
    $templatePath = __DIR__ . '/../theme/page-template.php';
    if (!is_file($templatePath)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Template fehlt: $templatePath";
        return;
    }

    // i18n vorbereiten
    $LANG               = detect_lang();                 
    $GLOBALS['L']       = load_lang($LANG);              
    $L                  = $GLOBALS['L'];                 
    $LANG_SWITCH_HTML   = render_lang_switch_html($LANG);
    $HREFLANG_LINKS     = build_hreflang_links();

    // Variablen fürs Template
    $TITLE        = $title;
    $DESC         = $desc;
    $IMAGE        = $image ?: ($GLOBALS['APP_BASE'] . '/assets/images/og-default.jpg');
    $CONTENT      = $contentHtml;
    $SCHEMA_JSON  = $schemaJson; // <<< NEU

    $APP_BASE = $GLOBALS['APP_BASE'] ?? '';
    $csrf     = $GLOBALS['csrf']     ?? '';

    ob_start();
    include $templatePath;
    $html = (string)ob_get_clean();

    // Fallbacks
    if (strpos($html, '[CONTENT]') !== false) {
        $html = preg_replace('/\[CONTENT\]/', $contentHtml, $html, 1);
    }
    if (preg_match('#<title>.*?</title>#i', $html)) {
        $html = preg_replace(
            '#<title>.*?</title>#i',
            '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>',
            $html,
            1
        );
    } else {
        $html = str_replace('{{TITLE}}', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), $html);
    }

    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}


if (!function_exists('first_post_image')) {
    /**
     * Findet das erste <img> im Content und gibt den absoluten URL zurück.
     *
     * @param string $html  Post-Inhalt (HTML)
     * @param string $fallback Fallback-URL (z. B. Default-OG-Bild)
     * @return string
     */
    function first_post_image(string $html, string $fallback = ''): string {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            $src = $m[1];
            // Falls relativer Pfad → absolut machen
            if (!preg_match('#^https?://#i', $src)) {
                $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                      . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                if ($src[0] !== '/') $src = '/' . $src;
                $src = $base . $src;
            }
            return $src;
        }
        return $fallback;
    }
}
if (!function_exists('h')) {
    function h(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('hh_schema_event')) {
    /**
     * Turnier-Event (Schema.org Event)
     * @param array $t  Turnier-Row: id,name,description,starts_at,ends_at,platform,titelbild
     * @param string $base Absolute Basis-URL (APP_BASE, z.B. https://hunthub.online)
     */
    function hh_schema_event(array $t, string $base): string {
        $url = rtrim($base, '/') . '/tournaments/view.php?id=' . (int)($t['id'] ?? 0);
        $img = '';
        if (!empty($t['titelbild'])) {
            $img = (preg_match('#^https?://#i', $t['titelbild'])) ? $t['titelbild'] : rtrim($base, '/') . $t['titelbild'];
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => (string)($t['name'] ?? 'Tournament'),
            'startDate' => (string)($t['starts_at'] ?? ''),
            'endDate' => (string)($t['ends_at'] ?? ''),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
            'location' => [
                '@type' => 'VirtualLocation',
                'url' => $url,
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'Hunthub Online',
                'url'  => rtrim($base, '/'),
            ],
            'description' => strip_tags((string)($t['description'] ?? '')),
            'url' => $url,
        ];
        if ($img) { $data['image'] = [$img]; }
        if (!empty($t['platform'])) {
            $data['about'] = ['@type' => 'Thing', 'name' => (string)$t['platform']];
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('hh_schema_forum_thread')) {
    /**
     * Forum-Thread (Schema.org DiscussionForumPosting)
     * @param array $thread  z.B. ['id','title','slug','created_at','updated_at']
     * @param array $op      erster Post: ['author_name','content','image'] optional
     * @param string $base   APP_BASE, z.B. https://hunthub.online
     */
    function hh_schema_forum_thread(array $thread, array $op, string $base): string {
        $url = rtrim($base, '/') . '/forum/thread.php?t=' . (int)($thread['id'] ?? 0);
        if (!empty($thread['slug'])) {
            $url .= '&slug=' . rawurlencode((string)$thread['slug']);
        }
        $img = '';
        if (!empty($op['image'])) {
            $img = (preg_match('#^https?://#i', $op['image'])) ? $op['image'] : rtrim($base, '/') . $op['image'];
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => (string)($thread['title'] ?? 'Thread'),
            'articleBody' => trim(strip_tags((string)($op['content'] ?? ''))),
            'datePublished' => (string)($thread['created_at'] ?? ''),
            'dateModified'  => (string)($thread['updated_at'] ?? ''),
            'author' => [
                '@type' => 'Person',
                'name'  => (string)($op['author_name'] ?? 'User')
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => 'Hunthub Online',
                'url'   => rtrim($base, '/'),
            ],
            'url' => $url,
            'mainEntityOfPage' => $url,
        ];
        if ($img) { $data['image'] = [$img]; }
        return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('hh_schema_collection')) {
    /**
     * Board-Übersicht (Schema.org CollectionPage)
     * @param string $name   Boardname
     * @param string $base   APP_BASE
     * @param string $path   z.B. '/forum/board.php?b=123'
     * @param string $desc   Kurzbeschreibung
     */
    function hh_schema_collection(string $name, string $base, string $path, string $desc=''): string {
        $url = rtrim($base, '/') . $path;
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => (string)$desc,
            'url' => $url,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name'  => 'Hunthub Online',
                'url'   => rtrim($base, '/'),
            ],
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}

// -------------------------------------------------------------
// Abwärtskompatible Wrapper: start_layout()/end_layout()
// -------------------------------------------------------------

/**
 * Viele deiner bestehenden Seiten rufen start_layout($title) … end_layout().
 * Diese Wrapper binden weiterhin header/footer ein und initialisieren i18n.
 */
function start_layout(string $title = 'Hunthub'): void {
    // i18n global bereitstellen
    $LANG         = detect_lang();
    $GLOBALS['L'] = load_lang($LANG);

    $APP_BASE = $GLOBALS['APP_BASE'] ?? '';
    $csrf     = $GLOBALS['csrf']     ?? '';
    $L        = $GLOBALS['L']        ?? [];

    // Optional für Templates:
    $LANG_SWITCH_HTML = render_lang_switch_html($LANG);
    $HREFLANG_LINKS   = build_hreflang_links();

    // Header einbinden (falls vorhanden)
    $header = __DIR__ . '/../theme/header.php';
    if (is_file($header)) {
        // Variablen für header.php verfügbar machen:
        $TITLE = $title;
        include $header;
    } else {
        // Minimaler Fallback, falls header.php fehlt
        echo "<!doctype html>\n<html lang=\"".htmlspecialchars($LANG)."\">\n<head>\n";
        echo "  <meta charset=\"utf-8\">\n";
        echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
        echo "  <title>".htmlspecialchars($title)."</title>\n";
        echo $HREFLANG_LINKS . "\n";
        echo "</head>\n<body>\n";
    }

    // Markiere als gestartet (für end_layout)
    $GLOBALS['__layout_started'] = true;
}

/**
 * Schließt die Layout-Ausgabe mit theme/footer.php (oder Fallback) ab.
 */
function end_layout(): void {
    $footer = __DIR__ . '/../theme/footer.php';
    if (is_file($footer)) {
        include $footer;
    } else {
        echo "\n</body>\n</html>";
    }
    unset($GLOBALS['__layout_started']);
}

// -------------------------------------------------------------
// Kleine Helfer (optional)
// -------------------------------------------------------------

if (!function_exists('e')) {
    /** HTML-escape */
    function e(?string $s): string {
        return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
// -------------------------------------------------------------
// SEO Helper
// -------------------------------------------------------------
if (!function_exists('seo_meta')) {
    /**
     * Erzeugt Title-, Description-, OG- und Twitter-Meta-Tags.
     *
     * @param string $title   Seitentitel
     * @param string $desc    Seitenbeschreibung
     * @param string $image   Absolute URL zu einem Bild (optional)
     * @return string HTML für <head>
     */
    function seo_meta(string $title, string $desc, string $image = ''): string {
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $url  = $base . ($_SERVER['REQUEST_URI'] ?? '/');

        $html = [];
        $html[] = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        $html[] = '<meta name="description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<link rel="canonical" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';

        // OpenGraph
        $html[] = '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<meta property="og:description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<meta property="og:url" content="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
        if ($image) {
            $html[] = '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">';
        }

        // Twitter
        $html[] = '<meta name="twitter:card" content="summary_large_image">';
        $html[] = '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<meta name="twitter:description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">';
        if ($image) {
            $html[] = '<meta name="twitter:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">';
        }

        return implode("\n", $html);
    }
}

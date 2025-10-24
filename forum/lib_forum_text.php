<?php
// Sicheres HTML-Rendering (Whitelist + einfache Attribut-Filter)
function forum_render_html(?string $html): string {
    $html = (string)$html;
    if ($html === '') return '';

    // Entitiertes HTML zurückwandeln (&lt;p&gt;...)
    if (strpos($html, '&lt;') !== false && strpos($html, '<') === false) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // --- Whitelist (WICHTIG: <source> erlauben!) ---
    $allowed = '<p><br><strong><b><em><i><u><blockquote><ul><ol><li>'
             . '<span><code><pre><hr><table><tr><td><th><thead><tbody>'
             . '<img><a><h1><h2><h3><figure><figcaption><video><source>';

    // 1) Grob: unerlaubte Tags weg
    $clean = strip_tags($html, $allowed);

    // 2) Offensichtliche Eventhandler / javascript:-URLs entfernen
    $clean = preg_replace('/\s+on[a-z-]+\s*=\s*"[^"]*"/i', '', $clean);
    $clean = preg_replace("/\s+on[a-z-]+\s*=\s*'[^']*'/i", '', $clean);
    $clean = preg_replace('/(href|src)\s*=\s*"(javascript:[^"]*)"/i', '$1="#"', $clean);
    $clean = preg_replace("/(href|src)\s*=\s*'(javascript:[^']*)'/i", '$1=\"#\"', $clean);

    // 3) TinyMCE-Vorschau-Hüllen entfernen (mce-preview-object / mce-object-video / mce-shim)
    // 3.1 data-mce-* Attribute global löschen
    $clean = preg_replace('/\sdata-mce-[a-z0-9_-]+="[^"]*"/i', '', $clean);
    $clean = preg_replace("/\sdata-mce-[a-z0-9_-]+='[^']*'/i", '', $clean);
    // 3.2 mce-shim ganz entfernen
    $clean = preg_replace('~<span[^>]*class="[^"]*mce-shim[^"]*"[^>]*>\s*</span>~i', '', $clean);
    // 3.3 Wrapper entpacken: behalte den Inhalt (inkl. <video>) und wirf den <span> weg
    $clean = preg_replace('~<span[^>]*class="[^"]*(?:mce-preview-object|mce-object-video)[^"]*"[^>]*>(.*?)</span>~is', '$1', $clean);

    // 4) Minimal härten: <video> immer mit controls/preload, <source> mit type
    //    (nur, wenn das Element existiert – reine String-Variante, bewusst simpel)
    $clean = preg_replace('~<video(?![^>]*\bcontrols\b)~i', '<video controls', $clean);
    $clean = preg_replace('~<video([^>]*)\bcontrols\b(?![^>]*\bpreload\b)~i', '<video$1 controls preload="metadata"', $clean);
    $clean = preg_replace('~<source([^>]*)\btype=~i', '<source$1 type=', $clean); // idempotent
    $clean = preg_replace('~<source((?:(?!type=).)*)/?>~is', '<source$1 type="video/mp4">', $clean);

    return $clean;
}

/**
 * Hauptfunktion, die im Template verwendet wird.
 * - Erkennt automatisch HTML/entitiertes HTML und rendert es (-> forum_render_html)
 * - Andernfalls: Plaintext sicher escapen + Zeilenumbrüche beibehalten
 */
function forum_render_text(?string $text): string {
    $text = (string)$text;
    if ($text === '') return '';

    // HTML vorhanden? (echte Tags oder &lt;…&gt;)
    $looksLikeHtml = (strpos($text, '<') !== false && preg_match('/<\/?[a-z][\s>]/i', $text))
                  || (strpos($text, '&lt;') !== false && strpos($text, '&gt;') !== false);

    if ($looksLikeHtml) {
        return forum_render_html($text);
    }

    // sonst: Plaintext -> HTML mit <br>
    return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../lib/layout.php';

/**
 * lib/wall.php ruft standardmäßig require_auth() auf,
 * außer die laufende Datei heißt "wallguest.php".
 * Für öffentliche Permalinks wollen wir optional_auth().
 * Kleiner, lokaler Workaround: Dateiname kurz spoof’en.
 */
$__orig = $_SERVER['SCRIPT_FILENAME'] ?? '';
$_SERVER['SCRIPT_FILENAME'] = 'wallguest.php';
require_once __DIR__ . '/../lib/wall.php';
$_SERVER['SCRIPT_FILENAME'] = $__orig;

// Gäste dürfen lesen
$me = optional_auth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    render_theme_page(
        '<div class="p-6"><h1 class="text-xl font-semibold mb-2">Beitrag nicht gefunden</h1><p>Ungültige ID.</p></div>',
        'Beitrag nicht gefunden'
    );
    exit;
}

$db   = db();
$post = wall_get_post($db, $id);
if (!$post) {
    http_response_code(404);
    render_theme_page(
        '<div class="p-6"><h1 class="text-xl font-semibold mb-2">Beitrag nicht gefunden</h1><p>Dieser Beitrag existiert nicht mehr.</p></div>',
        'Beitrag nicht gefunden'
    );
    exit;
}

// Titel/OG vorbereiten
$uname   = (string)($post['username'] ?? 'Mitglied');
$plain   = trim((string)($post['content_plain'] ?? ''));
$title   = mb_substr($plain !== '' ? $plain : "Beitrag von $uname", 0, 80);
$slug    = (string)($post['slug'] ?? '');
$base    = function_exists('app_base') ? rtrim((string)app_base(), '/') : '';
$canonical = $base . '/p/' . $id . ($slug !== '' ? '-' . rawurlencode($slug) : '');

// OG-Bild (erstes <img> im HTML, falls vorhanden)
$image = '';
if (!empty($post['content_html'])) {
    if (preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', (string)$post['content_html'], $m)) {
        $image = $m[1];
        if ($image && $image[0] === '/') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $image  = $scheme.'://'.$host.$image;
        }
    }
}

ob_start();
?>
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
<div class="container mx-auto max-w-3xl px-4 py-6"
     data-wall-single="1"
     data-post-id="<?= (int)$id ?>">
  <?= wall_render_post($post) ?>

  <!-- Inline-Kommentare -->
  <section class="mt-6" id="hh-comments-inline">
    <h2 class="text-base font-semibold mb-3">Kommentare</h2>

    <!-- Composer (Top-Level) -->


    <!-- Liste -->
    <div class="hh-comments-list space-y-3" data-comments-for="<?= (int)$id ?>"></div>
  </section>
</div>

<!-- benötigte JS: Likes/Reactions/Media -->
<script src="/assets/js/wall.likes.js"></script>
<script src="/assets/js/wall.reactions.js"></script>
<script src="/assets/js/wall.media-viewer.js"></script>
<script src="/assets/js/wall.comments.js"></script>

<!-- Inline-Kommentare (neu) -->
<script src="/assets/js/wall.single-inline.js"></script>

<!-- Fallback-Styles: Reaction-Picker standardmäßig verstecken -->
<style>
  .hh-reaction-picker, .reaction-picker, .like-reactions, .hh-like-reactions { display: none; }
  .hh-like:hover .hh-reaction-picker,
  .like-bar:hover .reaction-picker,
  .hh-like-reactions.open, .reaction-picker.open { display: flex; }
  .hh-reaction-picker img, .reaction-picker img { width: 44px; height: 44px; object-fit: contain; }
</style>




<link rel="stylesheet" href="/assets/styles/wall.css">
<script>
(function () {
  const root = document.querySelector('[data-wall-single="1"]');
  if (!root) return;

  const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const isCommentAction = (url) => /\/api\/wall\/comment_(create|update|delete)\.php/i.test(url || '');

  // Alle Kommentar-Forms (Top + Replies) per Event-Delegation abfangen
  root.addEventListener('submit', function (ev) {
    const form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;
    const action = form.getAttribute('action') || '';
    if (!isCommentAction(action)) return;

    ev.preventDefault();

    const fd = new FormData(form);
    fetch(action, {
      method: 'POST',
      body: fd,
      headers: csrf ? {'X-CSRF-Token': csrf} : {}
    })
    .then(r => r.json())
    .then(j => {
      if (j && j.ok) {
        // einfach & robust: Seite neu zeichnen (keine JSON-Schnipsel im DOM)
        window.location.reload();
      } else {
        alert((j && (j.error || j.msg)) || 'Fehler beim Senden.');
      }
    })
    .catch(() => alert('Netzwerkfehler.'));
  });
})();
</script>
<style>
  /* Picker standardmäßig ausblenden */
  .hh-reaction-picker,
  .reaction-picker,
  .like-reactions,
  .hh-like-reactions { display: none; }

  /* Anzeige erst bei Hover oder explizit .open */
  .hh-like:hover .hh-reaction-picker,
  .like-bar:hover .reaction-picker,
  .hh-like-reactions.open,
  .reaction-picker.open { display: flex; }

  /* keine Riesen-Emojis */
  .hh-reaction-picker img,
  .reaction-picker img { width: 44px; height: 44px; object-fit: contain; }
</style>


<!-- Fallback: Reaction-Picker standardmäßig verstecken (falls Styles nicht gebündelt sind) -->
<style>
  /* versteckt den Reaction-Picker bis Hover/JS-Open */
  .hh-reaction-picker,
  .reaction-picker,
  .like-reactions,
  .hh-like-reactions { display: none; }

  /* gängige Strukturen: Picker als Kind der Like-Zeile */
  .hh-like:hover .hh-reaction-picker,
  .like-bar:hover .reaction-picker,
  .hh-like-reactions.open,
  .reaction-picker.open { display: flex; }

  /* sicherstellen, dass die Dinger nicht fullscreen wirken */
  .hh-reaction-picker img,
  .reaction-picker img { width: 44px; height: 44px; object-fit: contain; }
</style>

<?php
$content = (string)ob_get_clean();

render_theme_page(
    $content,
    $title,
    $plain !== '' ? $plain : 'Wall-Beitrag',
    $image
);

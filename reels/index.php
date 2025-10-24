<?php
declare(strict_types=1);

/**
 * Reels-Übersicht – sucht robust in mehreren möglichen Verzeichnissen und
 * akzeptiert *.mp4, *.m4v, *.webm. Poster: gleichnamig .jpg/.webp optional.
 */

$doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__.'/..', '/');

/* Mögliche Speicherorte – der erste existierende wird genommen. */
$candidates = [
  '/files/reels',
  '/uploads/reels',
  '/uploads/videos/reels',
  '/files/uploads/reels',
  '/storage/reels',
  '/public/reels',
  '/reels',                 // falls unterhalb des DocRoots
];

$dir = '';
foreach ($candidates as $rel) {
  $p = $doc.$rel;
  if (is_dir($p)) { $dir = $p; break; }
}
/* Fallback: Standardpfad anlegen */
if (!$dir) {
  $dir = $doc.'/files/reels';
  @mkdir($dir, 0775, true);
}

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');

/* Alle Videos einsammeln */
$files = [];
foreach (['mp4','m4v','webm'] as $ext) {
  $files = array_merge($files, glob($dir.'/*.'.$ext) ?: []);
}

/* In Karten umwandeln */
$items = [];
foreach ($files as $abs) {
  $fn  = basename($abs);
  $url = $baseUrl . str_replace($doc, '', $abs);
  $url = preg_replace('~//+~','/',$url); // doppelte Slashes vermeiden

  /* Poster: gleichnamige .jpg oder .webp falls vorhanden */
  $poster = '';
  foreach (['jpg','webp'] as $pex) {
    $pp = preg_replace('~\.(mp4|m4v|webm)$~i', '.'.$pex, $abs);
    if (is_file($pp)) {
      $poster = $baseUrl . str_replace($doc, '', $pp);
      $poster = preg_replace('~//+~','/',$poster);
      break;
    }
  }

  $items[] = [
    'file'   => $fn,
    'video'  => $url,
    'poster' => $poster,
    'mtime'  => @filemtime($abs) ?: 0,
  ];
}
function hh_list_reels(string $root, string $doc): array {
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
  );
  $out = [];
  foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $ext = strtolower($f->getExtension());
    if (!in_array($ext, ['mp4','m4v','webm'], true)) continue;

    $abs = $f->getPathname();
    $rel = ltrim(str_replace($doc, '', $abs), '/');        // z.B. uploads/reels/2025/09/21/clip.mp4

    // öffentlicher URL zum Video
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
    $videoUrl = $baseUrl . '/' . $rel;

    // optionales Poster (gleichnamig .jpg oder .webp)
    $poster = '';
    foreach (['jpg','webp'] as $pex) {
      $pp = preg_replace('~\.(mp4|m4v|webm)$~i', '.'.$pex, $abs);
      if (is_file($pp)) {
        $poster = $baseUrl . '/' . ltrim(str_replace($doc, '', $pp), '/');
        break;
      }
    }

    $out[] = [
      'rel'    => $rel,           // wird für view.php verwendet
      'video'  => $videoUrl,
      'poster' => $poster,
      'mtime'  => @filemtime($abs) ?: 0,
    ];
  }
  usort($out, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
  return $out;
}

$items = hh_list_reels($dir, $doc);
usort($items, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
?>
<!doctype html>
<html lang="de">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reels</title>
<style>
  body{background:#0f0f11;color:#eee;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;margin:0}
  .wrap{max-width:1050px;margin:24px auto;padding:0 16px}
  h1{margin:0 0 16px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
  .card{position:relative;border:1px solid #2a2a2d;border-radius:12px;overflow:hidden;background:#151518}
  .card video{width:100%;height:320px;object-fit:cover;background:#000;display:block}
  .badge{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);
         color:#fff;padding:4px 8px;border-radius:999px;font-size:12px}
  a{color:inherit;text-decoration:none}
  .empty{opacity:.8}
  .path{font-size:12px;opacity:.6;margin-bottom:8px}
</style>
<div class="wrap">
  <h1>Reels</h1>
  <div class="path">Quelle: <code><?= htmlspecialchars($dir) ?></code></div>

  <?php if (!$items): ?>
    <p class="empty">Noch keine Reels.</p>
  <?php else: ?>
    <div class="grid">
<?php foreach ($items as $it): ?>
  <a class="card" href="/reels/view.php?f=<?= rawurlencode($it['rel']) ?>">
    <video muted playsinline loop preload="metadata" poster="<?= htmlspecialchars($it['poster']) ?>">
      <source src="<?= htmlspecialchars($it['video']) ?>" type="video/mp4">
    </video>
    <div class="badge">Reel</div>
  </a>
<?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</html>

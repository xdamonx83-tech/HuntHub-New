<?php
declare(strict_types=1);
$doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__.'/..','/');

$candidates = ['/files/reels','/uploads/reels','/uploads/videos/reels','/files/uploads/reels','/storage/reels','/public/reels','/reels'];
$dir=''; foreach ($candidates as $rel){ $p=$doc.$rel; if(is_dir($p)){ $dir=$p; break; } }
if (!$dir) { http_response_code(404); exit('Reels-Verzeichnis nicht gefunden'); }

$f = $_GET['f'] ?? '';
// Erlaube a–z, A–Z, 0–9, Unterordner (/), Punkt und Minus/Unterstrich; kein ..!
if (!preg_match('~^[a-zA-Z0-9/_\.-]+\.(mp4|m4v|webm)$~', $f) || str_contains($f, '..')) {
  http_response_code(404);
  exit('Not found');
}
$abs = $doc . '/' . ltrim($f, '/');
if (!is_file($abs)) { http_response_code(404); exit('Not found'); }

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https':'http';
$base   = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');

$video  = $base . '/' . ltrim($f,'/');  // öffentlicher URL
// Poster optional:
$poster = '';
foreach (['jpg','webp'] as $pex) {
  $pp = preg_replace('~\.(mp4|m4v|webm)$~i', '.'.$pex, $abs);
  if (is_file($pp)) { $poster = $base . '/' . ltrim(str_replace($doc,'',$pp), '/'); break; }
}
?>
<!doctype html>
<html lang="de">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reel ansehen</title>
<style>
  body{background:#0f0f11;color:#eee;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial}
  .wrap{max-width:900px;margin:24px auto;padding:0 16px}
  .player{border:1px solid #2a2a2d;border-radius:12px;overflow:hidden;background:#000}
  video{width:100%;max-height:78vh;display:block;background:#000}
  a{color:#9ecbff}
</style>
<div class="wrap">
  <p><a href="/reels/">← Zur Übersicht</a></p>
  <div class="player">
    <video controls playsinline autoplay poster="<?= htmlspecialchars($poster) ?>">
      <source src="<?= htmlspecialchars($video) ?>" type="video/mp4">
    </video>
  </div>
</div>
</html>

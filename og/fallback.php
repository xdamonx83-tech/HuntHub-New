<?php
/**
 * File: /og/fallback.php
 * Purpose: Generate a dynamic Open‑Graph preview image (1200x630)
 * Usage:  /og/fallback.php?title=My%20Page&subtitle=HuntHub&bg=11131a
 */

declare(strict_types=1);
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$W = 1200; $H = 630; // OG size
$title    = trim((string)($_GET['title'] ?? '')) ?: 'HuntHub';
$subtitle = trim((string)($_GET['subtitle'] ?? '')) ?: '';
$bghex    = preg_replace('~[^0-9a-f]~i', '', (string)($_GET['bg'] ?? '11131a'));
$bghex    = str_pad(substr($bghex, 0, 6), 6, '0');

// Helpers --------------------------------------------------------------------
function hex2rgb(string $hex): array {
  return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}
function ttf_try_paths(): array {
  return [
    __DIR__.'/../assets/fonts/Inter-Bold.ttf',
    __DIR__.'/../assets/fonts/Inter/Inter-Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
  ];
}
function ttf_reg_try_paths(): array {
  return [
    __DIR__.'/../assets/fonts/Inter-Regular.ttf',
    __DIR__.'/../assets/fonts/Inter/Inter-Regular.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
  ];
}
function wrap_text(string $text, string $font, int $size, int $maxWidth): array {
  // returns array of lines that fit $maxWidth
  $lines = [];
  $words = preg_split('~\s+~u', $text) ?: [];
  $line = '';
  foreach ($words as $w) {
    $try = $line ? ($line.' '.$w) : $w;
    $box = imagettfbbox($size, 0, $font, $try);
    $width = abs($box[4] - $box[0]);
    if ($width <= $maxWidth) { $line = $try; }
    else { if ($line) $lines[] = $line; $line = $w; }
  }
  if ($line) $lines[] = $line;
  return $lines;
}

// Canvas ---------------------------------------------------------------------
$im = imagecreatetruecolor($W, $H);
[$r,$g,$b] = hex2rgb($bghex);
$bg = imagecolorallocate($im, $r, $g, $b);
imagefilledrectangle($im, 0, 0, $W, $H, $bg);

// Subtle vignette
for ($i=0;$i<80;$i++){
  $alpha = (int) (50 * ($i/80));
  $col = imagecolorallocatealpha($im, 0, 0, 0, 127 - min(127, $alpha));
  imagerectangle($im, $i, $i, $W-$i, $H-$i, $col);
}

// Colors
$white = imagecolorallocate($im, 240, 240, 245);
$muted = imagecolorallocate($im, 170, 170, 180);
$accent= imagecolorallocate($im,  90, 120, 255);

// Fonts
$fontBold = null; foreach (ttf_try_paths() as $p)  { if (is_file($p)) { $fontBold = $p; break; } }
$fontReg  = null; foreach (ttf_reg_try_paths() as $p){ if (is_file($p)) { $fontReg  = $p; break; } }

// Title
if ($fontBold) {
  $maxW = $W - 2*80;
  $size = 64; // base
  // Auto‑downsize for very long titles
  while ($size > 34) {
    $lines = wrap_text($title, $fontBold, $size, $maxW);
    if (count($lines) <= 3) break;
    $size -= 2;
  }
  $y = 260; // start
  foreach ($lines as $i => $ln) {
    $box = imagettfbbox($size, 0, $fontBold, $ln);
    $tw = abs($box[4] - $box[0]);
    $x  = (int)(($W - $tw)/2);
    imagettftext($im, $size, 0, $x, $y, $white, $fontBold, $ln);
    $y += (int)($size * 1.25);
  }
} else {
  // Fallback bitmap fonts
  imagestring($im, 5, 80, 260, $title, $white);
}

// Subtitle
if ($subtitle) {
  if ($fontReg) {
    $box = imagettfbbox(26, 0, $fontReg, $subtitle);
    $tw = abs($box[4] - $box[0]);
    $x  = (int)(($W - $tw)/2);
    imagettftext($im, 26, 0, $x, $H - 120, $muted, $fontReg, $subtitle);
  } else {
    imagestring($im, 4, 80, $H - 120, $subtitle, $muted);
  }
}

// Accent bar
imagefilledrectangle($im, 0, $H-6, $W, $H, $accent);

imagepng($im);
imagedestroy($im);

// ----------------------------------------------------------------------------
// Hook snippet for page_template.php
// ----------------------------------------------------------------------------
/*
// In page_template.php, AFTER you have $PAGE_TITLE/$PAGE_DESC computed
if (empty($PAGE_OG_IMAGE)) {
  $titleShort = mb_substr($PAGE_TITLE ?: ($cfg['site_name'] ?? 'HuntHub'), 0, 90);
  $sub  = $cfg['site_name'] ?? 'HuntHub';
  $PAGE_OG_IMAGE = $APP_BASE . '/og/fallback.php?title=' . rawurlencode($titleShort) . '&subtitle=' . rawurlencode($sub);
}
// Keep your existing OG/Twitter meta generation that uses $PAGE_OG_IMAGE
*/

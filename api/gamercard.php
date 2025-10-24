<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/db.php';

$pdo = db();

/* ---- Input ---- */
$uid    = isset($_GET['id'])   ? (int)$_GET['id'] : 0;
$slug   = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$w      = isset($_GET['w'])    ? max(200, min(1600, (int)$_GET['w'])) : 0;
$debug  = isset($_GET['debug']) && $_GET['debug'] == '1';

function fail($msg, $code=500, $debug=false){
  http_response_code($code);
  if ($debug){
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
  }
  exit;
}

if ($uid <= 0 && $slug === '') fail('need id or slug', 400, $debug);
if (!extension_loaded('gd'))   fail('PHP GD extension missing', 500, $debug);

/* ---- User ---- */
if ($slug !== '') {
  $st = $pdo->prepare("SELECT id, display_name, slug, avatar_path, created_at FROM users WHERE slug=? LIMIT 1");
  $st->execute([$slug]);
} else {
  $st = $pdo->prepare("SELECT id, display_name, slug, avatar_path, created_at FROM users WHERE id=? LIMIT 1");
  $st->execute([$uid]);
}
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) fail('user not found', 404, $debug);
$uid = (int)$user['id'];

/* ---- Stats ---- */
$st = $pdo->prepare("SELECT COUNT(*) FROM threads WHERE author_id=?");
$st->execute([$uid]); $threadsCount = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE author_id=?");
$st->execute([$uid]); $postsCount = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COALESCE(SUM(likes_count),0) FROM threads WHERE author_id=?");
$st->execute([$uid]); $likesThreads = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COALESCE(SUM(likes_count),0) FROM posts WHERE author_id=?");
$st->execute([$uid]); $likesPosts = (int)$st->fetchColumn();

$likesTotal = $likesThreads + $likesPosts;

/* ---- Canvas ---- */
$W=1200; $H=360; $R=32; $PAD=32;

$im = imagecreatetruecolor($W,$H);
imagesavealpha($im,true); imagealphablending($im,true);
$trans = imagecolorallocatealpha($im,0,0,0,127);
imagefill($im,0,0,$trans);

$bgDark   = imagecolorallocate($im,17,17,17);
$muted    = imagecolorallocate($im,156,163,175);
$white    = imagecolorallocate($im,238,238,238);
$accent   = imagecolorallocate($im,242,150,32);

/* ---- Rounded rect helper ---- */
$rounded = function($img,$x1,$y1,$x2,$y2,$r,$col){
  imagefilledrectangle($img,$x1+$r,$y1,$x2-$r,$y2,$col);
  imagefilledrectangle($img,$x1,$y1+$r,$x2,$y2-$r,$col);
  imagefilledellipse($img,$x1+$r,$y1+$r,2*$r,2*$r,$col);
  imagefilledellipse($img,$x2-$r,$y1+$r,2*$r,2*$r,$col);
  imagefilledellipse($img,$x1+$r,$y2-$r,2*$r,2*$r,$col);
  imagefilledellipse($img,$x2-$r,$y2-$r,2*$r,2*$r,$col);
};
$rounded($im,0,0,$W-1,$H-1,$R,$bgDark);

/* ---- Avatar ---- */
function loadImageAny(string $path){
  if (!$path) return null;
  if (preg_match('~^https?://~i',$path)) {
    $data=@file_get_contents($path); if($data===false) return null;
    return @imagecreatefromstring($data);
  }
  $real = $_SERVER['DOCUMENT_ROOT'].'/'.ltrim($path,'/');
  if (!is_file($real)) return null;
  $ext=strtolower(pathinfo($real,PATHINFO_EXTENSION));
  return match($ext){
    'jpg','jpeg' => @imagecreatefromjpeg($real),
    'png'        => @imagecreatefrompng($real),
    'gif'        => @imagecreatefromgif($real),
    default      => @imagecreatefromstring(@file_get_contents($real))
  };
}
function circleThumb($src,int $size){
  $dst=imagecreatetruecolor($size,$size);
  imagesavealpha($dst,true);
  $t=imagecolorallocatealpha($dst,0,0,0,127);
  imagefill($dst,0,0,$t);
  $w=imagesx($src); $h=imagesy($src); $side=min($w,$h);
  $sx=(int)(($w-$side)/2); $sy=(int)(($h-$side)/2);
  imagecopyresampled($dst,$src,0,0,$sx,$sy,$size,$size,$side,$side);
  // Maske
  $mask=imagecreatetruecolor($size,$size);
  imagesavealpha($mask,true);
  $clear=imagecolorallocatealpha($mask,0,0,0,127);
  $solid=imagecolorallocatealpha($mask,0,0,0,0);
  imagefill($mask,0,0,$clear);
  imagefilledellipse($mask,$size/2,$size/2,$size,$size,$solid);
  for($x=0;$x<$size;$x++){
    for($y=0;$y<$size;$y++){
      $a=(imagecolorat($mask,$x,$y)&0x7F000000)>>24;
      $rgba=imagecolorat($dst,$x,$y);
      $a2=($rgba&0x7F000000)>>24;
      $newA=max($a,$a2);
      imagesetpixel($dst,$x,$y,($rgba&0x00FFFFFF)|($newA<<24));
    }
  }
  imagedestroy($mask);
  return $dst;
}

$AV_SIZE=140;
$av = loadImageAny((string)$user['avatar_path']);
if ($av){
  $circ = circleThumb($av,$AV_SIZE);
  imagecopy($im,$circ,$PAD,($H-$AV_SIZE)/2,0,0,$AV_SIZE,$AV_SIZE);
  imagedestroy($circ); imagedestroy($av);
} else {
  $ph=imagecolorallocate($im,60,60,60);
  imagefilledellipse($im,$PAD+$AV_SIZE/2,$H/2,$AV_SIZE,$AV_SIZE,$ph);
}

/* ---- Fonts + Text-Helper ---- */
$FONT_SEMI = __DIR__ . '/../assets/fonts/Inter-SemiBold.ttf';
$FONT_REG  = __DIR__ . '/../assets/fonts/Inter-Regular.ttf';
if (!is_file($FONT_SEMI) || !is_file($FONT_REG)) {
  // Server-Standard (häufig vorhanden). Wenn auch die nicht existieren, nutzen wir GD-Bitmap-Fonts.
  $fallback = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
  if (is_file($fallback)) $FONT_SEMI = $FONT_REG = $fallback;
  else { $FONT_SEMI = $FONT_REG = null; }
}

$drawText = function($img,$size,$x,$y,$color,$font,$text){
  if ($font && function_exists('imagettftext')) {
    imagettftext($img,$size,0,$x,$y,$color,$font,$text);
  } else {
    // GD Bitmap-Font (größenfix, y leicht korrigieren)
    imagestring($img, 5, $x, $y-14, $text, $color);
  }
};

/* ---- Textinhalte ---- */
$name  = (string)$user['display_name'];
$since = 'Mitglied seit '.date('d.m.Y', strtotime((string)$user['created_at']));
$line2 = "Threads: {$threadsCount}   ·   Beiträge: {$postsCount}   ·   Likes: {$likesTotal}";

/* ---- Schreiben ---- */
$drawText($im,34,$PAD+$AV_SIZE+24,120,$white,$FONT_SEMI,$name);
$drawText($im,20,$PAD+$AV_SIZE+24,170,$muted,$FONT_REG,$line2);
$drawText($im,18,$PAD+$AV_SIZE+24,210,$muted,$FONT_REG,$since);

/* ---- Badge ---- */
$badgeW=260; $badgeH=56; $bx=$W-$badgeW-$PAD; $by=$PAD;
$badgeBg=imagecolorallocatealpha($im,255,255,255,110);
$rounded($im,$bx,$by,$bx+$badgeW,$by+$badgeH,22,$badgeBg);
$drawText($im,20,$bx+22,$by+36,$accent,$FONT_SEMI,'HuntHub GamerCard');

/* ---- Optional skalieren ---- */
$out=$im;
if ($w>0 && $w<$W){
  $h=(int)round(($w/$W)*$H);
  $thumb=imagecreatetruecolor($w,$h);
  imagesavealpha($thumb,true); imagealphablending($thumb,false);
  $t=imagecolorallocatealpha($thumb,0,0,0,127); imagefill($thumb,0,0,$t);
  imagecopyresampled($thumb,$im,0,0,0,0,$w,$h,$W,$H);
  imagedestroy($im); $out=$thumb;
}

/* ---- Cache ---- */
$etag = '"' . md5($uid.$threadsCount.$postsCount.$likesTotal.$user['avatar_path'].$user['display_name']) . '"';
header('Content-Type: image/png');
header('Cache-Control: public, max-age=300');
header('ETag: '.$etag);
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { http_response_code(304); exit; }

/* ---- Ausgabe ---- */
imagepng($out);
imagedestroy($out);

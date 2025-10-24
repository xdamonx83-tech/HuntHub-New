<?php
declare(strict_types=1);

/**
 * Reels-Upload + Trimmen (ffmpeg) + Wall-Post anlegen
 * Speichert Mapping in /uploads/reels/map.json (keine DB nötig)
 * Request: file, csrf, start, end, poster_time
 * Response: { ok, id, post_id, video, poster, reel_url, duration }
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

/* --- CSRF --- */
$csrf = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
if (function_exists('csrf_token')) {
  if (!$csrf || !hash_equals((string)csrf_token(), (string)$csrf)) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
  }
}

/* --- ffmpeg/ffprobe: KEINE is_file()-Prüfungen (open_basedir)! --- */
$FFMPEG  = '/usr/bin/ffmpeg';
$FFPROBE = '/usr/bin/ffprobe';

/* --- Verzeichnisse --- */
$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$dstVideos  = '/uploads/reels/videos';
$dstPosters = '/uploads/reels/posters';
@mkdir($publicRoot.$dstVideos, 0775, true);
@mkdir($publicRoot.$dstPosters, 0775, true);

/* --- Datei prüfen --- */
if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no_file']); exit;
}

$tmpIn   = $_FILES['file']['tmp_name'];
$start   = isset($_POST['start']) ? max(0.0, (float)$_POST['start']) : 0.0;
$end     = isset($_POST['end'])   ? (float)$_POST['end']   : 0.0;
$posterT = isset($_POST['poster_time']) ? (float)$_POST['poster_time'] : null;

/* --- Dauer via ffprobe (wenn möglich) --- */
$duration = null;
$cmdProbe = sprintf(
  '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
  escapeshellcmd($FFPROBE), escapeshellarg($tmpIn)
);
$outDur = @shell_exec($cmdProbe);
if ($outDur !== null && $outDur !== false) $duration = (float)trim((string)$outDur);

if ($end <= 0 && $duration !== null) $end = $duration;
$clipDur = max(0.05, ($end - $start));

/* --- Zieldateien --- */
$id      = bin2hex(random_bytes(8));
$outVid  = $dstVideos.'/'.$id.'.mp4';
$outAbs  = $publicRoot.$outVid;
$outJpg  = $dstPosters.'/'.$id.'.jpg';
$jpgAbs  = $publicRoot.$outJpg;

/* --- Trimmen/Transkodieren --- */
$cmd = sprintf(
  '%s -y -ss %s -i %s -t %s -c:v libx264 -preset veryfast -crf 22 -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k %s 2>&1',
  escapeshellcmd($FFMPEG),
  escapeshellarg((string)$start),
  escapeshellarg($tmpIn),
  escapeshellarg((string)$clipDur),
  escapeshellarg($outAbs)
);
@exec($cmd, $o, $rc);
if ($rc !== 0 || !is_file($outAbs)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'ffmpeg_failed','rc'=>$rc]); exit;
}
@chmod($outAbs, 0664);

/* --- Poster --- */
if ($posterT === null) $posterT = $start + ($clipDur/2);
$cmdJ = sprintf(
  '%s -y -ss %s -i %s -frames:v 1 -q:v 2 %s 2>&1',
  escapeshellcmd($FFMPEG),
  escapeshellarg((string)$posterT),
  escapeshellarg($outAbs),
  escapeshellarg($jpgAbs)
);
@exec($cmdJ);
if (is_file($jpgAbs)) @chmod($jpgAbs, 0664);

/* --- Wall-Post anlegen (ohne Text, mit content_html Video) --- */
/* --- Wall-Post anlegen (ohne Text, mit content_html Video) --- */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$BASE   = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
$content_html = '<figure class="hh-media-video"><video controls playsinline ' .
                (is_file($jpgAbs) ? 'poster="'.htmlspecialchars($outJpg,ENT_QUOTES).'" ' : '') .
                '><source src="'.htmlspecialchars($outVid,ENT_QUOTES).'" type="video/mp4"></video></figure>';

$postId = 0;
try {
  $ch = curl_init($BASE.'/api/wall/post_create.php');
  $fields = [
    'csrf'          => $csrf,
    'content_plain' => '',
    'content_html'  => $content_html,
  ];
  $cookieHeader = $_SERVER['HTTP_COOKIE'] ?? (session_name().'='.session_id());
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $fields,
    CURLOPT_COOKIE         => $cookieHeader,
    CURLOPT_HTTPHEADER     => [
      'X-Requested-With: XMLHttpRequest',
      'Referer: '.$BASE.'/wall/',
      'Origin: '.$BASE,
      'Expect:',                      // verhindert 100-continue Hänger
      'User-Agent: '.($_SERVER['HTTP_USER_AGENT'] ?? 'curl-reels')
    ],
  ]);
  $resp  = curl_exec($ch);
  $code  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  $j = json_decode((string)$resp, true);
  if ($code >= 200 && $code < 300 && is_array($j) && !empty($j['post']['id'])) {
    $postId = (int)$j['post']['id'];
  } else {
    error_log('reels: post_create failed HTTP '.$code.' | '.$resp);
  }
} catch (\Throwable $e) {
  error_log('reels: post_create exception: '.$e->getMessage());
}


/* --- Mapping-Datei pflegen (kein DB-Require nötig) --- */
$mapFile = $publicRoot.'/uploads/reels/map.json';
$map = [];
if (is_file($mapFile)) {
  $map = json_decode((string)file_get_contents($mapFile), true) ?: [];
}
$map[] = [
  'id'        => $id,
  'post_id'   => $postId,
  'video'     => $outVid,
  'poster'    => (is_file($jpgAbs) ? $outJpg : ''),
  'duration'  => $clipDur,
  'created_at'=> date('c'),
];
@file_put_contents($mapFile, json_encode($map, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
@chmod($mapFile, 0664);

/* --- Ziel-URL für den Swiper --- */
$reelUrl = '/reels/swipe.php#r-'.$id;

echo json_encode([
  'ok'       => true,
  'id'       => $id,
  'post_id'  => $postId,
  'video'    => $outVid,
  'poster'   => (is_file($jpgAbs)?$outJpg:''),
  'reel_url' => $reelUrl,
  'duration' => $clipDur,
]);

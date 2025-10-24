<?php
// /api/upload_video_tiny.php — Re-Encode + Debug/Selftest
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

@set_time_limit(300);

// ---- Konfiguration ----
const MAX_UPLOAD_BYTES = 200 * 1024 * 1024;
const TARGET_WIDTH     = 1280;
const TARGET_FPS       = 30;
const CRF              = 27;
const PRESET           = 'slow';
const MAXRATE          = '2500k';
const BUFSIZE          = '5000k';
const AUDIO_BITRATE    = '96k';
const FFMPEG_OVERRIDE = '/usr/bin/ffmpeg';

$__error_log = __DIR__ . '/../uploads/videos/ffmpeg_error.log';

function is_func_disabled(string $fn): bool {
  $d = ini_get('disable_functions') ?: '';
  return in_array($fn, array_map('trim', explode(',', $d)), true);
}
function find_ffmpeg(): string {
    // Wichtig: ohne is_file()/is_executable(), weil open_basedir das blockieren kann
    if (defined('FFMPEG_OVERRIDE') && FFMPEG_OVERRIDE) {
        return FFMPEG_OVERRIDE;
    }
    // Fallback-Suche, falls kein Override gesetzt ist
    $candidates = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        !is_func_disabled('shell_exec') ? trim((string)@shell_exec('command -v ffmpeg')) : '',
        !is_func_disabled('shell_exec') ? trim((string)@shell_exec('which ffmpeg'))     : '',
    ];
    foreach ($candidates as $p) {
        if ($p) return $p;
    }
    return '';
}

function root_dir(): string {
  $root = realpath(__DIR__ . '/..');
  if ($root === false) throw new RuntimeException('root_not_found');
  return $root;
}
// ---------- SELFTEST (GET ?selftest=1) ----------
if (isset($_GET['selftest'])) {
  $root = root_dir();
  $tmp  = $root . '/uploads/tmp';
  $out  = $root . '/uploads/videos';
  $ff   = find_ffmpeg();

  $resp = [
    'php' => [
      'upload_max_filesize' => ini_get('upload_max_filesize'),
      'post_max_size'       => ini_get('post_max_size'),
      'max_execution_time'  => ini_get('max_execution_time'),
      'open_basedir'        => ini_get('open_basedir') ?: null,
      'exec_disabled'       => is_func_disabled('exec'),
    ],
    'paths' => [
      'root' => $root,
      'tmp'  => $tmp,
      'out'  => $out,
      'tmp_writable' => is_writable($tmp),
      'out_writable' => is_writable($out),
    ],
    'ffmpeg' => [
      'path' => $ff ?: null,
      'exists' => $ff ? true : false,
    ],
  ];
  echo json_encode($resp, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  // ---- Datei prüfen ----
  if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    throw new RuntimeException('no_file');
  }
  $f = $_FILES['file'];
  if ($f['error'] !== UPLOAD_ERR_OK)   throw new RuntimeException('upload_error_' . $f['error']);
  if ($f['size']  > MAX_UPLOAD_BYTES)  throw new RuntimeException('too_large');

  // ---- Typ erlauben ----
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($f['tmp_name']) ?: 'application/octet-stream';
  $allowed = [
    'video/mp4'       => 'mp4',
    'video/webm'      => 'webm',
    'video/ogg'       => 'ogv',
    'video/ogv'       => 'ogv',
    'video/quicktime' => 'mov',
    'application/octet-stream' => 'mp4',
  ];
  if (!isset($allowed[$mime])) {
    throw new RuntimeException('unsupported_type:' . $mime);
  }

  // ---- Pfade/Ordner ----
  $root = root_dir();
  $tmpDir = $root . '/uploads/tmp';
  $outDir = $root . '/uploads/videos';
  foreach ([$tmpDir, $outDir] as $d) {
    if (!is_dir($d) && !mkdir($d, 0775, true) && !is_dir($d)) {
      throw new RuntimeException('mkdir_failed:' . $d);
    }
  }

  $base    = bin2hex(random_bytes(6));
  $safeSrc = preg_replace('/[^a-zA-Z0-9._-]/', '_', $f['name']);
  $srcPath = $tmpDir . '/' . $base . '_' . $safeSrc;
  $dstPath = $outDir . '/' . $base . '.mp4';
  $poster  = $outDir . '/' . $base . '.jpg';

  if (!move_uploaded_file($f['tmp_name'], $srcPath)) {
    throw new RuntimeException('move_failed');
  }

  // ---- FFmpeg & exec ----
  $ffmpeg = find_ffmpeg();
  if ($ffmpeg === '') throw new RuntimeException('ffmpeg_not_found');
  if (is_func_disabled('exec')) throw new RuntimeException('exec_disabled_in_php_ini');

  // ---- Transcoding (immer) ----
  $vf = "scale='min(" . TARGET_WIDTH . ",iw)':'-2',fps=" . TARGET_FPS;
  $cmd = sprintf(
    '%s -y -i %s -vf "%s" -c:v libx264 -preset %s -crf %d ' .
    '-maxrate %s -bufsize %s -movflags +faststart -pix_fmt yuv420p -profile:v high -level 4.1 ' .
    '-c:a aac -b:a %s %s 2>&1',
    escapeshellcmd($ffmpeg),
    escapeshellarg($srcPath),
    $vf,
    escapeshellarg(PRESET),
    (int)CRF,
    escapeshellarg(MAXRATE),
    escapeshellarg(BUFSIZE),
    escapeshellarg(AUDIO_BITRATE),
    escapeshellarg($dstPath)
  );

  $out = [];
  $rc  = 0;
  exec($cmd, $out, $rc);
  $log = implode("\n", $out);

  if ($rc !== 0 || !is_file($dstPath)) {
    $msg = 'transcode_failed: ' . substr($log, 0, 500);
    @file_put_contents($__error_log, '['.date('c')."] ".$msg."\n", FILE_APPEND);
    throw new RuntimeException($msg);
  }

  @unlink($srcPath);

  // Poster optional
  $cmdPoster = sprintf('%s -y -ss 00:00:01 -i %s -frames:v 1 -q:v 2 %s 2>&1',
    escapeshellcmd($ffmpeg), escapeshellarg($dstPath), escapeshellarg($poster));
  @exec($cmdPoster);

  $videoUrl  = '/uploads/videos/' . basename($dstPath);
  $posterUrl = is_file($poster) ? '/uploads/videos/' . basename($poster) : null;

  echo json_encode(['location' => $videoUrl, 'poster' => $posterUrl], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(400);
  $err = $e->getMessage();
  @file_put_contents($__error_log, '['.date('c')."] ".$err."\n", FILE_APPEND);
  echo json_encode(['error' => $err]);
}

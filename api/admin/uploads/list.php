<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in   = in_json();
  $kind = ($in['kind'] ?? 'images') === 'videos' ? 'videos' : 'images';

  // Projektwurzel: .../api/admin/uploads -> ../../../
  $root = realpath(__DIR__ . '/../../../');
  if ($root === false) { throw new RuntimeException('root_resolve_failed'); }

  // ----- Verzeichnisse bestimmen -----
  $dirs = [];
  if ($kind === 'images') {
    // NUR diese sechs Ordner durchsuchen (sowohl unter /uploads als auch /public/uploads)
    $sub = ['avatars','covers','forum','messages','posts','video_posters'];
    foreach ([$root . '/uploads', $root . '/public/uploads'] as $base) {
      foreach ($sub as $s) {
        $p = rtrim($base, '/') . '/' . $s;
        if (is_dir($p)) $dirs[] = $p;
      }
    }
  } else { // videos
    foreach ([$root . '/uploads/videos', $root . '/public/uploads/videos'] as $p) {
      if (is_dir($p)) $dirs[] = $p;
    }
  }

  if (!$dirs) {
    http_response_code(404);
    echo json_encode([
      'ok' => false,
      'error' => 'uploads_dir_missing',
      'required_dirs' => $kind === 'images'
        ? array_map(fn($s)=>"/uploads/$s & /public/uploads/$s", ['avatars','covers','forum','messages','posts','video_posters'])
        : ['/uploads/videos', '/public/uploads/videos'],
      'checked_root' => $root,
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ----- Dateifilter -----
  $extImg = ['jpg','jpeg','png','webp','gif','svg'];
  $extVid = ['mp4','mov','webm','mkv'];

  // ----- Scan -----
  $items = [];
  foreach ($dirs as $base) {
    $rii = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($rii as $f) {
      /** @var SplFileInfo $f */
      if (!$f->isFile()) continue;
      $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
      if ($kind === 'images' && !in_array($ext, $extImg, true)) continue;
      if ($kind === 'videos' && !in_array($ext, $extVid, true)) continue;

      $abs = $f->getPathname();
      $rel = substr($abs, strlen($root)); if ($rel === false || $rel === '') $rel = $abs;

      $mime = $kind === 'images'
        ? ($ext==='svg' ? 'image/svg+xml' :
           (($ext==='jpg'||$ext==='jpeg') ? 'image/jpeg' :
           ($ext==='png' ? 'image/png' :
           ($ext==='webp' ? 'image/webp' :
           ($ext==='gif' ? 'image/gif' : 'image/*')))))
        : ($ext==='mp4' ? 'video/mp4' :
           ($ext==='webm' ? 'video/webm' :
           ($ext==='mov' ? 'video/quicktime' :
           ($ext==='mkv' ? 'video/x-matroska' : 'video/*'))));

      $items[] = [
        'kind' => $kind,
        'path' => $abs,
        'rel'  => $rel,
        'size' => $f->getSize(),
        'mime' => $mime,
      ];
    }
  }

  usort($items, fn($a,$b) => $b['size'] <=> $a['size']);
  echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

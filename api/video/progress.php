<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$job = isset($_GET['job']) ? (string)$_GET['job'] : '';
$dur = isset($_GET['dur']) ? (float)$_GET['dur'] : 0.0;

if (!preg_match('/^[A-Za-z0-9_-]{8,40}$/', $job)) {
  echo json_encode(['ok'=>false,'error'=>'bad_job']); exit;
}

$progFile = __DIR__ . '/../../uploads/tmp/ffprog_' . $job . '.txt';
if (!is_file($progFile)) {
  echo json_encode(['ok'=>true,'state'=>'nojob','percent'=>null]); exit;
}

$txt = @file_get_contents($progFile) ?: '';
$state = (strpos($txt, 'progress=end') !== false) ? 'end' : 'encoding';

$percent = null;
if ($dur > 0) {
  if (preg_match('/out_time_ms=(\d+)/', $txt, $m)) {
    $out_ms = (float)$m[1];
    $percent = max(0.0, min(99.0, ($out_ms/1000000.0) / $dur * 100.0));
  }
}
$cfg = require __DIR__ . '/../../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/'); // Root => ''

$mp4Name    = basename($mp4);        // z.B. 2025-09-06_143755.mp4
$posterName = basename($posterJpg);   // z.B. 2025-09-06_143755.jpg

echo json_encode([
  'ok'     => true,
  'status' => 'done',
  // Root-relativ oder absolut, aber NICHT relativ zu /forum/
  'video'  => $APP_BASE . '/uploads/videos/' . $mp4Name,
  'poster' => $APP_BASE . '/uploads/video_posters/' . $posterName,
], JSON_UNESCAPED_SLASHES);
exit;
echo json_encode(['ok'=>true,'state'=>$state,'percent'=>$percent]);

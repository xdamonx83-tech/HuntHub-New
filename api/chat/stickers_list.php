<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/auth.php';
optional_auth();
require_auth();

$CFG = require __DIR__ . '/../../auth/config.php';
$dir = $CFG['stickers']['dir']      ?? (__DIR__ . '/../../uploads/stickers');
$url = $CFG['stickers']['base_url'] ?? '/uploads/stickers';

$allowed = ['png','webp','jpg','jpeg','gif'];

$items = [];
if (is_dir($dir)) {
  $files = scandir($dir);
  foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) continue;
    $items[] = [
      'name' => pathinfo($f, PATHINFO_FILENAME),
      'url'  => rtrim($url, '/') . '/' . rawurlencode($f),
      'type' => ($ext === 'gif' ? 'gif' : 'img'),
    ];
  }
}

echo json_encode(['ok'=>true,'items'=>$items]);

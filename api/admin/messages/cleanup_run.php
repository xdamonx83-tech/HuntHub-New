<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$in = in_json();
$retention  = max(1, (int)($in['retention_days'] ?? 15));
$placeholder= (string)($in['placeholder'] ?? 'Anhang (Bild/Video) automatisch gelöscht – älter als 15 Tage.');
$dry        = !empty($in['dry_run']) ? '1' : '0';
$batch      = max(100, (int)($in['batch'] ?? 500));
$chunk      = max(50,  (int)($in['update_chunk'] ?? 200));

// ENV setzen für das Cleanup-Script
putenv("HH_RETENTION_DAYS={$retention}");
putenv("HH_PLACEHOLDER={$placeholder}");
putenv("HH_DRY_RUN={$dry}");
putenv("HH_BATCH={$batch}");
putenv("HH_UPDATE_CHUNK={$chunk}");

// Output abfangen
ob_start();
require __DIR__ . '/../../messages/cleanup_messages.php';
$out = trim(ob_get_clean());

// Falls das Script bereits JSON ausgibt, direkt durchreichen
$json = json_decode($out, true);
if (is_array($json)) {
  echo json_encode(['ok'=>true,'result'=>$json], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} else {
  echo json_encode(['ok'=>true,'raw'=>$out]);
}

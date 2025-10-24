<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';      // <-- NEU: für PDO
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/reels.php';

$me = require_auth();
verify_csrf(db(), (string)($_POST['csrf'] ?? ''));   // <-- NEU: PDO + Token
reels_ensure_dirs();

if (empty($_FILES['file']['tmp_name'])) {
  echo json_encode(['ok'=>false,'error'=>'no_file']); exit;
}

$ext = '.mp4';
$hash = bin2hex(random_bytes(8));
$base = 'u'.$me['id'].'_'.$hash;
$dstRel = '/uploads/reels_src/'.$base.$ext;
$dstAbs = __DIR__.'/../../uploads/reels_src/'.$base.$ext;
// Limit (z. B. 64 MB, passend zu PHP ini)
$max = 64 * 1024 * 1024;
if (($_FILES['file']['size'] ?? 0) > $max) {
  echo json_encode(['ok'=>false,'error'=>'file_too_large']); exit;
}

// MIME prüfen
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = $finfo ? finfo_file($finfo, $_FILES['file']['tmp_name']) : null;
if ($finfo) finfo_close($finfo);

$allowed = ['video/mp4','video/quicktime','video/webm','video/x-m4v'];
if (!$mime || !in_array($mime, $allowed, true)) {
  echo json_encode(['ok'=>false,'error'=>'bad_mime','mime'=>$mime]); exit;
}

// Endung anhand MIME bestimmen
$ext = match ($mime) {
  'video/mp4','video/x-m4v' => '.mp4',
  'video/quicktime'         => '.mov',
  'video/webm'              => '.webm',
  default                   => '.mp4',
};

if (!@move_uploaded_file($_FILES['file']['tmp_name'], $dstAbs)) {
  echo json_encode(['ok'=>false,'error'=>'move_failed']); exit;
}

echo json_encode(['ok'=>true,'token'=>$base.$ext,'path'=>$dstRel]);

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../lib/reels.php';

try {
  $pdo = db();
  $me  = require_auth();
  verify_csrf($pdo, (string)($_POST['csrf'] ?? ''));

  reels_ensure_dirs();

  // Eingaben aus Composer
  $token      = trim((string)($_POST['token'] ?? ''));           // z.B. u123_abcd1234.mp4
  $desc       = (string)($_POST['description'] ?? '');
  $startMs    = max(0, (int)($_POST['start_ms'] ?? 0));
  $endMs      = max(0, (int)($_POST['end_ms'] ?? 0));
  $posterMs   = max(0, (int)($_POST['poster_ms'] ?? 0));
  $filter     = (string)($_POST['filter'] ?? 'none');
  $posterData = (string)($_POST['poster_dataurl'] ?? '');

  if ($token === '' || !preg_match('/^[A-Za-z0-9_]+\.(mp4|mov|webm|m4v)$/i', $token)) {
    echo json_encode(['ok'=>false,'error'=>'bad_token']); exit;
  }

  // -------------- Dateien & Pfade (ohne realpath) --------------
  $srcTmpAbs = __DIR__ . '/../../uploads/reels_src/' . $token;   // Upload-Zwischenablage
  $dstAbs    = __DIR__ . '/../../uploads/reels/' . $token;       // Finale Datei
  $dstRel    = '/uploads/reels/' . $token;

  if (!is_file($srcTmpAbs)) {
    echo json_encode(['ok'=>false,'error'=>'file_missing','debug'=>$srcTmpAbs]); exit;
  }

  // verschieben (rename) → fallback copy/unlink
  if (!@rename($srcTmpAbs, $dstAbs)) {
    if (!@copy($srcTmpAbs, $dstAbs)) {
      echo json_encode(['ok'=>false,'error'=>'move_failed']); exit;
    }
    @unlink($srcTmpAbs);
  }

  // Poster (optional, aus dataURL)
  $posterRel = null;
  if ($posterData && str_starts_with($posterData, 'data:image')) {
    $base = preg_replace('/\.[^.]+$/', '', $token);
    $posterAbs = __DIR__ . '/../../uploads/reels_posters/' . $base . '.jpg';
    @mkdir(__DIR__ . '/../../uploads/reels_posters', 0775, true);

    $comma = strpos($posterData, ',');
    $raw   = $comma !== false ? substr($posterData, $comma + 1) : $posterData;
    $bin   = base64_decode($raw, true);

    if ($bin !== false) {
      if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $im = @imagecreatefromstring($bin);
        if ($im !== false) {
          @imagejpeg($im, $posterAbs, 85);
          @imagedestroy($im);
        } else {
          @file_put_contents($posterAbs, $bin);
        }
      } else {
        @file_put_contents($posterAbs, $bin);
      }
      if (is_file($posterAbs)) $posterRel = '/uploads/reels_posters/' . $base . '.jpg';
    }
  }

  // Dauer (falls getrimmt)
  $durationMs = 0;
  if ($endMs > $startMs) $durationMs = $endMs - $startMs;

  $filters = [
    'preset'    => $filter,
    'start_ms'  => $startMs,
    'end_ms'    => $endMs,
    'poster_ms' => $posterMs,
  ];

  // DB-Eintrag
  $reelId = reels_insert((int)$me['id'], $dstRel, $posterRel, $desc, $durationMs, $filters);

  // Item zurück (mit Username/Avatar)
  if (!function_exists('reels_fetch_by_id')) {
    // Fallback für ältere lib: nutzt deine reels_get()
    function reels_fetch_by_id(int $id): ?array { return reels_get($id); }
  }
  $item = reels_fetch_by_id($reelId) ?? [
    'id' => $reelId, 'src'=>$dstRel, 'poster'=>$posterRel, 'description'=>$desc,
    'user_id'=>(int)$me['id'], 'username'=>$me['display_name'] ?? ('u'.$me['id']),
    'likes_count'=>0, 'comments_count'=>0, 'liked'=>false
  ];

  echo json_encode(['ok'=>true, 'id'=>$reelId, 'reel'=>$item], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'server']); // bewusst generisch
}

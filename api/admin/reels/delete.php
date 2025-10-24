<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__, 3);
require_once $ROOT . '/auth/db.php';
require_once $ROOT . '/auth/guards.php';
require_once $ROOT . '/auth/csrf.php';

try {
  $pdo = db();
  require_admin();

  $in   = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
  $csrf = $_SERVER['HTTP_X_CSRF'] ?? ($in['csrf'] ?? '');
  if (function_exists('verify_csrf') && !verify_csrf($pdo, (string)$csrf)) {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'invalid_csrf']); exit;
  }

  $id = (int)($in['id'] ?? 0);
  if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }

  // Spalten finden
  $cols=[]; foreach ($pdo->query("SHOW COLUMNS FROM reels", PDO::FETCH_ASSOC) as $c) $cols[$c['Field']]=true;
  $videoCol  = isset($cols['video_rel'])  ? 'video_rel'  : (isset($cols['video_path']) ? 'video_path' : (isset($cols['src']) ? 'src' : ''));
  $posterCol = isset($cols['poster_rel']) ? 'poster_rel' : (isset($cols['poster_path'])? 'poster_path' : (isset($cols['poster']) ? 'poster' : ''));

  $st = $pdo->prepare("SELECT id".($videoCol?", `$videoCol`":'').($posterCol?", `$posterCol`":'')." FROM reels WHERE id=:id");
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  // Neben-Tabellen säubern (falls vorhanden)
  foreach (['reel_likes','reels_likes'] as $t) { try { $pdo->exec("DELETE FROM `$t` WHERE reel_id=".(int)$id); } catch (\Throwable $e) {} }
  foreach (['reel_comments','reels_comments'] as $t) { try { $pdo->exec("DELETE FROM `$t` WHERE reel_id=".(int)$id); } catch (\Throwable $e) {} }
  try { $pdo->exec("DELETE FROM `reports` WHERE (type='reel' OR entity_type='reel') AND entity_id=".(int)$id); } catch (\Throwable $e) {}

  $del = $pdo->prepare("DELETE FROM reels WHERE id=:id");
  $del->execute([':id'=>$id]);

  // Dateien löschen – nur innerhalb /uploads/
  $safeUnlink = static function(?string $rel): void {
    if (!$rel) return;
    $rel = '/'.ltrim($rel,'/');
    if (strpos($rel, '/uploads/') !== 0) return;
    $baseUploads = realpath($_SERVER['DOCUMENT_ROOT'].'/uploads/');
    $abs = realpath($_SERVER['DOCUMENT_ROOT'].$rel);
    if (!$abs || !$baseUploads) return;
    if (strpos($abs, $baseUploads) !== 0) return;
    if (is_file($abs)) @unlink($abs);
  };
  if ($videoCol)  $safeUnlink($row[$videoCol]  ?? null);
  if ($posterCol) $safeUnlink($row[$posterCol] ?? null);

  echo json_encode(['ok'=>true]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','hint'=>$e->getMessage()]);
}

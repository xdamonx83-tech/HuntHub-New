<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in   = in_json();
  $rel  = (string)($in['rel'] ?? '');
  if ($rel === '' || str_contains($rel, '..')) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_rel']); exit;
  }

  $root = realpath(__DIR__ . '/../../../');
  if ($root === false) { throw new RuntimeException('root_resolve_failed'); }

  // Erlaubte Wurzeln
  $allowed = [
    realpath($root . '/uploads') ?: $root . '/uploads',
    realpath($root . '/public/uploads') ?: $root . '/public/uploads',
  ];

  $abs = realpath($root . '/' . ltrim($rel, '/'));
  if (!$abs || !is_file($abs)) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  $ok = false;
  foreach ($allowed as $base) {
    if ($base && str_starts_with($abs, rtrim($base,'/'))) { $ok = true; break; }
  }
  if (!$ok) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'outside_uploads_root']); exit; }

  @unlink($abs);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

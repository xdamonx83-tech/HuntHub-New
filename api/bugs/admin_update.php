<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $admin = require_admin();
  verify_csrf_if_available();
  $pdo = db();

  // JSON-Body unterstützen, falls Content-Type: application/json
  $raw = file_get_contents('php://input') ?: '';
  $isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
  $j = $isJson && $raw !== '' ? (json_decode($raw, true) ?: []) : [];

  // Werte aus JSON bevorzugen, sonst aus POST, sonst aus GET
  $in = function(string $k, $def='') use ($j) {
    return array_key_exists($k, $j) ? $j[$k] : ($_POST[$k] ?? ($_GET[$k] ?? $def));
  };

  // ID akzeptiert als id | bug_id | ticket_id
  $id = (int)($in('id', 0) ?: $in('bug_id', 0) ?: $in('ticket_id', 0));
  $status    = trim((string)$in('status',''));
  $priority  = trim((string)$in('priority',''));
  $badge     = trim((string)$in('badge_code',''));
  $xpStr     = trim((string)$in('xp_awarded',''));
  $note      = trim((string)$in('admin_note',''));




  if ($id <= 0) json_err('missing_id', 400);

  // Welche Felder existieren? (funktioniert mit deinem db_has_col aus lib.php)
  $hasStatus  = db_has_col($pdo, 'bugs', 'status');
  $hasPrio    = db_has_col($pdo, 'bugs', 'priority');
  $hasBadge   = db_has_col($pdo, 'bugs', 'badge_code');
  $hasXp      = db_has_col($pdo, 'bugs', 'xp_awarded');

  // erlaubte Werte absichern (nur falls Spalte existiert)
  $allowedStatus = ['open','waiting','closed'];
  $allowedPrio   = ['low','medium','high','urgent'];

  $updates = [];
  $params  = [];

  if ($hasStatus && $status !== '') {
    if (!in_array($status, $allowedStatus, true)) json_err('invalid_status', 422, ['allowed'=>$allowedStatus]);
    $updates[] = 'status = ?';
    $params[]  = $status;
  }
  if ($hasPrio && $priority !== '') {
    if (!in_array($priority, $allowedPrio, true)) json_err('invalid_priority', 422, ['allowed'=>$allowedPrio]);
    $updates[] = 'priority = ?';
    $params[]  = $priority;
  }
  if ($hasBadge) {
    // leeren Wert explizit auf NULL setzen
    if ($badge === '') { $updates[] = 'badge_code = NULL'; }
    else { $updates[] = 'badge_code = ?'; $params[] = $badge; }
  }
  if ($hasXp && $xpStr !== '') {
    $xp = (int)$xpStr;
    if ($xp < 0) $xp = 0;
    $updates[] = 'xp_awarded = ?';
    $params[]  = $xp;
  }

  if (!$updates && $note === '') {
    json_err('nothing_to_update', 400);
  }

  // Ticket existiert?
  $st = $pdo->prepare('SELECT id FROM bugs WHERE id = ?');
  $st->execute([$id]);
  if (!$st->fetchColumn()) json_err('not_found', 404);

  $pdo->beginTransaction();

  if ($updates) {
    $sql = 'UPDATE bugs SET '.implode(', ', $updates).', updated_at = NOW() WHERE id = ?';
    $paramsUpd = array_merge($params, [$id]);
    $up = $pdo->prepare($sql);
    $up->execute($paramsUpd);
  }

  // Admin-Kommentar optional protokollieren (wenn Tabelle vorhanden)
  if ($note !== '') {
    // Stelle sicher, dass bug_comments existiert (sonst ignoriere Kommentar)
    $stC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bug_comments'");
    $stC->execute();
    if ((int)$stC->fetchColumn() > 0) {
      $ins = $pdo->prepare('INSERT INTO bug_comments (bug_id, user_id, is_admin, message) VALUES (?,?,1,?)');
      $ins->execute([$id, (int)$admin['id'], $note]);
    }
  }

  $pdo->commit();

  json_ok(['id'=>$id, 'updated'=>true]);
} catch (Throwable $e) {
  if ($pdo ?? null) { try { $pdo->rollBack(); } catch(Throwable $__) {} }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server','hint'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}

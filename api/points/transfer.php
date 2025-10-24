<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';       // egal, wie sie intern heißt
require_once __DIR__ . '/../../lib/points.php';

$me  = require_auth();
$pdo = db();

/* ---------- helpers ---------- */
function bail(string $msg, int $code = 400): never {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function col_exists(PDO $pdo, string $table, string $col): bool {
  try { return (bool)$pdo->query("SHOW COLUMNS FROM `$table` LIKE ".$pdo->quote($col))->fetchColumn(); }
  catch (Throwable) { return false; }
}

/* ---------- CSRF robust prüfen (mehrere Funktionsnamen + Fallback) ---------- */
$csrf = (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
$csrfOk = true;
if (function_exists('verify_csrf')) {
  $csrfOk = verify_csrf($pdo, $csrf);
} elseif (function_exists('csrf_verify')) {
  $csrfOk = csrf_verify($pdo, $csrf);
} elseif (function_exists('check_csrf')) {
  $csrfOk = check_csrf($pdo, $csrf);
} elseif (isset($_SESSION['csrf']) || isset($_SESSION['csrf_token'])) {
  $tok = (string)($_SESSION['csrf'] ?? $_SESSION['csrf_token']);
  $csrfOk = $csrf !== '' && hash_equals($tok, $csrf);
}
if (!$csrfOk) bail('csrf_invalid', 403);

/* ---------- Input ---------- */
$rawTarget = trim((string)($_POST['to'] ?? ''));      // slug | email | ref_code
$amount    = (int)($_POST['amount'] ?? 0);
$note      = trim((string)($_POST['note'] ?? ''));

if ($rawTarget === '')            bail('missing_target');
if ($amount <= 0)                 bail('amount_must_be_positive');
if ($amount > 1_000_000)          bail('amount_too_large');   // Sicherheitsdeckel
if (mb_strlen($note) > 140)       $note = mb_substr($note, 0, 140);

/* ---------- Empfänger auflösen ---------- */
$where = []; $params = [];
if (filter_var($rawTarget, FILTER_VALIDATE_EMAIL) && col_exists($pdo,'users','email')) {
  $where[] = "email = ?";     $params[] = $rawTarget;
}
if (col_exists($pdo,'users','slug')) {
  $where[] = "slug = ?";      $params[] = $rawTarget;
}
if (col_exists($pdo,'users','ref_code')) {
  $where[] = "ref_code = ?";  $params[] = $rawTarget;
}
if (!$where) bail('no_lookup_field_in_users', 500);

$sql = "SELECT id, display_name, slug FROM users WHERE ".implode(' OR ', $where)." LIMIT 1";
$st  = $pdo->prepare($sql);
$st->execute($params);
$rcpt = $st->fetch(PDO::FETCH_ASSOC);
if (!$rcpt) bail('target_not_found', 404);

$toId   = (int)$rcpt['id'];
$toName = (string)($rcpt['display_name'] ?? $rcpt['slug'] ?? ('#'.$toId));
if ($toId === (int)$me['id']) bail('self_transfer_forbidden');

/* ---------- Transaktion (atomar, mit Sperren) ---------- */
try {
  $pdo->beginTransaction();

  // Immer in fixer Reihenfolge sperren → keine Deadlocks
  $a = min((int)$me['id'], $toId);
  $b = max((int)$me['id'], $toId);
  $sel = $pdo->prepare("SELECT user_id, points FROM user_points WHERE user_id IN (?, ?) FOR UPDATE");
  $sel->execute([$a, $b]);
  $rows = $sel->fetchAll(PDO::FETCH_KEY_PAIR); // [user_id => points]

  // user_points-Zeilen anlegen, falls nicht vorhanden
  foreach ([$a, $b] as $uid) {
    if (!array_key_exists($uid, $rows)) {
      $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, 0)")->execute([$uid]);
      $rows[$uid] = 0;
    }
  }
  $fromBal = (int)$rows[$me['id']];
  $toBal   = (int)$rows[$toId];

  if ($fromBal < $amount) {
    $pdo->rollBack();
    bail('not_enough_points', 400);
  }

  // Abbuchen & gutschreiben über award_points() (damit Log & balance_after stimmen)
  $metaOut = ['to_user_id'=>$toId, 'to_name'=>$toName, 'note'=>$note];
  $metaIn  = ['from_user_id'=>(int)$me['id'], 'from_name'=>(string)($me['display_name'] ?? $me['slug'] ?? ('#'.$me['id'])), 'note'=>$note];

  $out = award_points($pdo, (int)$me['id'], 'transfer_out', -$amount, $metaOut);
  if (!($out['ok'] ?? false)) throw new RuntimeException($out['msg'] ?? 'award_points out failed');

  $in  = award_points($pdo, $toId,        'transfer_in',  +$amount, $metaIn);
  if (!($in['ok'] ?? false))  throw new RuntimeException($in['msg']  ?? 'award_points in failed');

  $pdo->commit();

  echo json_encode([
    'ok'      => true,
    'sent'    => $amount,
    'to'      => ['id'=>$toId, 'name'=>$toName],
    'balance' => $out['balance'] ?? max(0, $fromBal - $amount)
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('points/transfer error: '.$e->getMessage());
  bail('server_error', 500);
}

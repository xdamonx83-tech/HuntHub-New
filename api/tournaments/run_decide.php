<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

$pdo = db();
$admin = require_admin();

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf($pdo, $csrf)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

$runId  = (int)($_POST['run_id'] ?? 0);
$action = strtolower((string)($_POST['action'] ?? '')); // 'approve' | 'reject'
$reason = trim((string)($_POST['reason'] ?? ''));

if ($runId <= 0 || !in_array($action, ['approve','reject'], true)) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

// existiert der Run?
$st = $pdo->prepare('SELECT id, status FROM tournament_runs WHERE id=? LIMIT 1');
$st->execute([$runId]);
$run = $st->fetch(PDO::FETCH_ASSOC);
if (!$run) { echo json_encode(['ok'=>false,'error'=>'run_not_found']); exit; }

// Status + Reason setzen
$newStatus = $action === 'approve' ? 'approved' : 'rejected';
$st = $pdo->prepare('UPDATE tournament_runs
  SET status=?, decision_reason=?, decided_by=?, decided_at=NOW()
  WHERE id=?');
$st->execute([$newStatus, ($newStatus === 'rejected' ? ($reason ?: 'Kein Grund angegeben') : null), (int)$admin['id'], $runId]);

echo json_encode(['ok'=>true, 'id'=>$runId, 'status'=>$newStatus]);

<?php
declare(strict_types=1);
@ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../../auth/db.php';
require_once __DIR__.'/../../auth/guards.php';

$pdo  = db();
$user = function_exists('optional_auth') ? optional_auth() : (function_exists('current_user') ? current_user() : null);

$log_id = (int)($_POST['log_id'] ?? 0);
$vote   = (int)($_POST['vote']   ?? 0);   // 1 oder -1
$reason = trim((string)($_POST['reason'] ?? ''));

if (!$log_id || !in_array($vote, [1,-1], true)) {
  echo json_encode(['ok'=>false,'message'=>'Bad request']); exit;
}

try {
  $st = $pdo->prepare("INSERT INTO qa_feedback (log_id, user_id, vote, reason) VALUES (?,?,?,?)");
  $st->execute([$log_id, $user['id']??null, $vote, $reason ?: null]);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'message'=>'DB error']); 
}

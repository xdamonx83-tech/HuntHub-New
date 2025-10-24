<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// keine Fehlmeldungen an den Browser, aber ins Error-Log
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
  require_once __DIR__ . '/../../auth/db.php';

  // --- Admin-Key laden (robust: ENV, Root, ai.openai) ---
  $cfg = [];
  try { $cfg = require __DIR__ . '/../../config.php'; } catch (Throwable $e) {}
  $validKey = getenv('QA_ADMIN_KEY')
           ?: ($cfg['qa_admin_key'] ?? null)
           ?: ($cfg['ai']['qa_admin_key'] ?? null)
           ?: ($cfg['ai']['openai']['qa_admin_key'] ?? null);

  $key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
  if (!$validKey || !hash_equals((string)$validKey, $key)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden','msg'=>'Invalid or missing admin key']);
    exit;
  }

  // Quick ping für schnellen Test
  if (isset($_GET['ping'])) {
    echo json_encode(['ok'=>true,'pong'=>true]);
    exit;
  }

  $pdo = db();

  // helper: existiert Tabelle?
  $has = function(string $t) use ($pdo): bool {
    try {
      $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
      $st->execute([$t]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
  };

  $stats = [
    'logs_total'      => 0,
    'logs_7d'         => 0,
    'avg_conf_last200'=> null,
    'feedback_total'  => 0,
    'feedback_pos'    => 0,
    'feedback_neg'    => 0,
    'notes'           => [],
  ];

  // --- qa_logs ---
  if ($has('qa_logs')) {
    try {
      $stats['logs_total'] = (int)$pdo->query("SELECT COUNT(*) FROM qa_logs")->fetchColumn();
      $stats['logs_7d']    = (int)$pdo->query("SELECT COUNT(*) FROM qa_logs WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
      $stats['avg_conf_last200'] = (float)$pdo->query("SELECT AVG(confidence) FROM (SELECT confidence FROM qa_logs ORDER BY id DESC LIMIT 200) t")->fetchColumn();
    } catch (Throwable $e) {
      $stats['notes'][] = 'qa_logs: ' . $e->getMessage();
    }
  } else {
    $stats['notes'][] = 'Tabelle qa_logs fehlt';
  }

  // --- qa_feedback ---
  if ($has('qa_feedback')) {
    try {
      $stats['feedback_total'] = (int)$pdo->query("SELECT COUNT(*) FROM qa_feedback")->fetchColumn();
      $stats['feedback_pos']   = (int)$pdo->query("SELECT COUNT(*) FROM qa_feedback WHERE vote = 1")->fetchColumn();
      $stats['feedback_neg']   = (int)$pdo->query("SELECT COUNT(*) FROM qa_feedback WHERE vote = -1")->fetchColumn();
    } catch (Throwable $e) {
      $stats['notes'][] = 'qa_feedback: ' . $e->getMessage();
    }
  } else {
    $stats['notes'][] = 'Tabelle qa_feedback fehlt';
  }

  echo json_encode(['ok'=>true,'stats'=>$stats]);

} catch (Throwable $e) {
  // Fallback: niemals 500 „weiß“ antworten
  error_log('[admin_stats] '.$e->getMessage());
  http_response_code(200);
  echo json_encode(['ok'=>false,'error'=>'internal','msg'=>'Admin stats failed', 'detail'=>$e->getMessage()]);
}

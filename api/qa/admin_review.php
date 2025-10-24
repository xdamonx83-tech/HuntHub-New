<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../../auth/db.php';
$cfg = require __DIR__.'/../../config/app.php';

function require_admin_key(array $cfg): void {
  $key = $_POST['key'] ?? $_GET['key'] ?? '';
  $adm = (string)($cfg['ai']['openai']['qa_admin_key'] ?? '');
  if ($adm === '' || !hash_equals($adm, (string)$key)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
  }
}
function sanitize_html(string $html): string {
  // kleine Allowlist
  $allowed = '<b><strong><i><em><u><br><p><ul><ol><li><a><code><pre>';
  return strip_tags($html, $allowed);
}

$pdo = db();
require_admin_key($cfg);

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

try {
  if ($action === 'list') {
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 30)));
    $sql = "SELECT l.id, l.lang, l.question, l.answer, l.citations, l.confidence, l.created_at,
                   COALESCE(SUM(f.vote),0) AS fb_score, COUNT(f.id) AS fb_count
            FROM qa_logs l
            LEFT JOIN qa_feedback f ON f.log_id = l.id
            GROUP BY l.id
            HAVING (l.confidence < 0.80 OR fb_score < 0)
            ORDER BY fb_score ASC, l.confidence ASC, l.created_at DESC
            LIMIT :lim";
    $st = $pdo->prepare($sql);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    echo json_encode(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
  }

  if ($action === 'mark_ok') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare("UPDATE qa_logs SET confidence=0.99 WHERE id=?");
    $st->execute([$id]);
    echo json_encode(['ok'=>true]); exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM qa_feedback WHERE log_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM qa_logs WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]); exit;
  }

  if ($action === 'promote_faq') {
    $id   = (int)($_POST['id'] ?? 0);
    $lang = $_POST['lang'] ?? 'de';
    $q    = trim((string)($_POST['question'] ?? ''));
    $tags = trim((string)($_POST['tags'] ?? 'hunthub;auto'));
    $prio = (int)($_POST['priority'] ?? 1);

    $row = $pdo->prepare("SELECT answer FROM qa_logs WHERE id=?");
    $row->execute([$id]);
    $html = (string)($row->fetchColumn() ?: '');
    if ($html==='') { echo json_encode(['ok'=>false,'error'=>'no_answer']); exit; }

    $html = sanitize_html($html);
    $st = $pdo->prepare("INSERT INTO qa_faq (lang,question,answer_html,tags,priority) VALUES (?,?,?,?,?)");
    $st->execute([$lang,$q,$html,$tags,$prio]);

    echo json_encode(['ok'=>true]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'unknown_action']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

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
  $allowed = '<b><strong><i><em><u><br><p><ul><ol><li><a><code><pre>';
  return strip_tags($html, $allowed);
}

$pdo = db();
require_admin_key($cfg);

$act = $_POST['action'] ?? $_GET['action'] ?? 'list';

try {
  if ($act === 'list') {
    $st = $pdo->query("SELECT id,lang,question,tags,priority,created_at FROM qa_faq ORDER BY priority ASC, id DESC LIMIT 200");
    echo json_encode(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
  }
  if ($act === 'create') {
    $lang = $_POST['lang'] ?? 'de';
    $q    = trim((string)($_POST['question'] ?? ''));
    $html = sanitize_html((string)($_POST['answer_html'] ?? ''));
    $tags = trim((string)($_POST['tags'] ?? 'hunthub'));
    $prio = (int)($_POST['priority'] ?? 1);
    $pdo->prepare("INSERT INTO qa_faq (lang,question,answer_html,tags,priority) VALUES (?,?,?,?,?)")
        ->execute([$lang,$q,$html,$tags,$prio]);
    echo json_encode(['ok'=>true]); exit;
  }
  if ($act === 'update') {
    $id   = (int)($_POST['id'] ?? 0);
    $lang = $_POST['lang'] ?? 'de';
    $q    = trim((string)($_POST['question'] ?? ''));
    $html = sanitize_html((string)($_POST['answer_html'] ?? ''));
    $tags = trim((string)($_POST['tags'] ?? ''));
    $prio = (int)($_POST['priority'] ?? 1);
    $pdo->prepare("UPDATE qa_faq SET lang=?,question=?,answer_html=?,tags=?,priority=? WHERE id=?")
        ->execute([$lang,$q,$html,$tags,$prio,$id]);
    echo json_encode(['ok'=>true]); exit;
  }
  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM qa_faq WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'unknown_action']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

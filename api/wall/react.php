<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
  require_once __DIR__ . '/../../auth/bootstrap.php';
  require_once __DIR__ . '/../../lib/wall_likes.php';
  require_once __DIR__ . '/../../auth/db.php';

  $me = require_auth_or_redirect('/wallguest.php');
  $uid = (int)$me['id'];

  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = $_POST['_action'] ?? $_GET['_action'] ?? '';

  // ---------- READ-ONLY: aktuelle Reaktion ohne Änderung ----------
  if ($method === 'GET' || $action === 'get') {
    $type = (string)($_GET['type'] ?? $_POST['type'] ?? 'post');
    $id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
    if ($id <= 0) throw new InvalidArgumentException('Invalid id');

    $db = db();
    $reaction = wall_user_reaction($db, $uid, $type, $id);
    $counts   = wall_reaction_count($db, $type, $id);
    $total    = array_sum($counts);

    echo json_encode(['ok'=>true, 'reaction'=>$reaction, 'counts'=>$counts, 'total'=>$total]);
    exit;
  }

  // ---------- WRITE: setzen/entfernen (POST) ----------
  require_once __DIR__ . '/../../auth/csrf.php';
  if (!check_csrf_request($_POST)) throw new RuntimeException('Invalid CSRF token');

  $type = (string)($_POST['type'] ?? 'post');
  $id   = (int)($_POST['id'] ?? 0);
  if ($id <= 0) throw new InvalidArgumentException('Invalid id');

  $db = db();

  // leere Zeichenkette = löschen; (reaction nicht gesetzt -> behandeln wie löschen)
  $reaction = array_key_exists('reaction', $_POST) ? (string)$_POST['reaction'] : '';

  $res = wall_reaction_set($db, $uid, $type, $id, $reaction);
  echo json_encode(['ok'=>true] + $res);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

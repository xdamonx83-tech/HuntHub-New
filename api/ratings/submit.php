<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../../auth/db.php';
require_once __DIR__.'/../../auth/guards.php';
require_once __DIR__.'/../../auth/csrf.php';

try {
  $pdo = db();
  $me  = require_auth();

  // JSON oder klassisches Form-POST akzeptieren
  $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ctype, 'application/json') !== false) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
  } else {
    $in = $_POST ?: [];
  }

  $ratee = (int)($in['ratee_id'] ?? 0);
  $play  = (int)($in['play'] ?? 0);
  $friendly = (int)($in['friendly'] ?? 0);
  $helpful  = (int)($in['helpful'] ?? 0);
  $comment  = trim((string)($in['comment'] ?? ''));

  if ($ratee <= 0) throw new RuntimeException('ratee_id fehlt');
  if ($ratee === (int)$me['id']) throw new RuntimeException('Selbstbewertung nicht erlaubt');
  foreach (['play'=>$play,'friendly'=>$friendly,'helpful'=>$helpful] as $k=>$v) {
    if ($v < 1 || $v > 6) throw new RuntimeException("$k 1..6");
  }

  $sql = "INSERT INTO user_ratings (rater_id, ratee_id, play, friendly, helpful, comment)
          VALUES (?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE
            play=VALUES(play), friendly=VALUES(friendly),
            helpful=VALUES(helpful), comment=VALUES(comment),
            updated_at=CURRENT_TIMESTAMP";
  $st = $pdo->prepare($sql);
  $st->execute([(int)$me['id'],$ratee,$play,$friendly,$helpful,$comment !== '' ? $comment : null]);

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

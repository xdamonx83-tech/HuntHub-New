<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

$pdo = db();
$me  = require_auth();
$my  = (int)$me['id'];
$q   = trim((string)($_GET['q'] ?? ''));

try {
  $convSql = "
    SELECT partner_id, MAX(id) AS last_msg_id, MAX(created_at) AS last_at
    FROM (
      SELECT recipient_id AS partner_id, id, created_at FROM messages WHERE sender_id = ?
      UNION ALL
      SELECT sender_id   AS partner_id, id, created_at FROM messages WHERE recipient_id = ?
    ) mm
    GROUP BY partner_id
  ";
  $st = $pdo->prepare($convSql);
  $st->execute([$my, $my]);
  $pairs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  if (!$pairs) { echo json_encode(['ok'=>true,'items'=>[]]); exit; }

  $ids = array_map(fn($r)=>(int)$r['partner_id'], $pairs);
  $ph  = implode(',', array_fill(0, count($ids), '?'));

  $sqlU = "SELECT id, display_name, avatar_path FROM users WHERE id IN ($ph)";
  $params = $ids;
  if ($q !== '') {
    $sqlU .= " AND display_name LIKE ?";
    $params[] = '%'.$q.'%';
  }
  $u = $pdo->prepare($sqlU);
  $u->execute($params);
  $users = [];
  foreach ($u->fetchAll(PDO::FETCH_ASSOC) as $row) { $users[(int)$row['id']] = $row; }

  $getBody   = $pdo->prepare("SELECT body FROM messages WHERE id = ?");
  $cntUnread = $pdo->prepare("
    SELECT COUNT(*) FROM messages
    WHERE sender_id = ? AND recipient_id = ? AND read_at IS NULL
  ");

  $items = [];
  foreach ($pairs as $r) {
    $uid = (int)$r['partner_id'];
    if (!isset($users[$uid])) continue;

    $getBody->execute([(int)$r['last_msg_id']]);
    $last = (string)($getBody->fetchColumn() ?: '');
    $label = trim(preg_replace('/\s+/', ' ', $last));
    if ($label !== '' && $label[0] === '{') {
      $j = json_decode($label, true);
      if (is_array($j) && ($j['type'] ?? '') === 'attach') {
        $kind  = strtolower((string)($j['kind'] ?? 'anhang'));
        $label = '[Anhang: '.$kind.']';
      }
    }

    $cntUnread->execute([$uid, $my]);
    $unread = (int)$cntUnread->fetchColumn();

    $items[] = [
      'user_id'      => $uid,
      'display_name' => $users[$uid]['display_name'],
      'avatar_path'  => $users[$uid]['avatar_path'],
      'last_text'    => $label,
      'unread'       => $unread,
    ];
  }

  // Wenn du zusätzlich nach „letzte Aktivität“ sortieren willst:
  usort($items, function($a, $b) {
    return ($b['unread'] <=> $a['unread']);
  });

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'db_error', 'detail'=>$e->getMessage()]);
}

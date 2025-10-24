<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

$pdo = db();
$me  = require_auth();

$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));

try {
  // === 1) recent_contacts.php „einbinden“ oder direkt abfragen ===
  // Falls recent_contacts.php alleinsteht und selbst JSON echo't,
  // ist includen unpraktisch. Dann die SQL hier spiegeln ODER
  // recent_contacts als Funktion auslagern.
  //
  // Beispiel: wir gehen davon aus, recent_contacts.php würde etwa
  // ['ok'=>true,'items'=>[ ... ]] liefern. Holen wir es direkt nach:

  // Wenn recent_contacts.php als Funktion existiert, wäre ideal:
  // $rows = recent_contacts($pdo, (int)$me['id'], $limit);

  // Für universell:
  $st = $pdo->prepare("
    SELECT partner_id, MAX(id) AS last_msg_id, MAX(created_at) AS last_at
    FROM (
      SELECT recipient_id AS partner_id, id, created_at FROM messages WHERE sender_id = ?
      UNION ALL
      SELECT sender_id   AS partner_id, id, created_at FROM messages WHERE recipient_id = ?
    ) mm
    GROUP BY partner_id
    ORDER BY last_at DESC
    LIMIT {$limit}
  ");
  $st->execute([(int)$me['id'], (int)$me['id']]);
  $pairs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  if (!$pairs) { echo json_encode(['ok'=>true,'items'=>[]]); exit; }

  $ids = array_map(fn($r)=>(int)$r['partner_id'], $pairs);
  $ph  = implode(',', array_fill(0, count($ids), '?'));
  $u = $pdo->prepare("SELECT id, display_name, avatar_path FROM users WHERE id IN ($ph)");
  $u->execute($ids);
  $lookup = [];
  foreach ($u->fetchAll(PDO::FETCH_ASSOC) as $row) $lookup[(int)$row['id']] = $row;

  // Unread zählen
  $cntUnread = $pdo->prepare("
    SELECT COUNT(*) FROM messages
    WHERE sender_id = ? AND recipient_id = ? AND read_at IS NULL
  ");

  $getBody = $pdo->prepare("SELECT body FROM messages WHERE id = ?");

  $items = [];
  foreach ($pairs as $r) {
    $uid = (int)$r['partner_id'];

    $getBody->execute([(int)$r['last_msg_id']]);
    $last = (string)($getBody->fetchColumn() ?: '');
    $label = trim(preg_replace('/\s+/', ' ', $last));
    if ($label !== '' && $label[0] === '{') {
      $j = json_decode($label, true);
      if (is_array($j) && ($j['type'] ?? '') === 'attach') {
        $label = '[Anhang: ' . strtolower((string)($j['kind'] ?? 'Datei')) . ']';
      }
    }

    $cntUnread->execute([$uid, (int)$me['id']]);
    $unread = (int)$cntUnread->fetchColumn();

    // === 2) Antwort im „alten“ JS-Format ===
    $items[] = [
      'peer_id'     => $uid,
      'peer_name'   => $lookup[$uid]['display_name'] ?? 'User',
      'peer_avatar' => $lookup[$uid]['avatar_path'] ?? null,
      'last_text'   => $label,
      'last_at'     => $r['last_at'],
      'last_msg_id' => (int)$r['last_msg_id'],
      'unread'      => $unread,
    ];
  }

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_error','detail'=>$e->getMessage()]);
}

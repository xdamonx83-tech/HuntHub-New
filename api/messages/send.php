<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method']); exit;
}

$pdo = db();
$me  = require_auth();
$cfg = require __DIR__ . '/../../auth/config.php';
$sn  = $cfg['cookies']['session_name'] ?? '';

// --- simples Rate-Limit: max. 20 Nachrichten pro 60 Sekunden ---
$WINDOW_SECONDS = 60;
$MAX_MESSAGES   = 20;
$cutoff = date('Y-m-d H:i:s', time() - $WINDOW_SECONDS);

$st = $pdo->prepare("SELECT COUNT(*) AS c FROM messages WHERE sender_id = :me AND created_at > :cutoff");
$st->execute([':me'=>$me['id'], ':cutoff'=>$cutoff]);
$cnt = (int)$st->fetchColumn();

if ($cnt >= $MAX_MESSAGES) {
  http_response_code(429);
  echo json_encode(['ok'=>false,'error'=>'rate_limit','detail'=>'Zu viele Nachrichten, bitte kurz warten.']);
  exit;
}
// --- Ende Rate-Limit ---

if (!check_csrf($pdo, $_COOKIE[$sn] ?? '', (string)($_POST['csrf'] ?? ''))) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}

$otherId = (int)($_POST['user_id'] ?? 0);
$body    = trim((string)($_POST['body'] ?? ''));

if ($otherId <= 0 || $otherId === (int)$me['id']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_target']); exit; }
if ($body === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'empty']); exit; }

// >4000 Zeichen auf 4000 kürzen (Default)
if (mb_strlen($body, 'UTF-8') > 4000) {
  $body = mb_substr($body, 0, 4000, 'UTF-8');
}

/**
 * Optional: Body ist Attachment-JSON (von der Chat-UI).
 * Wir härten es (nur erlaubte Keys / Pfade; Meta ggf. einkürzen).
 */
try {
  if ($body !== '' && $body[0] === '{') {
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (
      is_array($data) &&
      ($data['type'] ?? '') === 'attach' &&
      in_array(($data['kind'] ?? ''), ['image','video'], true) &&
      !empty($data['url'])
    ) {
      $url = (string)$data['url'];

      // nur eigene Upload-Pfade erlauben
      if (!str_starts_with($url, '/uploads/messages/')) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad_attach_url']);
        exit;
      }

      $clean = [
        'type' => 'attach',
        'kind' => (string)$data['kind'],
        'url'  => $url,
      ];

      // Meta whitelisten
      $meta = [];
      if (isset($data['meta']) && is_array($data['meta'])) {
        if (!empty($data['meta']['thumb']) && is_string($data['meta']['thumb']) && str_starts_with($data['meta']['thumb'], '/uploads/messages/')) {
          $meta['thumb'] = $data['meta']['thumb'];
        }
        if (isset($data['meta']['width']))    $meta['width']    = (int)$data['meta']['width'];
        if (isset($data['meta']['height']))   $meta['height']   = (int)$data['meta']['height'];
        if (isset($data['meta']['duration'])) $meta['duration'] = (float)$data['meta']['duration'];
      }
      if ($meta) $clean['meta'] = $meta;

      $json = json_encode($clean, JSON_UNESCAPED_SLASHES);
      if ($json === false) { throw new RuntimeException('json_encode_failed'); }

      // auf 4000 einpassen (Meta ggf. entfernen)
      if (mb_strlen($json, 'UTF-8') > 4000) {
        unset($clean['meta']);
        $json = json_encode($clean, JSON_UNESCAPED_SLASHES);
      }
      if ($json === false) { throw new RuntimeException('json_encode_failed'); }
      $body = $json;
    }
  }
} catch (Throwable $ignore) {
  // Wenn JSON kaputt ist: einfach als Text durchlassen (bereits oben gekürzt)
}

try {
  $st = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)");
  $st->execute([(int)$me['id'], $otherId, $body]);
  $id = (int)$pdo->lastInsertId();

  $st2 = $pdo->prepare("SELECT id, sender_id, recipient_id, body, created_at FROM messages WHERE id=?");
  $st2->execute([$id]);
  echo json_encode(['ok'=>true, 'message'=>$st2->fetch(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'sql', 'detail'=>$e->getMessage()]);
}

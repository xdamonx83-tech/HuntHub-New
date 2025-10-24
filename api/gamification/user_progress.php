<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
  }

  $pdo = db();
  $me  = require_auth();

  // Stats (Fallbacks, wenn noch keine Zeile existiert)
  $st = $pdo->prepare("
    SELECT threads_count, posts_count, likes_given, likes_received, friends_count, points_total
    FROM user_stats WHERE user_id = ?
  ");
  $st->execute([(int)$me['id']]);
  $stats = $st->fetch(PDO::FETCH_ASSOC) ?: [
    'threads_count'=>0,'posts_count'=>0,'likes_given'=>0,'likes_received'=>0,'friends_count'=>0,'points_total'=>0
  ];

  // Alle aktiven Achievements + ob schon freigeschaltet
  $q = $pdo->prepare("
    SELECT a.id, a.`key`, a.title, a.description, a.icon, a.rule_stat, a.threshold, ua.unlocked_at
    FROM achievements a
    LEFT JOIN user_achievements ua
      ON ua.user_id = :uid AND ua.achievement_id = a.id
    WHERE a.is_active = 1
    ORDER BY a.rule_stat, a.threshold
  ");
  $q->execute([':uid' => (int)$me['id']]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $items = [];
  foreach ($rows as $r) {
    $current = (int)($stats[$r['rule_stat']] ?? 0);
    $need    = max(1, (int)$r['threshold']);
    $pct     = (int)floor(min(100, ($current / $need) * 100));
    $items[] = [
      'id'          => (int)$r['id'],
      'key'         => (string)$r['key'],
      'title'       => (string)$r['title'],
      'description' => (string)$r['description'],
      'icon'        => (string)($r['icon'] ?? ''),
      'rule_stat'   => (string)$r['rule_stat'],
      'current'     => $current,
      'threshold'   => $need,
      'percent'     => $pct,
      'unlocked_at' => $r['unlocked_at'] ?: null,
      'unlocked'    => (bool)$r['unlocked_at'],
    ];
  }

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'internal_error']);
}

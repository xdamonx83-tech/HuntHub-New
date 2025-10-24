<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';

/**
 * Stellt sicher, dass es eine Stats-Zeile für den Nutzer gibt.
 */
function gamify_ensure_stats(PDO $pdo, int $userId): void {
  $pdo->prepare("INSERT IGNORE INTO user_stats (user_id) VALUES (?)")->execute([$userId]);
}

/**
 * Erhöht/Verringert einen Zähler und gibt den neuen Wert zurück.
 * Erlaubte $stat: threads_count, posts_count, likes_given, likes_received, friends_count, points_total
 */
function gamify_bump(PDO $pdo, int $userId, string $stat, int $delta=1): int {
  gamify_ensure_stats($pdo, $userId);
  $allowed = ['threads_count','posts_count','likes_given','likes_received','friends_count','points_total'];
  if (!in_array($stat, $allowed, true)) throw new InvalidArgumentException('invalid stat');
  $pdo->prepare("UPDATE user_stats SET {$stat}=GREATEST({$stat}+?,0), updated_at=NOW() WHERE user_id=?")
      ->execute([$delta, $userId]);
  $st = $pdo->prepare("SELECT {$stat} FROM user_stats WHERE user_id=?");
  $st->execute([$userId]);
  return (int)$st->fetchColumn();
}

/** Platzhalter, falls später doch WS genutzt wird. */
if (!function_exists('ws_push_notify')) {
  function ws_push_notify(int $recipientId, array $payload, string $event='notify'): void { /* no-op */ }
}

/**
 * Prüft alle aktiven Achievements zum $event, schaltet fehlende frei
 * und gibt NEU freigeschaltete als Liste zurück (für Popups im Frontend).
 */
function gamify_check(PDO $pdo, int $userId, string $event): array {
  $st = $pdo->prepare("SELECT * FROM achievements WHERE is_active=1 AND rule_event=? ORDER BY threshold ASC");
  $st->execute([$event]);
  $achs = $st->fetchAll(PDO::FETCH_ASSOC);
  if (!$achs) return [];

  gamify_ensure_stats($pdo, $userId);
  $rs = $pdo->prepare("
    SELECT threads_count, posts_count, likes_given, likes_received, friends_count, points_total
    FROM user_stats WHERE user_id=?
  ");
  $rs->execute([$userId]);
  $s = $rs->fetch(PDO::FETCH_ASSOC) ?: [];

  $unlockedNow = [];
  foreach ($achs as $a) {
    $stat = (string)$a['rule_stat'];
    $need = (int)$a['threshold'];
    $have = (int)($s[$stat] ?? 0);
    if ($have < $need) continue;

    // bereits freigeschaltet?
    $chk = $pdo->prepare("SELECT 1 FROM user_achievements WHERE user_id=? AND achievement_id=?");
    $chk->execute([$userId, (int)$a['id']]);
    if ($chk->fetchColumn()) continue;

    // freischalten
    $pdo->prepare("INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?,?,NOW())")
        ->execute([$userId, (int)$a['id']]);

    // Punkte gutschreiben
    $pts = (int)$a['points'];
    if ($pts > 0) gamify_bump($pdo, $userId, 'points_total', $pts);

    $unlockedNow[] = [
      'type'        => 'achievement_unlocked',
      'title'       => (string)$a['title'],
      'description' => (string)$a['description'],
      'icon'        => (string)($a['icon'] ?? ''),
      'points'      => $pts,
      'key'         => (string)$a['key'],
    ];
  }
  return $unlockedNow;
}

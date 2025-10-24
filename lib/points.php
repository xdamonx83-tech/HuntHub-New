<?php
declare(strict_types=1);

/**
 * Source of truth: user_points.points
 * - get_user_points(): SELECT points FROM user_points WHERE user_id=?
 * - award_points():    INSERT into points_transactions (delta) + UPDATE user_points.points
 *   ➜ erkennt laufende Transaktionen und verschachtelt NICHT erneut.
 */

function get_user_points(PDO $pdo, int $userId): int {
  try {
    $st = $pdo->prepare("SELECT points FROM user_points WHERE user_id=? LIMIT 1");
    $st->execute([$userId]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? 0 : (int)$v;
  } catch (Throwable $e) {
    error_log('get_user_points(): '.$e->getMessage());
    return 0;
  }
}

/**
 * $delta > 0  => Gutschrift
 * $delta < 0  => Abbuchung
 *
 * Erwartet:
 *   - points_transactions(user_id, delta, reason, meta NULLABLE)
 *   - user_points(user_id PK/UNIQUE, points INT)
 *
 * Transaktionen:
 *   - Wenn bereits eine Transaktion läuft ($pdo->inTransaction() === true),
 *     werden KEIN begin/commit/rollback in dieser Funktion gemacht.
 *   - Ansonsten verwaltet die Funktion ihre eigene Transaktion.
 */
function award_points(PDO $pdo, int $userId, string $reason, int $delta, ?array $meta=null): array {
  if ($delta === 0) {
    return ['ok'=>true, 'balance'=>get_user_points($pdo, $userId)];
  }

  $outerTxn = $pdo->inTransaction(); // true, wenn z. B. buy.php schon BEGIN gemacht hat
  try {
    if (!$outerTxn) {
      $pdo->beginTransaction();
    }

    // 1) Transaktion loggen (falls Tabelle vorhanden – wenn nicht: überspringen)
    try {
      $hasMeta = false;
      try {
        $chk = $pdo->query("SHOW COLUMNS FROM `points_transactions` LIKE 'meta'");
        $hasMeta = (bool)$chk->fetchColumn();
      } catch (Throwable) { /* ignore */ }

      if ($hasMeta) {
        $st = $pdo->prepare("INSERT INTO points_transactions (user_id, delta, reason, meta) VALUES (?,?,?,?)");
        $st->execute([$userId, $delta, $reason, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
      } else {
        $st = $pdo->prepare("INSERT INTO points_transactions (user_id, delta, reason) VALUES (?,?,?)");
        $st->execute([$userId, $delta, $reason]);
      }
    } catch (Throwable $e) {
      // Tabelle existiert nicht? Kein Problem – wir buchen nur das Guthaben
      error_log('award_points(): tx log skipped -> '.$e->getMessage());
    }

    // 2) user_points updaten/anlegen
    try {
      $pdo->prepare(
        "INSERT INTO user_points (user_id, points) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE points = points + VALUES(points)"
      )->execute([$userId, $delta]);
    } catch (Throwable $e) {
      // Fallback ohne Unique Key
      $st = $pdo->prepare("SELECT points FROM user_points WHERE user_id=? LIMIT 1");
      $st->execute([$userId]);
      if ($st->fetchColumn() === false) {
        $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, ?)")->execute([$userId, $delta]);
      } else {
        $pdo->prepare("UPDATE user_points SET points = points + ? WHERE user_id=?")->execute([$delta, $userId]);
      }
    }

    if (!$outerTxn) {
      $pdo->commit();
    }

    return ['ok'=>true, 'balance'=>get_user_points($pdo, $userId)];
  } catch (Throwable $e) {
    if (!$outerTxn && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('award_points(): '.$e->getMessage());
    return ['ok'=>false, 'error'=>'server_error', 'msg'=>$e->getMessage()];
  }
}

function get_points_mapping(): array {
  return [
    'referral'       => 20,
    'signup'         => 10,
    'shop_purchase'  => 0, // (Abbuchung = -Preis)
    'daily_login'    => 1,
    'forum_post'     => 7,
    'forum_comment'  => 3,
  ];
}

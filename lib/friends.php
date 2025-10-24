<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';

const FRIEND_STATUS_PENDING   = 'pending';
const FRIEND_STATUS_ACCEPTED  = 'accepted';
const FRIEND_STATUS_DECLINED  = 'declined';
const FRIEND_STATUS_CANCELLED = 'cancelled';
const FRIEND_STATUS_BLOCKED   = 'blocked';

/** Liefert den Friendship-Datensatz (richtungsunabhängig) oder null. */
function get_friendship(PDO $pdo, int $a, int $b): ?array {
  if ($a <= 0 || $b <= 0) return null;
  $st = $pdo->prepare("
    SELECT *
    FROM friendships
    WHERE pair_key = CONCAT(LEAST(?,?), ':', GREATEST(?,?))
    LIMIT 1
  ");
  $st->execute([$a,$b,$a,$b]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/**
 * Ermittelt den Status aus Sicht von $me gegenüber $other:
 * - none
 * - pending_outgoing (ich habe angefragt)
 * - pending_incoming (der andere hat angefragt)
 * - friends
 * - declined / cancelled / blocked
 */
function friendship_status(PDO $pdo, int $me, int $other): string {
  $row = get_friendship($pdo, $me, $other);
  if (!$row) return 'none';
  $status = (string)$row['status'];
  if ($status === FRIEND_STATUS_PENDING) {
    return ((int)$row['requester_id'] === $me) ? 'pending_outgoing' : 'pending_incoming';
  }
  if ($status === FRIEND_STATUS_ACCEPTED) return 'friends';
  return $status; // declined / cancelled / blocked
}

/** Liste der Freunde (status=accepted), gibt User-Objekte mit joined_at zurück. */
function list_friends(PDO $pdo, int $uid, int $limit = 200, int $offset = 0): array {
  $sql = "
    SELECT u.id, u.display_name, u.slug, u.avatar_path, f.updated_at AS friends_since
    FROM friendships f
    JOIN users u
      ON (u.id = CASE WHEN f.requester_id = ? THEN f.addressee_id ELSE f.requester_id END)
    WHERE (f.requester_id = ? OR f.addressee_id = ?)
      AND f.status = 'accepted'
    ORDER BY f.updated_at DESC
    LIMIT ? OFFSET ?
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$uid, $uid, $uid, $limit, $offset]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

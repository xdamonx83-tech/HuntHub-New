<?php
declare(strict_types=1);

require_once __DIR__ . '/friends.php';
require_once __DIR__ . '/settings.php';

/** Darf $from $to anschreiben? */
function can_message(PDO $pdo, int $fromUserId, int $toUserId): bool {
  if ($fromUserId <= 0 || $toUserId <= 0 || $fromUserId === $toUserId) return false;
  $policy = get_message_policy($pdo, $toUserId);
  if ($policy === 'everyone') return true;
  // friends_only
  return friendship_status($pdo, $fromUserId, $toUserId) === 'friends';
}

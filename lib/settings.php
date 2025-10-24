<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';

function get_message_policy(PDO $pdo, int $userId): string {
  $st = $pdo->prepare("SELECT message_policy FROM user_settings WHERE user_id = ? LIMIT 1");
  $st->execute([$userId]);
  $val = (string)($st->fetchColumn() ?: '');
  return in_array($val, ['friends_only','everyone'], true) ? $val : 'friends_only';
}

function set_message_policy(PDO $pdo, int $userId, string $policy): bool {
  if (!in_array($policy, ['friends_only','everyone'], true)) return false;
  $st = $pdo->prepare("
    INSERT INTO user_settings (user_id, message_policy)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE message_policy = VALUES(message_policy), updated_at = CURRENT_TIMESTAMP
  ");
  return $st->execute([$userId, $policy]);
}

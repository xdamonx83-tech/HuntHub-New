<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';
if (file_exists(__DIR__ . '/lib.php')) require_once __DIR__ . '/lib.php';
else require_once __DIR__ . '/../tournaments/lib.php';
function bug_status_label(string $s): string {
  return match($s) {
    'open'    => 'Offen',
    'waiting' => 'Wartet',
    'closed'  => 'Geschlossen',
    default   => ucfirst($s),
  };
}
function bug_priority_label(string $p): string {
  return match($p) {
    'low'    => 'Niedrig',
    'medium' => 'Mittel',
    'high'   => 'Hoch',
    'urgent' => 'Dringend',
    default  => ucfirst($p),
  };
}
function bug_can_view(array $me, array $bug): bool {
  if ((int)$bug['user_id'] === (int)($me['id'] ?? 0)) return true;
  return !empty($me['role']) && in_array($me['role'], ['admin','moderator'], true);
}
function bug_is_admin(array $me): bool {
  return !empty($me['role']) && in_array($me['role'], ['admin','moderator'], true);
}
function bug_kind_from_mime(string $mime): string {
  if (str_starts_with($mime, 'image/')) return 'image';
  if (str_starts_with($mime, 'video/')) return 'video';
  return 'other';
}

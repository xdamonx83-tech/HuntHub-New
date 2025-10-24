<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';

const HH_REPORT_THRESHOLD = 3;

function wall_report_reasons(): array {
  return [
    'harassment' => 'Mobbing / Belästigung / Missbrauch',
    'selfharm'   => 'Suizid oder Selbstverletzung',
    'violence'   => 'Gewalt/Hass/verstörend',
    'scam'       => 'Scam/Betrug/Fehlinfo',
    'copyright'  => 'Urheberrechtsverletzung',
    'adult'      => 'Nicht jugendfrei',
    'under18'    => 'Minderjährige involviert',
    'illegal'    => 'Rechtswidrig',
    'dislike'    => 'Gefällt mir nicht',
  ];
}

function wall_mark_under_review(PDO $db, string $type, int $id): void {
  $tbl = $type === 'post' ? 'wall_posts' : 'wall_comments';
  $db->prepare("UPDATE {$tbl} SET under_review_at = IFNULL(under_review_at, NOW()) WHERE id=?")->execute([$id]);
}
function wall_clear_under_review(PDO $db, string $type, int $id): void {
  $tbl = $type === 'post' ? 'wall_posts' : 'wall_comments';
  $db->prepare("UPDATE {$tbl} SET under_review_at = NULL WHERE id=?")->execute([$id]);
}

function wall_submit_report(PDO $db, string $type, int $entityId, int $reporterId, string $reason, ?string $note): array {
  if ($type === 'post') {
    $db->prepare("INSERT INTO wall_post_reports (post_id, reporter_id, reason, note)
                  VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE reason=VALUES(reason), note=VALUES(note), resolution='open', created_at=NOW()")
       ->execute([$entityId, $reporterId, $reason, $note]);
    $cnt = (int)$db->query("SELECT COUNT(*) FROM wall_post_reports WHERE post_id={$entityId} AND resolution='open'")->fetchColumn();
  } else {
    $db->prepare("INSERT INTO wall_comment_reports (comment_id, reporter_id, reason, note)
                  VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE reason=VALUES(reason), note=VALUES(note), resolution='open', created_at=NOW()")
       ->execute([$entityId, $reporterId, $reason, $note]);
    $cnt = (int)$db->query("SELECT COUNT(*) FROM wall_comment_reports WHERE comment_id={$entityId} AND resolution='open'")->fetchColumn();
  }
  $under = false;
  if ($cnt >= HH_REPORT_THRESHOLD) { wall_mark_under_review($db, $type, $entityId); $under = true; }
  return ['count'=>$cnt, 'under_review'=>$under];
}

function wall_admin_clear_reports(PDO $db, string $type, int $entityId, int $adminId): void {
  if ($type === 'post') {
    $db->prepare("UPDATE wall_post_reports SET resolution='cleared', resolved_at=NOW(), resolved_by=? WHERE post_id=? AND resolution='open'")
       ->execute([$adminId, $entityId]);
  } else {
    $db->prepare("UPDATE wall_comment_reports SET resolution='cleared', resolved_at=NOW(), resolved_by=? WHERE comment_id=? AND resolution='open'")
       ->execute([$adminId, $entityId]);
  }
  wall_clear_under_review($db, $type, $entityId);
}

function wall_admin_remove_entity(PDO $db, string $type, int $entityId, int $adminId): void {
  if ($type === 'post') {
    $db->prepare("UPDATE wall_posts SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL")->execute([$entityId]);
    $db->prepare("UPDATE wall_comments SET deleted_at=NOW() WHERE post_id=? AND deleted_at IS NULL")->execute([$entityId]);
    $db->prepare("UPDATE wall_post_reports SET resolution='removed', resolved_at=NOW(), resolved_by=? WHERE post_id=? AND resolution='open'")
       ->execute([$adminId, $entityId]);
  } else {
    $db->prepare("UPDATE wall_comments SET content_plain=NULL, content_html=NULL, content_deleted_at=NOW(), content_deleted_by=? WHERE id=?")
       ->execute([$adminId, $entityId]);
    $db->prepare("UPDATE wall_comment_reports SET resolution='removed', resolved_at=NOW(), resolved_by=? WHERE comment_id=? AND resolution='open'")
       ->execute([$adminId, $entityId]);
  }
}

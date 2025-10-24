<?php
declare(strict_types=1);

function notify(PDO $pdo, int $recipientId, int $actorId, string $type, string $objectType, int $objectId, array $meta = []): void {
  if ($recipientId === $actorId) return; // kein Self-Notify
  $st = $pdo->prepare("
    INSERT INTO notifications (user_id, actor_id, type, object_type, object_id, meta_json, created_at)
    VALUES (:uid, :actor, :type, :otype, :oid, :meta, NOW())
  ");
  $st->execute([
    ':uid'   => $recipientId,
    ':actor' => $actorId,
    ':type'  => $type,
    ':otype' => $objectType,
    ':oid'   => $objectId,
    ':meta'  => json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  ]);
}

function remove_like_notification(PDO $pdo, int $recipientId, int $actorId, string $type, string $objectType, int $objectId): void {
  $st = $pdo->prepare("
    DELETE FROM notifications
    WHERE user_id=:uid AND actor_id=:actor AND type=:type AND object_type=:otype AND object_id=:oid
  ");
  $st->execute([
    ':uid'=>$recipientId, ':actor'=>$actorId, ':type'=>$type, ':otype'=>$objectType, ':oid'=>$objectId,
  ]);
}

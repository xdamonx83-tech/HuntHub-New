<?php
AND (revoked_at IS NULL)
AND muted_until > NOW()");
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
return $row ?: null;
}


function hh_is_user_muted(int $userId): bool {
return hh_mute_get_active($userId) !== null;
}


/**
* Bricht mit 403 JSON ab, wenn der Nutzer aktuell gemutet ist.
*/
function require_not_muted_or_fail(int $userId): void {
$mute = hh_mute_get_active($userId);
if ($mute) {
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
'ok' => false,
'error' => 'muted',
'message' => 'Du bist derzeit gemutet und kannst diese Aktion nicht ausführen.',
'muted_until' => $mute['muted_until'] ?? null,
'kind' => $mute['kind'] ?? null,
], JSON_UNESCAPED_UNICODE);
exit;
}
}


function hh_warning_add(int $userId, ?string $reason, int $createdBy): int {
$pdo = db();
$stmt = $pdo->prepare("INSERT INTO warnings (user_id, reason, created_by) VALUES (?, ?, ?)");
$stmt->execute([$userId, $reason, $createdBy]);
return (int)$pdo->lastInsertId();
}


function hh_warning_count_open(int $userId): int {
$pdo = db();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM warnings WHERE user_id = ? AND cleared_at IS NULL");
$stmt->execute([$userId]);
return (int)$stmt->fetchColumn();
}


function hh_mute_create(int $userId, int $minutes, ?string $reason, string $kind, int $createdBy): int {
$pdo = db();
$stmt = $pdo->prepare("INSERT INTO user_mutes (user_id, kind, reason, muted_until, active, created_by)
VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 1, ?)");
$stmt->execute([$userId, $kind, $reason, $minutes, $createdBy]);
return (int)$pdo->lastInsertId();
}


function hh_mute_revoke_active_for_user(int $userId, int $by): int {
$pdo = db();
$stmt = $pdo->prepare("UPDATE user_mutes
SET active = 0, revoked_at = NOW(), reason = IFNULL(reason, '')
WHERE user_id = ? AND active = 1 AND revoked_at IS NULL");
$stmt->execute([$userId]);
return $stmt->rowCount();
}


function hh_mute_revoke_by_id(int $muteId, int $by): int {
$pdo = db();
$stmt = $pdo->prepare("UPDATE user_mutes
SET active = 0, revoked_at = NOW()
WHERE id = ? AND active = 1 AND revoked_at IS NULL");
$stmt->execute([$muteId]);
return $stmt->rowCount();
}


/**
* Wenn genügend Verwarnungen offen sind, erstelle (falls noch nicht vorhanden) automatisch einen Mute.
*/
function hh_auto_mute_if_threshold(int $userId, int $createdBy, int $threshold = HH_WARN_THRESHOLD, int $autoMinutes = HH_AUTO_MUTE_MINUTES_DEFAULT): ?int {
$open = hh_warning_count_open($userId);
if ($open >= $threshold) {
if (!hh_is_user_muted($userId)) {
$reason = sprintf('Automatische Sperre: %d Verwarnungen', $open);
return hh_mute_create($userId, $autoMinutes, $reason, 'auto', $createdBy);
}
}
return null;
}
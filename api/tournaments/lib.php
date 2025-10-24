<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
}
function json_ok(array $data = []): void { json_out(['ok' => true] + $data, 200); }
function json_err(string $msg, int $code = 400, array $extra = []): void { json_out(['ok' => false, 'error' => $msg] + $extra, $code); }


function verify_csrf_if_available(): void {
if (function_exists('verify_csrf')) {
try { verify_csrf(); } catch (Throwable $e) { json_err('bad_csrf', 403); }
}
}


function require_admin_user(): array {
$me = require_auth();
$role = $me['role'] ?? 'user';
if (!in_array($role, ['administrator','moderator'], true)) json_err('forbidden', 403);
return $me;
}


function slugify(string $s): string {
$s = trim($s);
$s = mb_strtolower($s);
$s = preg_replace('~[^a-z0-9]+~u', '-', $s);
$s = trim($s, '-');
return $s !== '' ? $s : bin2hex(random_bytes(3));
}


function get_tournament(PDO $pdo, int $id): ?array {
$st = $pdo->prepare('SELECT * FROM tournaments WHERE id=?');
$st->execute([$id]);
$t = $st->fetch(PDO::FETCH_ASSOC);
return $t ?: null;
}


function ensure_team_member(PDO $pdo, int $teamId, int $userId): bool {
$st = $pdo->prepare('SELECT 1 FROM tournament_team_members WHERE tournament_team_id=? AND user_id=?');
$st->execute([$teamId, $userId]);
return (bool)$st->fetchColumn();
}


function compute_points_arr(array $run, array $sc): int {
$p = 0;
$p += (int)($run['kills'] ?? 0) * (int)($sc['kill'] ?? 1);
$p += (int)($run['boss_kills'] ?? 0) * (int)($sc['boss'] ?? 3);
$p += (int)($run['tokens'] ?? 0) * (int)($sc['token'] ?? 5);
if (!empty($run['gauntlet'])) $p += (int)($sc['gauntlet'] ?? 10);
$p += (int)($run['deaths'] ?? 0) * (int)($sc['death'] ?? -5);
return $p;
}


function can_submit_run(array $t): bool {
if (!in_array($t['status'], ['running','locked'], true)) return false;
$now = new DateTimeImmutable('now');
$start = new DateTimeImmutable($t['starts_at']);
$end = new DateTimeImmutable($t['ends_at']);
return $now >= $start && $now <= $end;
}


function store_upload_image_to(string $baseAbs, string $baseRel, array $file, array $allowed = ['png','jpg','jpeg','webp']): string {
if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) json_err('no_file', 400);
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) json_err('bad_filetype', 400);
if (!is_dir($baseAbs)) mkdir($baseAbs, 0775, true);
$fname = bin2hex(random_bytes(8)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $baseAbs . $fname)) json_err('upload_failed', 500);
return rtrim($baseRel, '/') . '/' . $fname;
}


function notify_safe(int $userId, string $type, array $payload = []): void {
$lib = __DIR__ . '/../notifications/lib_notify.php';
if (is_file($lib)) {
require_once $lib;
if (function_exists('notify_user')) {
try { notify_user($userId, $type, $payload); } catch (Throwable $e) { /* noop */ }
}
}
}
<?php /* ====== /api/_bootstrap.php ======================================= */
declare(strict_types=1);


// TEMP: Fehlersichtbarkeit aktivieren, bis alles läuft
error_reporting(E_ALL);
ini_set('display_errors','1');


header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../auth/csrf.php';


function db_conn(): PDO { return db(); }


function json_ok(array $data = [], int $http = 200): never {
http_response_code($http);
echo json_encode(array_merge(['ok'=>true], $data), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
exit;
}
function json_err(string $error, int $http = 400, array $extra = []): never {
http_response_code($http);
echo json_encode(array_merge(['ok'=>false,'error'=>$error], $extra), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
exit;
}
function require_admin_user(): array {
$me = require_admin(); // nutzt deine bestehende guards.php
return is_array($me) ? $me : (array)$me;
}
function verify_csrf_if_available(PDO $pdo): void {
$token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
if (function_exists('verify_csrf')) {
if (!verify_csrf($pdo, $token)) json_err('bad_csrf', 403);
}
}
function slugify(string $s): string {
$s = strtolower(trim($s));
$s = preg_replace('~[^a-z0-9]+~u','-',$s);
$s = trim($s,'-');
return $s ?: 'tournament';
}


/* ====== /api/tournaments/ping.php ====================================== */
?>
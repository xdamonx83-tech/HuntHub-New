<?php
declare(strict_types=1);

/**
 * Gemeinsame Helpers für /api/bugs/*
 * – bindet Auth/DB/CSRF ein
 * – json_ok/json_err
 * – verify_csrf_if_available (wie in deinem Script)
 * – db_has_col() um optionale Spalten (z. B. xp_awarded, badge_code) sauber zu prüfen
 */

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

if (!function_exists('json_ok')) {
  function json_ok(array $payload = []): void {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true] + $payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('json_err')) {
  function json_err(string $error, int $code=400, array $extra=[]): void {
    if (!headers_sent()) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['ok'=>false,'error'=>$error] + $extra, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('verify_csrf_if_available')) {
  function verify_csrf_if_available(): void {
    foreach (['verify_csrf','enforce_csrf','csrf_require','require_csrf','assert_csrf'] as $fn) {
      if (function_exists($fn)) { $fn(); return; }
    }
    $hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
    if ($hdr === '') json_err('csrf_missing', 400);
  }
}

/** prüft, ob eine Spalte existiert (case-insensitive) */
// Alte Version entfernen/überschreiben!
function db_has_col(PDO $pdo, string $table, string $col): bool {
  static $cache = [];
  $key = strtolower($table).'|'.strtolower($col);
  if (array_key_exists($key, $cache)) return $cache[$key];

  try {
    // Sichere Variante mit INFORMATION_SCHEMA (hier gehen Platzhalter)
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$table, $col]);
    $ok = (bool)$st->fetchColumn();
    return $cache[$key] = $ok;
  } catch (Throwable $e) {
    // Fallback: DESCRIBE und lokal prüfen (keine Platzhalter nötig)
    try {
      $cols = [];
      foreach ($pdo->query("DESCRIBE `".str_replace("`","``",$table)."`", PDO::FETCH_ASSOC) as $r) {
        $cols[strtolower($r['Field'] ?? '')] = true;
      }
      return $cache[$key] = isset($cols[strtolower($col)]);
    } catch (Throwable $e2) {
      // Letzter Ausweg: "weiß nicht" -> false
      return $cache[$key] = false;
    }
  }
}
/** Liefert den Spaltennamen für den Ticket-Eigentümer in bugs (z.B. user_id, created_by, reporter_id, owner_id, author_id, uid) */
function bug_owner_col(PDO $pdo): string {
static $cache = null;
if ($cache !== null) return $cache;


// Kandidaten in sinnvoller Reihenfolge
$cands = ['user_id','created_by','reporter_id','owner_id','author_id','uid'];
$have = [];


$st = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bugs'");
$st->execute();
foreach ($st->fetchAll(PDO::FETCH_COLUMN, 0) as $c) {
$have[strtolower($c)] = $c; // Originalschreibweise merken
}
foreach ($cands as $c) {
if (isset($have[$c])) return $cache = $have[$c];
}
// Kein Match gefunden
return $cache = '';
}


/** Wirf klaren Fehler, wenn es keine Owner‑Spalte gibt */
function bug_require_owner_col(PDO $pdo): string {
$col = bug_owner_col($pdo);
if ($col === '') {
json_err('bugs_owner_column_missing', 500, [
'table' => 'bugs',
'hint' => 'Erwarte z.B. user_id/created_by/reporter_id/owner_id/author_id/uid'
]);
}
return $col;
}
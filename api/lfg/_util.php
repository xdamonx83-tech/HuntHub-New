<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';

header('Content-Type: application/json; charset=utf-8');

function lfg_json($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(function(Throwable $e){
    // Einheitliche JSON-Fehlerantwort statt HTML
    lfg_json(['ok'=>false, 'error'=>'server_error', 'message'=>$e->getMessage()], 500);
});
set_error_handler(function($severity,$message,$file,$line){
    // Wandelt PHP-Warnings/Notices in Exceptions -> oben gefangen
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function lfg_json($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function lfg_require_auth(): array {
    $me = require_auth(); // liefert user-array oder beendet mit 401
    if (empty($me['id'])) lfg_json(['ok'=>false,'error'=>'unauthorized'], 401);
    return $me;
}

function lfg_int($v, ?int $min=null, ?int $max=null): ?int {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    $i = (int)$v;
    if ($min !== null && $i < $min) return $min;
    if ($max !== null && $i > $max) return $max;
    return $i;
}

function lfg_num($v, ?float $min=null, ?float $max=null): ?float {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    $f = (float)$v;
    if ($min !== null && $f < $min) return $min;
    if ($max !== null && $f > $max) return $max;
    return $f;
}

function lfg_in(string $v, array $allowed, string $default): string {
    return in_array($v, $allowed, true) ? $v : $default;
}

function lfg_bool($v): int { return ($v==='1' || $v===1 || $v===true || $v==='true') ? 1 : 0; }

function lfg_clean_csv(?string $s): ?string {
    if (!$s) return null;
    $parts = array_filter(array_map(fn($x)=>trim($x), explode(',', $s)));
    $parts = array_unique($parts);
    return $parts ? implode(',', $parts) : null;
}

/** ruft safe user public fields */
function lfg_public_user(array $row): array {
    return [
        'id' => (int)$row['id'],
        'display_name' => $row['display_name'] ?? ('User#'.$row['id']),
        'avatar_path' => $row['avatar_path'] ?? null,
        'slug' => $row['slug'] ?? null,
    ];
}

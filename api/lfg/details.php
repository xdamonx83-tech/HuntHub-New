<?php
declare(strict_types=1);

/**
 * /api/lfg/details.php – harte JSON-API (robust, keine HTML-Ausgaben)
 */

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'server_error', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function($severity,$message,$file,$line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../../auth/db.php';
$pdo = db();

// Ping
if (isset($_GET['__ping'])) {
    echo json_encode(['ok'=>true, 'pong'=>true, 'route'=>'/api/lfg/details.php']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok'=>false, 'error'=>'invalid_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tabellen vorhanden?
foreach (['lfg_posts','users'] as $tbl) {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl));
    if (!$st || !$st->fetchColumn()) {
        echo json_encode(['ok'=>false, 'error'=>'missing_tables', 'missing'=>$tbl], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Nur sichere/übliche Spalten aus users selektieren
$sql = "SELECT p.*,
               u.id AS u_id,
               u.display_name,
               u.avatar_path,
               u.slug
        FROM lfg_posts p
        JOIN users u ON u.id = p.user_id
        WHERE p.id=:id
          AND p.visible=1
          AND (p.expires_at IS NULL OR p.expires_at > NOW())";
$st = $pdo->prepare($sql);
$st->execute([':id'=>$id]);
$r = $st->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    echo json_encode(['ok'=>false, 'error'=>'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = [
    'id'           => (int)$r['u_id'],
    'display_name' => $r['display_name'] ?? ('User#'.$r['u_id']),
    'avatar_path'  => $r['avatar_path'] ?? null,
    'slug'         => $r['slug'] ?? null,
];

// User-Spalten aus dem Post entfernen
unset($r['u_id'], $r['display_name'], $r['avatar_path'], $r['slug']);

echo json_encode(['ok'=>true, 'post'=>$r, 'user'=>$user], JSON_UNESCAPED_UNICODE);

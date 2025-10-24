<?php
declare(strict_types=1);

// Hunthub – Presence API: liefert Online/Offline-Status für eine Liste von User-IDs
// Aufruf: POST /api/messages/presence.php mit ids[]=12&ids[]=34

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $me = require_auth(); // Nutzer muss eingeloggt sein
    $pdo = db();

    $ids = $_POST['ids'] ?? $_GET['ids'] ?? [];
    if (!is_array($ids)) { $ids = []; }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        echo json_encode(['ok'=>true,'users'=>[]]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, display_name, slug, last_seen FROM users WHERE id IN ($placeholders)");
    $st->execute($ids);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $ONLINE_WINDOW = 120; // Sekunden: innerhalb der letzten 2 Minuten gesehen = online
    $now = time();
    $out = [];
    foreach ($rows as $r) {
        $ls  = isset($r['last_seen']) ? strtotime((string)$r['last_seen']) : 0;
        $out[(int)$r['id']] = [
            'online'    => $ls && ($now - $ls) <= $ONLINE_WINDOW,
            'last_seen' => $r['last_seen'] ? date('c', $ls) : null,
            'name'      => $r['display_name'],
            'slug'      => $r['slug'],
        ];
    }

    echo json_encode(['ok'=>true, 'window'=>$ONLINE_WINDOW, 'users'=>$out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Serverfehler: '.$e->getMessage()]);
}

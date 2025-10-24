<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/csrf.php';

require_admin(); // nur Admin darf Flag ändern
verify_csrf();   // CSRF prüfen

$pdo = db();
$userId = (int)($_POST['user_id'] ?? 0);
$flag   = isset($_POST['is_twitch_streamer']) ? (int)!!$_POST['is_twitch_streamer'] : null;
$twitch = trim((string)($_POST['twitch_url'] ?? ''));

if ($userId <= 0 || $flag === null) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'invalid_params']);
    exit;
}

$sql = 'UPDATE users SET is_twitch_streamer = :f, twitch_url = :u WHERE id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute([':f'=>$flag, ':u'=>($twitch!==''?$twitch:null), ':id'=>$userId]);

echo json_encode(['ok'=>true, 'user_id'=>$userId, 'is_twitch_streamer'=>$flag, 'twitch_url'=>$twitch!==''?$twitch:null]);

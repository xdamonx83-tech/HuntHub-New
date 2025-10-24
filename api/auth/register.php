<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../auth/roles.php';
require_once __DIR__ . '/../../lib/points.php';

$input = $_POST ?: json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$pass  = $input['password'] ?? '';
$name  = trim($input['display_name'] ?? '');
$refInput = trim((string)($input['ref_code'] ?? ''));

if (!$email || !$pass || !$name) {
  http_response_code(422);
  exit(json_encode(['ok'=>false,'error'=>'missing_fields']));
}
if (!email_valid($email)) {
  http_response_code(422);
  exit(json_encode(['ok'=>false,'error'=>'invalid_email']));
}
if (strlen($pass) < 8) {
  http_response_code(422);
  exit(json_encode(['ok'=>false,'error'=>'weak_password']));
}
if (find_user_by_email($email)) {
  http_response_code(409);
  exit(json_encode(['ok'=>false,'error'=>'email_taken']));
}

$pdo = db();

function generate_ref_code(PDO $pdo): string {
    do {
        $code = strtoupper(bin2hex(random_bytes(3)));
        $st = $pdo->prepare("SELECT 1 FROM users WHERE ref_code=?");
        $st->execute([$code]);
    } while ($st->fetch());
    return $code;
}

$userId = create_user($email, $pass, $name, 'user');

// Eigenen Ref-Code setzen
$refCodeNew = generate_ref_code($pdo);
$pdo->prepare("UPDATE users SET ref_code=? WHERE id=?")->execute([$refCodeNew, $userId]);

// Referral prüfen
if ($refInput !== '') {
    error_log("[REGISTER] Referral code eingegeben: " . $refInput);
    $st = $pdo->prepare("SELECT id FROM users WHERE ref_code=? LIMIT 1");
    $st->execute([$refInput]);
    $refUser = $st->fetch(PDO::FETCH_ASSOC);

    if ($refUser) {
        $referrerId = (int)$refUser['id'];

        $pdo->prepare("INSERT IGNORE INTO user_referrals (referrer_id,referred_id) VALUES (?,?)")
            ->execute([$referrerId, $userId]);

        $POINTS = get_points_mapping();
        $points = $POINTS['referral'] ?? 20;
        award_points($pdo, $referrerId, 'referral', $points);

        error_log("[REGISTER] Punkte vergeben: {$points} an User {$referrerId}");
    } else {
        error_log("[REGISTER] Ref-Code nicht gefunden: " . $refInput);
    }
}

$session = create_session($userId);
echo json_encode([
  'ok'       => true,
  'user_id'  => $userId,
  'csrf'     => $session['csrf'],
  'ref_code' => $refCodeNew
]);

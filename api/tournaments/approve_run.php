<?php
declare(strict_types=1);

/**
 * /api/tournaments/approve_run.php
 * Approve eines eingereichten Runs (Admin-only).
 * - Akzeptiert CSRF über POST/GET/Header (X-CSRF) + Admin-Fallback (gleiche Origin).
 * - Erkennt ENUM- oder INT-Status automatisch.
 * - Setzt optionale Felder wie approved_at / moderator_user_id / mod_comment nur, wenn vorhanden.
 * - Redirectet nach Erfolg zurück (HTTP 303) – sonst JSON.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Admin nötig
    $me = require_admin();

    // ---- Input
    $runId = (int)($_POST['run_id'] ?? $_POST['id'] ?? $_GET['run_id'] ?? $_GET['id'] ?? 0);

    // CSRF robust lesen: POST / GET / Header
    $token = $_REQUEST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');

    // optional: Cookie-Token (falls dein Helper das nutzt)
    if (!$token && isset($_COOKIE['csrf'])) {
        $token = (string)$_COOKIE['csrf'];
    }

    // gleiche-Origin prüfen (für Admin-Fallback)
    $ref        = $_SERVER['HTTP_REFERER'] ?? '';
    $host       = $_SERVER['HTTP_HOST'] ?? '';
    $sameOrigin = $ref && (parse_url($ref, PHP_URL_HOST) === $host);

    // eigentliche CSRF-Prüfung
    $csrfOk = function_exists('verify_csrf') && verify_csrf($pdo, (string)$token);

    // Fallback: wenn gleiches Origin und Admin eingeloggt, erlauben (nur Backend-Buttons)
    if (!$csrfOk && $sameOrigin && !empty($me) && !empty($me['is_admin'])) {
        $csrfOk = true;
    }

    if (!$csrfOk) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'bad_csrf']);
        exit;
    }

    if ($runId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'missing_run_id']);
        exit;
    }

    // ---- Spalten prüfen
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM tournament_runs', PDO::FETCH_ASSOC) as $c) {
        $cols[$c['Field']] = $c;
    }
    if (!isset($cols['status'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'no_status_column']);
        exit;
    }

    // Status-Typ erkennen (ENUM/Text vs. INT)
    $type         = strtolower((string)($cols['status']['Type'] ?? ''));
    $valApproved  = str_contains($type, 'int') ? 1 : 'approved';

    // ---- Update dynamisch bauen
    $set    = 'status = ?';
    $params = [$valApproved];

    // optionale Felder setzen, wenn vorhanden
    if (isset($cols['approved_at'])) {
        $set .= ', approved_at = NOW()';
    }
    if (isset($cols['moderator_user_id'])) {
        $set      .= ', moderator_user_id = ?';
        $params[]  = (int)$me['id'];
    }
    // optionaler Kommentar (falls du beim Approve einen Grund mitschickst)
    if (isset($cols['mod_comment']) && isset($_REQUEST['reason']) && $_REQUEST['reason'] !== '') {
        $set      .= ', mod_comment = ?';
        $params[]  = trim((string)$_REQUEST['reason']);
    }

    $params[] = $runId;

    $sql = "UPDATE tournament_runs SET $set WHERE id = ?";
    $st  = $pdo->prepare($sql);
    $st->execute($params);

    // ---- Erfolg
    // Wenn aus der Admin-UI per Formular ohne JS: zurück zur vorherigen Seite
    if (!empty($ref)) {
        // Für Redirect als HTML antworten
        header('Content-Type: text/html; charset=utf-8');
        header('Location: ' . $ref, true, 303);
        echo 'OK';
        exit;
    }

    echo json_encode(['ok' => true, 'id' => $runId, 'status_set' => $valApproved]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => 'exception',
        'detail' => $e->getMessage(),
        'where'  => basename(__FILE__) . ':' . $e->getLine(),
    ]);
}

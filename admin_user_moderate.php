<?php
declare(strict_types=1);

require_once __DIR__ . '/api/admin/_bootstrap.php'; // Nutzt dein Admin-Bootstrap
require_once __DIR__ . '/lib/moderation.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    die("Keine User-ID angegeben.");
}

// Nutzerdaten laden
$user_st = $pdo->prepare("SELECT id, username, role, warning_count FROM users WHERE id = ?");
$user_st->execute([$userId]);
$user = $user_st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Nutzer nicht gefunden.");
}

// Verlaufsdaten laden
$warnings_st = $pdo->prepare("SELECT w.*, m.username as moderator_name FROM user_warnings w LEFT JOIN users m ON w.moderator_id = m.id WHERE w.user_id = ? ORDER BY w.created_at DESC");
$warnings_st->execute([$userId]);
$warnings = $warnings_st->fetchAll(PDO::FETCH_ASSOC);

$mutes_st = $pdo->prepare("SELECT m.*, u.username as moderator_name FROM user_mutes m LEFT JOIN users u ON m.moderator_id = u.id WHERE m.user_id = ? ORDER BY m.created_at DESC");
$mutes_st->execute([$userId]);
$mutes = $mutes_st->fetchAll(PDO::FETCH_ASSOC);

// Aktuellen Mute-Status prüfen
$activeMute = get_active_mute_info($pdo, $userId);

$feedbackMessage = '';

// POST-Request verarbeiten (Formular abgeschickt)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $duration = $_POST['duration'] ?? '1 DAY';

    if ($action === 'warn' && !empty($reason)) {
        if (issue_warning($pdo, $userId, $me['id'], $reason)) {
            $feedbackMessage = "Verwarnung wurde erfolgreich erteilt.";
        } else {
            $feedbackMessage = "Fehler: Verwarnung konnte nicht erteilt werden.";
        }
    } elseif ($action === 'mute') {
        if (manual_mute_user($pdo, $userId, $me['id'], $reason, $duration)) {
            $feedbackMessage = "Nutzer wurde manuell stummgeschaltet.";
        } else {
            $feedbackMessage = "Fehler: Nutzer konnte nicht stummgeschaltet werden.";
        }
    } elseif ($action === 'unmute') {
        if (unmute_user($pdo, $userId)) {
            $feedbackMessage = "Nutzer wurde entsperrt.";
        } else {
            $feedbackMessage = "Fehler beim Entsperren.";
        }
    } elseif ($action === 'clear_warnings') {
        if (clear_warnings($pdo, $userId)) {
            $feedbackMessage = "Alle Verwarnungen des Nutzers wurden gelöscht.";
        } else {
            $feedbackMessage = "Fehler beim Löschen der Verwarnungen.";
        }
    }
    
    // Seite neu laden, um Änderungen anzuzeigen
    header("Location: " . $_SERVER['PHP_SELF'] . "?user_id=" . $userId . "&feedback=" . urlencode($feedbackMessage));
    exit;
}

if(isset($_GET['feedback'])) {
    $feedbackMessage = htmlspecialchars($_GET['feedback']);
}

// Ab hier beginnt das HTML der Seite
// Du kannst dies in dein Admin-Template integrieren
$title = "Moderation für " . htmlspecialchars($user['username']);
include __DIR__ . '/partials/header.php'; // Dein Admin-Header
?>
<style>
    .mod-container { max-width: 1200px; margin: 2rem auto; padding: 2rem; background: #2d3748; border-radius: 8px; color: #cbd5e0; }
    .mod-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
    .mod-card { background: #4a5568; padding: 1.5rem; border-radius: 8px; }
    .mod-card h3 { font-size: 1.25rem; font-weight: bold; border-bottom: 1px solid #718096; padding-bottom: 0.5rem; margin-bottom: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; }
    .form-group input, .form-group textarea, .form-group select { width: 100%; background: #2d3748; border: 1px solid #718096; border-radius: 4px; padding: 0.5rem; color: #f7fafc; }
    .btn { padding: 0.5rem 1rem; border-radius: 4px; border: none; cursor: pointer; font-weight: bold; }
    .btn-warn { background: #dd6b20; color: white; }
    .btn-mute { background: #c53030; color: white; }
    .btn-unmute { background: #38a169; color: white; }
    .btn-clear { background: #718096; color: white; }
    .history-list { list-style: none; padding: 0; }
    .history-list li { background: #2d3748; padding: 0.75rem; border-radius: 4px; margin-bottom: 0.5rem; border-left: 3px solid #dd6b20; }
    .history-list li.mute { border-left-color: #c53030; }
    .feedback { padding: 1rem; background: #2c5282; color: white; border-radius: 4px; margin-bottom: 1.5rem; }
    .status-active { color: #f56565; font-weight: bold; }
</style>

<main class="mod-container">
    <h1><?= $title ?></h1>
    <a href="/admin.php#users">&laquo; Zurück zur Nutzerliste</a>

    <?php if ($feedbackMessage): ?>
        <div class="feedback"><?= $feedbackMessage ?></div>
    <?php endif; ?>

    <div class="mod-card" style="margin-top: 1rem;">
        <h3>Aktueller Status</h3>
        <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
        <p><strong>Verwarnungen:</strong> <?= (int)$user['warning_count'] ?></p>
        <?php if ($activeMute): ?>
            <p class="status-active">
                Stummgeschaltet bis: 
                <?= $activeMute['expires_at'] ? date('d.m.Y H:i', strtotime($activeMute['expires_at'])) . ' Uhr' : 'PERMANENT' ?>
            </p>
            <p><strong>Grund:</strong> <?= htmlspecialchars($activeMute['reason'] ?: 'N/A') ?></p>
        <?php else: ?>
            <p style="color: #68d391;">Account ist aktiv.</p>
        <?php endif; ?>
    </div>

    <div class="mod-grid" style="margin-top: 2rem;">
        <div class="mod-card">
            <h3>Aktionen</h3>

            <!-- Verwarnung -->
            <form method="POST">
                <h4>Verwarnung erteilen</h4>
                <div class="form-group">
                    <label for="reason-warn">Begründung (min. 10 Zeichen)</label>
                    <textarea id="reason-warn" name="reason" rows="3" required></textarea>
                </div>
                <button type="submit" name="action" value="warn" class="btn btn-warn">Verwarnen</button>
            </form>

            <hr style="margin: 2rem 0; border-color: #718096;">

            <!-- Mute -->
            <form method="POST">
                <h4>Nutzer manuell stummschalten</h4>
                <div class="form-group">
                    <label for="reason-mute">Begründung (optional)</label>
                    <textarea id="reason-mute" name="reason" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="duration">Dauer</label>
                    <select id="duration" name="duration">
                        <option value="1 HOUR">1 Stunde</option>
                        <option value="12 HOUR">12 Stunden</option>
                        <option value="1 DAY">1 Tag</option>
                        <option value="7 DAY">7 Tage</option>
                        <option value="1 MONTH">1 Monat</option>
                        <option value="PERMANENT">Permanent</option>
                    </select>
                </div>
                <button type="submit" name="action" value="mute" class="btn btn-mute">Stummschalten</button>
            </form>
            
            <hr style="margin: 2rem 0; border-color: #718096;">

            <!-- Weitere Aktionen -->
            <h4>Sonstige Aktionen</h4>
             <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <form method="POST" onsubmit="return confirm('Soll der Nutzer wirklich entsperrt werden?');">
                    <button type="submit" name="action" value="unmute" class="btn btn-unmute" <?= !$activeMute ? 'disabled' : '' ?>>Stummschaltung aufheben</button>
                </form>
                <form method="POST" onsubmit="return confirm('Sollen wirklich ALLE Verwarnungen für diesen Nutzer gelöscht werden?');">
                    <button type="submit" name="action" value="clear_warnings" class="btn btn-clear">Verwarnungen zurücksetzen</button>
                </form>
            </div>
        </div>
        <div class="mod-card">
            <h3>Verlauf</h3>
            <h4>Verwarnungen</h4>
            <ul class="history-list">
                <?php if (empty($warnings)): ?>
                    <li>Keine Verwarnungen vorhanden.</li>
                <?php else: foreach ($warnings as $w): ?>
                    <li>
                        <strong><?= date('d.m.Y H:i', strtotime($w['created_at'])) ?></strong>
                        von <?= htmlspecialchars($w['moderator_name'] ?? 'Unbekannt') ?>
                        <p style="margin-top: 0.5rem; white-space: pre-wrap;"><?= htmlspecialchars($w['reason']) ?></p>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
            <h4 style="margin-top: 1.5rem;">Stummschaltungen</h4>
            <ul class="history-list">
                 <?php if (empty($mutes)): ?>
                    <li>Keine Stummschaltungen vorhanden.</li>
                <?php else: foreach ($mutes as $m): ?>
                    <li class="mute" style="<?= $m['is_active'] ? 'opacity:1' : 'opacity:0.5'?>">
                        <strong><?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></strong>
                        (<?= $m['is_active'] ? 'AKTIV' : 'Abgelaufen' ?>)
                        <p><strong>Endet:</strong> <?= $m['expires_at'] ? date('d.m.Y H:i', strtotime($m['expires_at'])) : 'Permanent' ?></p>
                        <p><strong>Grund:</strong> <?= htmlspecialchars($m['reason'] ?: 'N/A') ?></p>
                        <small>Von: <?= htmlspecialchars($m['moderator_name'] ?? 'System') ?></small>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>
</main>

<?php
include __DIR__ . '/partials/footer.php'; // Dein Admin-Footer
?>
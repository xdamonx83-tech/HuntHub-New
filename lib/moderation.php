<?php
declare(strict_types=1);

/**
 * Prüft, ob ein Nutzer aktuell stummgeschaltet ist.
 * Gibt das Ablaufdatum als String zurück, 'PERMANENT' für permanent, oder null, wenn nicht gemutet.
 */
function get_active_mute_info(PDO $pdo, int $userId): ?array {
    $st = $pdo->prepare("
        SELECT expires_at, reason FROM user_mutes
        WHERE user_id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $st->execute([$userId]);
    $mute = $st->fetch(PDO::FETCH_ASSOC);

    if ($mute === false) {
        return null;
    }
    
    return [
        'expires_at' => $mute['expires_at'],
        'reason' => $mute['reason']
    ];
}

/**
 * Erteilt einem Nutzer eine Verwarnung.
 * Muted den Nutzer automatisch bei 3 Verwarnungen für 7 Tage.
 * Gibt bei Erfolg true zurück, bei Fehler false.
 */
function issue_warning(PDO $pdo, int $userId, int $moderatorId, string $reason): bool {
    // Regel: Mindestens 10 Zeichen für eine Begründung
    if (mb_strlen($reason) < 10) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        // 1. Verwarnung in die Tabelle eintragen
        $st_warn = $pdo->prepare("INSERT INTO user_warnings (user_id, moderator_id, reason) VALUES (?, ?, ?)");
        $st_warn->execute([$userId, $moderatorId, $reason]);

        // 2. Warn-Level des Nutzers erhöhen
        $st_level = $pdo->prepare("UPDATE users SET warning_count = warning_count + 1 WHERE id = ?");
        $st_level->execute([$userId]);
        
        // 3. Prüfen, wie viele Verwarnungen der Nutzer nun hat
        $st_count = $pdo->prepare("SELECT warning_count FROM users WHERE id = ?");
        $st_count->execute([$userId]);
        $warningCount = (int)$st_count->fetchColumn();

        // 4. Bei 3 (oder mehr) Verwarnungen automatisch für 7 Tage muten
        if ($warningCount >= 3) {
            $muteReason = "Automatische Stummschaltung nach 3 Verwarnungen.";
            // Dauer ist '7 DAY'
            manual_mute_user($pdo, $userId, $moderatorId, $muteReason, '7 DAY');
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log("Fehler beim Verwarnen: " . $e->getMessage());
        return false;
    }
}

/**
 * Muted einen Nutzer manuell für einen bestimmten Zeitraum.
 * Beispiele für $duration: '1 DAY', '2 WEEK', '1 MONTH', 'PERMANENT'
 * Gibt bei Erfolg true zurück, bei Fehler false.
 */
function manual_mute_user(PDO $pdo, int $userId, int $moderatorId, string $reason, string $duration): bool {
    // Alten Mute deaktivieren, falls vorhanden (damit der UNIQUE KEY nicht verletzt wird)
    $st_deactivate = $pdo->prepare("UPDATE user_mutes SET is_active = 0 WHERE user_id = ? AND is_active = 1");
    $st_deactivate->execute([$userId]);

    // Neuen Mute setzen
    if ($duration === 'PERMANENT') {
        $sql = "INSERT INTO user_mutes (user_id, moderator_id, reason, expires_at) VALUES (?, ?, ?, NULL)";
        $st = $pdo->prepare($sql);
        return $st->execute([$userId, $moderatorId, $reason]);
    } else {
        // Sicherheits-Check für die Dauer
        if (!preg_match('/^[0-9]+\s+(HOUR|DAY|WEEK|MONTH|YEAR)$/i', $duration)) {
            return false;
        }
        $sql = "INSERT INTO user_mutes (user_id, moderator_id, reason, expires_at) 
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL $duration))";
        $st = $pdo->prepare($sql);
        return $st->execute([$userId, $moderatorId, $reason]);
    }
}

/**
 * Entsperrt einen Nutzer, indem der aktive Mute deaktiviert wird.
 */
function unmute_user(PDO $pdo, int $userId): bool {
    $st = $pdo->prepare("UPDATE user_mutes SET is_active = 0 WHERE user_id = ? AND is_active = 1");
    return $st->execute([$userId]);
}

/**
 * Setzt die Verwarnungen eines Nutzers auf 0 zurück.
 */
function clear_warnings(PDO $pdo, int $userId): bool {
    $pdo->beginTransaction();
    try {
        $st_users = $pdo->prepare("UPDATE users SET warning_count = 0 WHERE id = ?");
        $st_users->execute([$userId]);

        $st_warnings = $pdo->prepare("DELETE FROM user_warnings WHERE user_id = ?");
        $st_warnings->execute([$userId]);
        
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log("Fehler beim Zurücksetzen der Verwarnungen: " . $e->getMessage());
        return false;
    }
}
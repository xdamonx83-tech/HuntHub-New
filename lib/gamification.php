<?php
declare(strict_types=1);

// Diese Datei ist jetzt deine zentrale Logik für Achievements und Quests.

/**
 * Aktualisiert den Zähler eines Nutzers in der user_stats Tabelle.
 */
function gamify_bump(PDO $pdo, int $userId, string $stat, int $value = 1): void {
    if ($value === 0) return;
    $op = ($value > 0) ? '+' : '-';
    $val = abs($value);
    $sql = "INSERT INTO user_stats (user_id, `$stat`) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `$stat` = GREATEST(0, `$stat` {$op} ?)";
    $pdo->prepare($sql)->execute([$userId, $val, $val]);
}

/**
 * Prüft, ob ein Nutzer durch eine Aktion neue Erfolge freigeschaltet hat.
 */
function gamify_check(PDO $pdo, int $userId, string $event): array {
    $st = $pdo->prepare("SELECT `key`, title, description, icon, points, threshold, rule_stat FROM achievements 
                         WHERE rule_event = ? AND is_active = 1");
    $st->execute([$event]);
    $rules = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rules) return [];

    $st = $pdo->prepare("SELECT * FROM user_stats WHERE user_id = ?");
    $st->execute([$userId]);
    $stats = $st->fetch(PDO::FETCH_ASSOC);
    if (!$stats) return [];

    $unlocked = [];
    foreach ($rules as $rule) {
        $statName = $rule['rule_stat'];
        if (isset($stats[$statName]) && $stats[$statName] >= $rule['threshold']) {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) 
                                 SELECT ?, id FROM achievements WHERE `key` = ?");
            $ins->execute([$userId, $rule['key']]);
            if ($ins->rowCount() > 0) {
                $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$rule['points'], $userId]);
                $unlocked[] = $rule;
            }
        }
    }
    return $unlocked;
}

/**
 * Aktualisiert den wöchentlichen Quest-Fortschritt für einen Benutzer.
 */
function update_quest_progress(PDO $pdo, int $userId, string $eventType, int $xpPerQuest = 25): void
{
    error_log("[Gamify] update_quest_progress called for user: $userId, event: $eventType");

    $stmt = $pdo->prepare(
        "SELECT id, title, threshold FROM quests WHERE is_active = 1 AND rule_event = ?"
    );
    $stmt->execute([$eventType]);
    $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$quests) {
        error_log("[Gamify] No active quests found for event: $eventType");
        return;
    }

    error_log("[Gamify] Found " . count($quests) . " quests for event: $eventType");

    foreach ($quests as $quest) {
        $questId = (int)$quest['id'];
        $threshold = (int)$quest['threshold'];

        $sql = "INSERT INTO user_quest_progress (user_id, quest_id, progress) VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE progress = progress + 1";
        $pdo->prepare($sql)->execute([$userId, $questId]);
        error_log("[Gamify] Progress updated for user: $userId, quest: $questId");

        $progressStmt = $pdo->prepare(
            "SELECT progress FROM user_quest_progress WHERE user_id = ? AND quest_id = ? AND completed_at IS NULL"
        );
        $progressStmt->execute([$userId, $questId]);
        $currentProgress = (int)$progressStmt->fetchColumn();

        if ($threshold > 0 && $currentProgress >= $threshold) {
            error_log("[Gamify] Quest COMPLETED for user: $userId, quest: $questId");
            $pdo->prepare("UPDATE user_quest_progress SET completed_at = NOW() WHERE user_id = ? AND quest_id = ?")
                ->execute([$userId, $questId]);

            $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
                ->execute([$xpPerQuest, $userId]);
        }
    }
}

/**
 * Prüft und aktualisiert die tägliche Login-Serie eines Nutzers.
 */
function process_daily_login(PDO $pdo, int $userId): void
{
    $st = $pdo->prepare("SELECT last_login_at, login_streak FROM user_stats WHERE user_id = ?");
    $st->execute([$userId]);
    $stats = $st->fetch(PDO::FETCH_ASSOC);

    if (!$stats) {
        // ##### KORREKTUR: Erstellt einen neuen Eintrag, falls keiner existiert #####
        $sql = "INSERT INTO user_stats (user_id, last_login_at, login_streak) VALUES (?, NOW(), 1)
                ON DUPLICATE KEY UPDATE last_login_at = NOW(), login_streak = 1";
        $pdo->prepare($sql)->execute([$userId]);
        check_stat_based_quests($pdo, $userId, 'login_streak');
        return;
    }

    $lastLogin = $stats['last_login_at'] ? new DateTime($stats['last_login_at']) : null;
    $today = new DateTime('today');
    $yesterday = new DateTime('yesterday');

    if ($lastLogin === null || $lastLogin < $yesterday) {
        $newStreak = 1;
    } elseif ($lastLogin >= $yesterday && $lastLogin < $today) {
        $newStreak = (int)$stats['login_streak'] + 1;
    } else {
        // Bereits heute eingeloggt, nichts tun
        return;
    }

    $pdo->prepare("UPDATE user_stats SET last_login_at = NOW(), login_streak = ? WHERE user_id = ?")
        ->execute([$newStreak, $userId]);
        
    check_stat_based_quests($pdo, $userId, 'login_streak');
}

/**
 * Prüft Quests, die auf einem Stat-Wert basieren.
 */
function check_stat_based_quests(PDO $pdo, int $userId, string $statName, int $xpPerQuest = 30): void
{
    $stmt = $pdo->prepare(
        "SELECT id, title, threshold FROM quests WHERE is_active = 1 AND rule_event = ?"
    );
    $stmt->execute([$statName]);
    $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$quests) return;

    $statStmt = $pdo->prepare("SELECT `$statName` FROM user_stats WHERE user_id = ?");
    $statStmt->execute([$userId]);
    $currentValue = (int)$statStmt->fetchColumn();

    foreach ($quests as $quest) {
        $threshold = (int)$quest['threshold'];
        if ($currentValue >= $threshold) {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_quest_progress (user_id, quest_id, progress, completed_at) VALUES (?, ?, ?, NOW())");
            $ins->execute([$userId, (int)$quest['id'], $currentValue]);
            
            if ($ins->rowCount() > 0) {
                $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
                    ->execute([$xpPerQuest, $userId]);
            }
        }
    }
}

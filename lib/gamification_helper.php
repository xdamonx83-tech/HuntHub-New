<?php
declare(strict_types=1);

function awardXP(PDO $pdo, int $userId, string $actionKey): bool
{
    if ($userId <= 0) {
        return false;
    }

    $xpConfig = require __DIR__ . '/xp_config.php';
    $xpAmount = (int)($xpConfig[$actionKey] ?? 0);

    if ($xpAmount <= 0) {
        return false;
    }

    try {
        $scoreCol = columnExists($pdo, 'users', 'xp') ? 'xp' : (columnExists($pdo, 'users', 'points') ? 'points' : null);
        if (!$scoreCol) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE users SET `$scoreCol` = `$scoreCol` + ? WHERE id = ?"
        );
        $stmt->execute([$xpAmount, $userId]);

        checkAndApplyLevelUp($pdo, $userId);

        return true;
    } catch (Throwable $e) {
        error_log('XP Award Error: ' . $e->getMessage());
        return false;
    }
}

function checkAndApplyLevelUp(PDO $pdo, int $userId): void
{
    $scoreCol = columnExists($pdo, 'users', 'xp') ? 'xp' : (columnExists($pdo, 'users', 'points') ? 'points' : null);
    $levelCol = columnExists($pdo, 'users', 'level') ? 'level' : (columnExists($pdo, 'users', 'lvl') ? 'lvl' : null);

    if (!$scoreCol || !$levelCol) {
        return;
    }

    $stmt = $pdo->prepare("SELECT `$levelCol` AS level, `$scoreCol` AS score FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return;
    }

    $currentLevel = (int)$user['level'];
    $currentScore = (int)$user['score'];
    $xpForNextLevel = (int)floor(pow($currentLevel + 1, 2) * 100);

    while ($currentScore >= $xpForNextLevel) {
        $currentLevel++;
        $xpForNextLevel = (int)floor(pow($currentLevel + 1, 2) * 100);
    }

    if ((int)$user['level'] !== $currentLevel) {
        $updateStmt = $pdo->prepare("UPDATE users SET `$levelCol` = ? WHERE id = ?");
        $updateStmt->execute([$currentLevel, $userId]);
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
        return $st && $st->fetchColumn() !== false;
    } catch (Throwable) {
        return false;
    }
}
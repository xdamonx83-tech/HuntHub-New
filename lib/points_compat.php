<?php
declare(strict_types=1);

/**
 * points_compat.php
 * - Lädt bestehende lib/points.php wenn vorhanden
 * - Definiert nur die fehlenden Funktionen, damit bestehendes System unangetastet bleibt
 */

// 1) Wenn es eine echte points.php gibt, zuerst einbinden
$possible = __DIR__ . '/points.php';
if (file_exists($possible)) {
    require_once $possible;
}

// 2) Fallbacks nur definieren, wenn Funktionen fehlen

if (!function_exists('get_user_points')) {
    /**
     * Liefert die Summe aller delta für user_id oder 0.
     */
    function get_user_points(PDO $pdo, int $userId): int {
        try {
            $st = $pdo->prepare("SELECT COALESCE(SUM(delta),0) AS s FROM points_transactions WHERE user_id = ?");
            $st->execute([$userId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return (int)($r['s'] ?? 0);
        } catch (Throwable $e) {
            // Wenn Tabelle/Spalte nicht existiert -> 0 zurückgeben (nicht fatal)
            error_log('get_user_points fallback error: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('award_points')) {
    /**
     * Fügt eine Transaktion hinzu.
     * Rückgabewert: true on success, false on fail.
     */
    function award_points(PDO $pdo, int $userId, string $reason, int $delta, ?array $meta = null): bool {
        try {
            $json = $meta ? json_encode($meta, JSON_THROW_ON_ERROR) : null;
            $st = $pdo->prepare("INSERT INTO points_transactions (user_id, delta, reason, meta) VALUES (?, ?, ?, ?)");
            return (bool)$st->execute([$userId, $delta, $reason, $json]);
        } catch (Throwable $e) {
            error_log('award_points fallback error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_points_mapping')) {
    function get_points_mapping(): array {
        return [
            'referral' => 20,
            'signup' => 10,
            'shop_purchase' => 0, // wird beim Kauf mit negativem Wert verwendet
        ];
    }
}

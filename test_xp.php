<?php
// Zum Debuggen
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Lade die notwendigen Dateien
require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/lib/gamification_helper.php';

echo "<h2>XP-Test wird ausgeführt...</h2>";

// --- BITTE ANPASSEN ---
$testUserID = 1; // <<-- Trage hier deine eigene User-ID ein!
// --------------------

$pdo = db();

echo "Versuche, 10 XP für User-ID $testUserID zu vergeben...<br>";

// Wir rufen die Funktion direkt auf
$erfolg = awardXP($pdo, $testUserID, 'new_post');

echo "<b>Ergebnis des Aufrufs:</b> ";
var_dump($erfolg);

if ($erfolg) {
    echo "<p style='color:green;'>Die Funktion wurde erfolgreich ausgeführt! Bitte prüfe die Datenbank.</p>";
} else {
    echo "<p style='color:red;'>Die Funktion ist fehlgeschlagen. Wahrscheinliche Ursachen: <br>
          - Die Datei 'lib/xp_config.php' existiert nicht oder hat die falschen Berechtigungen (muss 644 sein).<br>
          - Der Schlüssel 'new_post' fehlt in der 'xp_config.php'.<br>
          - Die Spalte 'xp' oder 'points' existiert in der 'users'-Tabelle nicht.</p>";
}
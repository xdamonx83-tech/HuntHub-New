<?php
// /home/users/hunthub/cron_reset_quests.php

require_once __DIR__ . '/www/hunthub.online/auth/db.php';

$pdo = db();
// Löscht den gesamten Fortschritt, damit die Nutzer von vorne anfangen können.
$pdo->query("TRUNCATE TABLE user_quest_progress");

echo "Wöchentlicher Quest-Fortschritt wurde zurückgesetzt.";
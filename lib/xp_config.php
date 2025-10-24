<?php
// lib/xp_config.php

/**
 * Zentrale Konfiguration für die Vergabe von Erfahrungspunkten (XP).
 *
 * Hier wird jeder Aktion ein XP-Wert zugeordnet.
 * Der Schlüssel (z.B. 'new_thread') wird im Code verwendet,
 * um die entsprechende Aktion auszulösen.
 */
return [
    // Dein Wunsch: Für Beiträge (Posts/Kommentare)
    'new_post'          => 10,  // z.B. 10 XP für jeden neuen Beitrag/Kommentar

    // Dein Wunsch: Für das Erstellen von Themen
    'new_thread'        => 25,  // z.B. 25 XP für ein komplett neues Thema

    // Weitere Ideen für die Zukunft:
    'receive_like'      => 5,   // 5 XP für den AUTOR, wenn sein Beitrag geliked wird
    'daily_login'       => 15,  // 15 XP für den ersten Login des Tages
    'add_friend'        => 20,  // 20 XP, wenn eine Freundschaftsanfrage angenommen wird
    'upload_avatar'     => 50,  // 50 XP für das erstmalige Hochladen eines Avatars
];
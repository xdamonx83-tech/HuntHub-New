<?php
/**
 * Hunthub – Self‑Service Datenexport (DSGVO Art. 20)
 * Vollständige, robuste Version mit:
 *  - Debug-Modus (?debug=1) → JSON-Vorschau + friends_debug
 *  - Rate-Limit (15s, ?reset=1 zum Zurücksetzen)
 *  - dynamischem Profil-SELECT (inkl. last_seen/last_login, falls vorhanden)
 *  - Friends-Export mit Auto-Detect (friendships/friends/user_friends/contacts/...) + Fallback über messages
 *  - JSON/JSONL in ZIP (Tempfile, richtige Header, kein Vorab-Output)
 */

declare(strict_types=1);

// === Fehlerbehandlung / Binär-Output sicher ===
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0'); // nie Binär-Streams kaputtmachen
if (function_exists('ini_get') && ini_get('zlib.output_compression') === '1') {
    ini_set('zlib.output_compression', '0');
}

// === Korrekte Includes (von /api/privacy/ nach /auth) ===
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';

$me = require_auth(); // bricht sauber ab, wenn nicht eingeloggt
$userId = (int)($me['id'] ?? 0);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// === Debug & Rate-Limit ===
$DEBUG = isset($_GET['debug']);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$key = 'hh_last_export_ts_' . $userId;
if (isset($_GET['reset'])) { unset($_SESSION[$key]); }
$COOLDOWN = 15; // Sekunden
$now = time();
if (!$DEBUG && !empty($_SESSION[$key]) && ($now - (int)$_SESSION[$key]) < $COOLDOWN) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Bitte später erneut versuchen (Rate-Limit).', 'cooldown_seconds' => $COOLDOWN], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION[$key] = $now;

// === Helpers ===
$tblExists = function(string $t) use ($pdo): bool {
    try { $q = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t)); return (bool)$q->fetchColumn(); } catch (Throwable $e) { return false; }
};
$colsOf = function(string $t) use ($pdo): array {
    $cols=[]; try { $q=$pdo->query("SHOW COLUMNS FROM `{$t}`"); while($r=$q->fetch(PDO::FETCH_ASSOC)){ $cols[]=$r['Field']; } } catch(Throwable $e){}
    return $cols;
};
$colOrNull = function(string $table, string $col) use ($colsOf): string {
    static $cache = [];
    if (!isset($cache[$table])) { $cache[$table] = $colsOf($table); }
    return in_array($col, $cache[$table], true) ? "`{$col}`" : ("NULL AS `{$col}`");
};

$toJson = static function ($data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
};
$toJsonlString = static function (Generator $gen): string {
    $h = fopen('php://temp', 'r+');
    foreach ($gen as $row) { fwrite($h, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n"); }
    rewind($h); $s = stream_get_contents($h); fclose($h); return $s;
};

// === 1) Profil (dynamisch) ===
$sel = [
    '`id`',
    $colOrNull('users','display_name'),
    $colOrNull('users','slug'),
    $colOrNull('users','email'),
    $colOrNull('users','bio'),
    $colOrNull('users','social_youtube'),
    $colOrNull('users','social_twitch'),
    $colOrNull('users','social_twitter'),
    $colOrNull('users','social_instagram'),
    $colOrNull('users','social_tiktok'),
    $colOrNull('users','avatar_path'),
    $colOrNull('users','cover_path'),
    $colOrNull('users','created_at'),
    $colOrNull('users','last_seen'),
    $colOrNull('users','last_login'),
];
$sql = 'SELECT ' . implode(', ', $sel) . ' FROM users WHERE id = :uid LIMIT 1';
$st = $pdo->prepare($sql); $st->execute([':uid'=>$userId]);
$profile = $st->fetch() ?: ['id'=>$userId];

// === 2) Freunde – Auto-Detect + Fallback ===
$friends = [];
$friends_debug = [];

$friendCandidates = ['friendships','friends','user_friends','friend_requests','contacts'];
$friendTable = null; foreach ($friendCandidates as $t) { if ($tblExists($t)) { $friendTable=$t; break; } }
if ($friendTable) {
    $fc = $colsOf($friendTable); $friends_debug['friend_table']=$friendTable; $friends_debug['friend_cols']=$fc;
    $pairs = [
        ['user_id','friend_id'], ['requester_id','receiver_id'], ['requester_id','requested_id'],
        ['from_user_id','to_user_id'], ['user_a','user_b'], ['uid','fid'], ['user','friend'], ['user_id','other_user_id']
    ];
    $A=$B=null; foreach ($pairs as [$a,$b]) { if (in_array($a,$fc,true) && in_array($b,$fc,true)) { $A=$a; $B=$b; break; } }
    if ($A && $B) {
        $sel = [ in_array('id',$fc,true)?'f.id':'NULL AS id' ];
        $sel[] = in_array('status',$fc,true)?'f.status':'NULL AS status';
        $sel[] = in_array('created_at',$fc,true)?'f.created_at':'NULL AS created_at';
        $sel[] = in_array('updated_at',$fc,true)?'f.updated_at':'NULL AS updated_at';
        $sel[] = "CASE WHEN f.`{$A}` = :uid THEN f.`{$B}` ELSE f.`{$A}` END AS other_user_id";
        $sql = 'SELECT ' . implode(',', $sel) . " FROM `{$friendTable}` f WHERE f.`{$A}`=:uid OR f.`{$B}`=:uid";
        $st = $pdo->prepare($sql); $st->execute([':uid'=>$userId]); $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows) {
            $uStmt = $pdo->prepare('SELECT id, display_name, slug FROM users WHERE id = :id');
            foreach ($rows as $r) {
                $uStmt->execute([':id'=>(int)$r['other_user_id']]); $u=$uStmt->fetch(PDO::FETCH_ASSOC)?:[];
                $r['other_display_name']=$u['display_name']??null; $r['other_slug']=$u['slug']??null; $friends[]=$r;
            }
        }
    } else {
        $cand = array_values(array_filter($fc, fn($c)=>preg_match('/user|friend|member|request/i',$c)));
        if ($cand) { $where=implode(' OR ', array_map(fn($c)=>"f.`{$c}`=:uid", $cand)); $st=$pdo->prepare("SELECT f.* FROM `{$friendTable}` f WHERE {$where}"); $st->execute([':uid'=>$userId]); $friends=$st->fetchAll(PDO::FETCH_ASSOC)?:[]; $friends_debug['friend_where']=$where; }
    }
}

if (!$friends) { // Fallback über Messages
    $msgCandidates = ['messages','private_messages','direct_messages','dm_messages','chat_messages'];
    $msgTable=null; foreach ($msgCandidates as $t) { if ($tblExists($t)) { $msgTable=$t; break; } }
    if ($msgTable) {
        $mc = $colsOf($msgTable); $friends_debug['message_table']=$msgTable; $friends_debug['message_cols']=$mc;
        $mpairs = [ ['sender_id','receiver_id'], ['from_user_id','to_user_id'], ['user_id','peer_id'] ];
        $MA=$MB=null; foreach($mpairs as [$a,$b]){ if(in_array($a,$mc,true)&&in_array($b,$mc,true)){ $MA=$a; $MB=$b; break; } }
        if ($MA && $MB) {
            $sql = "SELECT DISTINCT CASE WHEN m.`{$MA}`=:uid THEN m.`{$MB}` ELSE m.`{$MA}` END AS other_user_id FROM `{$msgTable}` m WHERE m.`{$MA}`=:uid OR m.`{$MB}`=:uid";
            $st=$pdo->prepare($sql); $st->execute([':uid'=>$userId]); $rows=$st->fetchAll(PDO::FETCH_ASSOC)?:[];
            if ($rows) { $uStmt=$pdo->prepare('SELECT id, display_name, slug FROM users WHERE id = :id'); foreach($rows as $r){ $oid=(int)$r['other_user_id']; if(!$oid) continue; $uStmt->execute([':id'=>$oid]); $u=$uStmt->fetch(PDO::FETCH_ASSOC)?:[]; $friends[]=['id'=>null,'status'=>'message_contact','created_at'=>null,'updated_at'=>null,'other_user_id'=>$oid,'other_display_name'=>$u['display_name']??null,'other_slug'=>$u['slug']??null]; } }
        }
    }
}

// === 3) Nachrichten (JSONL) ===
$genMessages = static function(PDO $pdo, int $uid): Generator {
    $sql = "SELECT m.id, m.sender_id, m.receiver_id, m.body, m.created_at, s.display_name AS sender_name, r.display_name AS receiver_name FROM messages m JOIN users s ON s.id=m.sender_id JOIN users r ON r.id=m.receiver_id WHERE m.sender_id=:uid OR m.receiver_id=:uid ORDER BY m.id ASC";
    try {
        $st = $pdo->prepare($sql); $st->execute([':uid'=>$uid]);
        while ($row = $st->fetch()) { yield $row; }
    } catch (Throwable $e) { /* Tabelle existiert evtl. nicht */ }
};

// === 4) Threads/Posts (JSONL) ===
$genThreads = static function(PDO $pdo, int $uid): Generator {
    $sql = "SELECT t.id, t.board_id, t.title, t.created_by, u.display_name AS author_name, t.created_at FROM threads t JOIN users u ON u.id=t.created_by WHERE t.created_by=:uid ORDER BY t.id ASC";
    try { $st = $pdo->prepare($sql); $st->execute([':uid'=>$uid]); while ($r = $st->fetch()) { yield $r; } } catch (Throwable $e) {}
};
$genPosts = static function(PDO $pdo, int $uid): Generator {
    $sql = "SELECT p.id, p.thread_id, p.body, p.created_by, u.display_name AS author_name, p.created_at FROM posts p JOIN users u ON u.id=p.created_by WHERE p.created_by=:uid ORDER BY p.id ASC";
    try { $st = $pdo->prepare($sql); $st->execute([':uid'=>$uid]); while ($r = $st->fetch()) { yield $r; } } catch (Throwable $e) {}
};

// === 5) Likes (optional) ===
$likes = [];
try { if ($tblExists('thread_likes')) { $st=$pdo->prepare('SELECT thread_id, created_at FROM thread_likes WHERE user_id=:uid ORDER BY created_at ASC'); $st->execute([':uid'=>$userId]); $likes['thread_likes']=$st->fetchAll(); } } catch (Throwable $e) {}
try { if ($tblExists('post_likes'))   { $st=$pdo->prepare('SELECT post_id, created_at FROM post_likes WHERE user_id=:uid ORDER BY created_at ASC');   $st->execute([':uid'=>$userId]); $likes['post_likes']=$st->fetchAll(); } } catch (Throwable $e) {}

// === 6) Notifications (JSONL) ===
$genNotifs = static function(PDO $pdo, int $uid): Generator {
    try { $st=$pdo->prepare('SELECT id, type, payload, is_read, created_at FROM notifications WHERE user_id=:uid ORDER BY id ASC'); $st->execute([':uid'=>$uid]); while($r=$st->fetch()){ yield $r; } } catch (Throwable $e) {}
};

// === 7) Einstellungen ===
$settings = [];
try { if ($tblExists('user_settings')) { $st=$pdo->prepare('SELECT setting_key, setting_value, updated_at FROM user_settings WHERE user_id=:uid'); $st->execute([':uid'=>$userId]); $settings=$st->fetchAll(); } } catch (Throwable $e) {}

// === 8) Achievements / Stats ===
$achievements = [];
try { if ($tblExists('user_achievements')) { $st=$pdo->prepare('SELECT ua.achievement_id, a.code, a.title, a.description, ua.unlocked_at FROM user_achievements ua JOIN achievements a ON a.id=ua.achievement_id WHERE ua.user_id=:uid ORDER BY ua.unlocked_at ASC'); $st->execute([':uid'=>$userId]); $achievements=$st->fetchAll(); } } catch (Throwable $e) {}
$stats = [];
try { if ($tblExists('user_stats')) { $st=$pdo->prepare('SELECT stat_key, stat_value, updated_at FROM user_stats WHERE user_id=:uid'); $st->execute([':uid'=>$userId]); $stats=$st->fetchAll(); } } catch (Throwable $e) {}

// === 9) Upload-Metadaten ===
$uploads = [ 'avatar'=>$profile['avatar_path'] ?? null, 'cover'=>$profile['cover_path'] ?? null, 'message_uploads'=>[] ];
try {
    if ($tblExists('message_attachments')) {
        $st = $pdo->prepare('SELECT a.file_path, a.mime, a.size, a.created_at FROM message_attachments a JOIN messages m ON m.id=a.message_id WHERE m.sender_id=:uid OR m.receiver_id=:uid ORDER BY a.id ASC');
        $st->execute([':uid'=>$userId]);
        $uploads['message_uploads'] = $st->fetchAll();
    }
} catch (Throwable $e) {}

// === DEBUG: JSON-Vorschau statt ZIP ===
if ($DEBUG) {
    header('Content-Type: application/json; charset=utf-8');
    echo $toJson([
        'profile'        => $profile,
        'friends'        => $friends,
        'friends_debug'  => $friends_debug,
        'messages_first' => (function() use ($genMessages,$pdo,$userId){ $g=$genMessages($pdo,$userId); $out=[]; foreach($g as $row){ $out[]=$row; if(count($out)>=5) break; } return $out; })(),
        'threads_first'  => (function() use ($genThreads,$pdo,$userId){ $g=$genThreads($pdo,$userId);  $out=[]; foreach($g as $row){ $out[]=$row; if(count($out)>=5) break; } return $out; })(),
        'posts_first'    => (function() use ($genPosts,$pdo,$userId){ $g=$genPosts($pdo,$userId);    $out=[]; foreach($g as $row){ $out[]=$row; if(count($out)>=5) break; } return $out; })(),
        'likes'          => $likes,
        'notifications_first' => (function() use ($genNotifs,$pdo,$userId){ $g=$genNotifs($pdo,$userId); $out=[]; foreach($g as $row){ $out[]=$row; if(count($out)>=5) break; } return $out; })(),
        'settings'       => $settings,
        'achievements'   => $achievements,
        'stats'          => $stats,
        'uploads'        => $uploads,
    ]);
    exit;
}

// === ZIP bauen & streamen ===
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'PHP ZipArchive nicht verfügbar. Bitte php-zip aktivieren.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tmpZip = tempnam(sys_get_temp_dir(), 'hhx');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); echo 'ZIP konnte nicht erstellt werden'; exit;
}

$zip->addFromString('profile.json', $toJson($profile));
$zip->addFromString('friends.json', $toJson($friends));
if (!empty($friends_debug)) { $zip->addFromString('_debug_friends.json', $toJson($friends_debug)); }
$zip->addFromString('messages.jsonl',      $toJsonlString($genMessages($pdo,$userId)));
$zip->addFromString('threads.jsonl',       $toJsonlString($genThreads($pdo,$userId)));
$zip->addFromString('posts.jsonl',         $toJsonlString($genPosts($pdo,$userId)));
$zip->addFromString('likes.json',          $toJson($likes));
$zip->addFromString('notifications.jsonl', $toJsonlString($genNotifs($pdo,$userId)));
$zip->addFromString('settings.json',       $toJson($settings));
$zip->addFromString('achievements.json',   $toJson($achievements));
$zip->addFromString('stats.json',          $toJson($stats));
$zip->addFromString('uploads.json',        $toJson($uploads));

$zip->close();

$filename = sprintf('hunthub-export-%d-%s.zip', $userId, date('Ymd-His'));
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string)filesize($tmpZip));
header('X-Content-Type-Options: nosniff');

$fp = fopen($tmpZip, 'rb'); if ($fp) { fpassthru($fp); fclose($fp); }
@unlink($tmpZip);
exit;

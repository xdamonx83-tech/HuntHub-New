<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo json_encode(['ok' => true, 'threads' => []]);
    exit;
}

$pdo = db();
$cfg = require __DIR__ . '/../../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$avatarFallback = $APP_BASE . '/assets/images/avatars/placeholder.png';

$searchTerm = '%' . $q . '%';

// Suche in Thread-Titeln und Post-Inhalten
// Wir holen nur den Thread, in dem ein Treffer vorkommt
$sql = "
SELECT
    t.id,
    t.title,
    t.slug,
    t.last_post_at,
    b.name AS board_name,
    b.id AS board_id,
    u.display_name AS author_name,
    u.avatar_path AS author_avatar
FROM threads t
JOIN users u ON u.id = t.author_id
JOIN boards b ON b.id = t.board_id
WHERE
    t.deleted_at IS NULL AND (
        t.title LIKE :q1
        OR
        EXISTS (
            SELECT 1 FROM posts p
            WHERE p.thread_id = t.id AND p.deleted_at IS NULL AND p.content LIKE :q2
        )
    )
ORDER BY t.last_post_at DESC
LIMIT 5
";

$st = $pdo->prepare($sql);
$st->execute([':q1' => $searchTerm, ':q2' => $searchTerm]);
$threads = $st->fetchAll(PDO::FETCH_ASSOC);

$results = array_map(function($t) use ($APP_BASE, $avatarFallback) {
    $threadUrl = $APP_BASE . '/forum/thread.php?t=' . (int)$t['id']
                 . (!empty($t['slug']) ? '&slug=' . urlencode($t['slug']) : '');
    return [
        'id' => (int)$t['id'],
        'title' => htmlspecialchars($t['title']),
        'board_name' => htmlspecialchars($t['board_name']),
        'author_name' => htmlspecialchars($t['author_name']),
        'avatar' => htmlspecialchars($t['author_avatar'] ?: $avatarFallback),
        'url' => $threadUrl
    ];
}, $threads);

echo json_encode(['ok' => true, 'threads' => $results]);
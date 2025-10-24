<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../auth/db.php';
    require_once __DIR__ . '/../../auth/guards.php';

    $me = current_user();
    if (!$me || empty($me['id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
        exit;
    }

    $meId  = (int)$me['id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    if ($limit < 1)  $limit = 1;
    if ($limit > 50) $limit = 50;

    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Wir nutzen :limit-Placeholder -> Emulation an
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    // --- Helper: prüfe, ob Attachments-Tabelle existiert ---
    $hasAttach = false;
    try {
        $chk = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'message_attachments'
            LIMIT 1
        ");
        $chk->execute();
        $hasAttach = (bool)$chk->fetchColumn();
    } catch (\Throwable $e) {
        $hasAttach = false;
    }

    // Unread-Map (schnell via Subselect joinen)
    // unread = Nachrichten an mich, ungelesen, gruppiert nach Absender
    $unreadSql = "
        SELECT sender_id AS other_id, COUNT(*) AS unread
        FROM messages
        WHERE recipient_id = :me AND read_at IS NULL
        GROUP BY sender_id
    ";

    // Basisteil: ermittle pro other_id die letzte Nachricht (höchste messages.id)
    $baseThreadCTE = "
        SELECT s.other_id, MAX(s.id) AS last_id
        FROM (
            SELECT recipient_id AS other_id, id
            FROM messages
            WHERE sender_id = :meA

            UNION ALL

            SELECT sender_id AS other_id, id
            FROM messages
            WHERE recipient_id = :meB
        ) AS s
        GROUP BY s.other_id
        ORDER BY last_id DESC
        LIMIT :limitVal
    ";

    // Optionaler Attachments-Join (aggregiert pro message_id)
    $attachJoin = "";
    if ($hasAttach) {
        $attachJoin = "
            LEFT JOIN (
                SELECT
                    message_id,
                    COUNT(*) AS att_count,
                    MAX(CASE WHEN kind='image' THEN 1 ELSE 0 END) AS has_image,
                    MAX(CASE WHEN kind='video' THEN 1 ELSE 0 END) AS has_video,
                    MAX(CASE WHEN kind='file'  THEN 1 ELSE 0 END) AS has_file
                FROM message_attachments
                GROUP BY message_id
            ) AS a ON a.message_id = t.last_id
        ";
    }

    // Vollständiges Query
    $sql = "
      SELECT
        t.other_id,
        m.id          AS last_id,
        m.body,
        m.created_at,
        u.display_name,
        u.avatar_path,
        u.slug,
        COALESCE(uc.unread, 0) AS unread_count
        " . ($hasAttach ? ",
        a.att_count,
        a.has_image,
        a.has_video,
        a.has_file
        " : "") . "
      FROM (
        $baseThreadCTE
      ) AS t
      JOIN messages AS m ON m.id = t.last_id
      JOIN users    AS u ON u.id = t.other_id
      LEFT JOIN ( $unreadSql ) AS uc ON uc.other_id = t.other_id
      $attachJoin
      ORDER BY m.id DESC
    ";

    $st = $pdo->prepare($sql);
    // Bindings
    $st->bindValue(':meA', $meId, PDO::PARAM_INT);
    $st->bindValue(':meB', $meId, PDO::PARAM_INT);
    $st->bindValue(':limitVal', $limit, PDO::PARAM_INT);
    $st->bindValue(':me', $meId, PDO::PARAM_INT);
    $st->execute();

    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Preview-Text erzeugen (bei Attachments „📷/🎬/📎 …“)
    $threads = [];
    foreach ($rows as $r) {
        $preview = trim((string)($r['body'] ?? ''));

        if ($preview === '' && $hasAttach) {
            $attCount = (int)($r['att_count'] ?? 0);
            $hasImg   = !empty($r['has_image']);
            $hasVid   = !empty($r['has_video']);
            $hasFile  = !empty($r['has_file']);

            if ($attCount > 0) {
                if ($hasVid)       $preview = "🎬 " . ($attCount > 1 ? "$attCount Videos" : "Video");
                elseif ($hasImg)   $preview = "📷 " . ($attCount > 1 ? "$attCount Fotos"  : "Foto");
                elseif ($hasFile)  $preview = "📎 " . ($attCount > 1 ? "$attCount Dateien": "Datei");
                else               $preview = "📎 Anhang";
            }
        }

        // Kürzen
        $preview = mb_strimwidth($preview, 0, 160, '…', 'UTF-8');

        $threads[] = [
            'other_id'     => (int)$r['other_id'],
            'last_id'      => (int)$r['last_id'],
            'last_at'      => (string)$r['created_at'],
            'preview'      => $preview,
            'unread_count' => (int)$r['unread_count'],
            'user'         => [
                'id'           => (int)$r['other_id'],
                'display_name' => (string)$r['display_name'],
                'avatar_path'  => $r['avatar_path'] ?: null,
                'slug'         => $r['slug'] ?? null,
            ],
        ];
    }

    echo json_encode([
        'ok'      => true,
        'threads' => $threads
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'exception',
        'detail'=> isset($_GET['debug']) ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

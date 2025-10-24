<?php
// /api/post_delete.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('method_not_allowed');
    }

    // ==== Abhängigkeiten (ggf. Pfade anpassen) ====
    require_once __DIR__ . '/../auth/db.php';
    require_once __DIR__ . '/../auth/auth.php';   // require_auth()
    require_once __DIR__ . '/../auth/csrf.php';   // verify_csrf()

    $pdo = db();
    $me  = require_auth(); // wirft bei nicht eingeloggt

    // ==== Eingaben ====
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId <= 0) throw new RuntimeException('invalid_post_id');

    $csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (function_exists('verify_csrf') && !verify_csrf($pdo, $csrf)) {
        throw new RuntimeException('csrf_invalid');
    }

    // ==== Post laden ====
    $stmt = $pdo->prepare('SELECT id, user_id, thread_id FROM forum_posts WHERE id = ? LIMIT 1');
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) throw new RuntimeException('not_found');

    // ==== Rechte prüfen: Autor oder Mod/Admin ====
    $isOwner = ((int)$post['user_id'] === (int)$me['id']);
    $isMod   = !empty($me['is_admin']) || !empty($me['is_mod']);
    if (!$isOwner && !$isMod) throw new RuntimeException('forbidden');

    // ==== Soft Delete bevorzugen ====
    $hasIsDeleted = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM forum_posts LIKE 'is_deleted'")->fetch();
        if ($col) $hasIsDeleted = true;
    } catch (Throwable $e) { /* ignorieren */ }

    if ($hasIsDeleted) {
        $upd = $pdo->prepare(
            "UPDATE forum_posts
               SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
             WHERE id = ?"
        );
        $upd->execute([$me['id'], $postId]);
    } else {
        // Fallback: Inhalt neutralisieren (Thread-Struktur bleibt erhalten)
        $upd = $pdo->prepare(
            "UPDATE forum_posts
                SET content = '[gelöscht]', updated_at = NOW()
              WHERE id = ?"
        );
        $upd->execute([$postId]);
    }

    // Ziel für Redirect (thread.php?t=... [&slug=...])
    $threadId = (int)$post['thread_id'];
    $slug = '';
    try {
        $q = $pdo->prepare('SELECT slug FROM forum_threads WHERE id = ? LIMIT 1');
        $q->execute([$threadId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['slug'])) $slug = (string)$row['slug'];
    } catch (Throwable $e) {}

    // JSON-OK & optionaler Redirect-URL (für XHR) 
    $target = '/forum/thread.php?t=' . $threadId . ($slug ? '&slug=' . rawurlencode($slug) : '');
    echo json_encode(['ok' => true, 'redirect' => $target]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

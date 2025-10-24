<?php
/** PATCH: robustes confirm-Parsing **
 * - Akzeptiert x-www-form-urlencoded, JSON und (Fallback) raw body/query
 * - Trim + Uppercase-Vergleich ("DELETE")
 */

declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
$cfg = require __DIR__ . '/../../auth/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $me = require_auth();
    $userId = (int)($me['id'] ?? 0);
    if (!$userId) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthenticated']); exit; }

    // ---- robustes confirm-Parsing ----
    $confirm = '';
    $ctype = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

    // 1) x-www-form-urlencoded / multipart → $_POST
    if (isset($_POST['confirm'])) {
        $confirm = (string)$_POST['confirm'];
    }

    // 2) JSON → raw decode
    if ($confirm === '' && str_contains($ctype, 'application/json')) {
        $raw = (string)file_get_contents('php://input');
        $j = json_decode($raw, true);
        if (is_array($j) && isset($j['confirm'])) { $confirm = (string)$j['confirm']; }
    }

    // 3) Fallback: raw urlencoded Body (falls $_POST leer ist)
    if ($confirm === '') {
        $raw = (string)file_get_contents('php://input');
        if ($raw !== '') {
            parse_str($raw, $arr);
            if (is_array($arr) && isset($arr['confirm'])) { $confirm = (string)$arr['confirm']; }
        }
    }

    // 4) Ultimativer Fallback: Querystring (nur hilfreich für Debug)
    if ($confirm === '' && isset($_GET['confirm'])) {
        $confirm = (string)$_GET['confirm'];
    }

    $confirmNorm = strtoupper(trim($confirm));
    if ($confirmNorm !== 'DELETE') {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Bestätige mit confirm=DELETE']);
        exit;
    }

    // ==== AB HIER: unveränderter Lösch-Workflow (gekürzt auf das Wesentliche) ====
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ghost-User sicherstellen
    $ensureGhost = static function(PDO $pdo): int {
        $display='Gelöschter Benutzer'; $slug='geloeschter-benutzer'; $email='deleted@hunthub.local';
        $st=$pdo->prepare('SELECT id FROM users WHERE slug=:s LIMIT 1'); $st->execute([':s'=>$slug]); $id=$st->fetchColumn(); if($id){return (int)$id;}
        $check=$pdo->prepare('SELECT 1 FROM users WHERE email=:e LIMIT 1'); $suffix=''; do{ $e=$email.$suffix; $check->execute([':e'=>$e]); if(!$check->fetchColumn()){ $email=$e; break; } $suffix='+' . bin2hex(random_bytes(3)); }while(true);
        $ins=$pdo->prepare('INSERT INTO users (email,password_hash,display_name,slug,role,created_at,updated_at,is_admin) VALUES (:e,:ph,:n,:s,\'user\',NOW(),NOW(),0)');
        $ph=password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT); $ins->execute([':e'=>$email,':ph'=>$ph,':n'=>$display,':s'=>$slug]);
        return (int)$pdo->lastInsertId();
    };

    // User-Dateien merken
    $pdo->beginTransaction();
    $st=$pdo->prepare('SELECT avatar_path, cover_path FROM users WHERE id=:id'); $st->execute([':id'=>$userId]);
    $u=$st->fetch()?:[]; $avatarPath=$u['avatar_path']??null; $coverPath=$u['cover_path']??null;

    $ghostId=$ensureGhost($pdo);

    // Threads/Posts umhängen (Passe Spalten ggf. an dein Schema an: author_id vs created_by)
    try { $pdo->prepare('UPDATE threads SET author_id=:g WHERE author_id=:u')->execute([':g'=>$ghostId,':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('UPDATE posts   SET author_id=:g WHERE author_id=:u')->execute([':g'=>$ghostId,':u'=>$userId]); } catch(Throwable $e){}

    // Nachrichten (Passe ggf.: recipient_id vs receiver_id)
    try { $pdo->prepare('UPDATE messages SET sender_id=:g    WHERE sender_id=:u')->execute([':g'=>$ghostId,':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('UPDATE messages SET recipient_id=:g WHERE recipient_id=:u')->execute([':g'=>$ghostId,':u'=>$userId]); } catch(Throwable $e){}

    // Beziehungen & Metadaten
    try { $pdo->prepare('DELETE FROM friendships WHERE requester_id=:u OR addressee_id=:u')->execute([':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('DELETE FROM post_likes   WHERE user_id=:u')->execute([':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('DELETE FROM thread_likes WHERE user_id=:u')->execute([':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('DELETE FROM notifications WHERE user_id=:u')->execute([':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('UPDATE notifications SET actor_id=:g WHERE actor_id=:u')->execute([':g'=>$ghostId,':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('DELETE FROM user_settings WHERE user_id=:u')->execute([':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('DELETE FROM user_stats    WHERE user_id=:u')->execute([':u'=>$userId]); } catch(Throwable $e){}
    try { $pdo->prepare('DELETE FROM user_achievements WHERE user_id=:u')->execute([':u'=>$userId]); } catch(Throwable $e){}

    // Sessions
    try { $tbl=$cfg['sessions_table']??'auth_sessions'; $pdo->prepare("DELETE FROM `{$tbl}` WHERE user_id=:u")->execute([':u'=>$userId]); } catch(Throwable $e){}

    // User löschen
    $pdo->prepare('DELETE FROM users WHERE id=:u')->execute([':u'=>$userId]);
    $pdo->commit();

    // Filesystem (best effort, innerhalb Webroot)
    $safeUnlink = static function(?string $p): void{ if(!$p) return; $p=str_replace('..','',$p); $root=dirname(__DIR__,2); $abs=realpath($root.'/'.ltrim($p,'/')); $rootReal=realpath($root); if($abs && $rootReal && str_starts_with($abs,$rootReal) && is_file($abs)){ @unlink($abs); } };
    $safeUnlink($avatarPath); $safeUnlink($coverPath);

    // Cookie killen
    $cookie = $cfg['cookies'] ?? []; $name=$cookie['session_name']??'sess_id';
    setcookie($name,'',[ 'expires'=>time()-3600, 'path'=>$cookie['path']??'/', 'domain'=>$cookie['domain']??null, 'secure'=>(bool)($cookie['secure']??false), 'httponly'=>(bool)($cookie['httponly']??true), 'samesite'=>$cookie['samesite']??'Lax' ]);

    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Serverfehler: '.$e->getMessage()]);
}

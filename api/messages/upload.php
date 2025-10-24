<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';

try {
    $cfg = require __DIR__ . '/../../auth/config.php';
    $APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

    $pdo = db();
    $me  = current_user();
    if (!$me || empty($me['id'])) {
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'not_logged_in']);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'method']);
        exit;
    }

    $sessionName = $cfg['cookies']['session_name'] ?? '';
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!check_csrf($pdo, $_COOKIE[$sessionName] ?? '', $csrf)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad_csrf']);
        exit;
    }

    if (empty($_FILES['file']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'no_file']);
        exit;
    }

    // Limits
    $MAX_IMAGE_MB = 15;
    $MAX_VIDEO_MB = 200;

    $tmp  = $_FILES['file']['tmp_name'];
    $name = (string)($_FILES['file']['name'] ?? 'file');
    $size = (int)($_FILES['file']['size'] ?? 0);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp) ?: 'application/octet-stream';

    $isImage = str_starts_with($mime, 'image/');
    $isVideo = str_starts_with($mime, 'video/');

    if (!$isImage && !$isVideo) {
        http_response_code(415);
        echo json_encode(['ok'=>false,'error'=>'unsupported_type','detail'=>$mime]);
        exit;
    }

    if ($isImage && $size > $MAX_IMAGE_MB*1024*1024) {
        http_response_code(413);
        echo json_encode(['ok'=>false,'error'=>'image_too_large']);
        exit;
    }
    if ($isVideo && $size > $MAX_VIDEO_MB*1024*1024) {
        http_response_code(413);
        echo json_encode(['ok'=>false,'error'=>'video_too_large']);
        exit;
    }

    // Upload-Ordner
    $Y = date('Y'); $m = date('m');
    $root  = dirname(__DIR__, 1); // /api
    $upDir = $root . '/../uploads/messages/' . $Y . '/' . $m . '/';
    if (!is_dir($upDir) && !@mkdir($upDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'mkdir_failed']);
        exit;
    }

    // Dateinamen absichern
    $safeName = function(string $s): string {
        $s = preg_replace('/[^\w\.\-]+/u', '_', $s) ?? 'file';
        return trim($s, '._');
    };

    $base   = pathinfo($name, PATHINFO_FILENAME);
    $extIn  = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
    $uniq   = bin2hex(random_bytes(8));

    // bessere Ext-Mapping nach MIME
    $extByMime = function(string $mime, bool $isVideo, string $fallbackExt) {
        $map = [
            'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif',
            'video/mp4'=>'mp4', 'video/quicktime'=>'mov', 'video/webm'=>'webm', 'video/x-matroska'=>'mkv'
        ];
        return $map[$mime] ?? ($fallbackExt ?: ($isVideo ? 'mp4' : 'jpg'));
    };
    $ext    = $extByMime($mime, $isVideo, $extIn);

    $meta = [
        'original_name' => $name,
        'size'          => $size,
        'mime'          => $mime
    ];

    if ($isImage) {
        $fileName = $safeName($base) . '_' . $uniq . '.' . $ext;
        $dest     = $upDir . $fileName;
        $public   = '/uploads/messages/'.$Y.'/'.$m.'/'.$fileName;

        if (!move_uploaded_file($tmp, $dest)) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'move_failed']);
            exit;
        }

        // Optional: Abmessungen
        [$w,$h] = @getimagesize($dest) ?: [null,null];
        if ($w && $h) { $meta['width']=$w; $meta['height']=$h; }

        echo json_encode([
            'ok'=>true,
            'type'=>'image',
            'url'=>$public,
            'meta'=>$meta
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ---- Video ----
    $trimStart = isset($_POST['trim_start']) ? (float)$_POST['trim_start'] : null;
    $trimEnd   = isset($_POST['trim_end'])   ? (float)$_POST['trim_end']   : null;

    // Eingangsdatei erst sicher unter eigenem Namen ablegen
    $origName = $safeName($base) . '_orig_' . $uniq . '.' . $ext;
    $origPath = $upDir . $origName;
    if (!move_uploaded_file($tmp, $origPath)) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'move_failed']);
        exit;
    }

    // ffmpeg/ffprobe vorhanden?
    $ffmpeg  = trim(shell_exec('command -v ffmpeg')  ?? '');
    $ffprobe = trim(shell_exec('command -v ffprobe') ?? '');
    $duration = null;

    if ($ffprobe) {
        $cmd = escapeshellcmd($ffprobe)
            .' -v error -show_entries format=duration -of default=nw=1:nk=1 '
            .escapeshellarg($origPath).' 2>/dev/null';
        $dur = trim(shell_exec($cmd) ?? '');
        if (is_numeric($dur)) $duration = (float)$dur;
        $meta['duration'] = $duration;
    }

    // Ziel: bevorzugt MP4 (H.264/AAC)
    $outName = $safeName($base) . '_' . $uniq . '.mp4';
    $outPath = $upDir . $outName;
    $outUrl  = '/uploads/messages/'.$Y.'/'.$m.'/'.$outName;

    $thumbUrl = null;

    if ($ffmpeg) {
        $haveTrim = ($trimStart !== null && $trimEnd !== null && $trimEnd > $trimStart + 0.01);
        if ($haveTrim) {
            $ss = max(0.0, (float)$trimStart);
            $to = max(0.0, (float)$trimEnd);
            $cmd = sprintf(
                '%s -ss %s -to %s -i %s -vf "scale=\'min(1280,iw)\':-2" -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart %s 2>&1',
                escapeshellcmd($ffmpeg),
                escapeshellarg((string)$ss),
                escapeshellarg((string)$to),
                escapeshellarg($origPath),
                escapeshellarg($outPath)
            );
        } else {
            $cmd = sprintf(
                '%s -i %s -vf "scale=\'min(1280,iw)\':-2" -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart %s 2>&1',
                escapeshellcmd($ffmpeg),
                escapeshellarg($origPath),
                escapeshellarg($outPath)
            );
        }
        $out = shell_exec($cmd);

        if (!is_file($outPath)) {
            // Fallback: Original-Datei behalten
            @rename($origPath, $outPath);
        } else {
            // Thumbnail erzeugen (bei 0.5s ab Start oder 0.5s ab trimStart)
            $thumbName = $safeName($base) . '_' . $uniq . '.jpg';
            $thumbPath = $upDir . $thumbName;
            $thumbUrl  = '/uploads/messages/'.$Y.'/'.$m.'/'.$thumbName;

            $ts = 0.5;
            if ($trimStart !== null) $ts = max(0.0, (float)$trimStart + 0.5);

            $thumbCmd = sprintf(
                '%s -ss %s -i %s -frames:v 1 -vf "scale=\'min(640,iw)\':-2" -q:v 3 %s 2>&1',
                escapeshellcmd($ffmpeg),
                escapeshellarg((string)$ts),
                escapeshellarg($outPath),
                escapeshellarg($thumbPath)
            );
            shell_exec($thumbCmd);
        }

        // Aufräumen
        if (is_file($origPath)) @unlink($origPath);
    } else {
        // Kein ffmpeg: Originaldatei unter Original-Endung zurückgeben
        $noFfmpegName = $safeName($base) . '_' . $uniq . '.' . $ext;
        $noFfmpegPath = $upDir . $noFfmpegName;
        @rename($origPath, $noFfmpegPath);
        $outPath = $noFfmpegPath;
        $outUrl  = '/uploads/messages/'.$Y.'/'.$m.'/'.$noFfmpegName;
        // kein Thumbnail
    }

    $meta['thumb'] = $thumbUrl;

    echo json_encode([
        'ok'   => true,
        'type' => 'video',
        'url'  => $outUrl,
        'meta' => $meta
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'exception',
        'detail'=> $e->getMessage()
    ]);
}

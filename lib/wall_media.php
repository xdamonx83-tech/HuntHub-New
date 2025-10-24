<?php
declare(strict_types=1);

/**
 * Wall Media Helpers
 *
 * Speichert Uploads je Post unter:
 *   /uploads/posts/p{POST_ID}/
 *
 * Regeln:
 *  - Erlaubt nur Bilder (image/*)
 *  - GIFs bleiben GIF
 *  - Alle anderen Bilder werden zu WEBP (Qualität 82) konvertiert
 *  - Optionales Resize: Max-Kante 2048px
 *  - Liefert immer öffentliche URLs zurück (inkl. app_base/Projekt-Subpfad)
 *
 * Zusätzlich:
 *  - Deduplication auf Basis sha1_file(), falls dieselbe Datei doppelt gesendet wird
 *  - HTML-Renderer für Attachments (mit data-full + CSS-Klassen für Viewer)
 */

/* -------------------------------------------------------
 *   Verzeichnisse & URL-Basis
 * -----------------------------------------------------*/

if (!function_exists('wall_media_base_dir')) {
  /** Absoluter Pfad zum Basis-Upload-Ordner: /uploads/posts */
  function wall_media_base_dir(): string {
    // /lib -> Projektroot
    $root = dirname(__DIR__);
    $dir  = $root . '/uploads/posts';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
  }
}

if (!function_exists('wall_media_post_dir')) {
  /** Absoluter Pfad zum Post-Ordner: /uploads/posts/p{POST_ID} */
  function wall_media_post_dir(int $postId): string {
    $dir = wall_media_base_dir() . '/p' . $postId;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
  }
}

if (!function_exists('wall_media_post_url_base')) {
  /** Öffentliche URL-Basis (mit app_base, falls vorhanden) */
  function wall_media_post_url_base(int $postId): string {
    $base = '';
    if (function_exists('app_base'))      $base = rtrim((string)app_base(), '/');
    elseif (function_exists('hh_base'))   $base = rtrim((string)hh_base(), '/');
    return $base . '/uploads/posts/p' . $postId . '/';
  }
}

/* -------------------------------------------------------
 *   Listen & Rendering
 * -----------------------------------------------------*/

if (!function_exists('wall_media_list')) {
  /** Liefert eine sortierte Liste der Medien-URLs eines Posts */
  function wall_media_list(int $postId): array {
    $dir = wall_media_post_dir($postId);
    if (!is_dir($dir)) return [];
    $files = array_values(array_filter(scandir($dir) ?: [], fn($f) => $f !== '.' && $f !== '..'));
    natsort($files);
    $base = wall_media_post_url_base($postId);
    return array_values(array_map(fn($f) => $base . $f, $files));
  }
}

if (!function_exists('wall_render_post_attachments_html')) {
  /**
   * Baut das HTML für die Medien eines Posts
   * - Ein Bild: vollbreit-Container (.hh-wall-media-1)
   * - Mehrere: Grid (.hh-wall-media-grid)
   * - <img> enthält class="hh-media-img" und data-full für den Viewer
   */
  function wall_render_post_attachments_html(int $postId): string {
    $urls = wall_media_list($postId);
    if (!$urls) return '';
    $c = count($urls);

    $html = '<div class="hh-wall-media">';
    if ($c === 1) {
      $u = htmlspecialchars($urls[0], ENT_QUOTES, 'UTF-8');
      $html .= '<div class="hh-wall-media-1">'
             . '<img class="hh-media-img" loading="lazy" src="'.$u.'" data-full="'.$u.'" alt="">'
             . '</div>';
    } else {
      $html .= '<div class="hh-wall-media-grid">';
      foreach ($urls as $u) {
        $u = htmlspecialchars($u, ENT_QUOTES, 'UTF-8');
        $html .= '<div class="hh-wall-media-item">'
               . '<img class="hh-media-img" loading="lazy" src="'.$u.'" data-full="'.$u.'" alt="">'
               . '</div>';
      }
      $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
  }
}

/* -------------------------------------------------------
 *   Upload & Konvertierung
 * -----------------------------------------------------*/

if (!function_exists('wall_media_save_uploads')) {
  /**
   * Speichert hochgeladene Dateien aus $_FILES['files'] (und toleriert zusätzlich 'file').
   *   - Nicht-GIF -> WEBP (Qualität 82)
   *   - GIF -> bleibt GIF
   *   - Dedup via sha1_file()
   *   - Resize auf max. 2048px Kantenlänge
   * Rückgabe: Liste der öffentlichen URLs der gespeicherten Dateien.
   */
  function wall_media_save_uploads(int $postId): array {
    if (empty($_FILES)) return [];

    // Eingänge normalisieren
    $targets = [];
    foreach (['files', 'file'] as $key) {
      if (!isset($_FILES[$key])) continue;
      $e = $_FILES[$key];

      if (is_array($e['name'])) {
        $n = count($e['name']);
        for ($i = 0; $i < $n; $i++) {
          // Überspringe leere Felder
          if (($e['name'][$i] ?? '') === '') continue;
          $targets[] = [
            'name' => (string)$e['name'][$i],
            'type' => (string)($e['type'][$i] ?? ''),
            'tmp'  => (string)$e['tmp_name'][$i],
            'err'  => (int)($e['error'][$i] ?? 0),
          ];
        }
      } else {
        if (($e['name'] ?? '') === '') continue;
        $targets[] = [
          'name' => (string)$e['name'],
          'type' => (string)($e['type'] ?? ''),
          'tmp'  => (string)$e['tmp_name'],
          'err'  => (int)($e['error'] ?? 0),
        ];
      }
    }

    if (!$targets) return [];

    $dir  = wall_media_post_dir($postId);
    $base = wall_media_post_url_base($postId);

    $urls = [];
    $seen = []; // sha1 -> true (Dedup)

    foreach ($targets as $t) {
      if ($t['err'] !== UPLOAD_ERR_OK || !is_uploaded_file($t['tmp'])) continue;

      // Duplikate filtern
      $hash = @sha1_file($t['tmp']) ?: null;
      if ($hash && isset($seen[$hash])) continue;
      if ($hash) $seen[$hash] = true;

      // MIME robust bestimmen
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime  = $finfo ? (string)finfo_file($finfo, $t['tmp']) : ($t['type'] ?: '');
      if ($finfo) finfo_close($finfo);

      // Nur Bilder
      if (strpos($mime, 'image/') !== 0) continue;

      $isGif = (strtolower($mime) === 'image/gif');
      $uniq  = bin2hex(random_bytes(6));
      $dst   = $dir . '/m_' . $uniq . ($isGif ? '.gif' : '.webp');
      $url   = $base . basename($dst);

      if ($isGif) {
        @move_uploaded_file($t['tmp'], $dst);
        if (file_exists($dst)) { @chmod($dst, 0644); $urls[] = $url; }
        continue;
      }

      $maxEdge = 2048;

      // 1) Imagick bevorzugen
      if (class_exists('Imagick')) {
        try {
          $im = new Imagick($t['tmp']);
          if ($im->getNumberImages() > 1) $im = $im->coalesceImages();
          $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);

          $w = $im->getImageWidth(); $h = $im->getImageHeight();
          $scale = max($w, $h) > $maxEdge ? ($maxEdge / max($w, $h)) : 1.0;
          if ($scale < 1.0) {
            $im->resizeImage((int)round($w*$scale), (int)round($h*$scale), Imagick::FILTER_LANCZOS, 1);
          }

          $im->setImageFormat('webp');
          $im->setImageCompressionQuality(82);
          $im->writeImage($dst);
          $im->clear(); $im->destroy();

          if (file_exists($dst)) { @chmod($dst, 0644); $urls[] = $url; }
          continue;
        } catch (\Throwable $e) {
          // Fallback auf GD unten
        }
      }

      // 2) GD Fallback
      $blob = @file_get_contents($t['tmp']);
      if ($blob === false) continue;
      $img = @imagecreatefromstring($blob);
      if (!$img) continue;

      $w = imagesx($img); $h = imagesy($img);
      $scale = max($w, $h) > $maxEdge ? ($maxEdge / max($w, $h)) : 1.0;
      if ($scale < 1.0) {
        $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
        $dstImg = imagecreatetruecolor($nw, $nh);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        imagecopyresampled($dstImg, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dstImg;
      }

      imagealphablending($img, false);
      imagesavealpha($img, true);

      // WEBP speichern (falls GD WEBP kann)
      $ok = false;
      if (function_exists('imagewebp')) {
        $ok = @imagewebp($img, $dst, 82);
      }
      imagedestroy($img);

      if ($ok && file_exists($dst)) {
        @chmod($dst, 0644);
        $urls[] = $url;
      }
    }

    return $urls;
  }
}
/* =======================================================
 *   COMMENT MEDIA  (/uploads/comments/c{ID}/)
 * =====================================================*/

if (!function_exists('comment_media_base_dir')) {
  function comment_media_base_dir(): string {
    $root = dirname(__DIR__);
    $dir  = $root . '/uploads/comments';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
  }
}

if (!function_exists('comment_media_comment_dir')) {
  function comment_media_comment_dir(int $commentId): string {
    $dir = comment_media_base_dir() . '/c' . $commentId;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
  }
}

if (!function_exists('comment_media_comment_url_base')) {
  function comment_media_comment_url_base(int $commentId): string {
    $base = '';
    if (function_exists('app_base'))      $base = rtrim((string)app_base(), '/');
    elseif (function_exists('hh_base'))   $base = rtrim((string)hh_base(), '/');
    return $base . '/uploads/comments/c' . $commentId . '/';
  }
}

if (!function_exists('comment_media_list')) {
  function comment_media_list(int $commentId): array {
    $dir = comment_media_comment_dir($commentId);
    if (!is_dir($dir)) return [];
    $files = array_values(array_filter(scandir($dir) ?: [], fn($f) => $f !== '.' && $f !== '..'));
    natsort($files);
    $base = comment_media_comment_url_base($commentId);
    return array_values(array_map(fn($f) => $base . $f, $files));
  }
}

if (!function_exists('comment_media_save_uploads')) {
  /** Speichert Bilder aus $_FILES (Nur image/*; GIF bleibt GIF; sonst WEBP) */
  function comment_media_save_uploads(int $commentId): array {
    if (empty($_FILES)) return [];
    $targets = [];
    foreach (['files','file'] as $key) {
      if (!isset($_FILES[$key])) continue;
      $e = $_FILES[$key];
      if (is_array($e['name'])) {
        for ($i=0,$n=count($e['name']); $i<$n; $i++) {
          if (($e['name'][$i] ?? '') === '') continue;
          $targets[] = ['name'=>(string)$e['name'][$i], 'type'=>(string)($e['type'][$i]??''), 'tmp'=>(string)$e['tmp_name'][$i], 'err'=>(int)($e['error'][$i]??0)];
        }
      } else {
        if (($e['name'] ?? '') === '') continue;
        $targets[] = ['name'=>(string)$e['name'], 'type'=>(string)($e['type']??''), 'tmp'=>(string)$e['tmp_name'], 'err'=>(int)($e['error']??0)];
      }
    }
    if (!$targets) return [];

    $dir  = comment_media_comment_dir($commentId);
    $base = comment_media_comment_url_base($commentId);
    $urls = [];
    $seen = [];

    foreach ($targets as $t) {
      if ($t['err'] !== UPLOAD_ERR_OK || !is_uploaded_file($t['tmp'])) continue;

      $hash = @sha1_file($t['tmp']) ?: null;
      if ($hash && isset($seen[$hash])) continue;
      if ($hash) $seen[$hash] = true;

      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime  = $finfo ? (string)finfo_file($finfo, $t['tmp']) : ($t['type'] ?: '');
      if ($finfo) finfo_close($finfo);
      if (strpos($mime, 'image/') !== 0) continue;

      $isGif = (strtolower($mime) === 'image/gif');
      $uniq  = bin2hex(random_bytes(6));
      $dst   = $dir . '/m_' . $uniq . ($isGif ? '.gif' : '.webp');
      $url   = $base . basename($dst);

      if ($isGif) {
        @move_uploaded_file($t['tmp'], $dst);
        if (file_exists($dst)) { @chmod($dst,0644); $urls[] = $url; }
        continue;
      }

      $maxEdge = 1600;
      if (class_exists('Imagick')) {
        try {
          $im = new Imagick($t['tmp']);
          if ($im->getNumberImages() > 1) $im = $im->coalesceImages();
          $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
          $w = $im->getImageWidth(); $h = $im->getImageHeight();
          $scale = max($w,$h) > $maxEdge ? ($maxEdge / max($w,$h)) : 1.0;
          if ($scale < 1.0) $im->resizeImage((int)round($w*$scale),(int)round($h*$scale),Imagick::FILTER_LANCZOS,1);
          $im->setImageFormat('webp');
          $im->setImageCompressionQuality(82);
          $im->writeImage($dst);
          $im->clear(); $im->destroy();
          if (file_exists($dst)) { @chmod($dst,0644); $urls[] = $url; }
          continue;
        } catch (\Throwable $e) { /* GD */ }
      }

      $blob = @file_get_contents($t['tmp']); if ($blob === false) continue;
      $img  = @imagecreatefromstring($blob); if (!$img) continue;
      $w=imagesx($img); $h=imagesy($img);
      $scale = max($w,$h) > $maxEdge ? ($maxEdge / max($w,$h)) : 1.0;
      if ($scale < 1.0) {
        $nw=(int)round($w*$scale); $nh=(int)round($h*$scale);
        $dstImg = imagecreatetruecolor($nw,$nh);
        imagealphablending($dstImg,false); imagesavealpha($dstImg,true);
        imagecopyresampled($dstImg,$img,0,0,0,0,$nw,$nh,$w,$h);
        imagedestroy($img); $img = $dstImg;
      }
      imagealphablending($img,false); imagesavealpha($img,true);
      $ok = function_exists('imagewebp') ? @imagewebp($img,$dst,82) : false;
      imagedestroy($img);
      if ($ok && file_exists($dst)) { @chmod($dst,0644); $urls[] = $url; }
    }

    return $urls;
  }
}

if (!function_exists('comment_render_attachments_html')) {
  function comment_render_attachments_html(int $commentId): string {
    $urls = comment_media_list($commentId);
    if (!$urls) return '';
    $c = count($urls);
    $h = '<div class="hh-comment-media">';
    if ($c === 1) {
      $u = htmlspecialchars($urls[0], ENT_QUOTES, 'UTF-8');
      $h .= '<div class="hh-wall-media-1"><img class="hh-media-img" loading="lazy" src="'.$u.'" data-full="'.$u.'" alt=""></div>';
    } else {
      $h .= '<div class="hh-wall-media-grid">';
      foreach ($urls as $u) {
        $u = htmlspecialchars($u, ENT_QUOTES, 'UTF-8');
        $h .= '<div class="hh-wall-media-item"><img class="hh-media-img" loading="lazy" src="'.$u.'" data-full="'.$u.'" alt=""></div>';
      }
      $h .= '</div>';
    }
    $h .= '</div>';
    return $h;
  }
}

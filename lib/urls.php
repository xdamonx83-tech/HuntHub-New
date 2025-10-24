<?php
declare(strict_types=1);

if (!function_exists('hh_slugify')) {
  function hh_slugify(string $s): string {
    $s = trim($s);
    if (function_exists('iconv')) {
      $t = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
      if ($t !== false) $s = $t;
    }
    $s = mb_strtolower($s,'UTF-8');
    $s = preg_replace('~[^\pL0-9]+~u','-',$s);
    $s = preg_replace('~-+~','-',$s);
    return trim($s,'-') ?: '';
  }
}

if (!function_exists('hh_base')) {
  function hh_base(?string $appBase = null): string {
    if ($appBase !== null && $appBase !== '') return rtrim($appBase,'/');
    if (isset($GLOBALS['APP_BASE'])) return rtrim((string)$GLOBALS['APP_BASE'],'/');
    return '';
  }
}

if (!function_exists('hh_forum_path')) {
  function hh_forum_path(): string {
    static $p = null;
    if ($p !== null) return $p;
    $cfgFile = __DIR__ . '/../auth/config.php';
    $cfg = is_file($cfgFile) ? require $cfgFile : [];
    $p = rtrim((string)($cfg['forum_base'] ?? '/forum'), '/');
    if ($p === '') $p = '/forum';
    return $p; // z.B. /frm
  }
}

if (!function_exists('forum_root_url')) {
  function forum_root_url(?string $appBase = null): string {
    return hh_base($appBase) . hh_forum_path() . '/';
  }
}

if (!function_exists('forum_url_board')) {
  function forum_url_board(array|int $board, ?string $appBase = null): string {
    $id   = is_array($board) ? (int)($board['id'] ?? 0) : (int)$board;
    $text = is_array($board) ? (string)($board['slug'] ?? $board['name'] ?? $board['title'] ?? '') : '';
    $slug = $text !== '' ? ('-' . hh_slugify($text)) : '';
    return hh_base($appBase) . hh_forum_path() . '/board/' . $id . $slug;
  }
}

if (!function_exists('forum_url_thread')) {
  function forum_url_thread(array|int $thread, ?string $appBase = null): string {
    $id   = is_array($thread) ? (int)($thread['id'] ?? 0) : (int)$thread;
    $text = is_array($thread) ? (string)($thread['slug'] ?? $thread['title'] ?? '') : '';
    $slug = $text !== '' ? ('-' . hh_slugify($text)) : '';
    return hh_base($appBase) . hh_forum_path() . '/thread/' . $id . $slug;
  }
}

if (!function_exists('forum_url_user')) {
  function forum_url_user(array|int $user, ?string $appBase = null): string {
    $id   = is_array($user) ? (int)($user['id'] ?? 0) : (int)$user;
    $text = is_array($user) ? (string)($user['slug'] ?? $user['display_name'] ?? $user['name'] ?? '') : '';
    $slug = $text !== '' ? ('-' . hh_slugify($text)) : '';
    return hh_base($appBase) . '/user/' . $id . $slug;
  }
}

/**
 * Normalisiert generische Social Handles (TikTok, Instagram, Twitter, etc.)
 */
if (!function_exists('norm_handle_basic')) {
  function norm_handle_basic(?string $raw): ?string {
    if ($raw === null) return null;
    $s = trim($raw);
    if ($s === '') return null;

    $s = strtolower($s);

    // Falls URL mit /@user oder /user
    if (preg_match('~^(https?://)?(www\.)?[^/]+/(@?[a-z0-9._-]+)~i', $s, $m)) {
      return ltrim($m[3], '@');
    }

    // Falls mit @ eingegeben
    if ($s[0] === '@') {
      return substr($s, 1);
    }

    return $s;
  }
}

/**
 * Normalisiert YouTube Eingaben (Kanal-ID, /c/, /channel/, @Handle)
 */
if (!function_exists('norm_youtube')) {
  function norm_youtube(?string $raw): ?string {
    if ($raw === null) return null;
    $s = trim($raw);
    if ($s === '') return null;

    $s = strtolower($s);

    // handle @name
    if ($s[0] === '@') {
      return substr($s, 1);
    }

    // Volle URLs
    if (preg_match('~youtube\.com/(?:c/|channel/|user/)?(@?[a-z0-9._-]+)~i', $s, $m)) {
      return ltrim($m[1], '@');
    }

    // Shorts oder watch → ignorieren
    if (preg_match('~youtube\.com/(watch|shorts)~i', $s)) {
      return null;
    }

    return $s;
  }
}

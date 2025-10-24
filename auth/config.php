<?php
declare(strict_types=1);

return [
  'db' => [
    'dsn'  => 'mysql:host=localhost;dbname=hunthub_db1;charset=utf8mb4',
    'user' => 'hunthub_db1',
    'pass' => 'T2M25sfeLZ@j#_bH%_',
    'options' => [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ],
  ],

  // Basis-URL-Pfad relativ zur Domain (leer = Root)
  'app_base' => '',
  // Öffentliche Basis-URL (für Links in E-Mails)
  'app_base_public' => 'https://hunthub.online',

  // Sessions
  'sessions_table'     => 'auth_sessions',
  'sessions_touch_col' => 'last_seen',

  // ---- App Keys ----
  'app_key'       => 'CHANGE_ME_LONG_RANDOM',
  'ws_url'        => 'wss://hunthub.online',
  'ws_jwt_secret' => 'a37f92c41d68b02e5acf13897be456d20a9cf1673b8d24ea7519bd406cf82a91',

  // ---- Cookie-Settings (Top-Level: für Helper/Legacy-Code) ----
  'session_cookie'  => 'sess_id',
  'cookie_path'     => '/',
  'cookie_domain'   => null,
  'cookie_secure'   => true,
  'cookie_httponly' => true,
  'cookie_samesite' => 'Lax',

  // ---- Optional: bestehender Block für andere Stellen im Code ----
  'cookies' => [
    'session_name' => 'sess_id',
    'lifetime'     => 60*60*24*14, // 14 Tage
    'secure'       => true,
    'httponly'     => true,
    'samesite'     => 'Lax',
    'path'         => '/',
    'domain'       => null,        // ggf. '.hunthub.online'
  ],

  'gifs' => ['provider'=>'giphy','giphy_key'=>'E2VjWjIwY7seuWxgFrpjS3DPDBrmcY51','limit'=>24],

  // Sticker Ordner (für Sticker-Tab)
  'stickers' => [
    'dir'      => __DIR__ . '/../uploads/stickers',
    'base_url' => '/uploads/stickers',
  ],

  // Uploads
  'uploads' => [
    'avatars_dir' => __DIR__ . '/../uploads/avatars',
    'covers_dir'  => __DIR__ . '/../uploads/covers',
    'max_size'    => 2 * 1024 * 1024,
    'allowed'     => ['image/jpeg','image/png','image/gif','image/webp'],

    // ▼ Videos
    'videos_dir'       => __DIR__ . '/../uploads/videos',
    'videos_max_size'  => 300 * 1024 * 1024,
    'videos_allowed'   => ['video/mp4','video/quicktime','video/webm','video/x-matroska'],
    'ffmpeg_bin'       => '/usr/bin/ffmpeg',
    'ffprobe_bin'      => '/usr/bin/ffprobe',
  ],

  // ---------- Mail & SMTP ----------
'mail' => [
  'from'      => 'no-reply@hunthub.online', // als Senderadresse in Brevo verifizieren!
  'from_name' => 'Hunthub',
],
'smtp' => [
  'host'   => 'smtp-relay.brevo.com',
  'port'   => 587,
  'secure' => 'tls', // bei Port 465 wäre 'ssl'
  'user'   => '963dc1001@smtp-brevo.com',
  'pass'   => 'd4N6HJDFxMOwzbUP', // dein Brevo SMTP-Schlüssel
  'timeout'=> 12,
],
'qa_admin_key' => getenv('QA_ADMIN_KEY') ?: 'oty4JIvLgRJAMWgBG6I3',
  'ai' => [
    'provider' => 'openai',
    'openai' => [
      'api_key'     => getenv('OPENAI_API_KEY') ?: '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',
      'chat_model'  => 'gpt-4o-mini',
      'embed_model' => 'text-embedding-3-small',
      'temperature' => 0.2,
      'max_tokens'  => 220,
    ],
  ],  // <-- ai sauber schließen


  // Tabelle für Passwort-Resets
  'password_resets_table' => 'auth_password_resets',
];
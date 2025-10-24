<?php
// /auth/oauth_config.php
// Lies gern aus deiner /.env – hier simple Variante mit getenv() Fallbacks.
$APP_BASE = rtrim((require __DIR__ . '/config.php')['app_base'] ?? '', '/'); 
// Falls dein config.php kein Array zurückgibt, setz $APP_BASE hier hart:
// $APP_BASE = ''; // z.B. '' oder '/cms' – bei dir meist ''

return [
  'app_base' => $APP_BASE, // für Redirect-URIs

  'google' => [
    'client_id'     => getenv('GOOGLE_CLIENT_ID') ?: '381692498038-eclm7ssbhf4miurs95ip4quo1s8mejnm.apps.googleusercontent.com',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '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',
    'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url'     => 'https://oauth2.googleapis.com/token',
    'userinfo_url'  => 'https://openidconnect.googleapis.com/v1/userinfo',
    'scopes'        => ['openid','email','profile'],
  ],

  'facebook' => [
    'client_id'     => getenv('FACEBOOK_CLIENT_ID') ?: '1276128401231208',
    'client_secret' => getenv('FACEBOOK_CLIENT_SECRET') ?: '54da7a7b1dfa743ea12cfaacab105667',
    'authorize_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
    'token_url'     => 'https://graph.facebook.com/v19.0/oauth/access_token',
    'userinfo_url'  => 'https://graph.facebook.com/me?fields=id,name,email,picture',
    'scopes'        => ['email','public_profile'],
  ],

  'twitch' => [
    'client_id'     => getenv('TWITCH_CLIENT_ID') ?: 'wp33fylsuogwv49bsrlqrtgianmkuk',
    'client_secret' => getenv('TWITCH_CLIENT_SECRET') ?: 'dvso23g5evi9t2refnjhma5gwwsvu3',
    'authorize_url' => 'https://id.twitch.tv/oauth2/authorize',
    'token_url'     => 'https://id.twitch.tv/oauth2/token',
    'userinfo_url'  => 'https://api.twitch.tv/helix/users',
    'scopes'        => ['user:read:email'],
  ],

  // Steam nutzt OpenID (kein klassisches OAuth Code-Exchange)
  'steam' => [
    'openid_url' => 'https://steamcommunity.com/openid/login',
    // Für Userdetails brauchst du einen API-Key
    'webapi_key' => getenv('STEAM_WEBAPI_KEY') ?: '192B615F9298DC2A27AB538618DB1C55',
    'playersummaries_url' => 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/',
  ],
];

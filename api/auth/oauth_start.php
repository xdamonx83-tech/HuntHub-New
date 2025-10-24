<?php
declare(strict_types=1);

/**
 * /api/auth/oauth_start.php
 * Startet den OAuth/OpenID-Flow für google | facebook | twitch | steam.
 * Baut IMMER eine ABSOLUTE redirect_uri auf Basis von Host/Proto (Reverse-Proxy-tauglich).
 */

require_once __DIR__ . '/oauth_lib.php'; // enthält State-Helpers, optional app_origin()
$config   = require __DIR__ . '/../../auth/oauth_config.php';
$APP_BASE = rtrim($config['app_base'] ?? '', '/');

// ---------- Origin bestimmen (falls Helper nicht existiert) ----------
if (!function_exists('app_origin')) {
  function app_origin(): string {
    // Reverse-Proxy-Header respektieren
    $proto = !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
      ? $_SERVER['HTTP_X_FORWARDED_PROTO']
      : (((($_SERVER['HTTPS'] ?? '') !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http');
    $host = $_SERVER['HTTP_X_FORWARDED_HOST']
      ?? ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    return $proto . '://' . $host;
  }
}
$ORIGIN = app_origin();

// ---------- Provider prüfen ----------
$provider = $_GET['provider'] ?? '';
$allowed  = ['google','facebook','twitch','steam'];
if (!in_array($provider, $allowed, true)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Unknown provider';
  exit;
}

// ---------- Steam (OpenID) ----------
if ($provider === 'steam') {
  $state    = oauth_state_start('steam');
  $returnTo = $ORIGIN . $APP_BASE . "/api/auth/oauth_callback.php?provider=steam&state={$state}";

  $openidParams = [
    'openid.ns'         => 'http://specs.openid.net/auth/2.0',
    'openid.mode'       => 'checkid_setup',
    'openid.return_to'  => $returnTo,
    'openid.realm'      => $ORIGIN, // z. B. https://hunthub.online
    'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
  ];

  $openidUrl = $config['steam']['openid_url'] ?? 'https://steamcommunity.com/openid/login';
  header('Location: ' . $openidUrl . '?' . http_build_query($openidParams));
  exit;
}

// ---------- OAuth 2.0 Standard-Provider ----------
$state = oauth_state_start($provider);

switch ($provider) {
  case 'google': {
    $p      = $config['google'];
    $redir  = $ORIGIN . $APP_BASE . '/api/auth/oauth_callback.php?provider=google';
    $params = [
      'client_id'     => $p['client_id'],
      'redirect_uri'  => $redir,                 // ABSOLUTE Redirect-URI (muss mit Google-Konsole matchen)
      'response_type' => 'code',
      'scope'         => implode(' ', $p['scopes']),
      'state'         => $state,
      'access_type'   => 'online',
      //'prompt'        => 'consent',
    ];
    header('Location: ' . $p['authorize_url'] . '?' . http_build_query($params));
    exit;
  }

  case 'facebook': {
    $p      = $config['facebook'];
    $redir  = $ORIGIN . $APP_BASE . '/api/auth/oauth_callback.php?provider=facebook';
    $params = [
      'client_id'     => $p['client_id'],
      'redirect_uri'  => $redir,                 // ABSOLUT
      'response_type' => 'code',
      'scope'         => implode(',', $p['scopes']),
      'state'         => $state,
    ];
    header('Location: ' . $p['authorize_url'] . '?' . http_build_query($params));
    exit;
  }

  case 'twitch': {
    $p      = $config['twitch'];
    $redir  = $ORIGIN . $APP_BASE . '/api/auth/oauth_callback.php?provider=twitch';
    $params = [
      'client_id'     => $p['client_id'],
      'redirect_uri'  => $redir,                 // ABSOLUT
      'response_type' => 'code',
      'scope'         => implode(' ', $p['scopes']),
      'state'         => $state,
      'force_verify'  => 'true',
    ];
    header('Location: ' . $p['authorize_url'] . '?' . http_build_query($params));
    exit;
  }
}

// Fallback (sollte nie erreicht werden)
http_response_code(400);
header('Content-Type: text/plain; charset=utf-8');
echo 'Provider not handled';

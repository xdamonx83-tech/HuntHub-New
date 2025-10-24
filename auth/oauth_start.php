<?php
// /api/auth/oauth_start.php
declare(strict_types=1);

require_once __DIR__ . '/oauth_lib.php';

$config = require __DIR__ . '/../../auth/oauth_config.php';
$APP_BASE = rtrim($config['app_base'] ?? '', '/');

$provider = $_GET['provider'] ?? '';
$allowed = ['google','facebook','twitch','steam'];
if (!in_array($provider, $allowed, true)) {
  http_response_code(400);
  echo "Unknown provider";
  exit;
}

// Steam (OpenID) hat eigenen Flow
if ($provider === 'steam') {
  $state = oauth_state_start('steam');
  $returnTo = $APP_BASE . "/api/auth/oauth_callback.php?provider=steam&state={$state}";
  $openidParams = [
    'openid.ns'         => 'http://specs.openid.net/auth/2.0',
    'openid.mode'       => 'checkid_setup',
    'openid.return_to'  => $returnTo,
    'openid.realm'      => ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'],
    'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
  ];
  $url = $config['steam']['openid_url'] . '?' . http_build_query($openidParams);
  header("Location: $url");
  exit;
}

// OAuth2 Standard
$state = oauth_state_start($provider);
switch ($provider) {
  case 'google':
    $p = $config['google'];
    $params = [
      'client_id'     => $p['client_id'],
      'redirect_uri'  => $APP_BASE . '/api/auth/oauth_callback.php?provider=google',
      'response_type' => 'code',
      'scope'         => implode(' ', $p['scopes']),
      'state'         => $state,
      'access_type'   => 'offline',
      'prompt'        => 'consent',
    ];
    header('Location: '.$p['authorize_url'].'?'.http_build_query($params));
    exit;

  case 'facebook':
    $p = $config['facebook'];
    $params = [
      'client_id'     => $p['client_id'],
      'redirect_uri'  => $APP_BASE . '/api/auth/oauth_callback.php?provider=facebook',
      'response_type' => 'code',
      'scope'         => implode(',', $p['scopes']),
      'state'         => $state,
    ];
    header('Location: '.$p['authorize_url'].'?'.http_build_query($params));
    exit;

  case 'twitch':
    $p = $config['twitch'];
    $params = [
      'client_id'     => $p['client_id'],
      'redirect_uri'  => $APP_BASE . '/api/auth/oauth_callback.php?provider=twitch',
      'response_type' => 'code',
      'scope'         => implode(' ', $p['scopes']),
      'state'         => $state,
      'force_verify'  => 'true',
    ];
    header('Location: '.$p['authorize_url'].'?'.http_build_query($params));
    exit;
	$pdo->prepare("UPDATE users SET last_login = NOW(), last_seen = NOW() WHERE id = :id")
->execute([':id' => (int)$userId]);
}

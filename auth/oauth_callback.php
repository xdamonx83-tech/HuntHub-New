<?php
// /api/auth/oauth_callback.php
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

try {
  if ($provider === 'steam') {
    // OpenID Verifikation
    $state = $_GET['state'] ?? null;
    oauth_state_check('steam', $state);

    // Steam schickt die OpenID-Parameter zurück. Verifiziere beim OpenID-Endpunkt.
// NUR openid.* Parameter übernehmen, wie von Steam zurückgegeben
$openid = [];
foreach ($_GET as $k => $v) {
  if (strncmp($k, 'openid.', 7) === 0) {
    $openid[$k] = $v;
  }
}
$openid['openid.mode'] = 'check_authentication';

// Verify an Steam
$openidUrl = $config['steam']['openid_url'] ?? 'https://steamcommunity.com/openid/login';
$ch = curl_init($openidUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($openid),
]);
$resp = curl_exec($ch);
$cerr = curl_error($ch);
curl_close($ch);

if (!$resp || !preg_match('/is_valid\s*:\s*true/i', (string)$resp)) {
  throw new RuntimeException('Steam OpenID not valid' . ($cerr ? " ($cerr)" : ''));
}

    $ch = curl_init($config['steam']['openid_url']);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($params),
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp || !preg_match('/is_valid\s*:\s*true/i', $resp)) {
      throw new RuntimeException('Steam OpenID not valid');
    }

    // SteamID extrahieren
    if (!isset($_GET['openid.claimed_id'])) {
      throw new RuntimeException('Missing claimed_id');
    }
    if (!preg_match('~https?://steamcommunity.com/openid/id/(\d+)~', $_GET['openid.claimed_id'], $m)) {
      throw new RuntimeException('SteamID parse failed');
    }
    $steamId = $m[1];

    // Optionale Profildaten
    $avatar = null; $display = 'Steam User '.$steamId;
    $apiKey = $config['steam']['webapi_key'];
    if ($apiKey && $apiKey !== 'STEAM_WEBAPI_KEY_HERE') {
      $url = $config['steam']['playersummaries_url'].'?'.http_build_query([
        'key'=>$apiKey, 'steamids'=>$steamId
      ]);
      $summary = http_get_json($url);
      $player = $summary['response']['players'][0] ?? null;
      if ($player) {
        $display = $player['personaname'] ?? $display;
        $avatar  = $player['avatarfull'] ?? ($player['avatar'] ?? null);
      }
    }

    upsert_social_user_and_login('steam', $steamId, null, $display, $avatar);
    header('Location: '.$APP_BASE.'/');
    exit;
  }

  // --- OAuth2 Code-Exchange ---
  $code  = $_GET['code'] ?? null;
  $state = $_GET['state'] ?? null;
  oauth_state_check($provider, $state);
  if (!$code) throw new RuntimeException('Missing code');

  switch ($provider) {
    case 'google': {
      $p = $config['google'];
      $token = http_post_json($p['token_url'], [
        'client_id' => $p['client_id'],
        'client_secret' => $p['client_secret'],
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $APP_BASE . '/api/auth/oauth_callback.php?provider=google',
      ]);
      $userinfo = http_get_json($p['userinfo_url'], [
        'Authorization: Bearer '.$token['access_token'],
      ]);
      $sub   = $userinfo['sub'] ?? null;
      $email = $userinfo['email'] ?? null;
      $name  = $userinfo['name'] ?? ($userinfo['given_name'] ?? 'Google User');
      $avatar= $userinfo['picture'] ?? null;

      if (!$sub) throw new RuntimeException('No Google sub');
      upsert_social_user_and_login('google', $sub, $email, $name, $avatar);
      header('Location: '.$APP_BASE.'/');
      exit;
    }

    case 'facebook': {
      $p = $config['facebook'];
      $token = http_get_json($p['token_url'].'?'.http_build_query([
        'client_id' => $p['client_id'],
        'client_secret' => $p['client_secret'],
        'code' => $code,
        'redirect_uri' => $APP_BASE . '/api/auth/oauth_callback.php?provider=facebook',
      ]));
      $userinfo = http_get_json($p['userinfo_url'].'&'.http_build_query([
        'access_token' => $token['access_token']
      ]));
      $id    = $userinfo['id'] ?? null;
      $email = $userinfo['email'] ?? null;
      $name  = $userinfo['name'] ?? 'Facebook User';
      $avatar= $userinfo['picture']['data']['url'] ?? null;

      if (!$id) throw new RuntimeException('No Facebook id');
      upsert_social_user_and_login('facebook', $id, $email, $name, $avatar);
      header('Location: '.$APP_BASE.'/');
      exit;
    }

    case 'twitch': {
      $p = $config['twitch'];
      $token = http_post_json($p['token_url'], [
        'client_id' => $p['client_id'],
        'client_secret' => $p['client_secret'],
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $APP_BASE . '/api/auth/oauth_callback.php?provider=twitch',
      ]);
      // Userinfo via Helix
      $userinfoResp = http_get_json($p['userinfo_url'], [
        'Authorization: Bearer '.$token['access_token'],
        'Client-Id: '.$p['client_id'],
      ]);
      $data = $userinfoResp['data'][0] ?? [];
      $id     = $data['id'] ?? null;
      $email  = $data['email'] ?? null;
      $name   = $data['display_name'] ?? ($data['login'] ?? 'Twitch User');
      $avatar = $data['profile_image_url'] ?? null;

      if (!$id) throw new RuntimeException('No Twitch id');
      upsert_social_user_and_login('twitch', $id, $email, $name, $avatar);
      header('Location: '.$APP_BASE.'/');
      exit;
    }
  }

} catch (Throwable $e) {
  http_response_code(500);
  // Für Debug:
  echo "OAuth error (".$provider."): ".$e->getMessage();
  // In Produktion lieber auf eine Fehlerseite leiten:
  // header('Location: '.$APP_BASE.'/login.php?oauth_error=1');
  exit;
}

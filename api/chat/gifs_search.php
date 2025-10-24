<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/auth.php';
optional_auth();
require_auth();

$CFG = require __DIR__ . '/../../auth/config.php';

$provider = strtolower($_GET['provider'] ?? ($CFG['gifs']['provider'] ?? 'tenor'));
$q        = trim((string)($_GET['q'] ?? ''));
$limitCfg = (int)($CFG['gifs']['limit'] ?? 24);
$limit    = max(1, min(50, $limitCfg));

function http_get_json(string $url, array $headers = []): array {
  $ua = 'Hunthub/1.0 (+https://hunthub.online)';
  $hdrs = array_merge(['Accept: application/json', 'User-Agent: '.$ua], $headers);

  // Prefer cURL if available
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_HTTPHEADER => $hdrs,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
      $err = curl_error($ch);
      $code = curl_errno($ch);
      curl_close($ch);
      return ['__error' => "curl_error:{$code}:{$err}"];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400) return ['__error' => "http_$code", '__raw' => $raw];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['__error' => 'json_decode'];
  }

  // Fallback: file_get_contents
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 10,
      'header' => implode("\r\n", array_merge($hdrs, ['Connection: close'])),
    ],
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
    ],
  ]);
  $raw = @file_get_contents($url, false, $context);
  if ($raw === false) {
    $err = error_get_last();
    return ['__error' => 'fopen_error:'.($err['message'] ?? 'unknown')];
  }
  // HTTP code aus $http_response_header lesen
  $status = 0;
  if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
    $status = (int)$m[1];
  }
  if ($status >= 400) return ['__error' => "http_$status", '__raw' => $raw];

  $data = json_decode($raw, true);
  return is_array($data) ? $data : ['__error' => 'json_decode'];
}

try {
  if ($provider === 'giphy') {
    $key = $CFG['gifs']['giphy_key'] ?? null;
    if (!$key) {
      echo json_encode(['ok'=>true,'provider'=>'giphy','items'=>[],'hint'=>'NO_API_KEY']); exit;
    }

    $endpoint = $q === ''
      ? "https://api.giphy.com/v1/gifs/trending?api_key={$key}&limit={$limit}&rating=pg"
      : "https://api.giphy.com/v1/gifs/search?api_key={$key}&q=" . rawurlencode($q) . "&limit={$limit}&rating=pg";

    $resp = http_get_json($endpoint);
    if (isset($resp['__error'])) {
      echo json_encode(['ok'=>false,'error'=>$resp['__error']]); exit;
    }

    $items = [];
    foreach (($resp['data'] ?? []) as $r) {
      $images = $r['images'] ?? [];
      // gute Größen
      $gif   = $images['downsized']['url']        ?? ($images['original']['url'] ?? null);
      $thumb = $images['preview_gif']['url']      ?? ($images['fixed_height_small_still']['url'] ?? $gif);
      if (!$gif) continue;
      $items[] = ['url'=>$gif, 'thumb'=>$thumb ?? $gif, 'type'=>'gif'];
    }
    echo json_encode(['ok'=>true,'provider'=>'giphy','items'=>$items]); exit;
  }

  if ($provider === 'tenor') {
    $key = $CFG['gifs']['tenor_key'] ?? null;
    if (!$key) {
      echo json_encode(['ok'=>true,'provider'=>'tenor','items'=>[],'hint'=>'NO_API_KEY']); exit;
    }

    $endpoint = $q === ''
      ? "https://tenor.googleapis.com/v2/featured?key={$key}&limit={$limit}&media_filter=gif,tinygif,mediumgif"
      : "https://tenor.googleapis.com/v2/search?q=" . rawurlencode($q) . "&key={$key}&limit={$limit}&media_filter=gif,tinygif,mediumgif";

    $resp = http_get_json($endpoint);
    if (isset($resp['__error'])) {
      echo json_encode(['ok'=>false,'error'=>$resp['__error']]); exit;
    }

    $items = [];
    foreach (($resp['results'] ?? []) as $r) {
      $media = $r['media_formats'] ?? [];
      $gif   = $media['gif']['url'] ?? ($media['mediumgif']['url'] ?? ($media['tinygif']['url'] ?? null));
      $thumb = $media['tinygif']['url'] ?? ($media['gif']['url'] ?? null);
      if (!$gif) continue;
      $items[] = ['url'=>$gif, 'thumb'=>$thumb ?? $gif, 'type'=>'gif'];
    }
    echo json_encode(['ok'=>true,'provider'=>'tenor','items'=>$items]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'UNKNOWN_PROVIDER']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER_ERROR','detail'=>$e->getMessage()]);
}

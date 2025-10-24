<?php
declare(strict_types=1);
header('Content-Type: application/json');

function detect_user_from_session(): array {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  $sess = $_SESSION ?? [];

  $cands = [
    'user'          => $sess['user']          ?? null,
    'current_user'  => $sess['current_user']  ?? null,
    'auth.user'     => $sess['auth']['user']  ?? null,
    'me'            => $sess['me']            ?? null,
    'account'       => $sess['account']       ?? null,
  ];

  foreach ($cands as $k=>$u) {
    if (is_array($u) && !empty($u['id'])) {
      return ['source'=>$k, 'id'=>(int)$u['id'], 'username'=>(string)($u['username'] ?? $u['name'] ?? ('User#'.$u['id']))];
    }
  }
  return ['source'=>null, 'id'=>0, 'username'=>'Gast'];
}

echo json_encode([
  'ok'=>true,
  'detected'=>detect_user_from_session(),
  'session_keys'=>array_keys($_SESSION ?? []),
]);

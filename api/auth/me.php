<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/guards.php';

$user = current_user();
if (!$user) {
  echo json_encode(['ok'=>true,'user'=>null,'csrf'=>null]);
  exit;
}
echo json_encode([
  'ok'=>true,
  'user'=>[
    'id'=>(int)$user['id'],
    'email'=>$user['email'],
    'display_name'=>$user['display_name'],
    'role'=>$user['role'],
    'bio'=>$user['bio'],
    'avatar'=>$user['avatar_path'],
    'created_at'=>$user['created_at'],
	    'social_twitch'    => $user['social_twitch'],
    'social_tiktok'    => $user['social_tiktok'],
    'social_youtube'   => $user['social_youtube'],
    'social_instagram' => $user['social_instagram'],
    'social_twitter'   => $user['social_twitter'],
    'social_facebook'  => $user['social_facebook'],
  ],
]);

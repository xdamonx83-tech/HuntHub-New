<?php
declare(strict_types=1);

const ROLES = ['administrator','moderator','user','uploader'];

function role_rank(string $role): int {
  // höhere Zahl = höhere Rechte
  return match($role) {
    'administrator' => 3,
    'moderator'     => 2,
    'uploader'      => 1,
    default         => 0, // user
  };
}

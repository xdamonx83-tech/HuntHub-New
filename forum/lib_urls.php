<?php
function slugify(string $s): string {
  $s = strtolower(trim($s));
  $s = preg_replace('~[^\pL0-9]+~u', '-', $s);
  $s = trim($s, '-');
  return $s ?: '';
}

function forum_url_board(array $b, string $APP_BASE): string {
  $slug = !empty($b['slug']) ? '-' . slugify($b['slug']) : '';
  return rtrim($APP_BASE, '/') . '/forum/board/' . (int)$b['id'] . $slug;
}

function forum_url_thread(array $t, string $APP_BASE): string {
  $slug = !empty($t['slug'] ?? $t['title'] ?? '') ? '-' . slugify((string)($t['slug'] ?? $t['title'])) : '';
  return rtrim($APP_BASE, '/') . '/forum/thread/' . (int)$t['id'] . $slug;
}

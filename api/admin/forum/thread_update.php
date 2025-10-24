<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

/**
 * Updates: title, slug, board_id, is_locked, is_pinned
 * - Felder optional; nur übergebene Felder werden gesetzt
 * - Slug-Validierung + (annähernd) eindeutiger Check
 * - Spalten 'is_locked' / 'is_pinned' werden nur gesetzt, wenn vorhanden
 */

$in  = in_json();
$id  = (int)($in['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$fields = [];
$params = [':id' => $id];

// Welche Spalten existieren?
$cols = $pdo->prepare("
  SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'threads'
");
$cols->execute();
$have = array_column($cols->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
$have = array_flip($have); // faster isset

// Titel
if (array_key_exists('title', $in)) {
  $title = trim((string)$in['title']);
  if ($title === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'title_empty']); exit; }
  $fields[] = "title = :title";
  $params[':title'] = $title;
}

// Slug
if (array_key_exists('slug', $in) && isset($have['slug'])) {
  $slug = trim((string)$in['slug']);
  if ($slug !== '') {
    // basic cleanup
    $slug = strtolower(preg_replace('~[^a-z0-9\-]+~', '-', $slug));
    $slug = trim($slug, '-');
    if ($slug === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'slug_empty']); exit; }

    // Eindeutigkeit (global). Wenn du Board-spezifisch willst, ergänze board_id in WHERE.
    $chk = $pdo->prepare("SELECT id FROM threads WHERE slug = :s AND id <> :id LIMIT 1");
    $chk->execute([':s'=>$slug, ':id'=>$id]);
    if ($chk->fetch()) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'slug_exists']); exit; }

    $fields[] = "slug = :slug";
    $params[':slug'] = $slug;
  }
}

// Board
if (array_key_exists('board_id', $in) && isset($have['board_id'])) {
  $bid = (int)$in['board_id'];
  if ($bid > 0) {
    // Board existiert?
    $b = $pdo->prepare("SELECT 1 FROM boards WHERE id = ? LIMIT 1");
    $b->execute([$bid]);
    if (!$b->fetch()) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'board_not_found']); exit; }
    $fields[] = "board_id = :bid";
    $params[':bid'] = $bid;
  }
}

// Flags
if (array_key_exists('is_locked', $in) && isset($have['is_locked'])) {
  $fields[] = "is_locked = :locked";
  $params[':locked'] = !empty($in['is_locked']) ? 1 : 0;
}
if (array_key_exists('is_pinned', $in) && isset($have['is_pinned'])) {
  $fields[] = "is_pinned = :pinned";
  $params[':pinned'] = !empty($in['is_pinned']) ? 1 : 0;
}

if (!$fields) { echo json_encode(['ok'=>true,'changed'=>0]); exit; }

$sql = "UPDATE threads SET ".implode(", ", $fields).", updated_at = NOW() WHERE id = :id";
$st  = $pdo->prepare($sql);
$st->execute($params);

echo json_encode(['ok'=>true,'changed'=>$st->rowCount()]);

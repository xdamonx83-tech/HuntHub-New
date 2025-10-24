<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';

if (!function_exists('esc')) {
  function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/** DB handle */
function wall_edits_db(): PDO { return db(); }

/** Speichere Post-Revision */
function wall_save_post_edit(PDO $db, int $postId, int $editorId, string $old, string $new): bool {
  $sql = "INSERT INTO wall_post_edits (post_id, editor_id, old_body, new_body) VALUES (?,?,?,?)";
  $st  = $db->prepare($sql);
  return $st->execute([$postId, $editorId, $old, $new]);
}

/** Speichere Kommentar-Revision */
function wall_save_comment_edit(PDO $db, int $commentId, int $editorId, string $old, string $new): bool {
  $sql = "INSERT INTO wall_comment_edits (comment_id, editor_id, old_body, new_body) VALUES (?,?,?,?)";
  $st  = $db->prepare($sql);
  return $st->execute([$commentId, $editorId, $old, $new]);
}

/** Gibt es Edits? */
function wall_has_edits(PDO $db, string $type, int $id): bool {
  if ($type === 'post') {
    $st = $db->prepare("SELECT 1 FROM wall_post_edits WHERE post_id=? LIMIT 1");
  } else {
    $st = $db->prepare("SELECT 1 FROM wall_comment_edits WHERE comment_id=? LIMIT 1");
  }
  $st->execute([$id]);
  return (bool)$st->fetchColumn();
}

/** Jüngstes Edit (Vorher/Nachher) */
function wall_latest_edit_pair(PDO $db, string $type, int $id): ?array {
  $isPost = ($type === 'post');
  $table  = $isPost ? 'wall_post_edits' : 'wall_comment_edits';
  $idCol  = $isPost ? 'post_id' : 'comment_id';

  // Wichtig: keine festen User-Spalten referenzieren -> u.* holen
  $sql = "SELECT e.*, u.*
          FROM {$table} e
          LEFT JOIN users u ON u.id = e.editor_id
          WHERE e.{$idCol} = ?
          ORDER BY e.id DESC
          LIMIT 1";
  $st = $db->prepare($sql);
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return null;

  // Editor-Name robust aus vorhandenen Spalten bauen
  $nameKeys = ['display_name','username','name','slug','nick','nickname','user_name'];
  $editorName = null;
  foreach ($nameKeys as $k) {
    if (array_key_exists($k, $row) && (string)$row[$k] !== '') { $editorName = (string)$row[$k]; break; }
  }
  if (!$editorName) $editorName = 'ID ' . (int)$row['editor_id'];

  return [
    'before'     => (string)$row['old_body'],
    'after'      => (string)$row['new_body'],
    'edited_at'  => (string)$row['created_at'],
    'editor_id'  => (int)$row['editor_id'],
    'editor_name'=> $editorName,
  ];
}


/** (Optional) Server-Side Badge-Renderer für initialen Seiten-Render */
function wall_render_edited_badge(PDO $db, string $type, int $id): string {
  if (!in_array($type, ['post','comment'], true)) return '';
  if (!wall_has_edits($db, $type, $id)) return '';
  $dataType = esc($type);
  $dataId   = (int)$id;
  return '<a href="#" class="hh-edited-badge" data-type="'.$dataType.'" data-id="'.$dataId.'" title="Bearbeitet – Verlauf ansehen">Bearbeitet</a>';
}

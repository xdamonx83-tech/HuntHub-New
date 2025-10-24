<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in   = in_json();
  $rels = $in['rels'] ?? [];
  if (!is_array($rels) || !$rels) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'no_rels']);
    exit;
  }
  // Sicherheitslimit
  if (count($rels) > 200) $rels = array_slice($rels, 0, 200);

  // kleine Helfer für Schema-Checks
  $hasTable = function(string $t) use ($pdo): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $st->execute([$t]);
    return (bool)$st->fetchColumn();
  };
  $hasCol = function(string $t, string $c) use ($pdo): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$t, $c]);
    return (bool)$st->fetchColumn();
  };

  // posts + threads vorhanden?
  if (!$hasTable('posts') || !$hasCol('posts','thread_id') || !$hasTable('threads')) {
    echo json_encode(['ok'=>true, 'map'=>[], 'note'=>'posts_or_threads_missing']);
    exit;
  }

  // Content-Spalte in posts ermitteln
  $contentCandidates = ['content','body','text','message'];
  $contentCol = null;
  foreach ($contentCandidates as $c) {
    if ($hasCol('posts', $c)) { $contentCol = $c; break; }
  }
  if (!$contentCol) {
    echo json_encode(['ok'=>true, 'map'=>[], 'note'=>'no_content_column']);
    exit;
  }

  // Titel + Slug in threads ermitteln
  $titleCol = $hasCol('threads','title') ? 'title' : ($hasCol('threads','subject') ? 'subject' : null);
  $titleSel = $titleCol ? "t.`$titleCol`" : "CONCAT('Thread ', t.id)";
  $slugSel  = $hasCol('threads','slug') ? "t.`slug`" : "NULL AS slug";

  // Prepared Statement (LIKE + ESCAPE)
  $sql = "SELECT p.id AS post_id, p.thread_id, {$titleSel} AS thread_title, {$slugSel}
          FROM posts p
          JOIN threads t ON t.id = p.thread_id
          WHERE p.`$contentCol` LIKE :q ESCAPE '\\\\'
          LIMIT 3";
  $st = $pdo->prepare($sql);

  // Helper: prefix-check ohne PHP8-Abhängigkeit
  $starts_with = function(string $s, string $prefix): bool {
    return strncmp($s, $prefix, strlen($prefix)) === 0;
  };

  $map = [];

  foreach ($rels as $rel) {
    if (!is_string($rel) || $rel === '') continue;

    // auf /uploads/ normalisieren (falls /public/uploads/…)
    $needle = $rel;
    if ($starts_with($needle, '/public/uploads/')) {
      $needle = substr($needle, strlen('/public')); // -> /uploads/…
    }

    // LIKE-Escapes
    $esc = strtr($needle, ['\\'=>'\\\\', '%'=>'\\%', '_'=>'\\_']);
    $st->execute([':q' => "%{$esc}%"]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: nur Dateiname probieren
    if (!$rows) {
      $base = basename($needle);
      if ($base !== '') {
        $escB = strtr($base, ['\\'=>'\\\\', '%'=>'\\%', '_'=>'\\_']);
        $st->execute([':q' => "%{$escB}%"]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
      }
    }

    if ($rows) {
      // Ergebnisse mappen (klein halten)
      $map[$rel] = array_map(static function(array $r): array {
        return [
          'thread_id'    => (int)$r['thread_id'],
          'thread_title' => (string)$r['thread_title'],
          'post_id'      => (int)$r['post_id'],
          'slug'         => isset($r['slug']) ? (string)$r['slug'] : '',
        ];
      }, $rows);
    }
  }

  echo json_encode(['ok'=>true, 'map'=>$map], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

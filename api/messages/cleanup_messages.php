<?php
declare(strict_types=1);

/**
 * cleanup_messages.php
 *
 * Retention-Cleanup NUR für Mediennachrichten:
 * - Findet Nachrichten (älter als N Tage, Default 30) mit Bild/Video-Anhang.
 * - Löscht die dazugehörigen Dateien (Videos, Bilder, *_thumb.jpg, small_*, .webp).
 * - Ersetzt den Nachrichteninhalt direkt im Batch durch einen Platzhalter
 *   (Standard: "Gelöscht da älter als 30 Tage").
 *
 * Optional via Umgebungsvariablen:
 *   HH_UPLOAD_DIR       = absoluter Pfad zu /uploads/messages (überschreibt Auto-Discovery)
 *   HH_RETENTION_DAYS   = Aufbewahrung in Tagen (Default 30)
 *   HH_PLACEHOLDER      = Platzhalter-Text (Default unten)
 *   HH_DRY_RUN          = "1" -> nichts löschen/ändern, nur zählen
 *   HH_BATCH            = Batchgröße SELECT (Default 500)
 *   HH_UPDATE_CHUNK     = Update-Chunkgröße (Default 200)
 */

require_once __DIR__ . '/../../auth/db.php';
$pdo = db();

/* ------------------ Parameter ------------------ */
$RETENTION_DAYS = (int) (getenv('HH_RETENTION_DAYS') ?: 15);
$PLACEHOLDER    = (string) (getenv('HH_PLACEHOLDER') ?: 'Anhang (Bild/Video) automatisch gelöscht – älter als 15 Tage.');
$DRY_RUN        = (getenv('HH_DRY_RUN') === '1');
$BATCH          = (int) (getenv('HH_BATCH') ?: 500);
$UPDATE_CHUNK   = (int) (getenv('HH_UPDATE_CHUNK') ?: 200);

/* ------------------ Pfade ermitteln ------------------ */
$UPLOADDIR = null;
$DOCROOT   = null;

// 1) Override per Env
$env = getenv('HH_UPLOAD_DIR');
if ($env && is_dir($env)) {
  $UPLOADDIR = realpath($env) ?: $env;
  $DOCROOT   = preg_replace('~/?uploads/messages/?$~', '', $UPLOADDIR) ?: null;
  if ($DOCROOT && !is_dir($DOCROOT)) $DOCROOT = null;
}

// 2) Auto-Discovery
if (!$UPLOADDIR || !$DOCROOT) {
  $cur = __DIR__;
  for ($i=0; $i<8; $i++) {
    $candidate = $cur . '/uploads/messages';
    if (is_dir($candidate)) {
      $UPLOADDIR = realpath($candidate) ?: $candidate;
      $DOCROOT   = realpath($cur) ?: $cur;
      break;
    }
    $parent = dirname($cur);
    if ($parent === $cur) break;
    $cur = $parent;
  }
}
if (!$UPLOADDIR || !$DOCROOT) {
  fwrite(STDERR, "Fatal: DOCROOT/UPLOADDIR nicht gefunden. Setze HH_UPLOAD_DIR=/pfad/zu/uploads/messages\n");
  exit(1);
}

/* ------------------ Cutoff ------------------ */
$cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-'.$RETENTION_DAYS.' days')
            ->format('Y-m-d H:i:s');

/* ------------------ Helfer ------------------ */
$deletedFilesTotal = 0;
$totalScanned      = 0;
$updatedTotal      = 0;

$addFile = function(string $abs) use (&$deletedFilesTotal, $UPLOADDIR) {
  // nur Dateien innerhalb des Upload-Verzeichnisses zulassen (Whitelisting)
  $dir = realpath(is_dir($abs) ? $abs : dirname($abs)) ?: dirname($abs);
  if (strpos($dir, $UPLOADDIR) !== 0) return;
  if (is_file($abs) && !is_link($abs)) {
    // idempotent: Fehler egal
    @unlink($abs);
    $deletedFilesTotal++;
  }
};

$urlToAbs = function(string $url) use ($DOCROOT) : ?string {
  $path = parse_url($url, PHP_URL_PATH) ?: '';
  if ($path === '') return null;
  if ($path[0] !== '/') $path = '/'.ltrim($path, '/');
  if (strpos($path, '/uploads/messages/') !== 0) return null; // nur unsere Uploads
  return $DOCROOT . $path;
};

$extractMediaUrls = function(string $body) : array {
  $urls = [];

  // JSON-Anhang (aktuelles Format)
  $json = json_decode($body, true);
  if (is_array($json) && ($json['type'] ?? '') === 'attach') {
    $kind = strtolower((string)($json['kind'] ?? ''));
    if (($kind === 'image' || $kind === 'video') && !empty($json['url'])) {
      $urls[] = (string)$json['url'];
      if (!empty($json['meta']['thumb'])) $urls[] = (string)$json['meta']['thumb'];
      return $urls;
    }
  }

  // Legacy: [img]...[/img], [video poster="..."]...[/video]
  if (preg_match_all('~\[(?:img|video)[^\]]*\](.*?)\[/\s*(?:img|video)\]~i', $body, $m)) {
    foreach ($m[1] as $u) $urls[] = (string)$u;
  }
  if (preg_match('~poster="([^"]+)"~i', $body, $m2)) $urls[] = (string)$m2[1];

  return $urls;
};

$updatePlaceholders = function(PDO $pdo, array $ids, string $placeholder, int $chunkSize) : int {
  if (!$ids) return 0;
  $affected = 0;
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); // erlaubt variable Anzahl Platzhalter
  foreach (array_chunk($ids, $chunkSize) as $chunk) {
    $in = implode(',', array_fill(0, count($chunk), '?'));
    $sql = "UPDATE messages SET body = ? WHERE id IN ($in)";
    $params = array_merge([$placeholder], $chunk);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $affected += $stmt->rowCount();
    $pdo->commit();
  }
  return $affected;
};

/* ------------------ Hauptschleife: Batches ------------------ */
while (true) {
  // nur nach Zeit filtern – Medien-Erkennung in PHP
  $st = $pdo->prepare("
    SELECT id, body
    FROM messages
    WHERE created_at < :cutoff
    ORDER BY id ASC
    LIMIT :lim
  ");
  $st->bindValue(':cutoff', $cutoff, PDO::PARAM_STR);
  $st->bindValue(':lim', $BATCH, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) break;

  $mediaIdsBatch = [];

  foreach ($rows as $r) {
    $totalScanned++;
    $id   = (int)$r['id'];
    $body = (string)$r['body'];

    $urls = $extractMediaUrls($body);
    if (!$urls) continue; // keine Medien -> Nachricht bleibt wie sie ist

    // Dateien löschen (inkl. Derivate)
    foreach ($urls as $u) {
      $abs = $urlToAbs($u);
      if (!$abs) continue;

      if (!$DRY_RUN) {
        $addFile($abs);
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $dir  = dirname($abs);
        $name = pathinfo($abs, PATHINFO_FILENAME);
        if ($ext === 'mp4') {
          $addFile($dir . '/' . $name . '_thumb.jpg');
        } else {
          $addFile($dir . '/small_' . basename($abs));
          $addFile($dir . '/' . $name . '.webp');
        }
      }
    }

    $mediaIdsBatch[] = $id;
  }

  // Platzhalter-Update direkt für diesen Batch
  if ($mediaIdsBatch && !$DRY_RUN) {
    $updatedTotal += $updatePlaceholders($pdo, $mediaIdsBatch, $PLACEHOLDER, $UPDATE_CHUNK);
  }
}

/* ------------------ leere Ordner wegräumen (optional) ------------------ */
if (!$DRY_RUN) {
  $it = new RecursiveDirectoryIterator($UPLOADDIR, FilesystemIterator::SKIP_DOTS);
  foreach (new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST) as $p) {
    if ($p->isDir()) { @rmdir($p->getPathname()); } // nur wenn leer
  }
}

/* ------------------ Ausgabe ------------------ */
echo json_encode([
  'ok'               => true,
  'dry_run'          => $DRY_RUN,
  'retention_days'   => $RETENTION_DAYS,
  'cutoff'           => $cutoff,
  'scanned'          => $totalScanned,
  'updated_messages' => $updatedTotal,      // so viele Bodies auf Platzhalter gesetzt
  'deleted_files'    => $deletedFilesTotal, // so viele Dateien entfernt
  'upload_dir'       => $UPLOADDIR,
  'docroot'          => $DOCROOT,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

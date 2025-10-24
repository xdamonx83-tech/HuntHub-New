<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../auth/db.php';

$db = db();
$me = require_auth();

// ---- CSRF
$csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (function_exists('csrf_verify')) {
  if (!csrf_verify($csrf)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
} elseif (function_exists('verify_csrf')) {
  if (!verify_csrf($db, $csrf)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
}

// ---- Payload (FormData ODER JSON)
$body = $_POST;
$raw  = file_get_contents('php://input') ?: '';
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false && $raw !== '') {
  $j = json_decode($raw, true);
  if (is_array($j)) $body = array_merge($body, $j);
}

$type   = strtolower(trim((string)($body['type'] ?? $body['entity_type'] ?? '')));
$rid    = (int)($body['id'] ?? $body['entity_id'] ?? $body['post_id'] ?? $body['comment_id'] ?? 0);
$reason = strtolower(trim((string)($body['reason'] ?? '')));
$note   = trim((string)($body['note'] ?? ''));

$ALLOWED = ['harassment','selfharm','violence','scam','copyright','adult','under18','illegal','dislike'];
if (!in_array($type, ['post','comment'], true) || $rid <= 0 || !in_array($reason, $ALLOWED, true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_request']); exit;
}

// ---- Entität existiert?
try {
  $st = ($type === 'post')
    ? $db->prepare('SELECT `id` FROM `wall_posts` WHERE `id`=? AND `deleted_at` IS NULL')
    : $db->prepare('SELECT `id` FROM `wall_comments` WHERE `id`=? AND `deleted_at` IS NULL');
  $st->execute([$rid]);
  if (!$st->fetch()) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_lookup']); exit;
}

// ---- Tabelle vorhanden? (DDL bei Bedarf)
$DDL = <<<SQL
CREATE TABLE IF NOT EXISTS `wall_reports` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `entity_type`  ENUM('post','comment') NOT NULL,
  `entity_id`    INT NOT NULL,
  `reporter_id`  INT NOT NULL,
  `reason`       VARCHAR(32) NOT NULL,
  `note`         TEXT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_report` (`entity_type`,`entity_id`,`reporter_id`),
  KEY `ix_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
SQL;

try {
  $db->exec($DDL);
} catch (Throwable $e) {
  // Kein harter Abbruch – kann bereits existieren ohne CREATE-Rechte
}

// ---- Spalten autodetektion (reporter_id vs. user_id/reported_by), ggf. migrieren
try {
  $cols = [];
  foreach ($db->query('SHOW COLUMNS FROM `wall_reports`', PDO::FETCH_ASSOC) as $c) {
    $cols[strtolower((string)$c['Field'])] = true;
  }

  $REPORTER_COL = 'reporter_id';
  if (!isset($cols['reporter_id'])) {
    if (isset($cols['user_id'])) {
      $REPORTER_COL = 'user_id';
    } elseif (isset($cols['reported_by'])) {
      $REPORTER_COL = 'reported_by';
    } else {
      // versuchen hinzuzufügen
      try {
        $db->exec("ALTER TABLE `wall_reports` ADD COLUMN `reporter_id` INT NOT NULL AFTER `entity_id`, ADD INDEX (`reporter_id`)");
        $REPORTER_COL = 'reporter_id';
        $cols['reporter_id'] = true;
      } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
          'ok'=>false,
          'error'=>'schema_incompatible',
          'details'=>substr($e->getMessage(),0,300),
          'hint'=>"Füge eine Spalte `reporter_id INT NOT NULL` in wall_reports hinzu ODER benenne `user_id` nach `reporter_id` um."
        ]);
        exit;
      }
    }
  }

  // optionale Komfortspalte note
  if (!isset($cols['note'])) {
    try { $db->exec("ALTER TABLE `wall_reports` ADD COLUMN `note` TEXT NULL"); } catch (Throwable $e) { /* ignore */ }
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'schema_check_failed','details'=>substr($e->getMessage(),0,300)]);
  exit;
}

// ---- doppelte Meldungen pro Nutzer/Entität vermeiden (works ohne UNIQUE)
try {
  $dup = $db->prepare("SELECT 1 FROM `wall_reports` WHERE `entity_type`=? AND `entity_id`=? AND `$REPORTER_COL`=? LIMIT 1");
  $dup->execute([$type, $rid, (int)$me['id']]);
  if ($dup->fetch()) {
    // schon gemeldet → trotzdem Zähler zurückgeben
    $cst = $db->prepare('SELECT COUNT(*) FROM `wall_reports` WHERE `entity_type`=? AND `entity_id`=?');
    $cst->execute([$type, $rid]);
    $cnt = (int)$cst->fetchColumn();
    echo json_encode(['ok'=>true,'under_review'=>($cnt>=3),'reports'=>$cnt]); exit;
  }
} catch (Throwable $e) {
  // weiter mit Insert; schlimmstenfalls doppelt, falls keine UNIQUE-Constraint
}

// ---- Insert
try {
  $sql = "INSERT INTO `wall_reports` (`entity_type`,`entity_id`,`$REPORTER_COL`,`reason`,`note`) VALUES (?,?,?,?,?)";
  $ins = $db->prepare($sql);
  $ins->execute([$type, $rid, (int)$me['id'], $reason, ($note !== '' ? $note : null)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'insert_failed','details'=>substr($e->getMessage(),0,300)]);
  exit;
}

// ---- Anzahl Meldungen
$cnt = 0;
try {
  $cst = $db->prepare('SELECT COUNT(*) FROM `wall_reports` WHERE `entity_type`=? AND `entity_id`=?');
  $cst->execute([$type, $rid]);
  $cnt = (int)$cst->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

// ---- ab 3 Meldungen: under_review_at setzen (falls Spalte existiert)
$underReview = false;
if ($cnt >= 3) {
  try {
    if ($type === 'post') {
      $db->exec("ALTER TABLE `wall_posts` ADD COLUMN IF NOT EXISTS `under_review_at` DATETIME NULL");
      $upd = $db->prepare('UPDATE `wall_posts` SET `under_review_at` = IFNULL(`under_review_at`, NOW()) WHERE `id`=?');
      $upd->execute([$rid]);
    } else {
      $db->exec("ALTER TABLE `wall_comments` ADD COLUMN IF NOT EXISTS `under_review_at` DATETIME NULL");
      $upd = $db->prepare('UPDATE `wall_comments` SET `under_review_at` = IFNULL(`under_review_at`, NOW()) WHERE `id`=?');
      $upd->execute([$rid]);
    }
    $underReview = true;
  } catch (Throwable $e) {
    // ältere MySQL-Versionen kennen "ADD COLUMN IF NOT EXISTS" nicht – still ok
    try {
      if ($type === 'post') {
        $upd = $db->prepare('UPDATE `wall_posts` SET `under_review_at` = IFNULL(`under_review_at`, NOW()) WHERE `id`=?');
        $upd->execute([$rid]);
      } else {
        $upd = $db->prepare('UPDATE `wall_comments` SET `under_review_at` = IFNULL(`under_review_at`, NOW()) WHERE `id`=?');
        $upd->execute([$rid]);
      }
      $underReview = true;
    } catch (Throwable $e2) { /* ignore */ }
  }
}

echo json_encode(['ok'=>true,'under_review'=>$underReview,'reports'=>$cnt]);

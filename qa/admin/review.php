<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/layout.php';

$me = function_exists('require_admin') ? require_admin() : null;
if (!$me || empty($me['is_admin'])) { http_response_code(403); echo "Admin only"; exit; }

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$L = $GLOBALS['L'];
$cfg = require __DIR__ . '/../../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$pdo = db();

$rows = $pdo->query("SELECT id, user_id, lang, question, confidence, created_at FROM qa_logs ORDER BY id DESC LIMIT 200")
            ->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QA Review</title>
  <?php if (function_exists('theme_header')) theme_header('QA Review'); ?>
</head>
<body>
<?php if (function_exists('theme_top')) theme_top(); ?>
<main class="container" style="max-width:1000px;margin:20px auto;padding:0 8px">
  <h1 class="heading-4 text-w-neutral-1 mb-2">QA Review</h1>
  <p class="text-w-neutral-4 mb-3">Letzte Fragen. Niedrige Confidence zuerst durchsehen.</p>
  <div class="hh-table">
    <div class="hh-table-head">
      <div>ID</div><div>Zeit</div><div>User</div><div>Sprache</div><div>Confidence</div><div>Frage</div>
    </div>
    <?php foreach ($rows as $r): ?>
      <div class="hh-row">
        <div><?= (int)$r['id'] ?></div>
        <div><?= htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
        <div><?= (int)($r['user_id'] ?? 0) ?></div>
        <div><?= htmlspecialchars((string)$r['lang'], ENT_QUOTES, 'UTF-8') ?></div>
        <div><?= number_format((float)($r['confidence'] ?? 0), 2) ?></div>
        <div><?= htmlspecialchars((string)$r['question'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php if (function_exists('theme_bottom')) theme_bottom(); ?>
</body>
</html>

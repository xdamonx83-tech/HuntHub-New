<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/layout.php';

$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

// Login ist Pflicht
if (function_exists('require_user'))      { $me = require_user(); }
elseif (function_exists('require_login')) { $me = require_login(); }
elseif (function_exists('require_auth'))  { $me = require_auth(); }
else                                      { $me = require_admin(); }
$st = $pdo->query('SELECT id,slug,name,platform,team_size,format,status,starts_at,ends_at,best_runs FROM tournaments ORDER BY starts_at DESC');
$items = $st->fetchAll(PDO::FETCH_ASSOC);

// CSRF-Token
$sessionCookieName = $cfg['cookies']['session_name'] ?? '';
$sessionCookie = $_COOKIE[$sessionCookieName] ?? '';
$csrf = issue_csrf($pdo, $sessionCookie);

// Scoring nur zur Anzeige
$scoring = [];
if (!empty($t['scoring_json'])) {
  $tmp = json_decode((string)$t['scoring_json'], true);
  if (is_array($tmp)) $scoring = $tmp;
}

ob_start();
?>
<main class="container">
<h1>Turniere</h1>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($items as $t): ?>
<article class="card p-4">
<h2 class="text-xl font-bold mb-1"><?= htmlspecialchars($t['name']) ?></h2>
<div class="text-sm opacity-80 mb-2">
Plattform: <b><?= htmlspecialchars($t['platform']) ?></b> · Teamgröße: <b><?= (int)$t['team_size'] ?></b> · Modus: <b><?= htmlspecialchars($t['format']) ?></b>
</div>
<div class="text-sm mb-3">
<?= htmlspecialchars(date('d.m.Y H:i', strtotime($t['starts_at']))) ?> – <?= htmlspecialchars(date('d.m.Y H:i', strtotime($t['ends_at']))) ?>
<span class="ml-2 badge">Status: <?= htmlspecialchars($t['status']) ?></span>
</div>
<a class="btn" href="/tournaments/view.php?id=<?= (int)$t['id'] ?>">Details</a>
</article>
<?php endforeach; ?>
</div>
</main>
<?php end_layout();
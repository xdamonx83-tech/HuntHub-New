<?php
declare(strict_types=1);

// --- TEMP: Debug einschalten (später wieder entfernen) ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../auth/guards.php';

$pdo  = db();
$cfg  = require __DIR__ . '/../auth/config.php';
$APP  = rtrim($cfg['app_base'] ?? '', '/');
$title = 'Erfolge & Quests';

// ---------- kleine Helfer ----------
$fmt = function($v): string {
  if (!$v) return '';
  try {
    $dt = new DateTimeImmutable((string)$v, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y - H:i');
  } catch (Throwable) { return (string)$v; }
};
$tableExists = function(string $t) use ($pdo): bool {
  try {
    $st = $pdo->query("SHOW TABLES LIKE ".$pdo->quote($t));
    if ($st === false) return false;
    return $st->fetchColumn() !== false;
  }
  catch (Throwable) { return false; }
};
$colExists = function(string $t, string $c) use ($pdo): bool {
  try {
    $st = $pdo->query("SHOW COLUMNS FROM `$t` LIKE ".$pdo->quote($c));
    if ($st === false) return false;
    return $st->fetchColumn() !== false;
  }
  catch (Throwable) { return false; }
};

// ---------- Daten laden ----------
$my = null; $leaders = []; $ach = []; $quests = [];
$xpLog = []; 
// +++ NEU: Konfiguration für die Texte im Logbuch +++
$xpLogMessages = [
    'new_post' => 'Neuer Kommentar verfasst',
    'new_thread' => 'Neues Thema erstellt',
    'receive_like' => 'Like für einen Beitrag erhalten',
    'daily_login' => 'Täglicher Login',
    'add_friend' => 'Freund hinzugefügt',
    'upload_avatar' => 'Avatar hochgeladen',
];
$scoreCol = $colExists('users','xp') ? 'xp' : ($colExists('users','points') ? 'points' : null);
$levelCol = $colExists('users','level') ? 'level' : ($colExists('users','lvl') ? 'lvl' : null);

$me = function_exists('optional_auth') ? optional_auth() : null;
$uid = (int)($me['id'] ?? 0);

// Eigene Stats
if ($uid && ($scoreCol || $levelCol)) {
    try {
        $sel = "id, display_name, avatar_path";
        if ($scoreCol) $sel .= ", `$scoreCol` AS score";
        if ($levelCol) $sel .= ", `$levelCol` AS level";
        $st = $pdo->prepare("SELECT $sel FROM users WHERE id=? LIMIT 1");
        $st->execute([$uid]);
        $my = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable) { $my = null; }
}

// Leaderboard
if ($scoreCol) {
    try {
        $q = "SELECT id, display_name, avatar_path, `$scoreCol` AS score".
             ($levelCol ? ", `$levelCol` AS level" : ", NULL AS level").
             " FROM users ORDER BY `$scoreCol` DESC LIMIT 10";
        $leaders = $pdo->query($q)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) { $leaders = []; }
}

// Jüngste Erfolge
if ($uid && $tableExists('user_achievements')) {
    try {
        $sql = "SELECT a.id, a.title, a.icon, ua.unlocked_at AS created_at
                FROM user_achievements ua JOIN achievements a ON a.id=ua.achievement_id
                WHERE ua.user_id=? ORDER BY ua.unlocked_at DESC LIMIT 8";
        $st = $pdo->prepare($sql);
        $st->execute([$uid]);
        $ach = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) { $ach = []; }
}

// Quests mit Fortschritt und Bild
if ($uid && $tableExists('quests')) {
    try {
        $sql = "
            SELECT
                q.id, q.title, q.description, q.icon, q.threshold, q.ends_at,
                COALESCE(uqp.progress, 0) AS current_progress,
                uqp.completed_at
            FROM quests q
            LEFT JOIN user_quest_progress uqp ON q.id = uqp.quest_id AND uqp.user_id = ?
            WHERE q.is_active = 1
            ORDER BY q.id DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid]);
        $quests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) { $quests = []; }
}
// +++ NEU: Daten für das XP-Log laden +++
if ($uid && $tableExists('user_xp_log')) {
    try {
        $sql = "SELECT xp_amount, action_key, created_at
                FROM user_xp_log
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 15"; // Die 15 neuesten Einträge
        $st = $pdo->prepare($sql);
        $st->execute([$uid]);
        $xpLog = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        $xpLog = [];
    }
}


// Level/XP Anzeige
$level = (int)($my['level'] ?? 0);
// Level/XP Anzeige
$level = (int)($my['level'] ?? 0);
$score = (int)($my['score'] ?? 0);
$prevXP = (int)floor(pow(max(0,$level),2)*100);
$nextXP = (int)floor(pow($level+1,2)*100);
$progPct = $nextXP > $prevXP ? max(0,min(100, round(($score-$prevXP)/($nextXP-$prevXP)*100))) : 0;

// ---------- Render ----------
ob_start(); ?>
            <section class="pt-30p">
                <div class="section-pt">
                    <div
                        class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
                        <div class="container">
                            <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
                                <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                                    <h2 class="heading-2 text-w-neutral-1 mb-3">
                                        Erfolge & Quests
                                    </h2>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="/" class="breadcrumb-link">
                                                Home
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-current">Erfolge & Quests</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="overlay-11"></div>
                    </div>
                </div>
            </section>
<section class="section-pt">
  <div class="container">


    <!-- Meine Stats + Jüngste Erfolge -->
    <div class="grid grid-cols-12 gap-30p">
      <div class="col-span-12 lg:col-span-6 bg-b-neutral-3 rounded-12 p-24p">
        <h3 class="text-xl-medium mb-3">Deine Stats</h3>
        <?php if ($my): ?>
          <div class="flex items-center gap-3 mb-3">
            <img src="<?= htmlspecialchars($my['avatar_path'] ?? ($APP.'/assets/images/avatars/placeholder.png')) ?>"
                 class="size-60p rounded-full border border-white/10" alt="">
            <div>
              <div class="text-w-neutral-1 text-lg"><?= htmlspecialchars($my['display_name'] ?? 'Du') ?></div>
              <div class="text-w-neutral-4 text-sm">
                <?= $levelCol ? 'Level '.(int)$level : '' ?><?= ($levelCol && $scoreCol)?' · ':'' ?>
                <?= $scoreCol ? ((int)$score.' XP') : '' ?>
              </div>
            </div>
          </div>
          <?php if ($scoreCol && $levelCol): ?>
            <div class="text-sm text-w-neutral-4 mb-1"><?= $score ?> / <?= $nextXP ?> XP (bis L<?= $level+1 ?>)</div>
            <div class="h-2 bg-black/30 rounded"><div class="h-2 bg-primary rounded" style="width: <?= $progPct ?>%"></div></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-w-neutral-4">Einloggen, um persönliche Werte zu sehen.</div>
        <?php endif; ?>
      </div>

            <div class="col-span-12 lg:col-span-6 bg-b-neutral-3 rounded-12 p-24p">
                <h3 class="text-xl-medium mb-3">Jüngste Erfolge</h3>
                <?php if ($ach): ?>
                  <ul class="grid grid-cols-1 sm:grid-cols-2 gap-12p">
                    <?php foreach ($ach as $a): ?>
                    <li class="flex items-center gap-3">
                      <?php if (!empty($a['icon'])): ?>
                        <img style="max-height: 50px;" src="<?= htmlspecialchars($a['icon']) ?>" class="size-36p rounded" alt="">
                      <?php else: ?><div class="size-36p rounded bg-black/30 flex items-center justify-center">🏅</div><?php endif; ?>
                      <div>
                        <div class="text-w-neutral-1"><?= htmlspecialchars($a['title'] ?? 'Erfolg') ?></div>
                        <div class="text-w-neutral-4 text-sm"><?= htmlspecialchars($fmt($a['created_at'] ?? null)) ?></div>
                      </div>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?><div class="text-w-neutral-4">Keine Einträge.</div><?php endif; ?>
            </div>
    </div>

    <!-- Quests im Kartenstil (wie Screenshot) -->
    <div class="bg-b-neutral-3 rounded-12 p-24p mt-30p">
      <h3 class="text-xl-medium mb-3">Wöchentliche Quests</h3>

      <?php if ($quests): ?>
        <style>
          .gg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px}
          .gg-card{background:#0b0b0b;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:28px;text-align:center}
          .gg-icon-wrap{width:148px;height:148px;margin:0 auto 16px;border-radius:999px;background:#151515;display:flex;align-items:center;justify-content:center}
          .gg-icon{width:96px;height:96px;object-fit:contain}
          .gg-title{color:#fff;font-weight:700;font-size:22px;margin-top:4px}
          .gg-sub{color:#f29620;font-weight:700;margin:8px 0 18px}
          .gg-line{position:relative;height:12px;border-radius:999px;background:#e9ecef}
          .gg-line::before{content:"";position:absolute;left:12px;top:50%;width:22px;height:8px;background:#f29620;border-radius:3px;transform:translateY(-50%)}
          .gg-line::after{content:"";position:absolute;top:50%;width:22px;height:8px;background:#f29620;border-radius:3px;transform:translate(-50%,-50%);left:calc(var(--pct,0%) + 12px)}
          .gg-fill{position:absolute;left:0;top:0;bottom:0;background:#f29620;border-radius:999px;width:calc(var(--pct,0%) + 12px)}
          .gg-rest{position:absolute;right:0;top:50%;transform:translateY(-50%);height:8px;background:#fff;border-radius:999px;width:calc(100% - (var(--pct,0%) + 24px))}
        </style>

        <ul class="gg-grid">
          <?php foreach ($quests as $i => $q):
       $threshold = (int)$q['threshold'];
                        $progress = (int)$q['current_progress'];
                        $isCompleted = !empty($q['completed_at']);
                        $progressPct = $threshold > 0 ? min(100, round(($progress / $threshold) * 100)) : 0;
            $pct         = $threshold > 0 ? min(100, (int)round($progress / $threshold * 100)) : 0;
            $icon        = (string)($q['icon'] ?? '');
            if ($icon === '') {
              // Fallback-Icons – bei Bedarf auf echte Pfade anpassen
              $fallbacks = [
                $APP.'/assets/images/badges/badge1.png',
                $APP.'/assets/images/badges/badge2.png',
                $APP.'/assets/images/badges/badge3.png',
                $APP.'/assets/images/badges/badge4.png',
                $APP.'/assets/images/badges/badge5.png',
              ];
              $icon = $fallbacks[$i % count($fallbacks)];
            }
          ?>
            <li class="gg-card" style="<?= $isCompleted ? 'border: 1px solid rgb(26 104 19 / 77%);' : 'border-w-neutral-4/20' ?>">
              <div class="gg-icon-wrap">
			  <?php if (!empty($q['icon'])): ?>
                <img class="gg-icon" src="<?= htmlspecialchars($APP . $q['icon']) ?>" alt="">
				<?php else: ?>
                                        <i class="ti ti-star text-4xl <?= $isCompleted ? 'text-green-400' : 'text-primary' ?>"></i>
                                    <?php endif; ?>
              </div>
              <div class="gg-title"><?= htmlspecialchars($q['title'] ?? 'Quest') ?></div>
              <div class="gg-sub"><?= (int)$progress ?> of <?= (int)$threshold ?></div>
              <div class="gg-line" style="--pct: <?= $pct ?>%;">
                <div class="gg-fill"></div>
                <div class="gg-rest"></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="text-w-neutral-4">Keine aktiven Quests.</div>
      <?php endif; ?>
    </div>

    <!-- Leaderboard -->


  </div>
  <div class="container">


        <div class="grid grid-cols-1 lg:grid-cols-2 gap-30p mt-30p">

            <div class="bg-b-neutral-3 rounded-12 p-24p">
                <h3 class="text-xl-medium mb-3">Leaderboard</h3>
              <?php if ($leaders): ?>
                    <ol class="grid grid-cols-1 gap-12p">
                        <?php $r=1; foreach ($leaders as $u): ?>
						              <li class="flex items-center justify-between p-3 rounded-lg bg-black/20">
                                <div>
                                    <div class="text-w-neutral-1">
                                       <?= htmlspecialchars($u['display_name'] ?? 'User') ?>
                                    </div>
                                
                                </div>
                                <div class="text-primary font-bold text-lg">
                                       <?= (int)($u['level'] ?? 0) ? 'Lvl '.(int)$u['level'].' · ' : '' ?>
                  <?= (int)($u['score'] ?? 0) ?> XP
                                </div>
                            </li>
                            <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <div class="text-w-neutral-4">Kein Score-Feld (<code>xp</code> oder <code>points</code>) in <code>users</code> gefunden.</div>
                <?php endif; ?>
            </div>

            <div class="bg-b-neutral-3 rounded-12 p-24p">
                <h3 class="text-xl-medium mb-3">Aktivitätsprotokoll</h3>
                <?php if ($xpLog): ?>
                    <ul class="space-y-3">
                        <?php foreach ($xpLog as $logEntry): ?>
                            <li class="flex items-center justify-between p-3 rounded-lg bg-black/20">
                                <div>
                                    <div class="text-w-neutral-1">
                                        <?= htmlspecialchars($xpLogMessages[$logEntry['action_key']] ?? 'Aktion ausgeführt') ?>
                                    </div>
                                    <div class="text-w-neutral-4 text-sm">
                                        <?= htmlspecialchars($fmt($logEntry['created_at'] ?? null)) ?>
                                    </div>
                                </div>
                                <div class="text-primary font-bold text-lg">
                                    +<?= (int)$logEntry['xp_amount'] ?> XP
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($uid): ?>
                     <div class="text-w-neutral-4">Noch keine Aktivitäten aufgezeichnet.</div>
                <?php else: ?>
                    <div class="text-w-neutral-4">Einloggen, um persönliche Werte zu sehen.</div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</section><br>
<?php
$content = ob_get_clean();
render_theme_page($content, $title);

<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/auth/db.php';
@require_once __DIR__ . '/auth/mute.php';

$me = require_auth();
if (!in_array($me['role'] ?? 'user', ['moderator','administrator'], true)) {
  http_response_code(403); echo 'Forbidden'; exit;
}

$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$userId = (int)($_GET['user_id'] ?? 0);

$foundUsers = [];
if ($q !== '') {
  $stmt = $pdo->prepare("SELECT id, display_name, email, role
                         FROM users
                         WHERE display_name LIKE CONCAT('%',?,'%')
                            OR email LIKE CONCAT('%',?,'%')
                         ORDER BY display_name ASC
                         LIMIT 20");
  $stmt->execute([$q, $q]);
  $foundUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$target = null; $warnings = []; $mutes = []; $status = null;
if ($userId > 0) {
  $stmt = $pdo->prepare("SELECT id, display_name, email, role FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $target = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($target) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM warnings WHERE user_id = ? AND cleared_at IS NULL");
    $s->execute([$userId]);
    $warnCount = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT * FROM user_mutes WHERE user_id = ? AND active = 1 AND revoked_at IS NULL AND muted_until > NOW() LIMIT 1");
    $s->execute([$userId]);
    $mute = $s->fetch(PDO::FETCH_ASSOC) ?: null;

    $status = ['warnings' => $warnCount, 'mute' => $mute];

    $w = $pdo->prepare("SELECT w.*, u.display_name AS by_name
                        FROM warnings w LEFT JOIN users u ON u.id = w.created_by
                        WHERE w.user_id = ? ORDER BY w.created_at DESC LIMIT 100");
    $w->execute([$userId]);
    $warnings = $w->fetchAll(PDO::FETCH_ASSOC);

    $m = $pdo->prepare("SELECT m.*, u.display_name AS by_name
                        FROM user_mutes m LEFT JOIN users u ON u.id = m.created_by
                        WHERE m.user_id = ? ORDER BY m.created_at DESC LIMIT 100");
    $m->execute([$userId]);
    $mutes = $m->fetchAll(PDO::FETCH_ASSOC);
  }
}

// Optional Layout nutzen, falls vorhanden
$use_layout = false;
if (file_exists(__DIR__ . '/lib/layout.php')) {
  require_once __DIR__ . '/lib/layout.php';
  if (function_exists('layout_header')) { $use_layout = true; layout_header('Moderation'); }
}
if (!$use_layout): ?>
<!doctype html><html lang="de"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moderation – Verwarnungen & Mutes</title>
  <link rel="stylesheet" href="/assets/styles/app.css">
</head><body class="bg-[#0b0b0b] text-gray-200">
<?php endif; ?>

<div class="container mx-auto p-6 text-sm">
  <h1 class="text-2xl font-bold mb-4">Moderation – Verwarnungen &amp; Mutes</h1>

  <form method="get" class="mb-6 flex gap-2">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nutzer per Name oder E-Mail suchen" class="px-3 py-2 rounded border w-[360px] text-black">
    <button class="px-3 py-2 rounded bg-gray-800 text-white">Suchen</button>
  </form>

  <?php if ($q !== ''): ?>
    <div class="mb-8">
      <h2 class="font-semibold mb-2">Treffer</h2>
      <div class="border rounded">
        <?php if (!$foundUsers): ?>
          <div class="p-3 text-gray-500">Keine Nutzer gefunden.</div>
        <?php else: foreach ($foundUsers as $u): ?>
          <div class="p-3 border-b flex items-center justify-between">
            <div>
              <div class="font-medium"><?= htmlspecialchars($u['display_name']) ?> <span class="text-xs text-gray-500">(<?= htmlspecialchars($u['email']) ?>)</span></div>
              <div class="text-xs text-gray-500">Rolle: <?= htmlspecialchars($u['role']) ?></div>
            </div>
            <a href="?user_id=<?= (int)$u['id'] ?>" class="px-3 py-1 rounded bg-gray-700 text-white">Moderieren</a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($target): ?>
    <div class="mb-6">
      <h2 class="text-xl font-semibold mb-2">Zielnutzer</h2>
      <div class="p-3 rounded border">
        <div class="font-medium"><?= htmlspecialchars($target['display_name']) ?> <span class="text-xs text-gray-500">(<?= htmlspecialchars($target['email']) ?>)</span></div>
        <div class="text-xs text-gray-500">Rolle: <?= htmlspecialchars($target['role']) ?></div>
        <div class="mt-2 text-sm">
          <span class="inline-block mr-4">Offene Verwarnungen: <strong><?= (int)$status['warnings'] ?></strong></span>
          <span class="inline-block">Mute: <strong><?= $status['mute'] ? ('aktiv bis '.$status['mute']['muted_until']) : '—' ?></strong></span>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <h3 class="font-semibold mb-2">Verwarnung vergeben</h3>
        <form id="form-warn" class="p-3 border rounded">
          <input type="hidden" name="user_id" value="<?= (int)$target['id'] ?>">
          <label class="block text-sm mb-1">Grund (optional)</label>
          <input name="reason" class="w-full px-3 py-2 rounded border mb-3 text-black" placeholder="z. B. Beleidigungen im Forum">
          <label class="block text-sm mb-1">Auto-Mute Dauer (Minuten, optional)</label>
          <input name="auto_minutes" type="number" min="1" class="w-full px-3 py-2 rounded border mb-3 text-black" placeholder="(Standard: <?= defined('HH_AUTO_MUTE_MINUTES_DEFAULT') ? HH_AUTO_MUTE_MINUTES_DEFAULT : 1440 ?>)">
          <button class="px-3 py-2 rounded bg-amber-700 text-white">Verwarnung speichern</button>
        </form>
      </div>
      <div>
        <h3 class="font-semibold mb-2">Manuell muten</h3>
        <form id="form-mute" class="p-3 border rounded">
          <input type="hidden" name="user_id" value="<?= (int)$target['id'] ?>">
          <label class="block text-sm mb-1">Dauer in Minuten</label>
          <input name="minutes" type="number" min="1" value="60" class="w-full px-3 py-2 rounded border mb-3 text-black">
          <label class="block text-sm mb-1">Grund (optional)</label>
          <input name="reason" class="w-full px-3 py-2 rounded border mb-3 text-black" placeholder="z. B. Spam">
          <div class="flex gap-2">
            <button class="px-3 py-2 rounded bg-red-700 text-white">Mute setzen</button>
            <button id="btn-unmute" type="button" class="px-3 py-2 rounded bg-gray-700 text-white">Aktiven Mute aufheben</button>
          </div>
        </form>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
      <div>
        <h3 class="font-semibold mb-2">Verwarnungen (letzte 100)</h3>
        <div class="border rounded overflow-hidden">
          <?php if (!$warnings): ?><div class="p-3 text-gray-500">Keine Einträge</div><?php endif; ?>
          <?php foreach ($warnings as $w): ?>
            <div class="p-3 border-b">
              <div class="text-sm text-gray-400">am <?= htmlspecialchars($w['created_at']) ?> von <?= htmlspecialchars($w['by_name'] ?? ('#'.$w['created_by'])) ?></div>
              <div><?= htmlspecialchars($w['reason'] ?? '—') ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <h3 class="font-semibold mb-2">Mutes (letzte 100)</h3>
        <div class="border rounded overflow-hidden">
          <?php if (!$mutes): ?><div class="p-3 text-gray-500">Keine Einträge</div><?php endif; ?>
          <?php foreach ($mutes as $m): ?>
            <div class="p-3 border-b">
              <div class="text-sm text-gray-400">am <?= htmlspecialchars($m['created_at']) ?> von <?= htmlspecialchars($m['by_name'] ?? ('#'.$m['created_by'])) ?> (<?= htmlspecialchars($m['kind']) ?>)</div>
              <div>bis <strong><?= htmlspecialchars($m['muted_until']) ?></strong> — <?= htmlspecialchars($m['reason'] ?? '—') ?></div>
              <?php if ((int)$m['active'] === 1 && !$m['revoked_at']): ?>
                <form method="post" action="/api/moderation/unmute.php" class="mt-2" onsubmit="return confirm('Mute wirklich aufheben?')">
                  <input type="hidden" name="mute_id" value="<?= (int)$m['id'] ?>">
                  <button class="px-3 py-1 rounded bg-gray-700 text-white">Mute aufheben</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
(function(){
  const csrfEl = document.querySelector('meta[name="csrf"], meta[name="csrf-token"]');
  const csrf = csrfEl ? csrfEl.getAttribute('content') : '';
  async function post(url, data){
    const headers = {'Content-Type':'application/json'};
    if (csrf) headers['X-CSRF-Token'] = csrf;
    const res = await fetch(url, {method:'POST', headers, body: JSON.stringify(data)});
    try { return await res.json(); } catch { return {ok:false, error:'invalid_json'}; }
  }
  const fWarn = document.getElementById('form-warn');
  const fMute = document.getElementById('form-mute');
  const btnUnmute = document.getElementById('btn-unmute');

  if (fWarn) fWarn.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(fWarn); const data = Object.fromEntries(fd.entries());
    data.user_id = parseInt(data.user_id,10); if (data.auto_minutes) data.auto_minutes = parseInt(data.auto_minutes,10);
    const j = await post('/api/moderation/warn.php', data);
    alert(j.ok ? 'Verwarnung gespeichert' + (j.auto_muted ? '\nAuto-Mute aktiviert.' : '') : ('Fehler: ' + (j.error||'unknown')));
    if (j.ok) location.search='?user_id='+data.user_id;
  });

  if (fMute) fMute.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(fMute); const data = Object.fromEntries(fd.entries());
    data.user_id = parseInt(data.user_id,10); data.minutes = parseInt(data.minutes,10);
    const j = await post('/api/moderation/mute.php', data);
    alert(j.ok ? 'Mute gesetzt.' : ('Fehler: ' + (j.error||'unknown')));
    if (j.ok) location.search='?user_id='+data.user_id;
  });

  if (btnUnmute && fMute) btnUnmute.addEventListener('click', async ()=>{
    const fd = new FormData(fMute); const data = Object.fromEntries(fd.entries());
    data.user_id = parseInt(data.user_id,10);
    const j = await post('/api/moderation/unmute.php', {user_id: data.user_id});
    alert(j.ok ? 'Mute aufgehoben.' : ('Fehler: ' + (j.error||'unknown')));
    if (j.ok) location.search='?user_id='+data.user_id;
  });
})();
</script>

<?php if ($use_layout && function_exists('layout_footer')) { layout_footer(); } else { ?>
</body></html>
<?php } ?>

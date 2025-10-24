<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth/db.php';
$cfg = require __DIR__ . '/../../auth/config.php';

// --- super simple guard ---
// 1) Setze in ENV QA_ADMIN_KEY=deinpass  ODER in config.php  'qa_admin_key' => 'deinpass'
$ADMIN_KEY = getenv('QA_ADMIN_KEY') ?: ($cfg['qa_admin_key'] ?? '');
$key = $_GET['key'] ?? $_POST['key'] ?? '';
if (!$ADMIN_KEY || $key !== $ADMIN_KEY) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><title>QA Discovery – Login</title>';
  echo '<style>body{font-family:system-ui;margin:40px}</style>';
  echo '<h2>QA Discovery – Login</h2>';
  echo '<form method="get"><input name="key" placeholder="Admin-Key" autofocus> <button>Los</button></form>';
  echo '<p style="opacity:.7">Setze <code>QA_ADMIN_KEY</code> als Server-ENV oder <code>$cfg["qa_admin_key"]</code> in config.php.</p>';
  exit;
}

// Vorkonfigurierte Quellen
$sources = [
  ['name'=>'Hunt News (official)', 'url'=>'https://www.huntshowdown.com/news/', 'pat'=>'/news/', 'max'=>12, 'lang'=>'en'],
  ['name'=>'Steam App News (594650)', 'url'=>'https://store.steampowered.com/news/app/594650', 'pat'=>'/view/', 'max'=>12, 'lang'=>'en'],
];

header('Content-Type: text/html; charset=utf-8');
$base = rtrim(($cfg['app_base'] ?? ''), '/');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Hunthub – QA Discovery</title>
<style>
body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:24px}
h1{margin:0 0 16px}
.card{border:1px solid #ddd;border-radius:12px;padding:16px;margin:12px 0;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
input,select,button{padding:8px;border:1px solid #ccc;border-radius:8px}
button{cursor:pointer}
pre{background:#f8f8f8;padding:12px;border-radius:8px;max-height:40vh;overflow:auto}
small{opacity:.7}
</style>
</head>
<body>
<h1>QA Discovery <small>(Admin)</small></h1>

<div class="card">
  <div class="row">
    <strong>Admin-Key:</strong>
    <input id="adm" value="<?= htmlspecialchars($key,ENT_QUOTES) ?>" style="width:220px">
    <button onclick="location.search='?key='+encodeURIComponent(document.getElementById('adm').value)">Neu laden</button>
  </div>
</div>

<div class="card">
  <h3>Vorkonfigurierte Quellen</h3>
  <?php foreach ($sources as $i=>$s): ?>
    <div class="row" style="margin:6px 0">
      <span style="min-width:220px"><strong><?= htmlspecialchars($s['name']) ?></strong></span>
      <code style="min-width:320px"><?= htmlspecialchars($s['url']) ?></code>
      <span>pat=</span><code><?= htmlspecialchars($s['pat']) ?></code>
      <span>max=</span><code><?= (int)$s['max'] ?></code>
      <button onclick="runIngest('<?= htmlspecialchars($s['url'],ENT_QUOTES) ?>','<?= htmlspecialchars($s['pat'],ENT_QUOTES) ?>',<?= (int)$s['max'] ?>,'<?= htmlspecialchars($s['lang'],ENT_QUOTES) ?>')">Jetzt crawlen</button>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h3>Eigene Quelle hinzufügen</h3>
  <div class="row">
    <input id="u" placeholder="Start-URL (https://...)" style="min-width:380px">
    <input id="pat" placeholder="pat-Filter (z.B. /news/update-)" value="/news/">
    <input id="max" type="number" value="10" min="1" max="50" style="width:100px">
    <select id="lang">
      <option value="en">EN</option>
      <option value="de">DE</option>
    </select>
    <button onclick="runIngest(u.value, pat.value, max.value, lang.value)">Crawlen</button>
  </div>
</div>

<div class="card">
  <h3>Ergebnis</h3>
  <pre id="out">Bereit.</pre>
</div>
<hr class="my-8">

<h2>Überblick</h2>
<div id="kpis" class="text-sm opacity-80">Lade KPIs…</div>

<hr class="my-8">

<h2>Review & Training</h2>
<p class="text-sm opacity-80">Zeigt Antworten mit geringer Confidence oder negativem Feedback.</p>
<button id="revReload">Neu laden</button>
<table id="revTbl" class="w-full" style="border-collapse:collapse; margin-top:.5rem;">
  <thead><tr>
    <th align="left">ID</th><th align="left">Lang</th><th align="left">Frage</th>
    <th align="left">Confidence</th><th align="left">Score</th><th align="left">Aktionen</th>
  </tr></thead>
  <tbody></tbody>
</table>

<hr class="my-8">

<h2>FAQ verwalten</h2>
<div>
  <form id="faqNew" class="grid" style="grid-template-columns:1fr; gap:.5rem; max-width:900px;">
    <div>
      <label>Lang</label>
      <select name="lang"><option>de</option><option>en</option></select>
    </div>
    <div>
      <label>Frage</label>
      <input name="question" required style="width:100%">
    </div>
    <div>
      <label>Antwort (HTML erlaubt)</label>
      <textarea name="answer_html" rows="5" style="width:100%"></textarea>
    </div>
    <div>
      <label>Tags</label>
      <input name="tags" value="hunthub" style="width:100%">
    </div>
    <div>
      <label>Priority</label>
      <input name="priority" type="number" value="1" min="0" style="width:120px">
    </div>
    <button type="submit">FAQ anlegen</button>
  </form>
</div>

<div style="margin-top:1rem">
  <button id="faqReload">FAQ laden</button>
  <table id="faqTbl" class="w-full" style="border-collapse:collapse; margin-top:.5rem;">
    <thead><tr>
      <th align="left">ID</th><th align="left">Lang</th><th align="left">Frage</th>
      <th align="left">Tags</th><th align="left">Prio</th><th align="left">Aktionen</th>
    </tr></thead>
    <tbody></tbody>
  </table>
</div>

<script>
async function runIngest(url, pat, max, lang){
  const out = document.getElementById('out');
  out.textContent = 'Lade…';
  const qs = new URLSearchParams({url, discover:'1', pat, max:String(max||10), lang});
  try{
    const r = await fetch('<?= $base ?>/api/qa/ingest.php?'+qs.toString());
    const t = await r.text();
    out.textContent = t;
  }catch(e){
    out.textContent = 'Fehler: '+e;
  }
}
</script>
<script>
(() => {
  const KEY = localStorage.getItem('hhqa_admin_key') || 'oty4JIvLgRJAMWgBG6I3'; // wie bei Discovery
  const Q = sel => document.querySelector(sel);

  // --- KPIs
  async function loadKpis(){
    const r = await fetch('/api/qa/admin_stats.php?key='+encodeURIComponent(KEY));
    const j = await r.json();
    if (!j.ok) { Q('#kpis').textContent = 'Fehler'; return; }
    const k = j.kpis;
    Q('#kpis').innerHTML =
      `Docs: <b>${k.docs}</b> • Chunks: <b>${k.chunks}</b> • Logs 7d: <b>${k.logs7}</b> • `
    + `Feedback 👍 <b>${k.fb_pos}</b> / 👎 <b>${k.fb_neg}</b> • FAQ: <b>${k.faq}</b>`;
  }

  // --- Review-Liste
  async function loadReview(){
    const r = await fetch('/api/qa/admin_review.php?action=list&limit=30&key='+encodeURIComponent(KEY));
    const j = await r.json();
    const tb = Q('#revTbl tbody'); tb.innerHTML = '';
    if (!j.ok) { tb.innerHTML = '<tr><td colspan="6">Fehler</td></tr>'; return; }
    j.items.forEach(it => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.id}</td>
        <td>${it.lang}</td>
        <td style="max-width:520px">${escapeHtml(it.question||'')}</td>
        <td>${(+it.confidence).toFixed(2)}</td>
        <td>${it.fb_score} (${it.fb_count})</td>
        <td>
          <button data-act="promote" data-id="${it.id}" data-lang="${it.lang}">zu FAQ</button>
          <button data-act="ok" data-id="${it.id}">ok</button>
          <button data-act="del" data-id="${it.id}">löschen</button>
        </td>`;
      tb.appendChild(tr);
    });
  }
  Q('#revReload').addEventListener('click', loadReview);
  Q('#revTbl').addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    const id = b.dataset.id;
    if (b.dataset.act === 'ok') {
      await post('/api/qa/admin_review.php', {action:'mark_ok', id, key:KEY});
      loadReview();
    } else if (b.dataset.act === 'del') {
      if (!confirm('Log wirklich löschen?')) return;
      await post('/api/qa/admin_review.php', {action:'delete', id, key:KEY});
      loadReview();
    } else if (b.dataset.act === 'promote') {
      const lang = b.dataset.lang || 'de';
      const q = prompt('FAQ-Frage eingeben:');
      if (!q) return;
      const tags = prompt('Tags (Semikolon-getrennt):','hunthub;auto') || 'hunthub;auto';
      await post('/api/qa/admin_review.php', {action:'promote_faq', id, lang, question:q, tags, priority:1, key:KEY});
      alert('als FAQ angelegt');
    }
  });

  // --- FAQ-Liste & Create
  async function loadFaq(){
    const r = await fetch('/api/qa/admin_faq.php?action=list&key='+encodeURIComponent(KEY));
    const j = await r.json();
    const tb = Q('#faqTbl tbody'); tb.innerHTML = '';
    if (!j.ok) { tb.innerHTML = '<tr><td colspan="6">Fehler</td></tr>'; return; }
    j.items.forEach(f => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${f.id}</td>
        <td>${f.lang}</td>
        <td style="max-width:520px">${escapeHtml(f.question)}</td>
        <td>${escapeHtml(f.tags||'')}</td>
        <td>${f.priority}</td>
        <td>
          <button data-act="edit" data-id="${f.id}">edit</button>
          <button data-act="del" data-id="${f.id}">del</button>
        </td>`;
      tb.appendChild(tr);
    });
  }
  Q('#faqReload').addEventListener('click', loadFaq);

  Q('#faqTbl').addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    const id = b.dataset.id;
    if (b.dataset.act === 'del') {
      if (!confirm('FAQ löschen?')) return;
      await post('/api/qa/admin_faq.php', {action:'delete', id, key:KEY});
      loadFaq();
    }
    if (b.dataset.act === 'edit') {
      // ganz simpel: nur Frage/Prio/Tags per Prompt
      const q = prompt('Neue Frage:');
      if (!q) return;
      const tags = prompt('Tags (Semikolon):','hunthub') || 'hunthub';
      const pr = parseInt(prompt('Priority (Zahl, kleiner = höher)', '1')||'1',10);
      await post('/api/qa/admin_faq.php', {action:'update', id, question:q, tags, priority:pr, lang:'de', answer_html:'', key:KEY});
      loadFaq();
    }
  });

  Q('#faqNew').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action','create');
    fd.append('key',KEY);
    await fetch('/api/qa/admin_faq.php', { method:'POST', body:fd });
    e.target.reset();
    loadFaq();
  });

  // helpers
  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  async function post(url, obj){
    const body = new URLSearchParams(); Object.entries(obj).forEach(([k,v]) => body.append(k, String(v)));
    const r = await fetch(url, {method:'POST', body}); return r.json();
  }

  // initial
  loadKpis(); loadReview(); loadFaq();
})();
</script>

</body>
</html>

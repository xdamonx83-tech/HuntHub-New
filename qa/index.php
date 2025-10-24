<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/layout.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$L = $GLOBALS['L'];
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$csrf = function_exists('issue_csrf') ? issue_csrf(db(), $_COOKIE[$cfg['cookies']['session_name'] ?? ''] ?? '') : '';
?><!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf" content="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
  <title>Hunthub – QA Bot (Hunt: Showdown)</title>
  <?php if (function_exists('theme_header')) theme_header('QA'); ?>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
    .qa-card{border:1px solid #ddd;border-radius:12px;padding:16px;background:#fff}
    .qa-msg{display:flex;gap:10px;align-items:flex-start;margin:10px 0}
    .qa-msg.user .bubble{background:#f2f2f2}
    .qa-msg .bubble{flex:1;padding:12px;border-radius:12px}
    .qa-cite{opacity:.7;font-size:.9em;margin-top:6px}
    .row{display:flex; gap:8px; align-items:center}
    select{padding:6px 8px}
  </style>
</head>
<body>
<?php if (function_exists('theme_top')) theme_top(); ?>
<main class="container" style="max-width:900px;margin:20px auto;padding:0 8px">
  <h1 class="heading-4 mb-2">Frag Hunthub (Hunt: Showdown)</h1>
  <p class="mb-3">Der Bot zieht Infos aus erlaubten Quellen (Patchnotes, News, Hunthub/HTDA). Wenn er unsicher ist, zeigt er Quellen.</p>

  <section class="qa-card">
    <div class="row">
      <input id="qa-q" style="flex:1" placeholder="Z. B. Was änderte sich im letzten Update?" />
      <select id="qa-lang">
        <option value="de" selected>DE</option>
        <option value="en">EN</option>
      </select>
      <button id="qa-ask">Fragen</button>
    </div>
    <div id="qa-out" class="mt-12"></div>
  </section>
</main>
<?php if (function_exists('theme_bottom')) theme_bottom(); ?>
<script>
(function(){
  const out = document.getElementById('qa-out');
  const input = document.getElementById('qa-q');
  const btn = document.getElementById('qa-ask');
  const sel = document.getElementById('qa-lang');

  async function ask(q, lang){
    const form = new URLSearchParams({q, lang});
    const r = await fetch('<?= $APP_BASE ?>/api/qa/ask.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:form});
    const j = await r.json();
    const user = document.createElement('div');
    user.className='qa-msg user';
    user.innerHTML = `<div class="bubble"><strong>Du:</strong><br>${escapeHtml(q)}</div>`;
    out.appendChild(user);
    const bot = document.createElement('div');
    bot.className='qa-msg';
    bot.innerHTML = `<div class="bubble"><strong>Bot:</strong><br>${j.ok ? (j.answer.html||'') : (j.message||'')}</div>`;
    out.appendChild(bot);
    out.scrollTop = out.scrollHeight;
  }
  function escapeHtml(s){return String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]))}
  btn.addEventListener('click', ()=>{ const q=input.value.trim(); if(q) ask(q, sel.value); });
  input.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ btn.click(); }});
})();
</script>
</body>
</html>

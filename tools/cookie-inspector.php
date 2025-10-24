<?php
declare(strict_types=1);

/**
 * Hunthub Cookie Inspector
 * - Listet Server-Cookies (inkl. HttpOnly erkennbar) + clientseitige Storage
 * - Ohne Abhängigkeit von layout.php
 * - Optional: nur für Admins freischalten (require_admin), falls guards.php vorhanden.
 */

@ini_set('display_errors','0'); error_reporting(E_ALL);

// ---- Optional: Zugriff nur für Admins
$ADMIN_ONLY = false; // auf true setzen, wenn nur Admins zugreifen dürfen

$guards = __DIR__ . '/../auth/guards.php';
if ($ADMIN_ONLY && is_file($guards)) {
  require_once $guards;
  if (function_exists('require_admin')) { require_admin(); }
}

// Serverseitig bekannte Cookies (inkl. HttpOnly)
$serverCookies = $_COOKIE ?? [];

?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cookie & Storage Inspector</title>
<style>
  :root{--bg:#111;--fg:#e7e7e7;--mut:#a8a8a8;--card:#1a1715;--hl:#f0f0f0;}
  body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.5 system-ui,Segoe UI,Roboto,Arial,sans-serif}
  header{padding:16px 20px;border-bottom:1px solid #2a2725;background:#131110;position:sticky;top:0;z-index:1}
  h1{margin:0;font-size:18px;letter-spacing:.2px}
  main{max-width:1100px;margin:0 auto;padding:20px}
  .grid{display:grid;grid-template-columns:1fr;gap:16px}
  @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
  .card{background:var(--card);border:1px solid #2b2826;border-radius:14px;padding:14px 14px 10px}
  .card h2{margin:0 0 8px;font-size:15px;color:var(--hl)}
  table{width:100%;border-collapse:collapse}
  th,td{padding:8px;border-bottom:1px solid #2b2826;vertical-align:top}
  th{text-align:left;color:#cfcfcf;font-weight:600}
  tbody tr:hover{background:#201d1b}
  .tag{display:inline-block;padding:2px 6px;border-radius:999px;border:1px solid #3a3633;color:#ddd;font-size:12px;margin-right:6px}
  .pill{padding:1px 8px;border-radius:999px;background:#2a2522;color:#ddd;font-size:12px}
  .mut{color:var(--mut)}
  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0}
  button{background:#2a2522;border:1px solid #3a3633;color:#eee;border-radius:10px;padding:8px 10px;cursor:pointer}
  button:hover{background:#342f2b}
  pre{white-space:pre-wrap;background:#141210;border:1px solid #2b2826;border-radius:10px;padding:10px;max-height:260px;overflow:auto}
  .small{font-size:12px}
</style>

<header><h1>Cookie & Storage Inspector</h1></header>
<main>
  <div class="row">
    <button id="btn-export">Export JSON</button>
    <span class="mut small">Tipp: Für Drittanbieter-Cookies DevTools öffnen (Application → Cookies) und Seite mit „including third-party cookies“ neu laden.</span>
  </div>

  <div class="grid">
    <section class="card">
      <h2>Cookies</h2>
      <table id="cookie-table">
        <thead><tr><th>Name</th><th>Wert (gekürzt)</th><th>Flags (erkannt)</th></tr></thead>
        <tbody></tbody>
      </table>
      <div class="small mut">Hinweis: <b>HttpOnly</b> wird erkannt, indem wir Server-Cookies mit <code>document.cookie</code> vergleichen. <b>Secure</b>/<b>SameSite</b>/<b>Path</b>/<b>Domain</b> sind clientseitig nicht zuverlässig auslesbar – dafür DevTools (Response-Header <code>Set-Cookie</code>) nutzen.</div>
    </section>

    <section class="card">
      <h2>localStorage</h2>
      <table id="ls-table"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody></tbody></table>
    </section>

    <section class="card">
      <h2>sessionStorage</h2>
      <table id="ss-table"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody></tbody></table>
    </section>

    <section class="card">
      <h2>IndexedDB</h2>
      <div id="idb-wrap"><span class="mut small">Prüfe Unterstützung…</span></div>
    </section>

    <section class="card">
      <h2>Cache Storage</h2>
      <div id="cache-wrap"><span class="mut small">Prüfe Caches…</span></div>
    </section>

    <section class="card">
      <h2>Rohdaten</h2>
      <pre id="raw"></pre>
    </section>
  </div>
</main>

<script>
// ---- Daten aus PHP in JS heben
const SERVER_COOKIES = <?php
  echo json_encode($serverCookies, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>;

// helpers
const el = (sel,root=document)=>root.querySelector(sel);
const tbody = (id)=>el('#'+id+' tbody');

function parseDocCookies() {
  const out = {};
  const raw = document.cookie || '';
  raw.split(';').forEach(pair => {
    const s = pair.trim();
    if (!s) return;
    const idx = s.indexOf('=');
    const k = idx >= 0 ? s.slice(0, idx) : s;
    const v = idx >= 0 ? decodeURIComponent(s.slice(idx+1)) : '';
    out[k] = v;
  });
  return out;
}

function shorten(v, n=80) {
  if (v == null) return '';
  v = String(v);
  return v.length > n ? v.slice(0,n)+'…' : v;
}

function renderCookies() {
  const jsCookies = parseDocCookies();
  const names = new Set([...Object.keys(SERVER_COOKIES), ...Object.keys(jsCookies)]);
  const rows = [];
  names.forEach(name => {
    const serverVal = SERVER_COOKIES[name];
    const jsVal     = jsCookies[name];
    const httpOnly  = (serverVal !== undefined) && (jsVal === undefined);
    const flags = [];
    if (httpOnly) flags.push('<span class="pill">HttpOnly</span>');
    // Secure/SameSite/Path/Domain sind hier nicht verlässlich ermittelbar
    rows.push(`<tr>
      <td><code>${name}</code></td>
      <td>${shorten(jsVal ?? serverVal ?? '')}</td>
      <td>${flags.join(' ') || '<span class="mut">unbekannt</span>'}</td>
    </tr>`);
  });
  tbody('cookie-table').innerHTML = rows.join('') || `<tr><td colspan="3" class="mut">Keine Cookies gefunden.</td></tr>`;
}

function renderStorage(kind, tableId) {
  try {
    const s = (kind === 'local') ? window.localStorage : window.sessionStorage;
    const rows = [];
    for (let i=0;i<s.length;i++) {
      const key = s.key(i);
      rows.push(`<tr><td><code>${key}</code></td><td>${shorten(s.getItem(key) ?? '')}</td></tr>`);
    }
    tbody(tableId).innerHTML = rows.join('') || `<tr><td colspan="2" class="mut">leer</td></tr>`;
  } catch(e) {
    tbody(tableId).innerHTML = `<tr><td colspan="2" class="mut">nicht verfügbar</td></tr>`;
  }
}

async function renderIndexedDB() {
  const wrap = el('#idb-wrap');
  if (!('indexedDB' in window)) { wrap.innerHTML = '<span class="mut">Nicht unterstützt</span>'; return; }
  if (!('databases' in indexedDB)) { wrap.innerHTML = '<span class="mut">API <code>indexedDB.databases()</code> nicht verfügbar – Browser limitiert.</span>'; return; }
  try {
    const dbs = await indexedDB.databases();
    if (!dbs || !dbs.length) { wrap.innerHTML = '<span class="mut">Keine Datenbanken</span>'; return; }
    const list = dbs.map(d => `<div class="tag">${(d.name||'(ohne Namen)')} <span class="mut">v${d.version||'-'}</span></div>`).join('');
    wrap.innerHTML = list;
  } catch(e) {
    wrap.innerHTML = '<span class="mut">Fehler beim Auslesen</span>';
  }
}

async function renderCaches() {
  const wrap = el('#cache-wrap');
  if (!('caches' in window)) { wrap.innerHTML = '<span class="mut">Nicht unterstützt</span>'; return; }
  try {
    const keys = await caches.keys();
    if (!keys.length) { wrap.innerHTML = '<span class="mut">Keine Caches</span>'; return; }
    const out = [];
    for (const k of keys) {
      const c = await caches.open(k);
      const reqs = await c.keys();
      out.push(`<div class="tag">${k} <span class="mut">${reqs.length} Einträge</span></div>`);
    }
    wrap.innerHTML = out.join('');
  } catch(e) {
    wrap.innerHTML = '<span class="mut">Fehler beim Auslesen</span>';
  }
}

function renderRaw() {
  const data = {
    serverCookies: SERVER_COOKIES,
    documentCookies: parseDocCookies(),
    localStorage: Object.fromEntries(Object.keys(localStorage||{}).map(k=>[k, localStorage.getItem(k)])),
    sessionStorage: Object.fromEntries(Object.keys(sessionStorage||{}).map(k=>[k, sessionStorage.getItem(k)])),
    time: new Date().toISOString()
  };
  el('#raw').textContent = JSON.stringify(data, null, 2);
  return data;
}

function exportJSON(data) {
  const blob = new Blob([JSON.stringify(data, null, 2)], {type:'application/json'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = 'hunthub-storage-report.json';
  document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}

// init
renderCookies();
renderStorage('local','ls-table');
renderStorage('session','ss-table');
renderIndexedDB();
renderCaches();
let lastDump = renderRaw();

el('#btn-export').addEventListener('click', ()=> exportJSON(lastDump));
</script>

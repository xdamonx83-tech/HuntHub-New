<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/layout.php';
hh_log('info', 'logger ok', ['uri'=>$_SERVER['REQUEST_URI'] ?? '']);
$pdo = db();
$me  = require_admin(); // blockt Nicht-Admins

$cfg = require __DIR__ . '/auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

// CSRF-Token passend zu deiner csrf.php erzeugen:
$sessionCookie = $_COOKIE[$cfg['cookies']['session_name']] ?? '';
$csrf = issue_csrf($pdo, $sessionCookie);

$view = $_GET['view'] ?? 'dashboard';

function count_row(PDO $pdo, string $table): int {
  $st = $pdo->query("SELECT COUNT(*) AS c FROM {$table}");
  return (int)$st->fetchColumn();
}

ob_start();
?>
<meta name="csrf" content="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
<style>
  .admin-wrap{max-width:1200px;margin:2rem auto;padding:1rem;padding-top:10%;}
  .admin-nav{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
  .admin-nav a{padding:.5rem .75rem;border:1px solid rgba(255,255,255,.15);border-radius:8px;text-decoration:none;color:#e5e7eb;background:#141414}
  .admin-card{background:#0e0e0e;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:1rem;margin-bottom:1rem}
  .grid{display:grid;gap:.75rem}
  .grid.cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}
  table{width:100%;border-collapse:collapse}
  th,td{padding:.5rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;vertical-align:top}
  .btn{display:inline-block;padding:.4rem .65rem;border:1px solid rgba(255,255,255,.15);border-radius:8px;background:#1b1b1b;color:#e5e7eb;text-decoration:none;cursor:pointer}
  .danger{border-color:#f87171;color:#fecaca}
  .ok{border-color:#4ade80;color:#bbf7d0}
  .muted{opacity:.85}
  .kpi{font-size:1.25rem;font-weight:600}
  input[type="text"],input[type="number"],input[type="datetime-local"],textarea,select{
    padding:.5rem;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:#111;color:#e5e7eb;width:100%
  }
  textarea{min-height:110px}
  .badge{display:inline-block;padding:.1rem .5rem;border:1px solid rgba(255,255,255,.15);border-radius:999px;font-size:12px}
  .app-layout { padding-top: 1px; }
</style>

<div class="admin-wrap">
  <h1>Admin</h1>
  <div class="admin-nav">
    <a href="<?= $APP_BASE ?>/admin.php?view=dashboard">Dashboard</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=users">Users</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=forum">Forum</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=tournaments">Turniere</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=uploads">Uploads</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=cache">Cache</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=health">Health</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=notifications">Notifications</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=gamification">Gamification</a>
    <a href="<?= $APP_BASE ?>/admin.php?view=messages">Messages</a>
    <a href="?view=inspector" class="<?= ($view ?? '') === 'inspector' ? 'is-active' : '' ?>">Storage-Inspector</a>
	<a href="<?= $APP_BASE ?>/admin.php?view=logs">Logs</a>
	<a href="<?= $APP_BASE ?>/admin.php?view=bugs">Bugs</a>
<a href="<?= $APP_BASE ?>/admin.php?view=reports">Reports</a>
<a href="<?= $APP_BASE ?>/admin.php?view=reels">Reels</a>

  </div>

  <?php if ($view==='dashboard'): ?>
    <?php
      $k_users   = count_row($pdo,'users');
      $k_threads = count_row($pdo,'threads');
      $k_posts   = count_row($pdo,'posts');
      $k_msgs    = count_row($pdo,'messages');
      $k_notif   = count_row($pdo,'notifications');
      $k_lp      = count_row($pdo,'link_previews');
      $k_ach     = count_row($pdo,'achievements');
      $k_uach    = count_row($pdo,'user_achievements');
    ?>
    <div class="grid cols-4">
      <div class="admin-card"><div class="muted">Users</div><div class="kpi"><?= $k_users ?></div></div>
      <div class="admin-card"><div class="muted">Threads</div><div class="kpi"><?= $k_threads ?></div></div>
      <div class="admin-card"><div class="muted">Posts</div><div class="kpi"><?= $k_posts ?></div></div>
      <div class="admin-card"><div class="muted">Messages</div><div class="kpi"><?= $k_msgs ?></div></div>
      <div class="admin-card"><div class="muted">Notifications</div><div class="kpi"><?= $k_notif ?></div></div>
      <div class="admin-card"><div class="muted">Link-Previews</div><div class="kpi"><?= $k_lp ?></div></div>
      <div class="admin-card"><div class="muted">Achievements</div><div class="kpi"><?= $k_ach ?></div></div>
      <div class="admin-card"><div class="muted">User-Achievements</div><div class="kpi"><?= $k_uach ?></div></div>

    </div>
	<?php elseif ($view==='reels'): ?>
  <style>
    /* Reels-Admin Mini-CSS (konfliktfrei zur Frontend reels.css) */
    .reels-admin .filters{display:grid;grid-template-columns:1fr 140px 140px 120px;gap:.5rem;align-items:end}
    .reels-admin .filters .wide{grid-column:1/-1}
    .reels-admin .bad{color:#fecaca}
    .reels-admin .ok{color:#bbf7d0}
    .reels-admin .muted{opacity:.85}
    .reels-admin .thumb{width:64px;height:96px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,.08)}
    #reelPreviewModal{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(2px)}
    #reelPreviewModal .wrap{max-width:920px;margin:5vh auto;background:#0e0e0e;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem}
    #reelPreviewModal .grid{display:grid;gap:12px;grid-template-columns:1fr 320px}
    #reelPreviewModal video{width:100%;max-height:70vh;background:#000;border-radius:10px}
    @media (max-width:980px){#reelPreviewModal .grid{grid-template-columns:1fr}}
    /* Iframe-Viewer Overlay (kapselt Frontend-CSS) */
    #reelIframeModal{display:none;position:fixed;inset:0;z-index:9999}
    #reelIframeModal .ov{position:absolute;inset:0;background:#000;opacity:.88}
    #reelIframeModal .frame{position:absolute;inset:0}
    #reelIframeModal iframe{width:100%;height:100%;border:0;display:block;background:#000}
    #reelIframeModal .x{position:absolute;top:16px;right:16px;width:40px;height:40px;border-radius:999px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.08);color:#fff;cursor:pointer}
  </style>

  <div class="admin-card reels-admin">
    <h3 style="margin:0 0 .5rem 0">Reels – Moderation</h3>
    <div class="filters">
      <div>
        <label>Suche</label>
        <input id="rl_q" type="text" placeholder="Text, #hashtag, @user, ID …">
      </div>
      <div>
        <label>Status</label>
        <select id="rl_status">
          <option value="">(alle)</option>
          <option value="visible">visible</option>
          <option value="hidden">hidden</option>
          <option value="blocked">blocked</option>
          <option value="under_review">under_review</option>
        </select>
      </div>
      <div>
        <label>User-ID</label>
        <input id="rl_uid" type="number" min="1" placeholder="optional">
      </div>
      <div>
        <button class="btn" onclick="reelsLoad()">Neu laden</button>
      </div>
    </div>
  </div>

  <div class="admin-card">
    <table id="reelsTable">
      <thead>
        <tr>
          <th>ID</th><th>Preview</th><th>Autor</th><th>Erstellt</th>
          <th>Status</th><th>Likes•Comments</th><th>Reports</th><th>Beschreibung</th><th>Aktion</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <!-- Inline-Preview Modal -->
  <div id="reelPreviewModal">
    <div class="wrap">
      <h3 style="margin:0 0 .5rem 0">Reel Vorschau</h3>
      <div class="grid">
        <div>
          <video id="rp_video" playsinline webkit-playsinline muted autoplay controls loop></video>
        </div>
        <div>
          <div class="muted" id="rp_meta"></div>
          <div id="rp_desc" style="white-space:pre-wrap;margin-top:8px"></div>
          <div id="rp_tags" class="muted" style="margin-top:6px"></div>
          <div style="margin-top:10px;display:flex;gap:.5rem;flex-wrap:wrap">
            <button class="btn" onclick="reelPreviewClose()">Schließen</button>
            <button class="btn" id="rp_btn_viewer">Als Viewer öffnen</button>
            <a class="btn" id="rp_btn_link" target="_blank" rel="noreferrer noopener">/reels.php</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Vollbild-Viewer als Iframe (CSS-Konflikte vermeiden) -->
  <div id="reelIframeModal" aria-hidden="true">
    <div class="ov"></div>
    <div class="frame"><iframe id="reelIframe" src=""></iframe></div>
    <button class="x" onclick="closeReelIframe()">✕</button>
  </div>


	<?php elseif ($view==='reports'): ?>
  <div class="admin-card">
    <h3 style="margin:0 0 .5rem 0">Meldungen (Wall)</h3>
    <div style="margin-bottom:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <button class="btn" onclick="reportsLoad()">Neu laden</button>
    </div>
    <table id="repTable">
      <thead>
        <tr>
          <th>Typ</th><th>Entity</th><th>Reports</th><th>Gründe</th>
          <th>Autor</th><th>Vorschau</th><th>Status</th><th>Aktion</th>
        </tr>
      </thead>
      <tbody></tbody><div id="repModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(2px);z-index:9999;">
  <div style="max-width:860px;margin:6vh auto;background:#0e0e0e;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem">
    <h3 style="margin:0 0 .5rem 0">Gemeldeter Inhalt</h3>
    <div id="repMeta" class="muted" style="margin-bottom:.5rem"></div>
    <div id="repReasons" class="muted" style="margin-bottom:.5rem"></div>
    <div id="repContent" style="white-space:pre-wrap;background:#0b0b0b;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:.75rem;max-height:46vh;overflow:auto"></div>
    <div style="margin-top:.75rem;display:flex;gap:.5rem;justify-content:flex-end">
      <button class="btn ok" id="repBtnRelease">Freigeben</button>
      <button class="btn danger" id="repBtnDelete">Löschen</button>
      <button class="btn" onclick="repClose()">Schließen</button>
    </div>
  </div>
</div>

    </table>
  </div>
<script>
(function(){
  if ("<?= $view ?>" !== "reports") return;

  const BASE = "<?= $APP_BASE ?>";
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

  // Wrapper: nutzt window.api, fällt sonst auf fetch zurück (reihenfolgen-sicher)
  async function rApi(path, payload){
    if (typeof window.api === 'function') {
      return await window.api(`reports/${path}`, payload);
    }
    const res = await fetch(`${BASE}/api/admin/reports/${path}`, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF': CSRF },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ csrf: CSRF }, payload || {}))
    });
    const text = await res.text();
    let j = null; try { j = JSON.parse(text); } catch(_) {}
    if (!res.ok || !j || j.ok === false) {
      const msg = j?.error || j?.details || text.slice(0,600) || 'unbekannter Fehler';
      alert(`API-Fehler (reports/${path}): HTTP ${res.status}\n${msg}`);
      throw new Error(msg);
    }
    return j;
  }

  function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}

  // Tabelle laden (verträgt beide Formen: reasons als Objekt ODER Array)
  async function reportsLoad(){
    const j = await rApi('list.php', {});
    const tb = document.querySelector('#repTable tbody'); if (!tb) return;
    tb.innerHTML = '';

    (j.items || []).forEach(it=>{
      const cnt = it.count ?? it.reports ?? 0;

      let reasonsStr = '–';
      if (Array.isArray(it.reasons)) {
        reasonsStr = it.reasons.map(([k,v]) => `${k} (${v})`).join(', ');
      } else if (it.reasons && typeof it.reasons === 'object') {
        reasonsStr = Object.entries(it.reasons)
          .sort((a,b)=>b[1]-a[1])
          .map(([k,v])=>`${k} (${v})`).join(', ');
      }

const tr = document.createElement('tr');
const reasons = Object.entries(it.reasons||{}).sort((a,b)=>b[1]-a[1]).slice(0,3).map(([k,v])=>`${k} (${v})`).join(', ');
tr.innerHTML = `
  <td>${it.type}</td>
  <td>#${it.entity_id}</td>
  <td>${it.reports}</td>
  <td class="muted">${reasons||'–'}</td>
  <td>${(it.author_name||'')} <span class="muted">#${it.author_id||''}</span></td>
  <td class="muted" title="${(it.preview||'').replace(/"/g,'&quot;')}">${(it.preview||'').slice(0,120)}</td>
  <td>${it.under_review ? '<span class="badge">under review</span>' : '–'}</td>
  <td style="display:flex;gap:.35rem;flex-wrap:wrap">
    <button class="btn" onclick="repOpen('${it.type}', ${it.entity_id})">Öffnen</button>
    <button class="btn ok" onclick="reportAct('${it.type}', ${it.entity_id}, 'release')">Freigeben</button>
    <button class="btn danger" onclick="reportAct('${it.type}', ${it.entity_id}, 'delete')">Löschen</button>
  </td>
`;
tb.appendChild(tr);

    });
  }

  // global verfügbar für deinen "Neu laden" Button
  window.reportsLoad = reportsLoad;

  // auto-init
  reportsLoad();
})();
let repCur = { type:'', id:0 };

function repClose(){
  document.getElementById('repModal').style.display='none';
  repCur = {type:'', id:0};
}

function esc(s){ return (s||'').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
function trimHtmlToText(html){
  try { return (html||'').replace(/<br\s*\/?>/gi, '\n').replace(/<\/p>/gi, '\n\n').replace(/<[^>]+>/g,''); } catch(e){ return html||''; }
}

async function repOpen(type, id){
  repCur = { type, id };
  const box = document.getElementById('repContent');
  const meta= document.getElementById('repMeta');
  const rsn = document.getElementById('repReasons');
  box.textContent = 'Lade…';
  meta.textContent = '';
  rsn.textContent  = '';

  const j = await api('reports/get.php', { type, entity_id:id });
  if (!j || !j.ok) { box.textContent = 'Fehler beim Laden.'; return; }

  const reasons = Object.entries(j.reasons||{}).sort((a,b)=>b[1]-a[1]).map(([k,v])=>`${k} (${v})`).join(', ');
  const flag = j.under_review ? ' • under review' : '';
  meta.innerHTML = `#${j.entity_id} · ${esc(j.type)} · ${esc(j.author_name||'')} <span class="muted">#${j.author_id||''}</span> · ${esc(j.created_at||'')} · Meldungen: ${j.reports}${flag}`;
  rsn.textContent = reasons || '–';

  // Inhalt: bevorzugt Plain, sonst HTML → zu Text konvertieren (für sichere Vorschau)
  const plain = (j.content && j.content.plain) ? j.content.plain : '';
  const html  = (j.content && j.content.html)  ? j.content.html  : '';
  const text  = plain !== '' ? plain : trimHtmlToText(html);
  box.textContent = text || '(leer)';

  // Buttons binden
  const btnR = document.getElementById('repBtnRelease');
  const btnD = document.getElementById('repBtnDelete');
  btnR.onclick = async ()=>{
    const r = await api('reports/act.php', { type, entity_id:id, action:'release' });
    if (r && r.ok) { repClose(); reportsLoad(); }
  };
  btnD.onclick = async ()=>{
    if (!confirm(`${type} #${id} wirklich löschen?`)) return;
    const r = await api('reports/act.php', { type, entity_id:id, action:'delete' });
    if (r && r.ok) { repClose(); reportsLoad(); }
  };

  // UX: ab 3 Meldungen → „Freigeben“ optisch als empfohlene Aktion
  if ((j.reports||0) >= 3 && j.under_review) {
    btnR.classList.add('ok');      // ist ohnehin ok
  }

  document.getElementById('repModal').style.display='block';
}

</script>





	<?php elseif ($view==='bugs'): ?>
  <div class="admin-card">
    <h3 style="margin:0 0 .5rem 0">Tickets</h3>
    <div class="grid" style="grid-template-columns:1fr 1fr 1fr 120px;align-items:end;gap:.5rem">
      <div><label>Status</label>
        <select id="bg_status"><option value="">(alle)</option><option>open</option><option>waiting</option><option>closed</option></select>
      </div>
      <div><label>Priorität</label>
        <select id="bg_prio"><option value="">(alle)</option><option>urgent</option><option>high</option><option>medium</option><option>low</option></select>
      </div>
      <div><label>&nbsp;</label><button class="btn" onclick="bgLoad()">Neu laden</button></div>
    </div>
  </div>
  <div class="admin-card">
    <table id="bgTable"><thead>
      <tr><th>ID</th><th>Titel</th><th>User</th><th>Status</th><th>Prio</th><th>XP</th><th>Badge</th><th>Updated</th><th>Aktion</th></tr>
    </thead><tbody></tbody></table>
  </div>

  <div id="bgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(2px);z-index:9999;">
    <div style="max-width:680px;margin:6vh auto;background:#0e0e0e;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem">
      <h3 style="margin:0 0 .75rem 0">Ticket bearbeiten</h3>
      <input type="hidden" id="bg_id">
      <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:.5rem">
        <div><label>Status</label><select id="bg_set_status"><option>open</option><option>waiting</option><option>closed</option></select></div>
        <div><label>Priorität</label><select id="bg_set_prio"><option>urgent</option><option>high</option><option selected>medium</option><option>low</option></select></div>
        <div><label>XP (+)</label><input id="bg_set_xp" type="number" value="0" min="0"></div>
      </div>
      <div class="grid" style="grid-template-columns:1fr;gap:.5rem;margin-top:.5rem">
        <div><label>Badge-Code (optional)</label><input id="bg_set_badge" type="text" placeholder="z.B. helper_1"></div>
        <div><label>Admin-Kommentar (Log)</label><textarea id="bg_set_comment" rows="4"></textarea></div>
      </div>
      <div style="margin-top:.75rem;display:flex;gap:.5rem;justify-content:flex-end">
        <button class="btn ok" onclick="bgSave()">Speichern</button>
        <button class="btn" onclick="bgClose()">Abbrechen</button>
      </div>
    </div>
  </div>

  <script>
  const BASE="<?= $APP_BASE ?>";
  const CSRF=document.querySelector('meta[name="csrf"]')?.content||'';
  function esc(s){return (s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));}

  async function bgLoad(){
    const st=document.getElementById('bg_status').value;
    const pr=document.getElementById('bg_prio').value;
    const u = `${BASE}/api/bugs/admin_list.php?status=${encodeURIComponent(st)}&priority=${encodeURIComponent(pr)}`;
    const r = await fetch(u, {headers:{'X-CSRF':CSRF}});
    const j = await r.json().catch(()=>null);
    const tb=document.querySelector('#bgTable tbody'); tb.innerHTML='';
    (j?.items||[]).forEach(b=>{
      const tr=document.createElement('tr');
      tr.innerHTML=`<td>${b.id}</td><td>${esc(b.title)}</td><td>${esc(b.username||b.user_id)}</td>
        <td>${b.status}</td><td>${b.priority}</td><td>${b.xp_awarded||0}</td><td>${esc(b.badge_code||'')}</td>
        <td>${b.updated_at}</td>
        <td><button class="btn" onclick='bgOpen(${b.id},${JSON.stringify(b).replace(/'/g,"&#39;")})'>Edit</button></td>`;
      tb.appendChild(tr);
    });
  }
  function bgOpen(id, b){
    document.getElementById('bg_id').value=id;
    document.getElementById('bg_set_status').value=b.status||'open';
    document.getElementById('bg_set_prio').value=b.priority||'medium';
    document.getElementById('bg_set_xp').value=0;
    document.getElementById('bg_set_badge').value='';
    document.getElementById('bg_set_comment').value='';
    document.getElementById('bgModal').style.display='block';
  }
  function bgClose(){ document.getElementById('bgModal').style.display='none'; }
  async function bgSave(){
    const id=parseInt(document.getElementById('bg_id').value||'0',10);
    const status=document.getElementById('bg_set_status').value;
    const priority=document.getElementById('bg_set_prio').value;
    const xp=parseInt(document.getElementById('bg_set_xp').value||'0',10);
    const badge=document.getElementById('bg_set_badge').value.trim();
    const comment=document.getElementById('bg_set_comment').value.trim();
    const fd=new FormData(); fd.set('bug_id', String(id)); fd.set('status', status); fd.set('priority', priority);
    fd.set('xp', String(xp)); fd.set('badge_code', badge); fd.set('comment', comment);
    const r=await fetch(`${BASE}/api/bugs/admin_update.php`, {method:'POST', headers:{'X-CSRF':CSRF}, body:fd});
    const j=await r.json().catch(()=>null);
    if(!j||!j.ok){ alert('Fehler beim Speichern'); return; }
    bgClose(); bgLoad();
  }

  if ("<?= $view ?>" === 'bugs') bgLoad();
  
  async function bugsAdminSave(cur){
  const payload = {
    id: cur.id, // WICHTIG
    status:  document.getElementById('adm_status').value,
    priority:document.getElementById('adm_priority').value,
    badge_code:document.getElementById('adm_badge').value.trim(),
    xp_awarded:document.getElementById('adm_xp').value.trim(),
    admin_note:document.getElementById('adm_note').value.trim()
  };

  const res = await fetch(`${BASE}/api/bugs/admin_update.php`, {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'X-CSRF': CSRF },
    body: JSON.stringify(payload),
    credentials: 'same-origin'
  });
  const j = await res.json();
  if (!j.ok) { alert('Fehler beim Speichern: '+(j.error||j.hint||'unknown')); return; }
  // success → UI aktualisieren …
}

  </script>


	<?php elseif ($view==='inspector'): ?>
   <style>
    .hh-inspector{--bg:#111;--fg:#e7e7e7;--mut:#a8a8a8;--card:#1a1715;--bd:#2b2826}
    .hh-inspector .grid{display:grid;gap:16px;grid-template-columns:1fr}
    @media(min-width:1100px){.hh-inspector .grid{grid-template-columns:1fr 1fr}}
    .hh-inspector .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:14px}
    .hh-inspector h1{margin:0 0 8px}
    .hh-inspector h2{margin:0 0 8px;font-size:15px;color:#f0f0f0}
    .hh-inspector table{width:100%;border-collapse:collapse}
    .hh-inspector th,.hh-inspector td{padding:8px;border-bottom:1px solid var(--bd);vertical-align:top}
    .hh-inspector tbody tr:hover{background:#201d1b}
    .hh-inspector .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0}
    .hh-inspector .mut{color:var(--mut)} .hh-inspector .small{font-size:12px}
    .hh-inspector button{background:#2a2522;border:1px solid #3a3633;color:#eee;border-radius:10px;padding:8px 10px;cursor:pointer}
    .hh-inspector button:hover{background:#342f2b}
    .hh-inspector pre{white-space:pre-wrap;background:#141210;border:1px solid var(--bd);border-radius:10px;padding:10px;max-height:260px;overflow:auto}
    .hh-inspector .tag{display:inline-block;padding:2px 6px;border-radius:999px;border:1px solid #3a3633;color:#ddd;font-size:12px;margin:0 6px 6px 0}
    .hh-inspector .pill{padding:2px 10px;border-radius:999px;background:#2a2522;color:#ddd;font-size:12px;border:1px solid #3a3633}
    .status{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:10px;border:1px solid #3a3633}
    .status i{width:10px;height:10px;border-radius:999px;display:inline-block}
    .green{background:#1c271c} .green i{background:#27c279}
    .yellow{background:#2a281a} .yellow i{background:#d7c95c}
    .red{background:#2b1a1a} .red i{background:#e86a6a}
    .list{margin:.5rem 0 0 1rem;padding:0} .list li{margin:.25rem 0}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
    .flex-scan{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .flex-scan input{background:#141210;border:1px solid #3a3633;color:#eee;border-radius:10px;padding:8px 10px;min-width:280px}
  </style>

  <div class="hh-inspector admin-wrap">
    <h1>Storage-Inspector</h1>
    <div class="row">
      <button id="hhsi-export">Export JSON</button>
      <span class="mut small">Tipp: Secure/SameSite/Path siehst du in DevTools → Network → <code>Set-Cookie</code>.</span>
    </div>

    <!-- POLICY CHECK -->
    <section class="card">
      <h2>Policy-Check (Ampel)</h2>
      <div class="row flex-scan">
        <span class="mut small">Scanne Seite (gleiches Origin):</span>
        <input id="hhsi-path" type="text" value="/" class="mono" />
        <button id="hhsi-scan">Scannen</button>
        <span id="hhsi-status" class="status green"><i></i><b>Kein Consent nötig</b><span class="mut small"> – nur notwendige Cookies</span></span>
      </div>
      <div id="hhsi-why" class="small" style="margin-top:.5rem"></div>
      <div id="hhsi-hosts" class="small" style="margin-top:.5rem"></div>
    </section>

    <div class="grid">
      <section class="card">
        <h2>Cookies</h2>
        <table id="hhsi-cookie"><thead><tr><th>Name</th><th>Wert (gekürzt)</th><th>Kategorie</th></tr></thead><tbody></tbody></table>
        <div class="small mut">HttpOnly wird indirekt erkannt (Server hat’s, JS nicht). Kategorien sind Heuristik.</div>
      </section>

      <section class="card">
        <h2>localStorage</h2>
        <table id="hhsi-ls"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody></tbody></table>
      </section>

      <section class="card">
        <h2>sessionStorage</h2>
        <table id="hhsi-ss"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody></tbody></table>
      </section>

      <section class="card">
        <h2>IndexedDB</h2>
        <div id="hhsi-idb"><span class="mut small">Prüfe Unterstützung…</span></div>
      </section>

      <section class="card">
        <h2>Cache Storage</h2>
        <div id="hhsi-cache"><span class="mut small">Prüfe Caches…</span></div>
      </section>

      <section class="card">
        <h2>Rohdaten</h2>
        <pre id="hhsi-raw"></pre>
      </section>
    </div>
  </div>

  <script>
    // ---- Server-Cookies (inkl. HttpOnly)
    const HH_SERVER_COOKIES = <?= json_encode($_COOKIE ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const ORIGIN_HOST = location.hostname;

    // Muster für Cookie-Klassifikation
    const CK_NEED   = [/sess/i,/session/i,/csrf/i,/^lang$/i,/pll_?language/i,/^tz$/i];
    const CK_STATS  = [/^_ga/i,/^_gid/i,/^_gat/i,/^_ga_/i,/^_pk_/i,/^_hj/i,/^_cl/i,/^amp/i,/^amplitude/i,/^mp_/,/^matomo/i,/^metrika/i];
    const CK_MARKET = [/^_fbp/i,/^fr$/i,/^_gcl_/i,/^tt(_|$)/i,/^_scid/i,/^_pin/,/^bcookie|lidc|liap/i,/^_uetsid|_uetvid/i];

    const KN_ANA = ['google-analytics.com','googletagmanager.com','stats.g.doubleclick.net','clarity.ms','hotjar.com','matomo','plausible.io'];
    const KN_MKT = ['connect.facebook.net','facebook.net','snapchat.com','analytics.tiktok.com','static.ads-twitter.com','doubleclick.net','adservice.google.com'];
    const KN_EMB = ['youtube.com','youtu.be','ytimg.com','twitch.tv','player.twitch.tv','spotify.com','soundcloud.com','discord.com','jsfiddle.net'];
    const KN_FONT= ['fonts.googleapis.com','fonts.gstatic.com','use.typekit.net','use.fontawesome.com','cdn.jsdelivr.net','unpkg.com'];
    const KN_CAP = ['google.com/recaptcha','gstatic.com/recaptcha'];

    const $ = (s,r=document)=>r.querySelector(s);
    const TBody = id => $('#'+id+' tbody');

    function parseDocCookies(){
      const out={}; const raw=document.cookie||'';
      raw.split(';').forEach(p=>{
        const s=p.trim(); if(!s) return;
        const i=s.indexOf('='); const k=i>=0?s.slice(0,i):s; const v=i>=0?decodeURIComponent(s.slice(i+1)):'';
        out[k]=v;
      });
      return out;
    }
    function short(v,n=80){ v=String(v??''); return v.length>n? v.slice(0,n)+'…' : v; }

    function classifyCookie(name){
      if (CK_NEED.some(rx=>rx.test(name))) return 'notwendig';
      if (CK_MARKET.some(rx=>rx.test(name))) return 'marketing';
      if (CK_STATS.some(rx=>rx.test(name)))  return 'statistik';
      return 'unbekannt';
    }

    function renderCookies(){
      const js = parseDocCookies();
      const names = new Set([...Object.keys(HH_SERVER_COOKIES), ...Object.keys(js)]);
      const rows = [];
      names.forEach(name=>{
        const sv = HH_SERVER_COOKIES[name];
        const jv = js[name];
        const cat = classifyCookie(name);
        rows.push(`<tr><td class="mono">${name}</td><td>${short(jv ?? sv ?? '')}</td><td>${cat}</td></tr>`);
      });
      TBody('hhsi-cookie').innerHTML = rows.join('') || `<tr><td colspan="3" class="mut">Keine Cookies</td></tr>`;
    }

    function renderStorage(kind, id){
      try{
        const s = (kind==='local')? localStorage : sessionStorage;
        const rows=[];
        for (let i=0;i<s.length;i++){ const k=s.key(i); rows.push(`<tr><td class="mono">${k}</td><td>${short(s.getItem(k))}</td></tr>`); }
        TBody(id).innerHTML = rows.join('') || `<tr><td colspan="2" class="mut">leer</td></tr>`;
      }catch(e){ TBody(id).innerHTML = `<tr><td colspan="2" class="mut">nicht verfügbar</td></tr>`; }
    }

    async function renderIDB(){
      const wrap=$('#hhsi-idb');
      if(!('indexedDB' in window)) return wrap.innerHTML='<span class="mut">Nicht unterstützt</span>';
      if(!('databases' in indexedDB)) return wrap.innerHTML='<span class="mut"><code>indexedDB.databases()</code> nicht verfügbar</span>';
      try{
        const dbs = await indexedDB.databases();
        if(!dbs?.length) return wrap.innerHTML='<span class="mut">Keine Datenbanken</span>';
        wrap.innerHTML = dbs.map(d=>`<span class="tag">${(d.name||'(ohne Namen)')} <span class="mut">v${d.version||'-'}</span></span>`).join('');
      }catch(e){ wrap.innerHTML='<span class="mut">Fehler beim Auslesen</span>'; }
    }

    async function renderCaches(){
      const wrap=$('#hhsi-cache');
      if(!('caches' in window)) return wrap.innerHTML='<span class="mut">Nicht unterstützt</span>';
      try{
        const keys = await caches.keys();
        if(!keys.length) return wrap.innerHTML='<span class="mut">Keine Caches</span>';
        const out=[];
        for(const k of keys){ const c=await caches.open(k); const reqs=await c.keys(); out.push(`<span class="tag">${k} <span class="mut">${reqs.length} Einträge</span></span>`); }
        wrap.innerHTML=out.join('');
      }catch(e){ wrap.innerHTML='<span class="mut">Fehler beim Auslesen</span>'; }
    }

    function hostOf(u){ try{ return new URL(u, location.origin).hostname; }catch(e){ return ''; } }

    function analyzeDOM(dom){
      const scripts = Array.from(dom.querySelectorAll('script[src]')).map(s=>s.getAttribute('src'));
      const iframes = Array.from(dom.querySelectorAll('iframe[src]')).map(s=>s.getAttribute('src'));
      const links   = Array.from(dom.querySelectorAll('link[href]')).map(l=>l.getAttribute('href'));
      const allUrls = [...scripts, ...iframes, ...links].filter(Boolean);
      const hosts = Array.from(new Set(allUrls.map(hostOf).filter(Boolean)));

      const third = hosts.filter(h=>h && h!==ORIGIN_HOST);
      const groups = {
        analytics: third.filter(h=>['google-analytics.com','googletagmanager.com','stats.g.doubleclick.net','clarity.ms','hotjar.com','matomo','plausible.io'].some(k=>h.includes(k))),
        marketing: third.filter(h=>['connect.facebook.net','facebook.net','snapchat.com','analytics.tiktok.com','static.ads-twitter.com','doubleclick.net','adservice.google.com'].some(k=>h.includes(k))),
        embeds:    third.filter(h=>['youtube.com','youtu.be','ytimg.com','twitch.tv','player.twitch.tv','spotify.com','soundcloud.com','discord.com','jsfiddle.net'].some(k=>h.includes(k))),
        fonts:     third.filter(h=>['fonts.googleapis.com','fonts.gstatic.com','use.typekit.net','use.fontawesome.com','cdn.jsdelivr.net','unpkg.com'].some(k=>h.includes(k))),
        captcha:   third.filter(h=>['google.com/recaptcha','gstatic.com/recaptcha'].some(k=>h.includes(k))),
        other:     third.filter(h=>!( ['google-analytics.com','googletagmanager.com','stats.g.doubleclick.net','clarity.ms','hotjar.com','matomo','plausible.io','connect.facebook.net','facebook.net','snapchat.com','analytics.tiktok.com','static.ads-twitter.com','doubleclick.net','adservice.google.com','youtube.com','youtu.be','ytimg.com','twitch.tv','player.twitch.tv','spotify.com','soundcloud.com','discord.com','jsfiddle.net','fonts.googleapis.com','fonts.gstatic.com','use.typekit.net','use.fontawesome.com','cdn.jsdelivr.net','unpkg.com','google.com/recaptcha','gstatic.com/recaptcha'].some(k=>h.includes(k))))
      };

      groups.autoEmbeds = Array.from(dom.querySelectorAll('iframe[src]')).map(x=>x.getAttribute('src')).filter(u=>u && ['youtube.com','youtu.be','twitch.tv','player.twitch.tv','spotify.com','soundcloud.com','discord.com'].some(k=>u.includes(k)));
      return groups;
    }

    function classifyAllCookieNames(){
      const all = new Set(Object.keys(HH_SERVER_COOKIES).concat(Object.keys(parseDocCookies())));
      const res = {notwendig:[], statistik:[], marketing:[], unbekannt:[]};
      all.forEach(n => res[classifyCookie(n)].push(n));
      return res;
    }

    function evaluatePolicy(groups){
      const cookieCats = classifyAllCookieNames();
      const reasons = [];
      if (cookieCats.marketing.length || groups.marketing.length) reasons.push('Marketing-Tracker erkannt (Cookies oder externe Hosts).');
      if (cookieCats.statistik.length || groups.analytics.length) reasons.push('Analytics mit Cookies/externen Hosts erkannt.');
      if (groups.captcha.length) reasons.push('reCAPTCHA eingebunden – gilt meist als zustimmungspflichtig.');
      if (groups.autoEmbeds.length) reasons.push('Externe Media-Embeds werden automatisch geladen (YouTube/Twitch/…).');
      const consent = reasons.length > 0;
      let color = 'green';
      if (consent) color = 'red'; else if (groups.fonts.length || groups.other.length) color = 'yellow';
      return {consent, color, reasons, cookieCats};
    }

    function paintStatus({consent, color, reasons, cookieCats}, groups){
      const box = document.querySelector('#hhsi-status');
      box.className = 'status '+color;
      if (!consent) box.innerHTML = '<i></i><b>Kein Consent nötig</b><span class="mut small"> – nur notwendige Cookies</span>';
      else box.innerHTML = '<i></i><b>Consent nötig</b><span class="mut small"> – siehe Gründe unten</span>';

      const why = document.querySelector('#hhsi-why');
      const li = [];
      if (reasons.length) li.push(...reasons.map(r=>`<li>${r}</li>`));
      if (cookieCats.marketing.length) li.push(`<li>Marketing-Cookies: <span class="mono">${cookieCats.marketing.join(', ')}</span></li>`);
      if (cookieCats.statistik.length) li.push(`<li>Statistik-Cookies: <span class="mono">${cookieCats.statistik.join(', ')}</span></li>`);
      if (!li.length) li.push('<li>Keine auffälligen Cookies/Hosts erkannt.</li>');
      why.innerHTML = `<ul class="list">${li.join('')}</ul>`;

      const hosts = document.querySelector('#hhsi-hosts');
      const mk = x => x.length? x.map(h=>`<span class="tag">${h}</span>`).join('') : '<span class="mut">–</span>';
      hosts.innerHTML = `
        <div class="row small"><b>Analytics-Hosts:</b> ${mk(groups.analytics)}</div>
        <div class="row small"><b>Marketing-Hosts:</b> ${mk(groups.marketing)}</div>
        <div class="row small"><b>Media-Embeds:</b> ${mk(groups.embeds)}</div>
        <div class="row small"><b>reCAPTCHA:</b> ${mk(groups.captcha)}</div>
        <div class="row small"><b>Externe Fonts/CDNs:</b> ${mk(groups.fonts)}</div>
        <div class="row small"><b>Weitere Dritthosts:</b> ${mk(groups.other)}</div>
      `;
    }

    async function scanPath(path){
      try{
        const res = await fetch(path, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
        const html = await res.text();
        const dom = new DOMParser().parseFromString(html, 'text/html');
        const groups = analyzeDOM(dom);
        const policy = evaluatePolicy(groups);
        paintStatus(policy, groups);
        return {groups, policy};
      }catch(e){
        document.querySelector('#hhsi-why').innerHTML = `<span class="mut small">Scan fehlgeschlagen (${String(e)}) – Seite vielleicht nicht erreichbar.</span>`;
        return {groups:{analytics:[],marketing:[],embeds:[],fonts:[],captcha:[],other:[],autoEmbeds:[]}, policy:{consent:false,color:'yellow',reasons:['Scan fehlgeschlagen'],cookieCats:{}}}
      }
    }

    function renderRaw(){
      const data = {
        serverCookies: HH_SERVER_COOKIES,
        documentCookies: parseDocCookies(),
        localStorage: Object.fromEntries(Object.keys(localStorage||{}).map(k=>[k, localStorage.getItem(k)])),
        sessionStorage: Object.fromEntries(Object.keys(sessionStorage||{}).map(k=>[k, sessionStorage.getItem(k)])),
        time: new Date().toISOString()
      };
      document.querySelector('#hhsi-raw').textContent = JSON.stringify(data, null, 2);
      return data;
    }

    function exportJSON(storageDump, scan){
      const payload = { storage: storageDump, scan: scan || null };
      const blob = new Blob([JSON.stringify(payload, null, 2)], {type:'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href=url; a.download='hunthub-storage-report.json';
      document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }

    // ---- INIT
    renderCookies();
    renderStorage('local','hhsi-ls');
    renderStorage('session','hhsi-ss');
    renderIDB();
    renderCaches();
    let storageDump = renderRaw();

    // Ersten Scan auf "/"
    let lastScan = null;
    scanPath('/').then(r => { lastScan = r; });

    document.querySelector('#hhsi-scan').addEventListener('click', async ()=>{
      const path = document.querySelector('#hhsi-path').value || '/';
      lastScan = await scanPath(path);
    });

    document.querySelector('#hhsi-export').addEventListener('click', ()=>{
      exportJSON(storageDump, lastScan);
    });
  </script>
  <?php elseif ($view === 'logs'): ?>
<section class="admin-card">
  <h2>System-Logs</h2>
<div class="controls" style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.5rem;">
  <label>Zeilen:
    <select id="logLines">
      <option value="200">200</option>
      <option value="500" selected>500</option>
      <option value="1000">1000</option>
      <option value="2000">2000</option>
      <option value="5000">5000</option>
    </select>
  </label>

  <label>Level:
    <select id="logLevel">
      <option value="">(alle)</option>
      <option value="[INFO]">INFO</option>
      <option value="[PHP]">PHP</option>
      <option value="[WARNING]">WARNING</option>
      <option value="[ERROR]">ERROR</option>
      <option value="[EXCEPTION]">EXCEPTION</option>
      <option value="[FATAL]">FATAL</option>
    </select>
  </label>

  <input id="logSearch" type="text" placeholder="Suche (z. B. signup, file_put_contents…)" style="min-width:280px">

  <label style="display:flex;align-items:center;gap:.35rem;"><input type="checkbox" id="logRegex"> RegEx</label>
  <label style="display:flex;align-items:center;gap:.35rem;"><input type="checkbox" id="logCase"> Groß/Klein</label>
  <label style="display:flex;align-items:center;gap:.35rem;"><input type="checkbox" id="logInvert"> invertieren</label>

  <button id="btnRefresh" type="button">Aktualisieren</button>
  <label style="display:flex;align-items:center;gap:.35rem;">
    <input type="checkbox" id="autoRefresh" checked> Auto-Refresh (10s)
  </label>
  <a id="btnDownload" class="button" href="<?= $APP_BASE ?>/api/admin/logs.php?download=1">Download</a>
  <button id="btnClear" type="button" style="margin-left:auto;background:#7a1c1c;color:#fff;">Log leeren</button>
</div>

  <pre id="logBox" style="max-height:60vh; overflow:auto; background:#0b0b0b; color:#cbd5e1; padding:1rem; border:1px solid #222; border-radius:8px;"></pre>
</section>

<script>
(function(){
  const BASE = "<?= $APP_BASE ?>";
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const box  = document.getElementById('logBox');
  const sel  = document.getElementById('logLines');
  const btnR = document.getElementById('btnRefresh');
  const chkA = document.getElementById('autoRefresh');
  const btnC = document.getElementById('btnClear');
  const btnD = document.getElementById('btnDownload');

  let timer = null;

  const lvlSel = document.getElementById('logLevel');
  const qInp   = document.getElementById('logSearch');
  const reChk  = document.getElementById('logRegex');
  const csChk  = document.getElementById('logCase');
  const invChk = document.getElementById('logInvert');

  function filterLog(raw) {
    const level = (lvlSel.value || '').trim();
    const q     = qInp.value.trim();

    // Schnellpfad: nichts zu filtern
    if (!level && !q) return raw;

    const flags = csChk.checked ? '' : 'i';
    let rx = null;
    if (q) {
      rx = reChk.checked
        ? new RegExp(q, flags)
        : new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), flags); // escape
    }

    const out = [];
    for (const line of raw.split('\n')) {
      if (level && !line.includes(level)) continue;
      let hit = true;
      if (rx) hit = rx.test(line);
      if (invChk.checked) hit = !hit;
      if (hit) out.push(line);
    }
    return out.join('\n');
  }

async function loadLog() {
  const lines = sel.value || 500;
  try {
    const res  = await fetch(`${BASE}/api/admin/logs.php?lines=${encodeURIComponent(lines)}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });

    let data = null;
    try {
      data = await res.json();
    } catch (e) {
      const text = await res.text();
      box.textContent = 'Antwort ist kein JSON:\n\n' + text;
      return;
    }

    if (!res.ok || !data || data.ok !== true) {
      box.textContent = 'Fehler:\n' + JSON.stringify(data || {}, null, 2);
      return;
    }

    const meta = [
      `Pfad: ${data.path}`,
      `existiert: ${data.exists}`,
      `schreibbar: ${data.writable}`,
      `Größe: ${data.size} Bytes`
    ].join(" | ");

    box.textContent = meta + "\n\n" + (data.log || '');
    box.scrollTop = box.scrollHeight;
  } catch (e) {
    box.textContent = 'Fetch-Fehler: ' + e;
  }
}


  // Filter-Events → sofort neu rendern (ohne Serverhit, wenn bereits Text da)
  function rerenderOnly() {
    const content = box.textContent || '';
    const i = content.indexOf('\n\n');
    if (i >= 0) {
      const meta = content.slice(0, i);
      const raw  = content.slice(i + 2);
      box.textContent = meta + "\n\n" + filterLog(raw);
    }
  }

  [lvlSel, qInp, reChk, csChk, invChk].forEach(el => {
    el?.addEventListener(el.type === 'text' ? 'input' : 'change', () => {
      // wenn Auto-Refresh läuft, sofort filtern; Daten holen wir eh periodisch
      rerenderOnly();
    });
  });




  function startAuto() {
    stopAuto();
    timer = setInterval(loadLog, 10000);
  }
  function stopAuto() {
    if (timer) { clearInterval(timer); timer = null; }
  }

  btnR?.addEventListener('click', loadLog);
  sel?.addEventListener('change', loadLog);
  chkA?.addEventListener('change', () => chkA.checked ? startAuto() : stopAuto());

  btnC?.addEventListener('click', async () => {
    if (!confirm('Log wirklich leeren?')) return;
    try {
      const res = await fetch(`${BASE}/api/admin/logs.php`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF, 'Accept':'application/json' },
        body: JSON.stringify({ action: 'clear' })
      });
      const data = await res.json();
      if (data && data.ok) {
        box.textContent = '';
      } else {
        alert('Fehler: ' + (data?.error || 'unknown'));
      }
    } catch (e) {
      alert('Fetch-Fehler: ' + e);
    }
  });

  // Download-Link sorgt für Dateiname – nix zu tun
  btnD?.addEventListener('click', () => { /* no-op */ });

  // initial
  loadLog();
  startAuto();

  // wenn Tab verlassen wird
  window.addEventListener('beforeunload', stopAuto);
})();
</script>


  <?php elseif ($view==='users'): ?>
    <div class="admin-card">
      <form id="userSearch" onsubmit="return false" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <input type="text" id="q" placeholder="Suche Name/Email/ID…" style="flex:1;min-width:240px">
        <button class="btn" onclick="loadUsers()">Suchen</button>
      </form>
    </div>
    <div class="admin-card">
      <table id="usersTable"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Aktionen</th></tr></thead><tbody></tbody></table>
    </div>
	<!-- User-Edit Modal -->
<div id="userEditModal" style="display:none;position:fixed;inset:0;backdrop-filter:blur(2px);background:rgba(0,0,0,.55);z-index:9999;">
  <div style="max-width:680px;margin:6vh auto;background:#0e0e0e;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem">
    <h3 style="margin:0 0 .75rem 0">User bearbeiten</h3>
    <div class="grid" style="grid-template-columns:1fr 1fr;gap:.75rem">
      <div>
        <label>User-ID</label>
        <input id="ue_id" type="number" disabled>
      </div>
      <div>
        <label>Rolle</label>
        <select id="ue_role">
          <option value="user">user</option>
          <option value="admin">admin</option>
        </select>
      </div>

      <div style="grid-column:1/-1">
        <label>Anzeigename</label>
        <input id="ue_display_name" type="text" placeholder="Display Name">
      </div>

      <div style="grid-column:1/-1">
        <label>E-Mail (read-only)</label>
        <input id="ue_email" type="text" disabled>
      </div>

      <div style="grid-column:1/-1;display:flex;align-items:center;gap:.5rem">
        <label style="display:flex;align-items:center;gap:.5rem;margin:0">
          <input id="ue_is_twitch" type="checkbox"> Twitch-Streamer
        </label>
        <input id="ue_twitch_url" type="url" placeholder="https://twitch.tv/username" style="flex:1">
      </div>
    </div>

    <div style="margin-top:1rem;display:flex;gap:.5rem;justify-content:flex-end">
      <button class="btn ok" onclick="saveUserEdit()">Speichern</button>
      <button class="btn" onclick="closeUserEdit()">Abbrechen</button>
    </div>
  </div>
</div>
<?php elseif ($view==='forum'): ?>
  <div class="admin-card">
    <h3 style="margin:0 0 .75rem 0">Threads verwalten</h3>
    <div class="grid" style="grid-template-columns: 2fr 1fr 140px; align-items:end;">
      <div>
        <label>Suche (Titel/Slug)</label>
        <input id="thr_q" type="text" placeholder="z. B. bug, willkommen">
      </div>
      <div>
        <label>Board</label>
        <select id="thr_board"><option value="0">Alle</option></select>
      </div>
      <div><button class="btn" onclick="forumThreadsLoad()">Neu laden</button></div>
    </div>
  </div>

  <div class="admin-card">
    <table id="thrTable">
      <thead>
        <tr>
          <th>ID</th><th>Board</th><th>Titel</th><th>Slug</th>
          <th>Autor</th><th>Posts</th><th>Locked</th><th>Pinned</th>
          <th>Erstellt</th><th>Aktion</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div style="margin-top:.75rem;display:flex;gap:.5rem">
      <button class="btn" onclick="forumPrev()">« Zurück</button>
      <button class="btn" onclick="forumNext()">Weiter »</button>
      <span class="muted" id="thrInfo" style="align-self:center"></span>
    </div>
  </div>

  <!-- Edit-Modal -->
  <div id="thrModal" style="display:none;position:fixed;inset:0;backdrop-filter:blur(2px);background:rgba(0,0,0,.55);z-index:9999;">
    <div style="max-width:640px;margin:5vh auto;background:#0e0e0e;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem">
      <h3 style="margin-top:0">Thread bearbeiten</h3>
      <div class="grid" style="grid-template-columns:1fr 1fr">
        <div>
          <label>Thread-ID</label>
          <input id="thr_id" type="number" disabled>
        </div>
        <div>
          <label>Board</label>
          <select id="thr_edit_board"></select>
        </div>
        <div style="grid-column:1/-1">
          <label>Titel</label>
          <input id="thr_title" type="text">
        </div>
        <div style="grid-column:1/-1">
          <label>Slug</label>
          <input id="thr_slug" type="text" placeholder="optional – leer lassen um nicht zu ändern">
        </div>
        <div>
          <label>Locked</label>
          <select id="thr_locked"><option value="0">0</option><option value="1">1</option></select>
        </div>
        <div>
          <label>Pinned</label>
          <select id="thr_pinned"><option value="0">0</option><option value="1">1</option></select>
        </div>
      </div>
      <div style="margin-top:1rem;display:flex;gap:.5rem">
        <button class="btn ok" onclick="forumThreadSave()">Speichern</button>
        <button class="btn" onclick="forumModalClose()">Abbrechen</button>
      </div>
    </div>
  </div>

<!-- Posts-Modal -->
<div id="postModal" style="display:none;position:fixed;inset:0;backdrop-filter:blur(2px);background:rgba(0,0,0,.55);z-index:9999;">
  <div style="max-width:900px;margin:4vh auto;background:#0e0e0e;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem">
    <h3 style="margin-top:0">Posts in Thread <span id="pm_thread_id"></span></h3>
    <div class="grid" style="grid-template-columns: 1fr 140px 140px; align-items:end; gap:.5rem">
      <div><label>Suche</label><input id="pm_q" type="text" placeholder="Textsuche"></div>
      <div><button class="btn" onclick="forumPostsPrev()">« Zurück</button></div>
      <div><button class="btn" onclick="forumPostsNext()">Weiter »</button></div>
    </div>

    <div class="admin-card" style="margin-top:.75rem">
      <table id="pm_table"><thead>
        <tr><th>ID</th><th>Autor</th><th>Inhalt</th><th>Erstellt</th><th>Aktion</th></tr>
      </thead><tbody></tbody></table>
      <div class="muted" id="pm_info" style="margin-top:.5rem"></div>
    </div>

    <div class="admin-card">
      <h4 style="margin:0 0 .5rem 0">Post bearbeiten</h4>
      <input type="hidden" id="pm_edit_id">
      <textarea id="pm_edit_content" style="min-height:120px"></textarea>
      <div style="margin-top:.5rem;display:flex;gap:.5rem">
        <button class="btn ok" onclick="forumPostSave()">Speichern</button>
        <button class="btn danger" onclick="forumPostDelete()">Löschen</button>
        <button class="btn" onclick="forumPostsClose()">Schließen</button>
      </div>
    </div>
  </div>
</div>

  <?php elseif ($view==='tournaments'): ?>
  
    <div class="admin-card" id="admin-tournaments-create">
      <h2>Neues Turnier anlegen</h2>
      <form id="tourCreateForm" class="grid" style="grid-template-columns:1fr 1fr;gap:.75rem" method="post" action="<?= $APP_BASE ?>/api/tournaments/create.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">

        <label class="col-span-2">Name
          <input class="box-input-3" name="name" required placeholder="Bayou Blitz" />
        </label>

        <label>Format
          <select class="input" name="format">
            <option value="points" selected>Punkte‑Rennen</option>
            <option value="ko">K.O. (v2)</option>
          </select>
        </label>

        <label>Teamgröße
          <select class="input" name="team_size">
            <option value="1">Solo</option>
            <option value="2">Duo</option>
            <option value="3" selected>Trio</option>
          </select>
        </label>

        <label>Plattform
          <select class="input" name="platform">
            <option value="mixed" selected>Gemischt</option>
            <option value="pc">PC</option>
            <option value="xbox">Xbox</option>
            <option value="ps">PlayStation</option>
          </select>
        </label>

        <label>Top‑Runs (Best of N)
          <input class="input" type="number" name="best_runs" min="1" max="5" value="3" />
        </label>

        <label>Max. Teams (optional)
          <input class="input" type="number" name="max_teams" min="2" step="1" placeholder="z. B. 64" />
        </label>

        <label>Start
          <input class="input" type="datetime-local" name="starts_at_local" required />
        </label>

        <label>Ende
          <input class="input" type="datetime-local" name="ends_at_local" required />
        </label>

        <label class="col-span-2">Regeln
          <textarea class="input" name="rules_text" rows="5" placeholder="Punktesystem, Upload‑Vorgaben, Fair‑Play …"></textarea>
        </label>

        <label class="col-span-2">Preise (optional)
          <textarea class="input" name="prizes_text" rows="3" placeholder="Ruhm & Ehre …"></textarea>
        </label>

        <label class="col-span-2">Scoring JSON
          <textarea class="input" name="scoring_json" rows="3">{"kill":1,"boss":3,"token":5,"gauntlet":10,"death":-5}</textarea>
        </label>

        <div class="col-span-2 flex" style="gap:.5rem">
          <button class="btn" type="submit">Turnier erstellen</button>
          <button class="btn" type="button" id="btnCreateAndOpen">Erstellen & Öffnen</button>
        </div>
      </form>
      <p id="tourCreateMsg" class="mt-2 text-sm muted"></p>
    </div>

    <?php
      // Übersicht Turniere
      $tSt = $pdo->query('SELECT id,name,status,platform,team_size,format,starts_at,ends_at FROM tournaments ORDER BY starts_at DESC LIMIT 50');
      $tournaments = $tSt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="admin-card">
      <h2>Turniere</h2>
      <table>
        <thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Plattform</th><th>Team</th><th>Format</th><th>Start</th><th>Ende</th><th>Link</th></tr></thead>
        <tbody>
          <?php if (!$tournaments): ?>
            <tr><td colspan="9" class="muted">Keine Turniere</td></tr>
          <?php else: foreach ($tournaments as $t): ?>
            <tr>
              <td>#<?= (int)$t['id'] ?></td>
              <td><?= htmlspecialchars($t['name']) ?></td>
              <td><span class="badge"><?= htmlspecialchars($t['status']) ?></span></td>
              <td><?= htmlspecialchars($t['platform']) ?></td>
              <td><?= (int)$t['team_size'] ?></td>
              <td><?= htmlspecialchars($t['format']) ?></td>
              <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($t['starts_at']))) ?></td>
              <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($t['ends_at']))) ?></td>
              <td><a class="btn" href="<?= $APP_BASE ?>/tournaments/view.php?id=<?= (int)$t['id'] ?>" target="_blank" rel="noreferrer noopener">öffnen</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

<?php
  // Moderations-Queue für Runs (robust: ENUM/INT-Status, optionale Stat-Spalten)
  $trCols = [];
  foreach ($pdo->query('SHOW COLUMNS FROM tournament_runs', PDO::FETCH_ASSOC) as $c) {
    $trCols[$c['Field']] = $c;
  }
  $has = fn(string $n) => isset($trCols[$n]);

  // Felder dynamisch wählen (je nach Schema vorhanden)
  $pointsSel  = $has('raw_points')        ? 'r.raw_points'
             : ($has('points_total')      ? 'r.points_total'
             : ($has('points')            ? 'r.points' : '0'));
  $shotSel    = $has('screenshot_rel')    ? 'r.screenshot_rel'
             : ($has('screenshot_path')   ? 'r.screenshot_path'
             : ($has('screenshot')        ? 'r.screenshot'
             : ($has('image_path')        ? 'r.image_path' : 'NULL')));
  $killsSel   = $has('kills')             ? 'r.kills' : 'NULL';
  $bossSel    = $has('bosses')            ? 'r.bosses'
             : ($has('boss_kills')        ? 'r.boss_kills' : 'NULL');
  $tokensSel  = $has('tokens')            ? 'r.tokens'
             : ($has('token')             ? 'r.token'
             : ($has('tokens_extracted')  ? 'r.tokens_extracted' : 'NULL'));
  $gaunSel    = $has('gauntlet')          ? 'r.gauntlet' : 'NULL';
  $deathsSel  = $has('deaths')            ? 'r.deaths'
             : ($has('deads')             ? 'r.deads' : 'NULL');
  $createdSel = $has('created_at')        ? 'r.created_at'
             : ($has('created')           ? 'r.created'
             : ($has('created_on')        ? 'r.created_on' : 'NOW()'));
  $statusSel  = $has('status')            ? 'r.status' : "'pending'";

  // Offene Einreichungen: ENUM (submitted/flagged/pending) ODER INT (0=pending, 3=flagged)
  $where = '1=1';
  if ($has('status')) {
    $type = strtolower((string)($trCols['status']['Type'] ?? ''));
    if (str_contains($type, 'int')) {
      $where = 'r.status IN (0,3)';
    } else {
      $where = "r.status IN ('submitted','flagged','pending')";
    }
  }

  $sql = "SELECT
            r.id, r.tournament_id, r.tournament_team_id,
            $pointsSel   AS raw_points,
            $shotSel     AS screenshot_path,
            $statusSel   AS status,
            $createdSel  AS created_at,
            $killsSel    AS kills,
            $bossSel     AS boss_kills,
            $tokensSel   AS tokens,
            $gaunSel     AS gauntlet,
            $deathsSel   AS deaths,
            tt.name      AS team_name,
            t.name       AS tournament_name
          FROM tournament_runs r
          JOIN tournament_teams tt ON tt.id = r.tournament_team_id
          JOIN tournaments     t  ON t.id  = r.tournament_id
          WHERE $where
          ORDER BY $createdSel ASC
          LIMIT 200";

  $rSt = $pdo->prepare($sql);
  $rSt->execute();
  $runs = $rSt->fetchAll(PDO::FETCH_ASSOC);
?>
    <div class="admin-card">
      <h2>Runs – Moderation</h2>
      <table>
  <thead>
  <tr>
    <th>ID</th><th>Team</th><th>Turnier</th>
    <th>Kills</th><th>Boss</th><th>Token</th><th>Deaths</th><th>Gauntlet</th>
    <th>Punkte</th><th>Screenshot</th><th>Status</th><th>Aktion</th>
  </tr>
</thead>
        <tbody>
        <?php if (!$runs): ?>
          <tr><td colspan="7" class="muted">Keine offenen Einreichungen</td></tr>
        <?php else: foreach ($runs as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['team_name']) ?></td>
            <td><?= htmlspecialchars($r['tournament_name']) ?></td>
			<td><?= (int)$r['kills'] ?></td>
<td><?= (int)$r['boss_kills'] ?></td>
<td><?= (int)$r['tokens_extracted'] ?></td>
<td><?= (int)$r['deaths'] ?></td>
<td><?= (int)$r['gauntlet'] ?></td>
            <td><b><?= (int)$r['raw_points'] ?></b></td>
            <td><?php if(!empty($r['screenshot_path'])): ?><a href="<?= htmlspecialchars($r['screenshot_path']) ?>" target="_blank">öffnen</a><?php endif; ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td>
              <form method="post" action="<?= $APP_BASE ?>/api/tournaments/approve_run.php" class="inline" style="display:inline-block">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>">
                <button class="btn ok">Approve</button>
              </form>
              <form method="post" action="<?= $APP_BASE ?>/api/tournaments/reject_run.php" class="inline" style="display:inline-block">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>">
                <input type="text" name="reason" placeholder="Grund" class="input" style="width:220px" required>
                <button class="btn danger">Reject</button>
              </form>
              <form method="post" action="<?= $APP_BASE ?>/api/tournaments/flag_run.php" class="inline" style="display:inline-block">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>">
                <button class="btn">Flag</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  <?php
/*
Ersetze in deiner admin.php innerhalb des Views $view==='tournaments' NUR den Block

    // Moderations-Queue für Runs
    $rSt = $pdo->query("SELECT ... LIMIT 200");
    $runs = $rSt->fetchAll(...);
    <div class="admin-card"> ... Tabelle ... </div>

mit folgendem Drop‑in. Der Code erkennt die Spalten in tournament_runs automatisch
(raw_points/points_total/points, screenshot_rel/screenshot_path/..., kills/bosses/tokens/gauntlet/deaths, ENUM- vs INT-Status) und
zeigt die Werte in der Tabelle an.
*/
?>
<?php
  // ---- Moderations‑Queue für Runs (mit Kills/Boss/Token/Gauntlet/Deaths) ----
  // Spalten der tournament_runs-Tabelle erkennen
  $trCols = [];
  foreach ($pdo->query('SHOW COLUMNS FROM tournament_runs', PDO::FETCH_ASSOC) as $c) {
    $trCols[$c['Field']] = $c;
  }
  // Helfer, ob Spalte existiert
  $has = fn(string $name) => array_key_exists($name, $trCols);

  // Selektoren pro Feld
  $pointsSel   = $has('raw_points')   ? 'r.raw_points'   : ($has('points_total') ? 'r.points_total' : ($has('points') ? 'r.points' : '0'));
  $shotSel     = $has('screenshot_rel') ? 'r.screenshot_rel' : ($has('screenshot_path') ? 'r.screenshot_path' : ($has('screenshot') ? 'r.screenshot' : ($has('image_path') ? 'r.image_path' : "NULL")));
  $killsSel    = $has('kills')      ? 'r.kills'      : 'NULL';
  $bossSel     = $has('bosses')     ? 'r.bosses'     : ($has('boss_kills') ? 'r.boss_kills' : 'NULL');
  $tokenSel    = $has('tokens')     ? 'r.tokens'     : ($has('token') ? 'r.token' : 'NULL');
  $gauntSel    = $has('gauntlet')   ? 'r.gauntlet'   : 'NULL';
  $deathSel    = $has('deaths')     ? 'r.deaths'     : ($has('deads') ? 'r.deads' : 'NULL');
  $createdSel  = $has('created_at') ? 'r.created_at' : ($has('created') ? 'r.created' : ($has('created_on') ? 'r.created_on' : 'NULL'));
  $statusSel   = $has('status')     ? 'r.status'     : "'pending'";

  // Offene Items filtern (submitted/flagged/pending bzw. 0/3)
  $where = '1=1';
  if ($has('status')) {
    $type = strtolower((string)($trCols['status']['Type'] ?? ''));
    if (str_contains($type, 'int')) {
      // 0=pending, 3=flagged (übliche Belegung)
      $where = 'r.status IN (0,3)';
    } else {
      $where = "r.status IN ('submitted','flagged','pending')";
    }
  }

  $sql = "SELECT r.id, r.tournament_id, r.tournament_team_id,
                 $pointsSel   AS pts,
                 $shotSel     AS shot,
                 $killsSel    AS kills,
                 $bossSel     AS bosses,
                 $tokenSel    AS tokens,
                 $gauntSel    AS gauntlet,
                 $deathSel    AS deaths,
                 $statusSel   AS status,
                 $createdSel  AS created_at,
                 tt.name      AS team_name,
                 t.name       AS tournament_name
          FROM tournament_runs r
          JOIN tournament_teams tt ON tt.id = r.tournament_team_id
          JOIN tournaments     t  ON t.id  = r.tournament_id
          WHERE $where
          ORDER BY r.id DESC
          LIMIT 200";
  $rSt  = $pdo->prepare($sql);
  $rSt->execute();
  $runs = $rSt->fetchAll(PDO::FETCH_ASSOC);
?>

    <script>
    (function(){
      const BASE = "<?= $APP_BASE ?>";
      const csrf = document.querySelector('meta[name="csrf"]').content || '';
      const f = document.getElementById('tourCreateForm');
      const msg = document.getElementById('tourCreateMsg');
      const btnOpen = document.getElementById('btnCreateAndOpen');

      function toMySql(dt){
        if(!dt) return '';
        const s = String(dt);
        return s.replace('T',' ') + (s.length === 16 ? ':00' : '');
      }

async function createTournament(openAfter){
msg.textContent = 'Speichere …';
const data = new FormData(f);
// CSRF aus <meta> sicher eintragen
const metaCsrf = document.querySelector('meta[name="csrf"]').content || '';
if (!data.get('csrf')) data.set('csrf', metaCsrf);
// datetime-local → MySQL
const toMySql = s => s ? (String(s).replace('T',' ') + (String(s).length===16?':00':'')) : '';
data.set('starts_at', toMySql(data.get('starts_at_local')));
data.set('ends_at', toMySql(data.get('ends_at_local')));
data.delete('starts_at_local');
data.delete('ends_at_local');


try {
const res = await fetch(f.action, { method:'POST', body:data });
const text = await res.text();
let j = null; try { j = JSON.parse(text); } catch(_) {}


if (!res.ok || !j) {
msg.textContent = `Fehler (HTTP ${res.status}) – ${text.slice(0,300)}`;
return;
}
if (!j.ok) { msg.textContent = `Fehler: ${j.error||'unknown'}`; return; }


msg.textContent = 'Angelegt (#'+j.id+').';
if (openAfter) {
const fd = new FormData();
fd.set('csrf', metaCsrf);
fd.set('id', j.id);
fd.set('status', 'open');
await fetch(`${BASE}/api/tournaments/update_status.php`, { method:'POST', body:fd });
}
window.location.href = `${BASE}/tournaments/view.php?id=${j.id}`;
} catch(e) {
msg.textContent = 'Netzwerkfehler: ' + (e && e.message ? e.message : String(e));
}
}

      if (f) f.addEventListener('submit', function(e){ e.preventDefault(); createTournament(false); });
      if (btnOpen) btnOpen.addEventListener('click', function(){ createTournament(true); });
    })();
    </script>

  <?php elseif ($view==='uploads'): ?>
    <div class="admin-card">
      <button class="btn" onclick="listUploads('images')">Bilder auflisten</button>
      <button class="btn" onclick="listUploads('videos')">Videos auflisten</button>
    </div>
    <div class="admin-card">
<table id="uploadsTable">
  <thead>
    <tr><th>Preview</th><th>Typ</th><th>Pfad</th><th>Thema</th><th>Size</th><th>Aktionen</th></tr>
  </thead>
  <tbody></tbody>
</table>
    </div>

  <?php elseif ($view==='cache'): ?>
    <div class="admin-card">
      <button class="btn danger" onclick="clearLinkPreviews()">Link-Preview Cache leeren</button>
    </div>

  <?php elseif ($view==='health'): ?>
    <div class="admin-card" id="healthBox">Health wird geladen…</div>

  <?php elseif ($view==='notifications'): ?>
    <div class="admin-card">
      <h3 style="margin:0 0 .5rem 0">Broadcast senden</h3>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:.75rem">
        <div><label>Titel</label><input id="bc_title" type="text"></div>
        <div><label>Level</label>
          <select id="bc_level"><option>info</option><option>warn</option><option>critical</option></select>
        </div>
      </div>
      <div style="margin-top:.5rem"><label>Body</label><textarea id="bc_body"></textarea></div>
      <div style="margin-top:.5rem" class="grid" style="grid-template-columns:1fr 200px">
        <div><label>Link (optional)</label><input id="bc_link" type="text" placeholder="https://…"></div>
        <div><label>&nbsp;</label><label style="display:flex;align-items:center;gap:.5rem"><input id="bc_fan" type="checkbox"> Fan-Out in notifications</label></div>
      </div>
      <div style="margin-top:.75rem"><button class="btn ok" onclick="broadcastSend()">Senden</button></div>
    </div>
    <div class="admin-card">
      <h3 style="margin:0 0 .5rem 0">Letzte Broadcasts</h3>
      <table id="bcTable"><thead><tr><th>ID</th><th>Titel</th><th>Level</th><th>Body (Preview)</th><th>Link</th><th>Active</th><th>Aktion</th></tr></thead><tbody></tbody></table>
      <div style="margin-top:.75rem"><button class="btn" onclick="broadcastList()">Neu laden</button></div>
    </div>

  <?php elseif ($view==='gamification'): ?>
    <div class="admin-card">
      <h3 style="margin:0 0 .5rem 0">Achievement anlegen/ändern</h3>
      <div class="grid" style="grid-template-columns: repeat(2, minmax(0,1fr));">
        <div><label>ID (leer = neu)</label><input id="ach_id" type="number"></div>
        <div><label>Key</label><input id="ach_key" type="text" placeholder="z.B. posts_10"></div>
        <div><label>Titel</label><input id="ach_title" type="text"></div>
        <div><label>Icon</label><input id="ach_icon" type="text" placeholder="/assets/images/achievements/x.png"></div>
        <div><label>Event</label><input id="ach_event" type="text" placeholder="z.B. post_created"></div>
        <div><label>Stat</label><input id="ach_stat" type="text" placeholder="z.B. posts_count"></div>
        <div><label>Threshold</label><input id="ach_thr" type="number" value="1"></div>
        <div><label>Punkte</label><input id="ach_pts" type="number" value="0"></div>
        <div><label>Aktiv</label><select id="ach_active"><option value="1">1</option><option value="0">0</option></select></div>
        <div style="grid-column:1/-1"><label>Beschreibung</label><input id="ach_desc" type="text"></div>
      </div>
      <div style="margin-top:.75rem"><button class="btn ok" onclick="achSave()">Speichern</button></div>
    </div>
    <div class="admin-card">
      <h3 style="margin:0 0 .5rem 0">Achievements</h3>
      <table id="achTable"><thead><tr><th>ID</th><th>Key</th><th>Titel</th><th>Event/Stat</th><th>Threshold</th><th>Punkte</th><th>Aktiv</th></tr></thead><tbody></tbody></table>
      <div style="margin-top:.75rem"><button class="btn" onclick="achList()">Neu laden</button></div>
    </div>
    <div class="admin-card">
      <h3 style="margin:0 0 .5rem 0">Achievement vergeben</h3>
      <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <div><label>User-ID</label><input id="grant_uid" type="number" placeholder="oder Email nutzen"></div>
        <div><label>Email</label><input id="grant_email" type="text" placeholder="oder User-ID nutzen"></div>
        <div><label>Achievement (ID oder Key)</label><input id="grant_aid" type="text" placeholder="z.B. 5 oder posts_10"></div>
      </div>
      <div style="margin-top:.75rem"><button class="btn ok" onclick="achGrant()">Vergeben</button></div>
    </div>

  <?php elseif ($view==='messages'): ?>
    <div class="admin-card">
      <h3 style="margin:0 0 .5rem 0">Messages Cleanup</h3>
      <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <div><label>Retention (Tage)</label><input id="mc_days" type="number" value="15"></div>
        <div><label>Batch</label><input id="mc_batch" type="number" value="500"></div>
        <div><label>Update-Chunk</label><input id="mc_chunk" type="number" value="200"></div>
      </div>
      <div style="margin-top:.5rem"><label>Platzhalter-Text</label>
        <input id="mc_placeholder" type="text" value="Anhang (Bild/Video) automatisch gelöscht – älter als 15 Tage.">
      </div>
      <div style="margin-top:.5rem;display:flex;align-items:center;gap:.75rem">
        <label style="display:flex;align-items:center;gap:.4rem"><input id="mc_dry" type="checkbox" checked> Dry-Run</label>
        <button class="btn" onclick="mcRun(true)">Preview (Dry-Run)</button>
        <button class="btn danger" onclick="mcRun(false)">Cleanup ausführen</button>
      </div>
    </div>
    <div class="admin-card"><pre id="mc_result" style="white-space:pre-wrap"></pre></div>
  <?php endif; ?>
</div>

<script>
const BASE = "<?= $APP_BASE ?>";
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

async function api(p, data){
  try {
    const res  = await fetch(`${BASE}/api/admin/${p}`, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF': CSRF },
      body: JSON.stringify(Object.assign({ csrf: CSRF }, data || {}))
    });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch { json = null; }

    if (!res.ok || !json || json.ok === false) {
      const msg = json?.error || text.slice(0, 600) || 'unbekannter Fehler';
      alert(`API-Fehler (${p}): HTTP ${res.status}\n${msg}`);
      return json || { ok:false, error: msg, http: res.status };
    }
    return json;
  } catch (e) {
    alert(`Netzwerkfehler (${p}): ${e.message}`);
    return { ok:false, error: e.message };
  }
}
/* ==== Admin: Reels Moderation ==== */
async function reelsLoad(){
  const q   = (document.getElementById('rl_q')?.value || '').trim();
  const st  = document.getElementById('rl_status')?.value || '';
  const uid = document.getElementById('rl_uid')?.value.trim();
  const payload = { q, status: st, user_id: uid ? parseInt(uid,10) : null, limit: 200, offset: 0 };

  // Erwartet: /api/admin/reels/list.php → {ok:true, items:[{ id, user_id, username, created_at, status, likes_count, comments_count, reports_count, description, hashtags_cache, src, poster }]}
  const j = await api('reels/list.php', payload);
  const tb = document.querySelector('#reelsTable tbody');
  if (!tb) return;
  tb.innerHTML = '';

  (j.items || []).forEach(it => {
    const tr = document.createElement('tr');
    const desc = String(it.description||'').replace(/\s+/g,' ').slice(0,120);
    const statusBadge = `<span class="badge">${(it.status||'visible')}</span>${it.under_review ? ' <span class="badge">under_review</span>' : ''}`;
    const rep = it.reports_count ? `<b class="bad">${it.reports_count}</b>` : '<span class="muted">0</span>';

    tr.innerHTML = `
      <td>#${it.id}</td>
      <td>${it.poster ? `<img class="thumb" src="${it.poster}" alt="">` : '<span class="muted">–</span>'}</td>
      <td>${escapeHtml(it.username||('u'+(it.user_id||'')))} <span class="muted">#${it.user_id||''}</span></td>
      <td>${escapeHtml(it.created_at||'')}</td>
      <td>${statusBadge}</td>
      <td>${(it.likes_count||0)} • ${(it.comments_count||0)}</td>
      <td>${rep}</td>
      <td class="muted" title="${escapeHtml(it.description||'')}">${escapeHtml(desc)}${desc.length===120?'…':''}</td>
      <td style="display:flex;gap:.35rem;flex-wrap:wrap">
        <button class="btn" onclick='reelPreviewOpen(${JSON.stringify(it).replace(/'/g,"&#39;")})'>Vorschau</button>
        ${it.status === 'hidden' || it.status === 'blocked'
          ? `<button class="btn ok" onclick="reelSetStatus(${it.id},'visible')">sichtbar</button>`
          : `<button class="btn" onclick="reelSetStatus(${it.id},'hidden')">verbergen</button>`}
        <button class="btn danger" onclick="reelDelete(${it.id})">löschen</button>
        <a class="btn" href="${BASE}/reels.php?id=${it.id}" target="_blank" rel="noreferrer noopener">/reels.php</a>
      </td>
    `;
    tb.appendChild(tr);
  });
}

function reelPreviewOpen(it){
  // Inline-Preview Modal ausfüllen
  const m  = document.getElementById('reelPreviewModal');
  const v  = document.getElementById('rp_video');
  const mt = document.getElementById('rp_meta');
  const ds = document.getElementById('rp_desc');
  const tg = document.getElementById('rp_tags');
  const l  = document.getElementById('rp_btn_link');
  const bv = document.getElementById('rp_btn_viewer');

  if (!m || !v) return;

  v.src = it.src || '';
  v.poster = it.poster || '';
  v.muted = true;
  v.play().catch(()=>{});

  mt.innerHTML = `@${escapeHtml(it.username||('u'+(it.user_id||'')))} <span class="muted">#${it.user_id||''}</span><br>
  ID #${it.id} · ${escapeHtml(it.created_at||'')} · Status: <b>${escapeHtml(it.status||'visible')}</b><br>
  Likes ${it.likes_count||0} · Comments ${it.comments_count||0} ${it.reports_count?('· Reports '+it.reports_count):''}`;

  ds.textContent = it.description || '';
  tg.textContent = (it.hashtags_cache || '');

  l.href = `${BASE}/reels.php?id=${it.id}`;
  bv.onclick = () => openReelIframe(it.id);

  m.style.display = 'block';
}

function reelPreviewClose(){
  const m  = document.getElementById('reelPreviewModal');
  const v  = document.getElementById('rp_video');
  if (v) { try { v.pause(); } catch(_){} }
  if (m) m.style.display = 'none';
}
window.reelPreviewClose = reelPreviewClose;

function openReelIframe(id){
  const box = document.getElementById('reelIframeModal');
  const fr  = document.getElementById('reelIframe');
  if (!box || !fr) return;
  fr.src = `${BASE}/reels.php?id=${encodeURIComponent(id)}`;
  box.style.display = 'block';
}
function closeReelIframe(){
  const box = document.getElementById('reelIframeModal');
  const fr  = document.getElementById('reelIframe');
  if (fr) fr.src = 'about:blank';
  if (box) box.style.display = 'none';
}
window.closeReelIframe = closeReelIframe;

async function reelSetStatus(id, status){
  // Erwartet: /api/admin/reels/status.php → {ok:true}
  const j = await api('reels/status.php', { id, status });
  if (!j || !j.ok) { alert('Speichern fehlgeschlagen'); return; }
  reelsLoad();
}

async function reelDelete(id){
  if (!confirm(`Reel #${id} wirklich löschen?`)) return;
  // Erwartet: /api/admin/reels/delete.php → {ok:true}
  const j = await api('reels/delete.php', { id });
  if (!j || !j.ok) { alert('Löschen fehlgeschlagen'); return; }
  // eventuell offene Previews schließen
  reelPreviewClose();
  closeReelIframe();
  reelsLoad();
}

/* Users */
async function loadUsers(){
  const q = document.getElementById('q').value.trim();
  const j = await api('users/list.php', {q});
  const tb = document.querySelector('#usersTable tbody'); tb.innerHTML='';

  (j.items||[]).forEach(u=>{
    const tr=document.createElement('tr');
    const badge = u.is_twitch_streamer ? '<span class="badge" title="Twitch-Streamer">Twitch</span>' : '';
    tr.innerHTML=`<td>${u.id}</td>
      <td>${escapeHtml(u.display_name||'')} ${badge}</td>
      <td>${escapeHtml(u.email||'')}</td>
      <td>${escapeHtml(u.role|| (u.is_admin ? 'admin*' : 'user'))}</td>
      <td style="display:flex;gap:.35rem;flex-wrap:wrap">
        <button class="btn ok" onclick="openUserEdit(${u.id})">Edit</button>
        <button class="btn" onclick="setRole(${u.id},'admin')">zu Admin</button>
        <button class="btn" onclick="setRole(${u.id},'user')">zu User</button>
      </td>`;
    tb.appendChild(tr);
  });
}
async function setRole(id, role){
  const j = await api('users/update_role.php', {id, role});
  alert(j.ok ? 'Gespeichert' : ('Fehler: '+(j.error||'unknown')));
  loadUsers();
}

/* Forum */
async function deletePost(){
  const id = parseInt(document.getElementById('postId').value||'0',10);
  if (!id) return alert('Post-ID fehlt');
  const j = await api('forum/delete_post.php', {id});
  alert(j.ok ? 'Post gelöscht' : ('Fehler: '+(j.error||'unknown')));
}
async function deleteThread(){
  const id = parseInt(document.getElementById('threadId').value||'0',10);
  if (!id) return alert('Thread-ID fehlt');
  const j = await api('forum/delete_thread.php', {id});
  alert(j.ok ? 'Thread gelöscht' : ('Fehler: '+(j.error||'unknown')));
}

/* Uploads */
async function listUploads(kind){
  const j = await api('uploads/list.php', {kind});
  const tb = document.querySelector('#uploadsTable tbody'); tb.innerHTML='';
  if (!j.ok) return;

  const rels = [];

  (j.items||[]).forEach(f=>{
    const url   = `${BASE}/api/admin/uploads/preview.php?rel=${encodeURIComponent(f.rel)}`;
    const isVid = (f.kind === 'videos') || (f.mime && f.mime.startsWith('video/'));

    const preview = isVid
      ? `<video class="admin-thumb" src="${url}" style="max-width:420px;" preload="metadata" playsinline muted controls></video>`
      : `<img class="admin-thumb" src="${url}" alt="" style="width:200px;">`;

    const tr = document.createElement('tr');
    tr.dataset.rel = f.rel; // merken
    tr.innerHTML = `
      <td>${isVid ? preview : `<a href="${url}" target="_blank" rel="noreferrer noopener">${preview}</a>`}</td>
      <td>${f.kind}</td>
      <td class="muted" style="max-width:420px;word-break:break-all">${f.rel}</td>
      <td class="theme-cell muted">–</td>
      <td>${(f.size||0).toLocaleString('de-DE')}</td>
      <td><button class="btn danger" onclick="delUpload('${(f.rel||'').replace(/'/g,"\\'")}')">löschen</button></td>
    `;
    tb.appendChild(tr);

    // Nur für ordnerbezogene Inhalte, die typischerweise in Posts vorkommen, auflösen:
    if (/\/uploads\/(posts|forum|messages|video_posters|videos)\//.test(f.rel)) {
      rels.push(f.rel);
    }
  });

  // Inline Video Click Play/Pause (optional)
  tb.querySelectorAll('video.admin-thumb').forEach(v=>{
    v.addEventListener('click', ()=>{ if (v.paused) v.play(); else v.pause(); });
  });

  // Zuordnung in einem Rutsch nachladen
  if (rels.length) resolveUploadsForum(rels);
}

async function delUpload(rel){
  const j = await api('uploads/delete.php', {rel});
  alert(j.ok ? 'Datei gelöscht' : ('Fehler: '+(j.error||'unknown')));
}

/* Cache */
async function clearLinkPreviews(){
  const j = await api('cache/clear_link_previews.php', {});
  alert(j.ok ? 'Cache geleert' : ('Fehler: '+(j.error||'unknown')));
}

/* Health */
(async function(){
  if ("<?= $view ?>" === 'health') {
    const j = await api('system/health.php', {});
    document.getElementById('healthBox').textContent = j.ok ? `OK (PHP ${j.php}, DB ${j.db})` : `Fehler: ${j.error||'unknown'}`;
  }
})();

/* Notifications - Broadcast */
async function broadcastSend(){
  const title = document.getElementById('bc_title').value.trim();
  const body  = document.getElementById('bc_body').value.trim();
  const link  = document.getElementById('bc_link').value.trim();
  const level = document.getElementById('bc_level').value;
  const fan   = document.getElementById('bc_fan').checked;
  if (!title || !body) return alert('Titel und Body erforderlich.');

  const j = await api('notifications/broadcast.php', {title, body, link, level, fan_out:fan});
  if (!j.ok) return alert('Fehler: '+(j.error||'unknown'));
  alert('Broadcast gesendet' + (j.fan_out_inserted ? ` (+${j.fan_out_inserted} Notifs)` : ''));
  broadcastList();
}
async function broadcastList(){
  const j = await api('notifications/list.php', {});
  const tb = document.querySelector('#bcTable tbody'); tb.innerHTML='';
  (j.items||[]).forEach(b=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `<td>${b.id}</td><td>${b.title}</td><td>${b.level}</td><td class="muted">${b.body_preview||''}</td><td>${b.link||''}</td><td>${b.is_active?'1':'0'}</td>
      <td>${b.is_active?`<button class="btn" onclick="broadcastDeactivate(${b.id})">deaktivieren</button>`:''}</td>`;
    tb.appendChild(tr);
  });
}
async function broadcastDeactivate(id){
  const j = await api('notifications/deactivate.php', {id});
  if (!j.ok) return alert('Fehler: '+(j.error||'unknown'));
  broadcastList();
}

/* Gamification */
async function achList(){
  const j = await api('gamification/achievements_list.php', {});
  const tb = document.querySelector('#achTable tbody'); tb.innerHTML='';
  (j.items||[]).forEach(a=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `<td>${a.id}</td><td>${a.key}</td><td>${a.title}</td><td>${a.rule_event}/${a.rule_stat}</td>
      <td>${a.threshold}</td><td>${a.points}</td><td>${a.is_active?1:0}</td>`;
    tr.style.cursor='pointer';
    tr.onclick = ()=>{
      document.getElementById('ach_id').value = a.id;
      document.getElementById('ach_key').value = a.key||'';
      document.getElementById('ach_title').value = a.title||'';
      document.getElementById('ach_icon').value = a.icon||'';
      document.getElementById('ach_event').value = a.rule_event||'';
      document.getElementById('ach_stat').value = a.rule_stat||'';
      document.getElementById('ach_thr').value = a.threshold||0;
      document.getElementById('ach_pts').value = a.points||0;
      document.getElementById('ach_active').value = a.is_active? '1':'0';
      document.getElementById('ach_desc').value = a.description||'';
    };
    tb.appendChild(tr);
  });
}
async function achSave(){
  const id    = parseInt(document.getElementById('ach_id').value||'0',10);
  const key   = document.getElementById('ach_key').value.trim();
  const title = document.getElementById('ach_title').value.trim();
  const icon  = document.getElementById('ach_icon').value.trim();
  const event = document.getElementById('ach_event').value.trim();
  const stat  = document.getElementById('ach_stat').value.trim();
  const thr   = parseInt(document.getElementById('ach_thr').value||'0',10);
  const pts   = parseInt(document.getElementById('ach_pts').value||'0',10);
  const active= document.getElementById('ach_active').value === '1';
  const desc  = document.getElementById('ach_desc').value.trim();

  const j = await api('gamification/achievements_save.php', {id, key, title, description:desc, icon, rule_event:event, rule_stat:stat, threshold:thr, points:pts, is_active:active});
  alert(j.ok ? 'Gespeichert' : ('Fehler: '+(j.error||'unknown')));
  achList();
}
async function achGrant(){
  const uidStr = document.getElementById('grant_uid').value.trim();
  const email  = document.getElementById('grant_email').value.trim();
  const aidKey = document.getElementById('grant_aid').value.trim();
  let user_id = parseInt(uidStr||'0',10);
  let achievement_id = 0, key = '';

  if (!user_id && !email) return alert('User-ID oder Email angeben.');
  if (/^\d+$/.test(aidKey)) achievement_id = parseInt(aidKey,10); else key = aidKey;

  const j = await api('gamification/grant.php', {user_id, email, achievement_id, key});
  alert(j.ok ? `Vergeben (User ${j.user_id}, Ach ${j.achievement_id}, +${j.points_added} Punkte)` : ('Fehler: '+(j.error||'unknown')));
}

/* Messages Cleanup */
async function mcRun(dry){
  const days = parseInt(document.getElementById('mc_days').value||'15',10);
  const placeholder = document.getElementById('mc_placeholder').value;
  const batch = parseInt(document.getElementById('mc_batch').value||'500',10);
  const chunk = parseInt(document.getElementById('mc_chunk').value||'200',10);
  const j = await api('messages/cleanup_run.php', {retention_days:days, placeholder, dry_run:dry, batch, update_chunk:chunk});
  document.getElementById('mc_result').textContent = JSON.stringify(j, null, 2);
}

/* ---- Forum: Threads Listing & Edit ---- */
let thrOffset = 0;
const thrLimit = 50;

async function forumBoardsLoad(){
  const j = await api('forum/boards_list.php', {});
  const sel = document.getElementById('thr_board');
  const sel2 = document.getElementById('thr_edit_board');
  [sel, sel2].forEach(s => { if (s) s.innerHTML = '<option value="0">Alle</option>'; });

  (j.items||[]).forEach(b=>{
    if (sel)  sel.insertAdjacentHTML('beforeend', `<option value="${b.id}">${b.name||('Board '+b.id)}</option>`);
    if (sel2) sel2.insertAdjacentHTML('beforeend', `<option value="${b.id}">${b.name||('Board '+b.id)}</option>`);
  });
}

async function forumThreadsLoad(){
  const q   = document.getElementById('thr_q').value.trim();
  const bid = parseInt(document.getElementById('thr_board').value||'0',10);
  const j = await api('forum/threads_list.php', {q, board_id: bid, limit: thrLimit, offset: thrOffset});
  const tb = document.querySelector('#thrTable tbody'); tb.innerHTML = '';
  (j.items||[]).forEach(t=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${t.id}</td>
      <td>${t.board_name||t.board_id}</td>
      <td>${escapeHtml(t.title||'')}</td>
      <td class="muted">${t.slug||''}</td>
      <td>${escapeHtml(t.author||'')}</td>
      <td>${t.posts_count||0}</td>
      <td>${t.is_locked?1:0}</td>
      <td>${t.is_pinned?1:0}</td>
      <td>${t.created_at||''}</td>
      <td>
       <button class="btn" onclick='forumThreadEdit(${JSON.stringify(t).replace(/'/g,"&#39;")})'>Edit</button>
  <button class="btn" onclick="forumPostsOpen(${t.id})">Posts</button>
  <button class="btn danger" onclick="deleteThreadId(${t.id})">Del</button>
      </td>
    `;
    tb.appendChild(tr);
  });
  document.getElementById('thrInfo').textContent = `Offset ${thrOffset} · geladen: ${(j.items||[]).length}`;
}
function forumPrev(){ thrOffset = Math.max(0, thrOffset - thrLimit); forumThreadsLoad(); }
function forumNext(){ thrOffset += thrLimit; forumThreadsLoad(); }

function forumThreadEdit(t){
  document.getElementById('thr_id').value     = t.id;
  document.getElementById('thr_title').value  = t.title||'';
  document.getElementById('thr_slug').value   = t.slug||'';
  document.getElementById('thr_locked').value = (t.is_locked?1:0);
  document.getElementById('thr_pinned').value = (t.is_pinned?1:0);

  // Board-Dropdown füllen, wenn leer
  const sel = document.getElementById('thr_edit_board');
  if (!sel.options || sel.options.length<=1) forumBoardsLoad();
  // später setzen (kleines Delay), falls boards gerade laden
  setTimeout(()=>{ sel.value = t.board_id; }, 50);

  document.getElementById('thrModal').style.display = 'block';
}
function forumModalClose(){ document.getElementById('thrModal').style.display = 'none'; }

async function forumThreadSave(){
  const id     = parseInt(document.getElementById('thr_id').value||'0',10);
  const title  = document.getElementById('thr_title').value.trim();
  const slug   = document.getElementById('thr_slug').value.trim();
  const board  = parseInt(document.getElementById('thr_edit_board').value||'0',10);
  const locked = document.getElementById('thr_locked').value === '1';
  const pinned = document.getElementById('thr_pinned').value === '1';

  const payload = { id, title, board_id: board, is_locked: locked, is_pinned: pinned };
  if (slug !== '') payload.slug = slug; // nur senden, wenn gesetzt

  const j = await api('forum/thread_update.php', payload);
  if (!j.ok) { alert('Fehler: ' + (j.error||'unknown')); return; }

  forumModalClose();
  forumThreadsLoad();
}

async function deleteThreadId(id){
  const tid = parseInt(id, 10);
  if (!tid) { alert('Ungültige Thread-ID'); return; }
  if (!confirm(`Thread ${tid} wirklich löschen?`)) return;
  const j = await api('forum/delete_thread.php', { id: tid }); // id explizit
  alert(j.ok ? 'Thread gelöscht' : ('Fehler: '+(j.error||'unknown')));
  forumThreadsLoad();
}

/* ---- Forum: Posts pro Thread ---- */
let pmThreadId = 0;
let pmOffset = 0;
const pmLimit = 50;
function threadUrl(id, slug){
  // Falls deine App Slugs in der URL nutzt, kannst du das hier anpassen:
  // return `${BASE}/forum/${encodeURIComponent(slug||('thread-'+id))}`;
  return `${BASE}/forum/thread.php?t=${id}`;
}
function forumPostsOpen(threadId){
  pmThreadId = threadId;
  pmOffset = 0;
  document.getElementById('pm_thread_id').textContent = threadId;
  document.getElementById('postModal').style.display = 'block';
  forumPostsLoad();
}
function forumPostsClose(){
  document.getElementById('postModal').style.display = 'none';
  document.getElementById('pm_table').querySelector('tbody').innerHTML = '';
  document.getElementById('pm_edit_id').value = '';
  document.getElementById('pm_edit_content').value = '';
}

async function forumPostsLoad(){
  const q = document.getElementById('pm_q').value.trim();
  const j = await api('forum/posts_list.php', {thread_id: pmThreadId, q, limit: pmLimit, offset: pmOffset});
  const tb = document.querySelector('#pm_table tbody'); tb.innerHTML = '';
  (j.items||[]).forEach(p=>{
    const tr = document.createElement('tr');
    const contentPreview = (p.content||'').toString().slice(0, 180).replace(/\n/g,' ');
    tr.innerHTML = `
      <td>${p.id}</td>
      <td>${escapeHtml(p.author||'')}</td>
      <td class="muted" title="${escapeHtml(p.content||'')}">${escapeHtml(contentPreview)}</td>
      <td>${p.created_at||''}</td>
      <td><button class="btn" onclick='forumPostEdit(${p.id}, ${JSON.stringify(p.content||'').replace(/'/g,"&#39;")})'>Edit</button></td>
    `;
    tb.appendChild(tr);
  });
  document.getElementById('pm_info').textContent = `Offset ${pmOffset} · geladen: ${(j.items||[]).length}`;
}
function forumPostsPrev(){ pmOffset = Math.max(0, pmOffset - pmLimit); forumPostsLoad(); }
function forumPostsNext(){ pmOffset += pmLimit; forumPostsLoad(); }

function forumPostEdit(id, content){
  document.getElementById('pm_edit_id').value = id;
  document.getElementById('pm_edit_content').value = content || '';
  // Scroll zum Editor
  document.getElementById('pm_edit_content').scrollIntoView({behavior:'smooth', block:'center'});
}

async function forumPostSave(){
  const id = parseInt(document.getElementById('pm_edit_id').value||'0',10);
  const content = document.getElementById('pm_edit_content').value;
  if (!id) return alert('Kein Post ausgewählt.');
  const j = await api('forum/post_update.php', {id, content});
  if (!j.ok) return;
  alert('Gespeichert');
  forumPostsLoad();
}

async function forumPostDelete(){
  const id = parseInt(document.getElementById('pm_edit_id').value||'0',10);
  if (!id) return alert('Kein Post ausgewählt.');
  if (!confirm('Post wirklich löschen?')) return;
  const j = await api('forum/post_delete.php', {id});
  if (!j.ok) return;
  alert('Gelöscht');
  document.getElementById('pm_edit_id').value = '';
  document.getElementById('pm_edit_content').value = '';
  forumPostsLoad();
}
async function resolveUploadsForum(rels){
  const j = await api('uploads/resolve_forum.php', {rels});
  if (!j.ok) return;

const map = j.map || {};
document.querySelectorAll('#uploadsTable tbody tr').forEach(tr=>{
  const rel = tr.dataset.rel;
  const cell = tr.querySelector('.theme-cell');
  if (!cell) return;
  const hits = map[rel];
  if (hits && hits.length) {
    const first = hits[0];
    const url   = threadUrl(first.thread_id, first.slug);
    const extra = hits.length > 1 ? ` (+${hits.length-1})` : '';
    cell.innerHTML = `<a href="${url}" target="_blank" rel="noreferrer noopener">#${first.thread_id} – ${escapeHtml(first.thread_title)}</a>${extra}`;
    cell.classList.remove('muted');
  } else {
    cell.textContent = '—';
    cell.classList.add('muted');
  }
});
}
async function openUserEdit(id){
  const j = await api('users/get.php', {id});
  if (!j.ok) { alert('Laden fehlgeschlagen'); return; }
  const u = j.user;

  document.getElementById('ue_id').value           = u.id;
  document.getElementById('ue_role').value         = u.role || 'user';
  document.getElementById('ue_display_name').value = u.display_name || '';
  document.getElementById('ue_email').value        = u.email || '';
  document.getElementById('ue_is_twitch').checked  = !!u.is_twitch_streamer;
  document.getElementById('ue_twitch_url').value   = u.twitch_url || '';

  document.getElementById('userEditModal').style.display = 'block';
}

function closeUserEdit(){
  document.getElementById('userEditModal').style.display = 'none';
}

async function saveUserEdit(){
  const id   = parseInt(document.getElementById('ue_id').value||'0',10);
  const role = document.getElementById('ue_role').value;
  const display_name = document.getElementById('ue_display_name').value.trim();
  const is_twitch_streamer = document.getElementById('ue_is_twitch').checked;
  const twitch_url = document.getElementById('ue_twitch_url').value.trim();

  if (!id) return alert('Ungültige User-ID');

  const payload = { id, role, display_name, is_twitch_streamer, twitch_url };
  const j = await api('users/save.php', payload);
  if (!j.ok) { alert('Speichern fehlgeschlagen: '+(j.error||'unknown')); return; }

  closeUserEdit();
  loadUsers();
}
// kleiner Helper für HTML-Escapes
function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

/* Auto-Init pro Tab */
(async function init(){
  const v = "<?= $view ?>";
  if (v==='users') loadUsers();
    if (v==='reels') reelsLoad();

  if (v==='health') {
    const j = await api('system/health.php', {});
    document.getElementById('healthBox').textContent = j.ok ? `OK (PHP ${j.php}, DB ${j.db})` : `Fehler: ${j.error||'unknown'}`;
  }
  
  if (v==='notifications') broadcastList();
  if (v==='gamification') achList();
  if (v==='forum') { await forumBoardsLoad(); await forumThreadsLoad(); }
})();

</script>
<?php
$content = ob_get_clean();
render_theme_page($content, 'Admin');

<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/layout.php';
$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$L = $GLOBALS['L']; // lokale Referenz
$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
if (!function_exists('t')) {
  function t(string $key, string $fallback=''): string {
    $L = $GLOBALS['L'] ?? [];
    return htmlspecialchars((string)($L[$key] ?? $fallback), ENT_QUOTES, 'UTF-8');
  }
}
// Login ist Pflicht
if (function_exists('require_user'))      { $me = require_user(); }
elseif (function_exists('require_login')) { $me = require_login(); }
elseif (function_exists('require_auth'))  { $me = require_auth(); }
else                                      { $me = require_admin(); }

$id = isset($_GET['t']) ? (int)$_GET['t'] : (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); echo 'Tournament ID missing'; exit; }

$st = $pdo->prepare('SELECT id, name, status, team_size, format, starts_at, ends_at, platform, prizes_text, scoring_json FROM tournaments WHERE id=? LIMIT 1');
$st->execute([$id]);
$t = $st->fetch(PDO::FETCH_ASSOC);
if (!$t) { http_response_code(404); echo 'Tournament not found'; exit; }

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
<meta name="csrf" content="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
<style>
/* zugängliches Verstecken des echten Inputs */
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
/* Datei-Pill + Thumbnail */
.file-pill{display:inline-flex;align-items:center;gap:.6rem;border:1px solid rgba(255,255,255,.12);background:#141414;border-radius:12px;padding:.5rem .75rem;min-height:44px}
.file-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;display:none}
.file-pill.has-file .file-thumb{display:block}
</style>
<main>
  <!-- Hero/Header wie Overview -->
  <section class="pt-30p">
    <div class="section-pt">
      <div class="bg-[url('../images/photos/tournamentBanner.webp')] bg-cover bg-no-repeat rounded-24 overflow-hidden h-[416px]">
        <div class="container">
          <div class="grid grid-cols-12 gap-30p relative xl:py-[110px] md:py-30 sm:py-25 py-20 z-[2]">
            <div class="lg:col-start-2 lg:col-end-12 col-span-12">
              <h2 class="heading-2 text-w-neutral-1 mb-3"><?= htmlspecialchars((string)$t['name']) ?></h2>
              <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= htmlspecialchars($APP_BASE) ?>/" class="breadcrumb-link">Start</a></li>
                <li class="breadcrumb-item"><span class="breadcrumb-icon"><i class="ti ti-chevrons-right"></i></span></li>
                <li class="breadcrumb-item"><a href="<?= htmlspecialchars($APP_BASE) ?>/tournaments/view.php?id=<?= (int)$t['id'] ?>" class="breadcrumb-link">Turnier</a></li>
               
              </ul>
            </div>
          </div>
        </div>
      </div>

      <div class="container">
        <div class="pb-30p overflow-visible relative grid 4xl:grid-cols-12 grid-cols-1 gap-30p lg:-mt-30 md:-mt-40 sm:-mt-48 -mt-56">
          <div class="4xl:col-start-2 4xl:col-end-12">
            <div class="relative z-10 grid 4xl:grid-cols-11 grid-cols-12 items-center gap-30p bg-b-neutral-3 shadow-4 p-40p rounded-24 xl:divide-x divide-shap/70">
              <div class="3xl:col-span-4 col-span-12">
                <div class="max-xl:flex-col-c max-xl:text-center">
                  <h3 class="heading-3 text-w-neutral-1 mb-20p"><?= htmlspecialchars((string)$t['name']) ?></h3>
                  <div class="flex-y flex-wrap max-xl:justify-center gap-16p">
                    <span class="badge badge-lg badge-primary"><?= htmlspecialchars((string)($t['starts_at'] ?: '–')) ?></span>
                    <span class="badge badge-lg badge-secondary"><?= htmlspecialchars((string)($t['ends_at'] ?: '–')) ?></span>
                  </div>
                </div>
              </div>
              <div class="3xl:col-span-4 xl:col-span-7 col-span-12 grid xl:grid-cols-2 grid-cols-1 gap-y-30p xl:divide-x divide-shap/70">
                <div class="flex-col-c text-center">
                  <p class="text-m-medium text-w-neutral-1"><?= t('tour_prize') ?></p>
                  <span class="text-xl-medium text-center text-secondary"><?= htmlspecialchars((string)($t['prizes_text'] ?: '–')) ?></span>
                </div>
                <div class="flex-col-c text-center">
                  <p class="text-m-medium text-w-neutral-1"><?= t('tour_name_prefix') ?></p>
                  <span class="text-xl-medium text-center text-primary"><?= htmlspecialchars((string)($t['platform'] ?: '–')) ?></span>
                </div>
              </div>
              <div class="3xl:col-span-3 xl:col-span-5 col-span-12">
                <div class="flex xl:justify-end justify-center">
                  <div class="flex-col-c text-center">
                    <a href="<?= htmlspecialchars($APP_BASE) ?>/tournaments/view.php?id=<?= (int)$t['id'] ?>" class="btn btn-md btn-primary rounded-12 mb-16p"><?= t('tour_back_to_tournament') ?></a>
                    <div class="flex items-center gap-3 py-16p">
                      <?php if ($scoring): ?>
                        <div><span class="text-m-medium text-white">1</span><span class="text-xs text-white"><?= t('tour_points_kills') ?></span></div>
                        <span class="text-primary icon-24">:</span>
                        <div><span class="text-m-medium text-white">3</span><span class="text-xs text-white"><?= t('tour_points_boss') ?></span></div>
                        <span class="text-primary icon-24">:</span>
                        <div><span class="text-m-medium text-white">5</span><span class="text-xs text-white"><?= t('tour_points_bounty') ?></span></div>
                        <span class="text-primary icon-24">:</span>
                        <div><span class="text-m-medium text-white">-5</span><span class="text-xs text-white"><?= t('tour_points_deaths') ?></span></div>
                      <?php endif; ?>
                    </div>
                    <p class="text-s-medium text-w-neutral-4"><?= t('tour_points_legend') ?></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Zwei-Spalten-Layout wie Overview -->
  <section class="section-pb relative overflow-visible">
    <div class="container">
      <div class="grid 4xl:grid-cols-12 grid-cols-1 gap-30p ">
        <div class="4xl:col-start-2 4xl:col-end-12">
          <div class="grid grid-cols-10 gap-30p items-start">

            <!-- Linke Spalte -->
            <div class="3xl:col-span-7 xl:col-span-6 col-span-10">
              <!-- Team-Card -->
              <div class="bg-b-neutral-3 rounded-12 p-32p mb-32p">
                <div class="flex items-center justify-between mb-20p">
                  <h3 class="heading-3 text-w-neutral-1"><?= t('tour_team_heading') ?></h3>
                  <span class="text-s-medium text-w-neutral-4"><?= t('tour_team_size') ?>: <?= (int)$t['team_size'] ?></span>
                </div>
                <div id="teamBox" class="text-w-neutral-3"><?= t('tour_team_loading') ?></div>

                <div id="teamActions" class="mt-20p hidden">
                  <div class="grid sm:grid-cols-3 grid-cols-1 gap-16p items-end">
                    <div class="sm:col-span-2">
                      <label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_team_new_label') ?></label>
                      <input id="team_name" type="text" class="box-input-3" placeholder="<?= t('tour_team_name_placeholder') ?>">
                    </div>
                    <button onclick="createTeam()" class="btn btn-md btn-primary rounded-12"><?= t('tour_team_create_btn') ?></button>
                  </div>

                  <div class="grid sm:grid-cols-3 grid-cols-1 gap-16p items-end mt-20p">
                    <div class="sm:col-span-2">
                      <label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_team_join_label') ?></label>
                      <input id="join_code" type="text" class="box-input-3" placeholder="z. B. 7GHD3K">
                    </div>
                    <button onclick="joinTeam()" class="btn btn-md btn-secondary rounded-12"><?= t('tour_team_join_btn') ?></button>
                  </div>
                </div>
              </div>

              <!-- Upload-Card -->
              <div class="bg-b-neutral-3 rounded-12 p-32p">
                <h3 class="heading-3 text-w-neutral-1 mb-16p"><?= t('tour_upload_heading') ?></h3>
                <p class="text-w-neutral-3" id="uploadHint"><?= t('tour_upload_need_team') ?></p>

                <form id="runForm" onsubmit="return false" class="hidden mt-16p">
                  <div class="grid md:grid-cols-3 grid-cols-1 gap-16p">
                    <div>
                      <label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_upload_kills') ?></label>
                      <input type="number" id="rf_kills" min="0" value="0" class="box-input-3">
                    </div>
                    <div>
                      <label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_upload_bosses') ?></label>
                      <input type="number" id="rf_bosses" min="0" value="0" class="box-input-3">
                    </div>
                    <div>
                      <label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_upload_tokens') ?></label>
                      <input type="number" id="rf_tokens" min="0" value="0" class="box-input-3">
                    </div>
                  </div>

                  <div class="grid md:grid-cols-2 grid-cols-1 gap-16p mt-16p">
                    <div>
                      <label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_upload_gauntlet') ?></label>
                      <select id="rf_gauntlet" class="ss-main select w-full sm:py-3 py-2 px-24p rounded-full"><option value="0">0</option><option value="1">1</option></select>
                    </div>
                    <div>
                      <label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_upload_deaths') ?></label>
                      <input type="number" id="rf_deaths" min="0" value="0" class="box-input-3">
                    </div>
                  </div>

               <style>
/* zugängliches Verstecken des echten Inputs */
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
</style>


<div class="mt-16p">
<label class="text-base text-w-neutral-4 mb-1 block"><?= t('tour_upload_screenshot') ?></label>


<!-- echter Input unsichtbar; ID bleibt rf_shot für deine Upload-Logik -->
<input id="rf_shot" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">


<div class="flex items-center gap-12p">
<!-- Auslöse-Button im HH-Stil -->
<label for="rf_shot" class="btn btn-md btn-primary rounded-12">
<i class="ti ti-upload icon-18"></i>
<span><?= t('tour_upload_choose') ?></span>
</label>


<!-- Datei-Pill mit Name + optionalem Thumbnail + Reset -->
<div id="rf_shot_pill" class="flex items-center gap-10p bg-b-neutral-2 border border-w-neutral-4/20 rounded-12 px-12p py-10p min-h-[44px]">
<img id="rf_shot_preview" class="hidden size-44p rounded-10 object-cover" alt="">
<span id="rf_shot_name" class="text-w-neutral-4"><?= t('tour_upload_none') ?></span>
<button type="button" id="rf_shot_clear" class="btn btn-sm btn-outline rounded-10 ml-10p"><?= t('tour_upload_reset') ?></button>
</div>
</div>
</div>


<!-- Fix: Name/Preview aktualisiert sich nicht → nutze Event Delegation und robuste Selektoren.
     Du musst NICHTS am Markup ändern, wenn du schon meinen letzten rf_shot‑Block nutzt. -->
<script>
(function(){
  const INPUT_SEL   = '#rf_shot';
  const NAME_SEL    = '#rf_shot_name';
  const PREVIEW_SEL = '#rf_shot_preview';
  const CLEAR_SEL   = '#rf_shot_clear';

  function updateFileUI(input){
    const nameEl  = document.querySelector(NAME_SEL);
    const preview = document.querySelector(PREVIEW_SEL);
    const f = input && input.files && input.files[0];
    if (f){
      if (nameEl) nameEl.textContent = f.name;
      if (preview){
        if ((f.type||'').startsWith('image/')){
          preview.src = URL.createObjectURL(f);
          preview.classList.remove('hidden');
        } else {
          preview.removeAttribute('src');
          preview.classList.add('hidden');
        }
      }
    } else {
      if (nameEl) nameEl.textContent = 'Kein Bild ausgewählt';
      if (preview){ preview.removeAttribute('src'); preview.classList.add('hidden'); }
    }
  }

  // 1) Reagiere auch dann, wenn der Input dynamisch ersetzt wird
  document.addEventListener('change', function(e){
    const t = e.target;
    if (t && t.matches && t.matches(INPUT_SEL)){
      updateFileUI(t);
    }
  }, true);

  // 2) Reset‑Button delegiert behandeln
  document.addEventListener('click', function(e){
    const btn = e.target && (e.target.closest ? e.target.closest(CLEAR_SEL) : null);
    if (!btn) return;
    const input = document.querySelector(INPUT_SEL);
    if (!input) return;
    input.value = '';
    // Bubbles, damit der oben registrierte Handler sicher läuft
    input.dispatchEvent(new Event('change', {bubbles:true}));
  });

  // 3) (optional) Debug bei doppelten IDs – kann Updates verhindern
  if (console && document.querySelectorAll(INPUT_SEL).length > 1){
    console.warn('Hunthub: Mehrere #rf_shot Inputs gefunden – bitte doppelte IDs entfernen.');
  }
})();
</script>

                  <div class="mt-20p flex items-center gap-12p">
                    <button onclick="uploadRun()" class="btn btn-md btn-primary rounded-12"><?= t('tour_upload_send') ?></button>
                    <span id="uploadMsg" class="text-w-neutral-4"></span>
                  </div>
                </form>
              </div>

              <!-- Meine Uploads -->
              <div class="bg-b-neutral-3 rounded-12 p-32p mt-32p">
                <h3 class="heading-3 text-w-neutral-1 mb-16p"><?= t('tour_upload_my_uploads') ?></h3>
                <div class="overflow-x-auto scrollbar-sm">
                  <table class="min-w-full">
                    <thead class="text-xl font-borda bg-transparent text-w-neutral-1 whitespace-nowrap">
                      <tr>
                        <th class="px-16p pb-12p text-left"><?= t('tour_upload_col_time') ?></th>
                        <th class="px-16p pb-12p text-left"><?= t('tour_upload_col_points') ?></th>
                        <th class="px-16p pb-12p text-left"><?= t('tour_upload_col_status') ?></th>
                        <th class="px-16p pb-12p text-left"><?= t('tour_upload_col_screenshot') ?></th>
                      </tr>
                    </thead>
                    <tbody id="runsTBody" class="text-base font-medium font-poppins text-w-neutral-1 divide-y-[12px] divide-b-neutral-4"></tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Rechte Spalte (kleine Sidebar wie Overview) -->
            <div class="3xl:col-span-3 xl:col-span-4 col-span-10 relative">
              <div class="xl:sticky xl:top-30">
                <div class="grid grid-cols-1 gap-y-30p *:bg-b-neutral-3 *:p-32p *:rounded-12">
                  <div>
                    <div class="flex-y gap-3 mb-12p">
                      <span class="icon-24 text-primary"><i class="ti ti-bolt-filled"></i></span>
                      <h5 class="heading-5 text-w-neutral-1"><?= t('tour_sidebar_note') ?></h5>
                    </div>
                    <p class="text-s-regular text-w-neutral-3"><?= t('tour_sidebar_note_text') ?></p>
                  </div>
                  <div>
				  <div class="flex-y gap-3 mb-12p">
				  <span class="icon-24 text-primary"><i class="ti ti-bolt-filled"></i></span>
                    <h5 class="heading-5 text-w-neutral-1"><?= t('tour_sidebar_rules') ?></h5>
					</div>
                    <p class="text-s-regular text-w-neutral-3"><?= t('tour_sidebar_rules_text') ?></p>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function(){
  const BASE = "<?= $APP_BASE ?>";
  const CSRF_TOKEN = document.querySelector('meta[name="csrf"]')?.content || '';
  const TID  = <?= (int)$t['id'] ?>;

  const $ = (sel, ctx=document) => ctx.querySelector(sel);
  const el = id => document.getElementById(id);
  const esc = s => (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m]));

  function show(elm){ if (elm) elm.classList.remove('hidden'); }
  function hide(elm){ if (elm) elm.classList.add('hidden'); }

  // API mit Timeout und Fehlerausgabe
  async function api(p, body, {timeoutMs=30000}={}) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
      const res = await fetch(`${BASE}/api/tournaments/${p}`, {
        method:'POST',
        body,
        credentials:'same-origin',
        signal: ctrl.signal
      });
      const txt = await res.text();
      let j=null; try{ j=JSON.parse(txt);}catch(_){}
      if(!res.ok || !j){ throw new Error(`HTTP ${res.status}: ${txt.slice(0,300)}`); }
      if(j.ok===false){ throw new Error(j.error || 'error'); }
      return j;
    } finally { clearTimeout(t); }
  }

  async function loadState(){
    const fd = new FormData();
    fd.set('csrf', CSRF_TOKEN);
    fd.set('tournament_id', TID);
    const j = await api('my_state.php', fd);

    const box = el('teamBox');
    const act = el('teamActions');
    const form= el('runForm');
    const hint= el('uploadHint');

    if (j.team){
      if (box) {
        box.innerHTML = `<div class="flex items-center gap-8p">
          <b>Team:</b> ${esc(j.team.name)}
          <span class="text-w-neutral-4">· Join-Code:</span>
          <span class="mono">${esc(j.team.join_code||'-')}</span>
        </div>` +
        (j.members && j.members.length ? `<div class="text-w-neutral-4 mt-8p">Mitglieder: ${j.members.map(m=>esc(m.name)).join(', ')}</div>` : '');
      }
      hide(act); show(form); if (hint) hint.textContent = '';
    } else {
      if (box) box.textContent = 'Du bist noch in keinem Team.';
      show(act); hide(form); if (hint) hint.textContent = 'Du musst in einem Team sein, um hochzuladen.';
    }

    const tb = el('runsTBody');
    if (tb){
      tb.innerHTML = '';
      (j.runs||[]).forEach(r=>{
        const tr = document.createElement('tr');
        tr.className = 'bg-b-neutral-3 hover:bg-b-neutral-2 transition-1 *:min-w-[180px]';
        const url = r.screenshot_url ? `<a class="link-1" href="${r.screenshot_url}" target="_blank" rel="noreferrer noopener">Bild</a>` : '—';
        tr.innerHTML = `
          <td class="px-16p py-3">${esc(r.created_at||'')}</td>
          <td class="px-16p py-3">${r.points|0}</td>
          <td class="px-16p py-3">${esc(r.status||'pending')}</td>
          <td class="px-16p py-3">${url}</td>`;
        tb.appendChild(tr);
      });
    }
  }

  async function createTeam(){
    const name = el('team_name')?.value.trim();
    if(!name) return alert('Teamname fehlt');
    const fd=new FormData();
    fd.set('csrf', CSRF_TOKEN);
    fd.set('tournament_id', TID);
    fd.set('name', name);
    await api('team_create.php', fd);
    await loadState();
  }

  async function joinTeam(){
    const code = el('join_code')?.value.trim();
    if(!code) return alert('Join-Code fehlt');
    const fd=new FormData();
    fd.set('csrf', CSRF_TOKEN);
    fd.set('tournament_id', TID);
    fd.set('join_code', code);
    await api('team_join.php', fd);
    await loadState();
  }

  async function uploadRun(){
    const kills    = +el('rf_kills')?.value || 0;
    const bosses   = +el('rf_bosses')?.value || 0;
    const tokens   = +el('rf_tokens')?.value || 0;
    const gauntlet = +el('rf_gauntlet')?.value || 0;
    const deaths   = +el('rf_deaths')?.value || 0;
    const shot     = el('rf_shot')?.files?.[0];
    if(!shot) return alert('Screenshot fehlt');

    const msg = el('uploadMsg');
    const btn = el('runForm')?.querySelector('button.btn.btn-md.btn-primary');
    let softTimer;

    try {
      if (btn) { btn.disabled = true; btn.dataset._label = btn.textContent; btn.textContent = 'Lade hoch…'; }
      if (msg) msg.textContent='Upload …';

      softTimer = setTimeout(()=>{ if(msg && msg.textContent==='Upload …') msg.textContent='Upload … (bitte warten)'; }, 10000);

      const fd=new FormData();
      fd.set('csrf', CSRF_TOKEN);
      fd.set('tournament_id', TID);
      fd.set('kills', kills);
      fd.set('bosses', bosses);
      fd.set('tokens', tokens);
      fd.set('gauntlet', gauntlet);
      fd.set('deaths', deaths);
      fd.set('shot', shot);

      const j = await api('run_upload.php', fd, {timeoutMs:45000});

if (j.suspicious) {
  if (msg) {
    msg.textContent = '⚠️ Upload markiert: ' + (j.reason || []).join(', ');
  }
} else {
  if (msg) {
    msg.textContent = '✔️ Upload erfolgreich – wartet auf Freigabe.';
  }
}
      await loadState();
      setTimeout(()=>{ if(msg && msg.textContent.startsWith('Gesendet')) msg.textContent=''; },4000);
    } catch(e){
      if (msg) msg.textContent = 'Fehler: ' + (e?.message || e);
    } finally {
      if (softTimer) clearTimeout(softTimer);
      if (btn) { btn.disabled = false; btn.textContent = btn.dataset._label || 'Upload senden'; }
    }
  }

  // Expose
  window.createTeam = createTeam;
  window.joinTeam   = joinTeam;
  window.uploadRun  = uploadRun;

  // Init
  document.addEventListener('DOMContentLoaded', loadState);
})();
</script>

<?php
$html = ob_get_clean();
render_theme_page($html, 'Mitmachen');

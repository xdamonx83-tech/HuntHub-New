<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/layout.php';

require_auth();
$pdo = db();
$cfg = require __DIR__ . '/../auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
$sessionCookie = $_COOKIE[$cfg['cookies']['session_name']] ?? '';
$csrf = issue_csrf($pdo, $sessionCookie);
$id = (int)($_GET['id'] ?? 0);

ob_start();
?>





































<main>
  <!-- CSRF -->
  <meta name="csrf" content="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">

  <!-- breadcrumb start -->
  <section class="pt-30p">
    <div class="section-pt">
      <div class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
        <div class="container">
          <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
            <div class="lg:col-start-2 lg:col-end-12 col-span-12">
              <h2 id="bugBreadcrumbTitle" class="heading-2 text-w-neutral-1 mb-3">Ticket</h2>
              <ul class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="<?= $APP_BASE ?>/bugs/index.php" class="breadcrumb-link">Bugs</a>
                </li>
                <li class="breadcrumb-item">
                  <span class="breadcrumb-icon"><i class="ti ti-chevrons-right"></i></span>
                </li>
                <li class="breadcrumb-item">
                  <span id="bugBreadcrumbCurrent" class="breadcrumb-current">Details</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
        <div class="overlay-11"></div>
      </div>
    </div>
  </section>
  <!-- breadcrumb end -->

  <!-- library details start -->
  <section class="pt-60p overflow-visible">
    <div class="container">
      <div class="grid grid-cols-12 gap-x-24p gap-y-10">

        <!-- LEFT: Media + Beschreibung -->
        <div class="xxl:col-span-8 col-span-12">
          <!-- Thumbs/Gallery -->
          <div class="mb-30p">
            <div id="bugMediaMain" class="grid grid-cols-12 gap-12p">
              <!-- Wird via JS gefüllt: große Slides -->
            </div>
          </div>

          <!-- Beschreibung -->
          <div>
            <div data-aos="fade-up">
              <h3 class="heading-3 text-w-neutral-1 mb-2.5">Beschreibung</h3>
              <p id="bugDescription" class="text-m-regular text-w-neutral-4 mb-2.5">Lade…</p>
            </div>

            <!-- Kommentar-Liste (Verlauf) -->
            <div class="mt-30p">
              <h3 class="heading-3 text-w-neutral-1 mb-2.5">Verlauf</h3>
              <div id="bugCommentsList" class="grid gap-12p">
                <!-- via JS -->
              </div>
            </div>

            <!-- Kommentar hinzufügen -->
            <div class="mt-30p">
              <h3 class="heading-3 text-w-neutral-1 mb-2.5">Kommentar hinzufügen</h3>
              <form id="cform" onsubmit="return false" class="grid gap-12p">
                   <textarea 
      name="message" 
      rows="4" 
      placeholder="Schreib dein Update..." 
      class="w-full rounded-12 p-16p bg-b-neutral-2 text-w-neutral-1 border border-shap focus:border-primary focus:ring-2 focus:ring-primary/60 resize-none"
    ></textarea>
                <div class="flex gap-12p items-center flex-wrap">
                  <input id="cfile" type="file" multiple accept="image/*,video/*" class="input">
                  <button id="csend" class="button-primary">Senden</button>
                </div>
                <div id="cprev" class="grid md:grid-cols-4 sm:grid-cols-3 grid-cols-2 gap-12p"></div>
              </form>
            </div>
          </div>
        </div>

        <!-- RIGHT: Meta -->
        <div class="xxl:col-span-4 col-span-12 relative">
          <div class="xxl:sticky xxl:top-30">
            <div class="p-40p rounded-12 bg-b-neutral-3">
              <div class="flex items-center gap-3 flex-wrap">
                <img class="avatar size-60p" src="<?= $APP_BASE ?>/assets/images/users/avatar7.png" alt="user" />
                <div>
                  <span id="bugOwnerLabel" class="text-xl-medium text-w-neutral-1 mb-1">Ticket</span>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-16p py-24p *:flex *:items-center *:justify-between *:flex-wrap *:gap-16p border-b border-shap">
                <div>
                  <span class="text-m-regular text-w-neutral-4">Titel</span>
                  <span id="bugMetaTitle" class="text-m-medium text-w-neutral-1">–</span>
                </div>
                <div>
                  <span class="text-m-regular text-w-neutral-4">ID</span>
                  <span id="bugMetaId" class="text-m-medium text-w-neutral-1">–</span>
                </div>
                <div>
                  <span class="text-m-regular text-w-neutral-4">Aktualisiert</span>
                  <span id="bugMetaDate" class="text-m-medium text-w-neutral-1">–</span>
                </div>
                <div>
                  <span class="text-m-regular text-w-neutral-4">Status</span>
                  <span id="bugMetaStatus" class="text-m-medium text-w-neutral-1">–</span>
                </div>
                <div>
                  <span class="text-m-regular text-w-neutral-4">Belohnung</span>
                  <span id="bugMetaReward" class="text-m-medium text-w-neutral-1">–</span>
                </div>
              </div>

              <div class="pt-24p">
                <span class="text-m-medium text-w-neutral-1 mb-20p">Anhänge</span>
                <div id="bugAttachmentList" class="grid grid-cols-2 gap-12p">
                  <!-- thumbs via JS -->
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>
  <!-- library details end -->
</main>

<script>
(() => {
  const BASE  = "<?= $APP_BASE ?>";
  const CSRF  = document.querySelector('meta[name="csrf"]')?.content || '';
  const BUG_ID = <?= $id ?>;

  const $ = s => document.querySelector(s);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
  const toDT = s => s ? new Date(String(s).replace(' ','T')).toLocaleString() : '–';

  async function loadView(){
    try{
      const r = await fetch(`${BASE}/api/bugs/view.php?id=${BUG_ID}`, { credentials:'same-origin' });
      const j = await r.json().catch(()=>null);
      if (!r.ok || !j?.ok) throw new Error(j?.error || `HTTP ${r.status}`);

      const b        = j.bug || {};
      const files    = j.attachments || [];
      const comments = j.comments || [];

      // Breadcrumb + Titel
      $('#bugBreadcrumbTitle').textContent  = `Ticket #${b.id || ''}`;
      $('#bugBreadcrumbCurrent').textContent = esc(b.title || 'Details');

      // Meta rechts
      $('#bugMetaTitle').textContent  = b.title || '–';
      $('#bugMetaId').textContent     = b.id ? `#${b.id}` : '–';
      $('#bugMetaDate').textContent   = toDT(b.updated_at || b.created_at);
      $('#bugMetaStatus').innerHTML   = `<b>${esc(b.status || '–')}</b> • Priorität: <b>${esc((b.priority||'').toUpperCase())}</b>`;
      $('#bugMetaReward').innerHTML   =
        `${b.xp_awarded?`XP: +${b.xp_awarded}`:'–'}${b.badge_code?` &nbsp;|&nbsp; Badge: ${esc(b.badge_code)}`:''}`;

      // Beschreibung
      $('#bugDescription').textContent = b.description || '—';

      // Attachments – große Ansicht (links)
      const main = $('#bugMediaMain'); main.innerHTML = '';
      if (files.length){
        files.forEach(a => {
          const wrap = document.createElement('div');
          wrap.className = 'col-span-12 rounded-12 overflow-hidden';
          if (a.kind === 'image'){
            wrap.innerHTML = `<a href="${a.path}" target="_blank" rel="noreferrer noopener">
              <img class="w-full xxl:h-[480px] xl:h-[400px] md:h-[380px] sm:h-[320px] h-[280px] object-cover" src="${a.path}" alt="attachment">
            </a>`;
          } else if (a.kind === 'video'){
            wrap.innerHTML = `<video class="w-full xxl:h-[480px] xl:h-[400px] md:h-[380px] sm:h-[320px] h-[280px] object-cover" src="${a.path}" controls playsinline></video>`;
          } else {
            wrap.innerHTML = `<a class="text-primary underline" href="${a.path}" target="_blank">${esc(a.path)}</a>`;
          }
          main.appendChild(wrap);
        });
      } else {
        main.innerHTML = `<div class="col-span-12 text-w-neutral-4">Keine Anhänge.</div>`;
      }

      // Attachments – kleine Thumbs (rechts)
      const thumbs = $('#bugAttachmentList'); thumbs.innerHTML = '';
      files.forEach(a => {
        const card = document.createElement('div');
        card.className = 'rounded-12 overflow-hidden border border-shap';
        if (a.kind === 'image'){
          card.innerHTML = `<a href="${a.path}" target="_blank"><img class="w-full h-[120px] object-cover" src="${a.path}" alt=""></a>`;
        } else if (a.kind === 'video'){
          card.innerHTML = `<video class="w-full h-[120px] object-cover" src="${a.path}" controls playsinline></video>`;
        } else {
          card.innerHTML = `<a class="block p-10 text-primary underline" href="${a.path}" target="_blank">${esc(a.path.split('/').pop())}</a>`;
        }
        thumbs.appendChild(card);
      });

      // Kommentare
      const list = $('#bugCommentsList'); list.innerHTML = '';
      if (!comments.length){
        list.innerHTML = `<em class="text-w-neutral-4">Keine Kommentare.</em>`;
      } else {
        comments.forEach(c => {
          const who = (c.is_admin==='1' || c.is_admin===1) ? 'Admin' : 'User';
          const box = document.createElement('div');
          box.className = 'p-16p rounded-12 border border-shap';
          box.innerHTML = `
            <div class="text-xs text-w-neutral-4 mb-1">${esc(who)} • ${toDT(c.created_at)}</div>
            <div class="text-w-neutral-1 whitespace-pre-wrap">${esc(c.message || '')}</div>
          `;
          list.appendChild(box);
        });
      }
    } catch(e){
      // minimaler Fallback
      $('#bugDescription').textContent = `Fehler beim Laden: ${e && e.message ? e.message : String(e)}`;
    }
  }

  // Kommentar senden (+ Upload)
  $('#csend')?.addEventListener('click', async () => {
    const msgEl  = document.querySelector('#cform textarea[name=message]');
    const fileEl = document.getElementById('cfile');
    const msg    = (msgEl?.value || '').trim();
    const files  = Array.from(fileEl?.files || []);

    if (!msg && !files.length) { alert('Bitte Nachricht oder Datei senden.'); return; }

    try{
      if (msg){
        const fd = new FormData();
        fd.append('bug_id', String(BUG_ID));
        fd.append('message', msg);
        const r = await fetch(`${BASE}/api/bugs/comment.php`, { method:'POST', headers:{'X-CSRF': CSRF}, body: fd });
        const j = await r.json().catch(()=>null);
        if (!r.ok || !j?.ok) throw new Error(j?.error || `HTTP ${r.status}`);
      }

      for (const f of files){
        const fd = new FormData();
        fd.append('bug_id', String(BUG_ID));
        fd.append('file', f);
        const r = await fetch(`${BASE}/api/bugs/upload.php`, { method:'POST', headers:{'X-CSRF': CSRF}, body: fd });
        const j = await r.json().catch(()=>null);
        if (!r.ok || !j?.ok) throw new Error(j?.error || `HTTP ${r.status}`);
      }

      // Reset + reload
      if (msgEl) msgEl.value = '';
      if (fileEl) fileEl.value = '';
      $('#cprev').innerHTML = '';
      await loadView();
    } catch(e){
      alert(`Fehler beim Senden: ${e && e.message ? e.message : String(e)}`);
    }
  });

  // File-Preview
  $('#cfile')?.addEventListener('change', (e) => {
    const box = $('#cprev'); box.innerHTML = '';
    Array.from(e.target.files || []).slice(0, 8).forEach(f => {
      const w = document.createElement('div');
      w.className = 'rounded-12 overflow-hidden border border-shap p-6';
      if (f.type.startsWith('image/')){
        const i = new Image(); i.src = URL.createObjectURL(f); i.className = 'w-full h-[120px] object-cover';
        w.appendChild(i);
      } else if (f.type.startsWith('video/')){
        const v = document.createElement('video');
        v.src = URL.createObjectURL(f); v.controls = true; v.className = 'w-full h-[120px] object-cover';
        w.appendChild(v);
      } else {
        w.textContent = f.name;
      }
      box.appendChild(w);
    });
  });

  // Init
  loadView();
})();
</script>

<?php
$content = ob_get_clean();
render_theme_page($content, 'Ticket ansehen');

<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/guards.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../auth/db.php';

$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$pdo = db();

ob_start();
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Frag Hunthub – Chat (Hunt: Showdown)</title>
<style>

  .chat-wrap{
    width:min(920px, 100%);
    border-radius:28px; overflow:hidden;
    background:linear-gradient(180deg, #0e1520 0%, #0b1017 100%);
    box-shadow: 0 20px 60px rgba(0,0,0,.35), 0 2px 0 rgba(255,255,255,.04) inset;
    border:1px solid #0f1722;
  }
  .chat-head{
    display:flex; gap:12px; align-items:center; padding:18px 20px;
    background:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,0));
    border-bottom:1px solid var(--border);
  }
  .avatar{width:42px; height:42px; border-radius:50%; background:#111; overflow:hidden; flex:0 0 42px; display:grid; place-items:center;}
  .avatar img{width:100%; height:100%; object-fit:cover}
  .head-meta{line-height:1.2}
  .title{font-weight:700; letter-spacing:.2px}
  .subtitle{font-size:.85rem; color:var(--muted)}
  .chip{margin-left:auto; font-size:.78rem; color:#a7f3d0; background:rgba(34,197,94,.15);
        border:1px solid rgba(34,197,94,.35); padding:6px 10px; border-radius:999px}

  .chat-body{
    height:min(62vh, 560px);
    background: radial-gradient(900px 360px at 10% -10%, #0f1720 0%, transparent 60%),
                radial-gradient(800px 420px at 100% 110%, #06111a 0%, transparent 70%),
                linear-gradient(180deg, var(--card-dim), var(--card-bg));
    padding:20px; overflow:auto; scrollbar-width:thin; border-bottom:1px solid var(--border);
  }
  .row{display:flex; gap:10px; margin:14px 0; align-items:flex-end}
  .row.user{justify-content:flex-end}
  .bubble{
    max-width:min(78%, 740px);
    padding:12px 14px; border-radius:16px 16px 16px 6px;
    background:#111926; border:1px solid #16202c; color:var(--text);
    box-shadow: 0 2px 0 rgba(255,255,255,.03) inset;
  }
  .row.user .bubble{
    background:#0d1f17; border-color:#163323;
    border-radius:16px 16px 6px 16px;
  }
  .bubble .sources{margin-top:12px; font-size:.85rem; color:var(--muted)}
  .bubble .sources a{color:#7dd3fc; text-decoration:none}
  .bubble .sources a:hover{text-decoration:underline}

  .typing{
    width:52px; height:34px; border-radius:20px; background:#0f1720; border:1px solid #1a2330;
    display:flex; align-items:center; justify-content:center; gap:4px; padding:0 10px; margin-left:4px;
  }
  .dot{width:6px; height:6px; border-radius:50%; background:#94a3b8; opacity:.35; animation:blink 1.2s infinite}
  .dot:nth-child(2){animation-delay:.2s}
  .dot:nth-child(3){animation-delay:.4s}
  @keyframes blink{0%,80%,100%{opacity:.25} 40%{opacity:1}}

  .chat-footer{
    display:flex; gap:10px; align-items:center; padding:14px 16px;
    background:linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,.03));
  }
  .input{
    flex:1; display:flex; align-items:center; gap:10px; background:#0c131c; border:1px solid #16202c;
    padding:10px 12px; border-radius:14px;
  }
  textarea{
    flex:1; resize:none; border:0; outline:none; background:transparent; color:var(--text);
    font:inherit; line-height:1.35; max-height:120px;
  }
  .controls{display:flex; gap:10px; align-items:center}
  .pill{background:#0c131c; color:#9fb0be; border:1px solid #16202c; padding:8px 10px; border-radius:10px; font-size:.85rem}
  select.pill{cursor:pointer}
  .send{
    background:var(--accent); color:#05210f; font-weight:700; letter-spacing:.3px;
    padding:10px 18px; border:0; border-radius:12px; cursor:pointer;
    box-shadow:0 8px 22px rgba(34,197,94,.25), inset 0 1px 0 rgba(255,255,255,.25);
  }
.hh-fb { margin-top: .5rem; display:flex; gap:.5rem; align-items:center; }
.hh-fb-btn { padding:.25rem .5rem; border-radius:.375rem; background:#2dd4bf22; border:1px solid #2dd4bf44; cursor:pointer; }
.hh-fb-btn:hover { background:#2dd4bf33; }
.hh-fb-reason { padding:.25rem .5rem; border:1px solid #ffffff22; background:#ffffff08; border-radius:.375rem; min-width:220px; }
.hh-fb-status { margin-left:.5rem; font-size:.8em; opacity:.8; }

</style>
</head>







 <main>

            <!-- breadcrumb start -->
            <section class="pt-30p">
                <div class="section-pt">
                    <div
                        class="relative bg-[url('../images/photos/breadcrumbImg.png')] bg-cover bg-no-repeat rounded-24 overflow-hidden">
                        <div class="container">
                            <div class="grid grid-cols-12 gap-30p relative xl:py-[130px] md:py-30 sm:py-25 py-20 z-[2]">
                                <div class="lg:col-start-2 lg:col-end-12 col-span-12">
                                    <h2 class="heading-2 text-w-neutral-1 mb-3">
                                        Frag Hunthub – Chat (Hunt: Showdown)
                                    </h2>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="#" class="breadcrumb-link">
                                                Home
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-icon">
                                                <i class="ti ti-chevrons-right"></i>
                                            </span>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <span class="breadcrumb-current">HuntHub Ki Bot</span>
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

            <!-- terms conditions section start -->
            <section class="section-pb pt-60p">
                <div class="container">
                    <div class="grid grid-cols-12 gap-30p">
                        <div class="4xl:col-start-3 4xl:col-end-11 xl:col-start-2 xl:col-end-12 col-span-12">
                            <div class="grid grid-cols-1 gap-y-40p">
                                <div data-aos="fade-up">
                          
                                    <p class="text-l-regular text-w-neutral-4">
                           
						   
						   
						   
						   
						   
						   
						     <div class="chat-wrap" id="chat">
    <div class="chat-head">
      <div class="avatar"><img src="https://hunthub.online/qa/1.webp" alt=""></div>
      <div class="head-meta">
        <div class="title">Hunthub – Hunt: Showdown</div>
        <div class="subtitle">Frage mich zu Updates, Events, Patchnotes – ich nenne Quellen.</div>
      </div>
      <span class="chip">online</span>
    </div>

    <div class="chat-body" id="log" aria-live="polite">
      <!-- Begrüßung -->
      <div class="row">
        <div class="avatar"><img src="https://hunthub.online/qa/1.webp" alt=""></div>
        <div class="bubble">
          **tl;dr:** Stell mir einfach deine Frage – ich fasse kurz auf Deutsch zusammen und hänge die Quellen an.<br><br>
          • Beispiel: *Was änderte sich in Update 24?*<br>
          • Beispiel: *Wann läuft das aktuelle Event & was bringt es?*<br>
          • Beispiel: *Wie funktioniert Gauntlet?*
        </div>
      </div>
    </div>

    <div class="chat-footer">
      <div class="input">
        <textarea id="msg" rows="1" placeholder="Type message… (Enter = senden, Shift+Enter = Zeilenumbruch)"></textarea>
      </div>
      <div class="controls">
        <select id="lang" class="pill">
          <option value="de" selected>DE</option>
          <option value="en">EN</option>
        </select>
        <select id="style" class="pill">
          <option value="bullets" selected>Bullets</option>
          <option value="paragraph">Kurztext</option>
        </select>
        <button class="send" id="send">SEND</button>
      </div>
    </div>
    <div class="hint">Hinweis: Jede Nachricht ist eine eigenständige Frage (RAG). Verlauf wird nicht an die API geschickt.</div>
  </div>

<script>
(() => {
  const API_ENDPOINT = '/api/qa/ask.php';   // ggf. anpassen
  const FEEDBACK_ENDPOINT = '/api/qa/feedback.php';

  const log = document.getElementById('log');
  const msg = document.getElementById('msg');
  const btn = document.getElementById('send');
  const lang = document.getElementById('lang');
  const styleSel = document.getElementById('style');

  function addRow(html, who='bot'){
    const row = document.createElement('div');
    row.className = 'row' + (who==='user' ? ' user' : '');
    row.innerHTML = `
      ${who==='bot' ? '<div class="avatar"><img src="https://hunthub.online/qa/1.webp" alt=""></div>' : ''}
      <div class="bubble">${html}</div>
      ${who==='user' ? '<div class="avatar"><img src="https://www.gravatar.com/avatar/?d=mp" alt=""></div>' : ''}
    `;
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
    return row.querySelector('.bubble'); // <- wichtig: wir geben die Bubble zurück
  }

  function addTyping(){
    const row = document.createElement('div');
    row.className = 'row';
    row.innerHTML = `
      <div class="avatar"><img src="https://hunthub.online/qa/1.webp" alt=""></div>
      <div class="typing"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
    `;
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
    return row;
  }

  // --- Feedback-Leiste an eine Bot-Bubble hängen
  function attachFeedbackBar(container, logId) {
    const bar = document.createElement('div');
    bar.className = 'hh-fb mt-2 flex items-center gap-2 text-sm opacity-80';
    bar.innerHTML = `
      <button class="hh-fb-btn" data-vote="1">👍 Hilfreich</button>
      <button class="hh-fb-btn" data-vote="-1">👎 Nicht hilfreich</button>
      <input class="hh-fb-reason" placeholder="optional: warum?" />
      <span class="hh-fb-status"></span>
    `;
    container.appendChild(bar);

    bar.querySelectorAll('.hh-fb-btn').forEach(btnEl => {
      btnEl.addEventListener('click', async () => {
        const vote   = parseInt(btnEl.dataset.vote, 10);
        const reason = bar.querySelector('.hh-fb-reason')?.value || '';
        const status = bar.querySelector('.hh-fb-status');
        try {
          const res = await fetch(FEEDBACK_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ log_id: String(logId), vote: String(vote), reason })
          }).then(r => r.json());
          status.textContent = res.ok ? 'Danke für dein Feedback!' : 'Feedback nicht gespeichert.';
          // einmalig deaktivieren, damit nicht gespammt wird:
          bar.querySelectorAll('button, input').forEach(el => el.disabled = true);
          bar.style.opacity = 0.7;
        } catch {
          status.textContent = 'Netzwerkfehler beim Feedback.';
        }
      });
    });
  }

  async function ask(q){
    btn.disabled = true;
    const userBubble = addRow(escapeHtml(q), 'user');
    const typing = addTyping();
    try{
      const form = new URLSearchParams({ q, lang: lang.value, style: styleSel.value });
      const r = await fetch(API_ENDPOINT, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: form
      });
      const data = await r.json();
      typing.remove();
      if(!data.ok){
        addRow(`<span style="color:#f87171">Fehler:</span> ${escapeHtml(data.message||'Unbekannt')}`);
        return;
      }
      // Bot-Antwort einfügen
      const bubble = addRow(data.answer.html || '—');
      // Feedback-Leiste anhängen, wenn die API die Log-ID liefert
      if (data.answer && typeof data.answer.log_id !== 'undefined' && data.answer.log_id !== null) {
        attachFeedbackBar(bubble, data.answer.log_id);
      }
    }catch(e){
      typing.remove();
      addRow(`<span style="color:#f87171">Netzwerkfehler:</span> ${escapeHtml(String(e))}`);
    }finally{
      btn.disabled = false;
      msg.focus();
    }
  }

  // helpers
  function escapeHtml(s){
    return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]));
  }
  function autoResize(){
    msg.style.height = 'auto';
    msg.style.height = Math.min(msg.scrollHeight, 120)+'px';
  }

  // interactions
  btn.addEventListener('click', () => {
    const q = msg.value.trim();
    if(!q) return;
    ask(q);
    msg.value = ''; autoResize();
  });
  msg.addEventListener('input', autoResize);
  msg.addEventListener('keydown', (e) => {
    if(e.key === 'Enter' && !e.shiftKey){
      e.preventDefault();
      btn.click();
    }
  });

  // focus
  msg.focus();
})();
</script>

						   
						   
						   
						   
						   
						   
						   
						   
						   
						   
						   
                                    </p>
                                </div>
                    
                         
             
                      
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- terms conditions section end -->

        </main>











<body>















</body>
</html>
<?php
$content = ob_get_clean();
$title   = ($lang === 'en' ? 'Ask Hunthub – Hunt: Showdown' : 'Frag Hunthub – Hunt: Showdown');
render_theme_page($content, $title);
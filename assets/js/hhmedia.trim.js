
/* HHMedia Trim Modal – minimal, schnell, ohne Framework
 * window.HHMedia.openTrim({ uploadUrl, csrf }) -> Promise<{ok, video, poster, reel_url}>
 */
(function(){
  if (window.HHMedia && typeof window.HHMedia.openTrim === 'function') return;

  // ---- Styles einmalig injizieren
  (function ensureStyles(){
    if (document.getElementById('hh-trim-css')) return;
    const st = document.createElement('style');
    st.id = 'hh-trim-css';
    st.textContent = `
      #hh-trim-modal{position:fixed;inset:0;z-index:2147483645;display:flex;align-items:center;justify-content:center}
      #hh-trim-modal .back{position:absolute;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(2px)}
      #hh-trim-modal .panel{
        position:relative;z-index:1;width:min(1200px,96vw);height:min(78vh,calc(var(--hh100vh,100vh)-12vh));
        background:rgba(16,16,18,.98);border:1px solid rgba(255,255,255,.08);border-radius:16px;
        display:grid;grid-template-rows:auto 1fr auto;box-shadow:0 20px 60px rgba(0,0,0,.55)
      }
      #hh-trim-modal .hd{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.08)}
      #hh-trim-modal .bd{display:grid;grid-template-rows:1fr auto;gap:12px;padding:12px 16px;min-height:0}
      #hh-trim-modal .ft{display:flex;gap:8px;justify-content:flex-end;padding:12px 16px;border-top:1px solid rgba(255,255,255,.08)}
      #hh-trim-modal video{width:100%;height:100%;object-fit:contain;background:#000;border-radius:10px}
      .trim-timeline{position:relative;height:68px;border-radius:10px;background:rgba(255,255,255,.06);overflow:hidden;user-select:none}
      .trim-track{position:absolute;inset:0}
      .trim-range{position:absolute;top:0;bottom:0;background:rgba(0,170,255,.2);outline:1px solid rgba(0,170,255,.5);border-radius:6px}
      .trim-handle{position:absolute;top:0;width:12px;height:100%;background:rgba(0,170,255,.9);cursor:ew-resize}
      .trim-handle:before{content:"";position:absolute;left:50%;top:50%;translate:-50% -50%;width:4px;height:40%;background:#fff;border-radius:2px}
      .timebar{display:flex;gap:12px;align-items:center;font:12px/1.3 system-ui;opacity:.8}
      .btn{appearance:none;border:1px solid rgba(255,255,255,.16);background:transparent;color:#fff;border-radius:10px;padding:8px 12px;cursor:pointer}
      .btn.primary{background:#5865f2;border-color:#5865f2}
      .btn:disabled{opacity:.5;cursor:not-allowed}
      .chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);padding:4px 8px;border-radius:999px}
    `;
    document.head.appendChild(st);
  })();

  function fmt(t){ if(!isFinite(t)||t<0)t=0; const m=Math.floor(t/60), s=Math.floor(t%60); return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`; }

  async function openTrim(opts){
    return new Promise((resolve)=>{
      let file;                      // ausgewählte Datei
      let start = 0, end = 0;        // Sekunden
      let dragging = null;           // 'L' | 'R' | 'RANGE'
      let videoEl, rangeEl, hL, hR, playBt, uploadBt, posLabel;

      // --- DOM
      const wrap = document.createElement('div');
      wrap.id = 'hh-trim-modal';
      wrap.innerHTML = `
        <div class="back"></div>
        <div class="panel">
          <div class="hd"><div style="font-weight:600">Video zuschneiden</div>
            <button class="btn" data-x>✕</button>
          </div>
          <div class="bd">
            <div style="min-height:0;display:grid;grid-template-columns:1fr;grid-template-rows:1fr auto;gap:10px">
              <div style="min-height:0"><video playsinline></video></div>
              <div>
                <div class="trim-timeline">
                  <div class="trim-track"></div>
                  <div class="trim-range"></div>
                  <div class="trim-handle" data-h="L" title="Start"></div>
                  <div class="trim-handle" data-h="R" title="Ende"></div>
                </div>
                <div class="timebar" style="margin-top:8px">
                  <span class="chip">Start <strong id="tl">00:00</strong></span>
                  <span class="chip">Ende <strong id="tr">00:00</strong></span>
                  <span class="chip">Dauer <strong id="td">00:00</strong></span>
                  <span style="margin-left:auto" class="chip">Position <strong id="tp">00:00</strong></span>
                </div>
              </div>
            </div>
          </div>
          <div class="ft">
            <input type="file" accept="video/*" hidden>
            <button class="btn" data-pick>Video wählen…</button>
            <button class="btn" data-play>▶︎ Abspielen</button>
            <button class="btn primary" data-ok disabled>Zuschneiden & Hochladen</button>
          </div>
        </div>`;
      document.body.appendChild(wrap);

      const $ = (s,el=wrap)=>el.querySelector(s);
      const close = ()=>{ wrap.remove(); resolve({ok:false, cancel:true}); };

      videoEl   = $('video');
      posLabel  = $('#tp');
      rangeEl   = $('.trim-range');
      hL        = $('.trim-handle[data-h="L"]');
      hR        = $('.trim-handle[data-h="R"]');
      playBt    = $('[data-play]');
      uploadBt  = $('[data-ok]');

      // Datei wählen
      const fileInput = $('input[type="file"]');
      $('[data-pick]').addEventListener('click', ()=> fileInput.click());
      $('[data-x]').addEventListener('click', close);
      $('.back').addEventListener('click', close);

      fileInput.addEventListener('change', ()=> {
        if (!fileInput.files?.length) return;
        file = fileInput.files[0];
        const url = URL.createObjectURL(file);
        videoEl.src = url;
        videoEl.addEventListener('loadedmetadata', ()=>{
          start = 0; end = videoEl.duration || 0;
          updateTimeline();
          uploadBt.disabled = false;
        }, {once:true});
      });

      // Live Position
      videoEl.addEventListener('timeupdate', ()=> { posLabel.textContent = fmt(videoEl.currentTime); });

      // Timeline helpers
      const tl = $('.trim-timeline');
      const labL = $('#tl'), labR = $('#tr'), labD = $('#td');

      function updateTimeline(){
        const dur = Math.max(0, (videoEl.duration||0));
        if (!dur) return;
        start = Math.max(0, Math.min(start, dur));
        end   = Math.max(start, Math.min(end||dur, dur));
        const W = tl.clientWidth;
        const xL = (start/dur)*W;
        const xR = (end/dur)*W;
        rangeEl.style.left  = Math.round(xL)+'px';
        rangeEl.style.width = Math.max(8, Math.round(xR-xL))+'px';
        hL.style.left = Math.round(xL-6)+'px';
        hR.style.left = Math.round(xR-6)+'px';
        labL.textContent = fmt(start);
        labR.textContent = fmt(end);
        labD.textContent = fmt(Math.max(0, end-start));
        if (videoEl.currentTime < start || videoEl.currentTime > end) videoEl.currentTime = start;
      }

      function pxToTime(px){
        const dur = videoEl.duration||0;
        const W   = tl.clientWidth||1;
        return Math.max(0, Math.min(dur, (px/W)*dur));
      }

      function onDown(e){
        if (!videoEl.duration) return;
        const rect = tl.getBoundingClientRect();
        const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
        const target = e.target.closest('.trim-handle')?.dataset.h;
        if (target) dragging = target; else {
          // drag Bereich
          const xL = parseFloat(rangeEl.style.left||'0');
          const xW = parseFloat(rangeEl.style.width||'0');
          const inside = x>=xL && x<=xL+xW;
          dragging = inside ? 'RANGE' : (x < xL ? 'L' : 'R');
        }
        onMove(e);
        e.preventDefault();
      }

      function onMove(e){
        if (!dragging) return;
        const rect = tl.getBoundingClientRect();
        const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
        const t = pxToTime(x);
        if (dragging === 'L'){ start = Math.min(t, end-0.05); }
        else if (dragging === 'R'){ end = Math.max(t, start+0.05); }
        else { // RANGE verschieben
          const dur = videoEl.duration||0;
          const len = end-start;
          let ns = t - (len/2);
          ns = Math.max(0, Math.min(dur-len, ns));
          start = ns; end = ns + len;
        }
        updateTimeline();
      }
      function onUp(){ dragging = null; }

      tl.addEventListener('mousedown', onDown);
      tl.addEventListener('touchstart', onDown, {passive:false});
      window.addEventListener('mousemove', onMove, {passive:false});
      window.addEventListener('touchmove', onMove, {passive:false});
      window.addEventListener('mouseup', onUp);
      window.addEventListener('touchend', onUp);

      // Play/Pause innerhalb Range
      playBt.addEventListener('click', ()=>{
        if (!videoEl.duration) return;
        if (videoEl.paused){ videoEl.play(); playBt.textContent = '⏸︎ Pause'; }
        else { videoEl.pause(); playBt.textContent = '▶︎ Abspielen'; }
      });
      videoEl.addEventListener('timeupdate', ()=>{ if (videoEl.currentTime >= end) videoEl.currentTime = start; });

      // Upload (serverseitiges Trimmen via ffmpeg)
      uploadBt.addEventListener('click', async ()=>{
        if (!file) return;
        uploadBt.disabled = true; uploadBt.textContent = 'Wird verarbeitet…';
        try{
          const fd = new FormData();
          if (opts?.csrf) fd.set('csrf', opts.csrf);
          fd.set('file', file);
          fd.set('start', String(start.toFixed(3)));
          fd.set('end',   String(end.toFixed(3)));
          fd.set('create_reel', '1');                 // Reel anlegen
          // optionale poster-Zeit: Mitte
          const mid = start + Math.max(0.1, (end-start)/2);
          fd.set('poster_time', String(mid.toFixed(2)));

          const res = await fetch(opts?.uploadUrl || '/api/video/upload.php', {
            method:'POST', body:fd, credentials:'same-origin',
            headers:{'X-Requested-With':'XMLHttpRequest'}
          });
          const j = await res.json().catch(()=> ({}));
          if (!res.ok || j?.ok===false) throw new Error(j?.error || `http_${res.status}`);
          // Erfolg
          wrap.remove();
          resolve({ ok:true, video:j.video, poster:j.poster||'', reel_url:j.reel_url||'' });
        }catch(err){
          alert('Video-Upload fehlgeschlagen.');
          uploadBt.disabled = false; uploadBt.textContent = 'Zuschneiden & Hochladen';
        }
      });

      // auto: wenn schon ein File vom Browser kommt (Drag&Drop o.ä.)
      // -> du kannst window.HHMedia.openTrim({file}) später erweitern
    });
  }

  window.HHMedia = Object.assign({}, window.HHMedia, { openTrim });
})();


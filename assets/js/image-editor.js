// /js/image-editor.js
// Bildeditor für <input type="file" id="replyFile">
// Tools: Zuschneiden, Text, Sticker, Zeichnen, Effekte
// Ebenen: base (Bild+Effekte+Crop), draw (Freihand), text (Text/Sticker), ui (Auswahl)
// Export: base + draw + text -> JPEG. Input-Befüllung + 2 Fallbacks (formdata + sendBtn Capture) + Vorschau.

function blobToDataURL(blob) {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(r.result);
    r.onerror = reject;
    r.readAsDataURL(blob);
  });
}

(function () {
  'use strict';

  const fileInput = document.getElementById('replyFile');
  if (!fileInput) return;

  let _ieEditedBlob = null;
  let _ieEditedName = '';

  /* ---------- Styles (Overlay) ---------- */
  if (!document.getElementById('imgEditorStyles')) {
    const css = `
#imgEditorOverlay{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:10050;display:none}
#imgEditorOverlay.open{display:flex}
.ie-shell{margin:auto;background:#111;border:1px solid rgba(255,255,255,.12);border-radius:14px;box-shadow:0 18px 60px rgba(0,0,0,.6);width:min(96vw,1100px);max-height:92vh;display:flex;flex-direction:column;overflow:hidden}
.ie-head{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08);background:#151515;color:#e5e7eb}
.ie-head .title{font-size:14px;opacity:.9}
.ie-head .actions{display:flex;gap:8px}
.ie-btn{background:#1f1f1f;border:1px solid rgba(255,255,255,.12);color:#e5e7eb;border-radius:10px;padding:6px 10px;font-size:13px;cursor:pointer}
.ie-btn.primary{background:#2563eb;border-color:#2563eb}
.ie-btn:disabled{opacity:.6;cursor:not-allowed}
.ie-body{display:grid;grid-template-columns:1fr 280px;gap:0;min-height:360px;max-height:calc(92vh - 96px)}
@media (max-width: 860px){.ie-body{grid-template-columns:1fr}.ie-tools{order:-1}}
.ie-canvasWrap{position:relative;background:#0e0e0f;display:flex;align-items:center;justify-content:center}
.ie-layer{position:absolute;inset:0}
#ieBase{position:relative;display:block;background:#000;max-width:100%;max-height:100%}
#ieDraw,#ieText{pointer-events:auto}
#ieUI{pointer-events:none}
.ie-tools{position:relative;z-index:2;pointer-events:auto;border-left:1px solid rgba(255,255,255,.08);background:#131313;color:#d1d5db;overflow:auto}
.ie-panel{padding:12px;border-bottom:1px solid rgba(255,255,255,.06)}
.ie-panel h3{font-size:13px;margin:0 0 8px 0;color:#e5e7eb;opacity:.9}
.ie-row{display:flex;align-items:center;gap:8px;margin:8px 0}
.ie-row input[type="color"]{width:34px;height:28px;border:0;padding:0;background:transparent}
.ie-row input[type="range"]{flex:1}
.ie-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:6px}
.ie-grid button{font-size:20px;line-height:1;padding:6px 4px;background:transparent;border:1px solid rgba(255,255,255,.12);border-radius:8px;color:#e5e7eb;cursor:pointer}
.ie-toolbar{display:flex;gap:6px;flex-wrap:wrap}
.ie-tool{background:#1c1c1c;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:6px 10px;font-size:12px;cursor:pointer;color:#cbd5e1}
.ie-tool.active{outline:2px solid #2563eb}
.ie-foot{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-top:1px solid rgba(255,255,255,.08);background:#151515}
.ie-note{font-size:12px;color:#9ca3af}
.ie-status{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:14px;gap:8px;background:#0e0e0f}
.ie-status .spin{width:16px;height:16px;border:2px solid #999;border-top-color:transparent;border-radius:50%;animation:ieSpin .9s linear infinite}
@keyframes ieSpin{to{transform:rotate(360deg)}}
.ie-alert{color:#fca5a5;font-size:13px;padding:6px 0}
#ieCropHud{position:absolute;inset:0;pointer-events:auto}
#ieCropRect{position:absolute;border:2px dashed #60a5fa;background:rgba(37,99,235,.12)}
`;
    const s = document.createElement('style');
    s.id = 'imgEditorStyles';
    s.appendChild(document.createTextNode(css));
    document.head.appendChild(s);
  }

  /* ---------- Overlay/Canvas ---------- */
  let overlay, baseCanvas, baseCtx, drawCanvas, drawCtx, textCanvas, textCtx, uiCanvas, uiCtx, statusDiv;
  let srcImage = null;
  let state = null;

  function buildOverlay(){
    if (overlay) return overlay;
    overlay = document.createElement('div');
    overlay.id = 'imgEditorOverlay';
    overlay.innerHTML = `
      <div class="ie-shell" role="dialog" aria-label="Bild bearbeiten">
        <div class="ie-head">
          <div class="title">Bild bearbeiten</div>
          <div class="actions">
            <span class="ie-alert" id="ieAlert" hidden></span>
            <button class="ie-btn" data-act="cancel">Abbrechen</button>
            <button class="ie-btn primary" data-act="apply">Speichern</button>
          </div>
        </div>
        <div class="ie-body">
          <div class="ie-canvasWrap" id="ieWrap">
            <div class="ie-status" id="ieStatus"><span class="spin"></span> <span>Lade Bild…</span></div>
            <canvas id="ieBase"></canvas>
            <canvas id="ieDraw" class="ie-layer"></canvas>
            <canvas id="ieText" class="ie-layer"></canvas>
            <canvas id="ieUI"   class="ie-layer"></canvas>
            <div id="ieCropHud" hidden><div id="ieCropRect"></div></div>
          </div>
          <div class="ie-tools">
            <div class="ie-panel">
              <div class="ie-toolbar">
                <button class="ie-tool" data-tool="move">Verschieben</button>
                <button class="ie-tool" data-tool="crop">Zuschneiden</button>
                <button class="ie-tool" data-tool="text">Text</button>
                <button class="ie-tool" data-tool="draw">Zeichnen</button>
                <button class="ie-tool" data-tool="stickers">Sticker</button>
                <button class="ie-tool" data-tool="fx">Effekte</button>
              </div>
            </div>

            <div class="ie-panel" id="panel-crop" hidden>
              <h3>Zuschneiden</h3>
              <div class="ie-row">
                <label>Verhältnis</label>
                <select id="cropAspect">
                  <option value="free">Frei</option>
                  <option value="1:1">1:1</option>
                  <option value="4:3">4:3</option>
                  <option value="16:9">16:9</option>
                </select>
                <button class="ie-btn" id="cropReset">Reset</button>
              </div>
              <div class="ie-row"><small>Ziehen, um Auswahl zu erstellen/ändern.</small></div>
            </div>

            <div class="ie-panel" id="panel-text" hidden>
              <h3>Text</h3>
              <div class="ie-row"><input id="tText" type="text" placeholder="Dein Text" style="flex:1"></div>
              <div class="ie-row">
                <label>Font</label>
                <select id="tFont">
                  <option>Arial</option><option>Roboto</option><option>Georgia</option>
                  <option>Impact</option><option>Verdana</option><option>Courier New</option>
                </select>
              </div>
              <div class="ie-row">
                <label>Größe</label><input id="tSize" type="range" min="12" max="128" value="32">
                <input id="tColor" type="color" value="#ffffff" title="Farbe">
              </div>
              <div class="ie-row">
                <button class="ie-btn" id="tAdd">Text hinzufügen</button>
                <button class="ie-btn" id="tDel" disabled>Ausgewählten Text löschen</button>
              </div>
              <div class="ie-row"><small>Im Bild anklicken & ziehen zum Positionieren.</small></div>
            </div>

            <div class="ie-panel" id="panel-draw" hidden>
              <h3>Zeichnen</h3>
              <div class="ie-row"><label>Farbe</label><input id="dColor" type="color" value="#ff0000"></div>
              <div class="ie-row"><label>Stärke</label><input id="dSize" type="range" min="2" max="48" value="6"></div>
              <div class="ie-row">
                <button class="ie-btn" id="dToggle">Zeichnen: Aus</button>
                <button class="ie-btn" id="dClear">Zeichenebene löschen</button>
              </div>
            </div>

            <div class="ie-panel" id="panel-stickers" hidden>
              <h3>Sticker</h3>
              <div class="ie-grid" id="stickGrid"></div>
            </div>

            <div class="ie-panel" id="panel-fx" hidden>
              <h3>Effekte</h3>
              <div class="ie-row"><label>Helligkeit</label><input id="fxBri" type="range" min="50" max="150" value="100"><span id="fxBriV">100%</span></div>
              <div class="ie-row"><label>Kontrast</label><input id="fxCon" type="range" min="50" max="150" value="100"><span id="fxConV">100%</span></div>
              <div class="ie-row"><label>Sättigung</label><input id="fxSat" type="range" min="50" max="200" value="100"><span id="fxSatV">100%</span></div>
              <div class="ie-row"><label>Sepia</label><input id="fxSep" type="range" min="0" max="100" value="0"><span id="fxSepV">0%</span></div>
              <div class="ie-row"><label>Graustufen</label><input id="fxGry" type="range" min="0" max="100" value="0"><span id="fxGryV">0%</span></div>
              <div class="ie-row"><label>Weichzeichner</label><input id="fxBlu" type="range" min="0" max="10" value="0"><span id="fxBluV">0px</span></div>
            </div>
          </div>
        </div>
        <div class="ie-foot"><div class="ie-note">Export = Editoransicht (optimiert für Posts).</div></div>
      </div>
    `;
    document.body.appendChild(overlay);

    baseCanvas = overlay.querySelector('#ieBase'); baseCtx = baseCanvas.getContext('2d');
    drawCanvas = overlay.querySelector('#ieDraw'); drawCtx = drawCanvas.getContext('2d', { willReadFrequently:true });
    textCanvas = overlay.querySelector('#ieText'); textCtx = textCanvas.getContext('2d');
    uiCanvas   = overlay.querySelector('#ieUI');   uiCtx   = uiCanvas.getContext('2d');
    statusDiv  = overlay.querySelector('#ieStatus');

    overlay.querySelector('[data-act="cancel"]').addEventListener('click', closeEditor);
    overlay.querySelector('[data-act="apply"]').addEventListener('click', applyEditor);

    wireFormFallback();
    wireSendButtonFallback();
    return overlay;
  }

  /* ---------- Formular-Fallbacks ---------- */
  function wireFormFallback(){
    const form = document.getElementById('replyPill');
    const fi   = document.getElementById('replyFile');
    if (!form || !fi || form._ieBound) return;
    form._ieBound = true;

    form.addEventListener('formdata', (e)=>{
      if (!_ieEditedBlob) return;
      const fieldName = fi.getAttribute('name') || 'file';
      e.formData.set(fieldName, _ieEditedBlob, _ieEditedName);
    });

    form.addEventListener('submit', async (e) => {
      if (!_ieEditedBlob) return;
      const hasInInput = fi.files && fi.files.length && fi.files[0]?.name === _ieEditedName;
      if (hasInInput) return;

      e.preventDefault();
      try {
        const fd = new FormData(form);
        const fieldName = fi.getAttribute('name') || 'file';
        if (!fd.get(fieldName)) fd.set(fieldName, _ieEditedBlob, _ieEditedName);

        const resp = await fetch(form.action, {
          method: (form.method || 'POST').toUpperCase(),
          body: fd,
          credentials: 'same-origin'
        });
        if (resp.redirected) window.location.href = resp.url;
        else { try { await resp.json(); } catch {} window.location.reload(); }
      } catch (err) {
        console.error('[image-editor] submit fallback failed', err);
        form.submit();
      }
    }, { capture:true });
  }

  function wireSendButtonFallback(){
    const form = document.getElementById('replyPill');
    const sendBtn = document.getElementById('sendBtn');
    const fi = document.getElementById('replyFile');
    if (!form || !sendBtn || sendBtn._ieBound) return;
    sendBtn._ieBound = true;

    sendBtn.addEventListener('click', async (e)=>{
      if (!_ieEditedBlob) return;
      const hasInInput = fi && fi.files && fi.files.length && fi.files[0]?.name === _ieEditedName;
      if (hasInInput) return;

      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation?.();

      try {
        const fd = new FormData(form);
        const fieldName = fi?.getAttribute('name') || 'file';
        fd.set(fieldName, _ieEditedBlob, _ieEditedName);

        const resp = await fetch(form.action, {
          method: (form.method || 'POST').toUpperCase(),
          body: fd,
          credentials: 'same-origin'
        });
        if (resp.redirected) window.location.href = resp.url;
        else { try { await resp.json(); } catch {} window.location.reload(); }
      } catch (err) {
        console.error('[image-editor] sendBtn fallback failed', err);
        form.submit();
      }
    }, { capture:true });
  }

  /* ---------- State / Utils ---------- */
  function defaultState(){
    return {
      viewW: 900, viewH: 600,
      crop: null,
      tool: 'move',
      draw: { enabled:false, size:6, color:'#ff0000', drawing:false },
      fx:   { bri:100, con:100, sat:100, sep:0, gry:0, blu:0 },
      items: [],
      selId: null,
      drag: null
    };
  }
  const clamp = (n,min,max)=>Math.max(min,Math.min(max,n));
  function setCanvasSize(w,h){ [baseCanvas,drawCanvas,textCanvas,uiCanvas].forEach(c=>{ c.width=w; c.height=h; }); }
  function setAlert(msg){ const a=overlay.querySelector('#ieAlert'); if(!a) return; if(msg){a.textContent=msg;a.hidden=false;} else {a.textContent='';a.hidden=true;} }
  function setStatus(on,msg){ statusDiv.style.display = on?'flex':'none'; if(msg) statusDiv.querySelector('span:last-child').textContent = msg; }
  function clientToCanvas(e){
    const rect = baseCanvas.getBoundingClientRect();
    const t = (e.touches && e.touches[0]) || e;
    const x = clamp(Math.round(t.clientX - rect.left), 0, baseCanvas.width);
    const y = clamp(Math.round(t.clientY - rect.top),  0, baseCanvas.height);
    return { x, y };
  }
  function tryAssignToInput(blob, name){
    try{
      const dt = new DataTransfer();
      dt.items.add(new File([blob], name, { type:'image/jpeg', lastModified:Date.now() }));
      fileInput.files = dt.files;
      return fileInput.files && fileInput.files.length && fileInput.files[0].name === name;
    }catch(e){ return false; }
  }

  /* ---------- Öffnen / Bild laden ---------- */
  fileInput.addEventListener('click', ()=>{ fileInput.value = ''; });

  fileInput.addEventListener('change', async ()=>{
    const f = fileInput.files?.[0]; if (!f) return;

    buildOverlay();
    overlay.classList.add('open');
    setStatus(true,'Lade Bild…'); setAlert('');

    const mime = (f.type||'').toLowerCase();
    if (!/^image\//.test(mime)){ setAlert('Bitte ein Bild auswählen.'); setStatus(false); return; }
    if (/image\/hei(c|f)/.test(mime)){ setAlert('HEIC/HEIF wird im Browser oft nicht unterstützt. Bitte JPG/PNG nutzen.'); setStatus(false); return; }

    try{
      if (window.createImageBitmap) {
        srcImage = await createImageBitmap(f);
      } else {
        srcImage = await new Promise((res,rej)=>{
          const url = URL.createObjectURL(f);
          const im = new Image();
          im.onload=()=>{ URL.revokeObjectURL(url); res(im); };
          im.onerror=(e)=>{ URL.revokeObjectURL(url); rej(e); };
          im.src = url;
        });
      }
    }catch(err){
      console.error('[image-editor] load error', err);
      setAlert('Bild konnte nicht geladen werden.'); setStatus(false); return;
    }

    state = defaultState();

    const natW = srcImage.width || srcImage.naturalWidth;
    const natH = srcImage.height || srcImage.naturalHeight;
    const maxW = Math.min(window.innerWidth * 0.92, 1024);
    const maxH = Math.min(window.innerHeight * 0.72, 720);
    const scale = Math.min(maxW/natW, maxH/natH, 1);
    state.viewW = Math.max(320, Math.floor(natW*scale));
    state.viewH = Math.max(240, Math.floor(natH*scale));
    setCanvasSize(state.viewW, state.viewH);
    state.crop = { x:0, y:0, w:state.viewW, h:state.viewH };

    initUI();
    renderAll();
    setStatus(false);
  });

  /* ---------- UI / Werkzeuge ---------- */
  function initUI(){
    const toolbar = overlay.querySelector('.ie-toolbar');
    toolbar.addEventListener('click', (e)=>{
      const btn = e.target.closest('.ie-tool'); if(!btn) return;
      e.preventDefault(); e.stopPropagation();
      if (btn.dataset.tool) setTool(btn.dataset.tool);
    }, { capture:true });

    function showPanel(name){
      ['crop','text','draw','stickers','fx'].forEach(p=>{
        const el = overlay.querySelector(`#panel-${p}`); if (el) el.hidden = (p !== name);
      });
    }

    function setTool(t){
      state.tool = t;
      overlay.querySelectorAll('.ie-tool').forEach(b => b.classList.toggle('active', b.dataset.tool === t));
      showPanel(t === 'move' ? null : t);
      const hud = overlay.querySelector('#ieCropHud'); if (hud) hud.hidden = (t !== 'crop');
      drawCanvas.style.cursor = (t === 'draw' && state.draw.enabled) ? 'crosshair' : 'default';
      textCanvas.style.cursor = (t === 'text' || t === 'stickers' || t === 'move') ? 'move' : 'default';
    }
    setTool('move');

    /* ---- Crop ---- */
    const cropHud  = overlay.querySelector('#ieCropHud');
    const cropRect = overlay.querySelector('#ieCropRect');
    const cropAspect = overlay.querySelector('#cropAspect');
    overlay.querySelector('#cropReset').addEventListener('click', ()=>{
      state.crop = { x:0, y:0, w:baseCanvas.width, h:baseCanvas.height };
      layoutCropRect(); renderAll();
    });

    let cropStart = null;
    function layoutCropRect(){
      const c = state.crop;
      cropRect.style.left = c.x+'px';
      cropRect.style.top  = c.y+'px';
      cropRect.style.width= c.w+'px';
      cropRect.style.height= c.h+'px';
    }
    cropHud.addEventListener('mousedown', cropDown);
    cropHud.addEventListener('touchstart', cropDown, {passive:false});
    window.addEventListener('mousemove', cropMove);
    window.addEventListener('touchmove', cropMove, {passive:false});
    window.addEventListener('mouseup', cropUp);
    window.addEventListener('touchend', cropUp);

    function cropDown(e){
      if (state.tool !== 'crop') return;
      e.preventDefault();
      const p = clientToCanvas(e);
      cropStart = p;
      cropRect.style.display = 'block';
      cropRect.style.left = p.x+'px'; cropRect.style.top = p.y+'px';
      cropRect.style.width = '1px';   cropRect.style.height = '1px';
    }
    function cropMove(e){
      if (!cropStart || state.tool !== 'crop') return;
      e.preventDefault();
      const p = clientToCanvas(e);
      let x = Math.min(cropStart.x, p.x), y = Math.min(cropStart.y, p.y);
      let w = Math.abs(p.x - cropStart.x), h = Math.abs(p.y - cropStart.y);
      const mode = cropAspect.value;
      if (mode !== 'free' && w>0 && h>0){
        const [a,b] = mode.split(':').map(Number);
        const ratio = a/b, cur = w/h;
        if (cur > ratio) w = h*ratio; else h = w/ratio;
        if (p.x < cropStart.x) x = cropStart.x - w;
        if (p.y < cropStart.y) y = cropStart.y - h;
      }
      cropRect.style.left = x+'px'; cropRect.style.top = y+'px';
      cropRect.style.width = w+'px'; cropRect.style.height = h+'px';
    }
    function cropUp(){
      if (!cropStart) return;
      const cr = cropRect.getBoundingClientRect();
      const br = baseCanvas.getBoundingClientRect();
      const x = clamp(cr.left - br.left, 0, baseCanvas.width);
      const y = clamp(cr.top  - br.top,  0, baseCanvas.height);
      const w = clamp(cr.width,  1, baseCanvas.width - x);
      const h = clamp(cr.height, 1, baseCanvas.height - y);
      state.crop = { x:Math.round(x), y:Math.round(y), w:Math.round(w), h:Math.round(h) };
      cropStart = null;
      renderAll();
    }

    /* ---- Text / Sticker ---- */
    const tText = overlay.querySelector('#tText');
    const tFont = overlay.querySelector('#tFont');
    const tSize = overlay.querySelector('#tSize');
    const tColor= overlay.querySelector('#tColor');
    const tAdd  = overlay.querySelector('#tAdd');
    const tDel  = overlay.querySelector('#tDel');

    tAdd.addEventListener('click', (e)=>{
      e.preventDefault();
      const txt = (tText.value||'').trim(); if (!txt) return;
      const id = 't'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
      state.items.push({ id, type:'text', text:txt, x: Math.round(baseCanvas.width*0.1), y: Math.round(baseCanvas.height*0.2),
                         font: tFont.value, size: +tSize.value, color: tColor.value });
      state.selId = id; renderText(); drawUI(); updateDelBtn();
    });
    tDel.addEventListener('click', (e)=>{
      e.preventDefault();
      if (!state.selId) return;
      state.items = state.items.filter(i => i.id !== state.selId);
      state.selId = null; renderText(); drawUI(); updateDelBtn();
    });
    function updateDelBtn(){ tDel.disabled = !state.selId; }

    const STICKS = ["😀","😎","🔥","💯","🎯","🎉","✨","💡","❤️","💙","💛","💚","😭","😡","👍","👎","👏","🙏","💪","🤯"];
    const stickGrid = overlay.querySelector('#stickGrid');
    stickGrid.innerHTML = '';
    STICKS.forEach(ch=>{
      const b = document.createElement('button'); b.type='button'; b.textContent=ch;
      b.addEventListener('click', ()=>{
        const id = 's'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
        state.items.push({ id, type:'sticker', text:ch, x: Math.round(baseCanvas.width*0.5), y: Math.round(baseCanvas.height*0.5),
                           font: 'Segoe UI Emoji, Apple Color Emoji, Noto Color Emoji, Arial', size: 64, color:'#ffffff' });
        state.selId = id; renderText(); drawUI(); updateDelBtn();
      });
      stickGrid.appendChild(b);
    });

    // Drag auf textCanvas
    textCanvas.addEventListener('mousedown', textDown);
    textCanvas.addEventListener('mousemove', textMove);
    window.addEventListener('mouseup', textUp);
    textCanvas.addEventListener('touchstart', textDown, {passive:false});
    textCanvas.addEventListener('touchmove',  textMove,  {passive:false});
    window.addEventListener('touchend', textUp);

    function textHit(p){
      for (let i = state.items.length - 1; i >= 0; i--){
        const o = state.items[i];
        textCtx.save(); textCtx.font = `${o.size}px ${o.font}`;
        const w = textCtx.measureText(o.text).width; const h = o.size * 1.2;
        textCtx.restore();
        if (p.x >= o.x && p.x <= o.x + w && p.y <= o.y && p.y >= o.y - h) return o;
      } return null;
    }
    function textDown(e){
      if (!['move','text','stickers'].includes(state.tool)) return;
      const p = clientToCanvas(e);
      const hit = textHit(p);
      if (hit){
        state.selId = hit.id;
        state.drag = { kind:'text', id:hit.id, dx: p.x - hit.x, dy: p.y - hit.y };
        renderText(); drawUI(); updateDelBtn(); e.preventDefault();
      } else { state.selId = null; state.drag = null; drawUI(); updateDelBtn(); }
    }
    function textMove(e){
      if (!state.drag || state.drag.kind!=='text') return;
      const p = clientToCanvas(e);
      const o = state.items.find(i => i.id === state.drag.id); if (!o) return;
      o.x = clamp(p.x - state.drag.dx, 0, baseCanvas.width);
      o.y = clamp(p.y - state.drag.dy, 0, baseCanvas.height);
      renderText(); drawUI(); e.preventDefault();
    }
    function textUp(){ state.drag = null; }

    /* ---- Zeichnen ---- */
    const dToggle = overlay.querySelector('#dToggle');
    const dColor  = overlay.querySelector('#dColor');
    const dSize   = overlay.querySelector('#dSize');
    const dClear  = overlay.querySelector('#dClear');

    dToggle.addEventListener('click', (e)=>{
      e.preventDefault();
      state.draw.enabled = !state.draw.enabled;
      dToggle.textContent = 'Zeichnen: ' + (state.draw.enabled ? 'An' : 'Aus');
      drawCanvas.style.cursor = state.draw.enabled ? 'crosshair' : 'default';
    });
    dClear.addEventListener('click', (e)=>{ e.preventDefault(); drawCtx.clearRect(0,0,drawCanvas.width, drawCanvas.height); });

    dColor.addEventListener('input', ()=>{ state.draw.color = dColor.value; });
    dSize .addEventListener('input', ()=>{ state.draw.size  = +dSize.value;  });

    drawCanvas.addEventListener('mousedown', drawDown);
    drawCanvas.addEventListener('mousemove', drawMove);
    window.addEventListener('mouseup', drawUp);
    drawCanvas.addEventListener('touchstart', drawDown, {passive:false});
    drawCanvas.addEventListener('touchmove',  drawMove,  {passive:false});
    window.addEventListener('touchend', drawUp);

    function drawDown(e){
      if (!state.draw.enabled || state.tool!=='draw') return;
      const p = clientToCanvas(e);
      drawCtx.strokeStyle = state.draw.color;
      drawCtx.lineWidth   = state.draw.size;
      drawCtx.lineCap='round'; drawCtx.lineJoin='round';
      drawCtx.beginPath(); drawCtx.moveTo(p.x, p.y);
      state.draw.drawing = true; e.preventDefault();
    }
    function drawMove(e){
      if (!state.draw.enabled || state.tool!=='draw' || !state.draw.drawing) return;
      const p = clientToCanvas(e);
      drawCtx.lineTo(p.x, p.y); drawCtx.stroke(); e.preventDefault();
    }
    function drawUp(){ if (state.draw.enabled) state.draw.drawing = false; }

    /* ---- Effekte ---- */
    const fxPanel = overlay.querySelector('#panel-fx');
    fxPanel.addEventListener('input', (e)=>{
      const map = { fxBri:'bri', fxCon:'con', fxSat:'sat', fxSep:'sep', fxGry:'gry', fxBlu:'blu' };
      const id = e.target.id; const key = map[id]; if (!key) return;
      const val = +e.target.value; state.fx[key] = val;
      const lab = overlay.querySelector('#'+id+'V'); if (lab) lab.textContent = (key==='blu') ? (val+'px') : (val+'%');
      renderBase();
    }, { capture:true });
  }

  /* ---------- Render ---------- */
  function renderBase(){
    const w = baseCanvas.width, h = baseCanvas.height;
    baseCtx.save();
    baseCtx.clearRect(0,0,w,h);
    baseCtx.filter = `brightness(${state.fx.bri}%) contrast(${state.fx.con}%) saturate(${state.fx.sat}%) sepia(${state.fx.sep}%) grayscale(${state.fx.gry}%) blur(${state.fx.blu}px)`;
    const off = document.createElement('canvas'); off.width = w; off.height = h;
    const octx = off.getContext('2d'); octx.drawImage(srcImage, 0, 0, off.width, off.height);
    const c = state.crop || { x:0, y:0, w:w, h:h };
    baseCtx.drawImage(off, c.x, c.y, c.w, c.h, 0, 0, w, h);
    baseCtx.restore();
  }
  function renderText(){
    textCtx.clearRect(0,0,textCanvas.width, textCanvas.height);
    state.items.forEach(o=>{
      textCtx.save();
      textCtx.font = `${o.size}px ${o.font}`;
      textCtx.fillStyle = o.color;
      textCtx.textBaseline = 'alphabetic';
      textCtx.fillText(o.text, o.x, o.y);
      textCtx.restore();
    });
  }
  function drawUI(){
    uiCtx.clearRect(0,0,uiCanvas.width, uiCanvas.height);
    if (state.selId){
      const o = state.items.find(i => i.id === state.selId);
      if (o){
        uiCtx.save();
        uiCtx.font = `${o.size}px ${o.font}`;
        const w = uiCtx.measureText(o.text).width;
        const h = o.size * 1.2;
        uiCtx.strokeStyle = 'rgba(37,99,235,.9)';
        uiCtx.lineWidth = 1.5;
        uiCtx.strokeRect(o.x-2, o.y-h, w+4, h+4);
        uiCtx.restore();
      }
    }
  }
  function renderAll(){ renderBase(); renderText(); drawUI(); }

  /* ---------- Helpers für Hidden/Vorschau ---------- */
  function ensureEditedHiddenInForm() {
    const form = document.getElementById('replyPill');
    let hidden = form ? form.querySelector('#editedImage') : null;
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'edited_image';
      hidden.id   = 'editedImage';
	  
      if (form) form.appendChild(hidden);
      else document.body.appendChild(hidden);
    }
    return hidden;
  }

  function showInlinePreview(dataUrl){
    const form = document.getElementById('replyPill');
    const input = document.getElementById('replyText');
    if (!form || !input) return;

    let box = document.getElementById('replyImgPreview');
    if (!box) {
      box = document.createElement('div');
      box.id = 'replyImgPreview';
      box.style.width = '40px';
      box.style.height = '40px';
      box.style.flex = '0 0 40px';
      box.style.borderRadius = '8px';
      box.style.overflow = 'hidden';
      box.style.marginRight = '10px';
      box.style.border = '1px solid rgba(255,255,255,.15)';
      // Box direkt vor das Text-Input setzen (im selben Flex-Row)
      form.insertBefore(box, input);
    }
    box.innerHTML = '';
    const img = new Image();
    img.src = dataUrl;
    img.alt = '';
    img.style.width = '100%';
    img.style.height = '100%';
    img.style.objectFit = 'cover';
    box.appendChild(img);

    // dezentes UI-Feedback
    input.placeholder = 'Bild angehängt ✓ — hier Text schreiben…';
  }

  /* ---------- Export / Speichern ---------- */
  async function applyEditor(){
    const w = baseCanvas.width, h = baseCanvas.height;
    const exp = document.createElement('canvas'); exp.width=w; exp.height=h;
    const x = exp.getContext('2d');
    x.drawImage(baseCanvas, 0, 0);
    x.drawImage(drawCanvas, 0, 0);
    x.drawImage(textCanvas, 0, 0);

    const blob = await new Promise(res => exp.toBlob(res, 'image/jpeg', 0.92));
    if (!blob){ closeEditor(); return; }

    // Hidden-Feld IM Formular setzen
    const dataUrl = await blobToDataURL(blob);
    const hidden  = ensureEditedHiddenInForm();
    hidden.value  = dataUrl;

    // Inline-Vorschau im Pill
    showInlinePreview(dataUrl);

    // Optional/Kosmetik: echtes File-Input füllen
    try {
      const orig = (fileInput.files && fileInput.files[0] && fileInput.files[0].name) || 'image.jpg';
      const base = orig.replace(/\.[^.]+$/, '');
      const outName = base + '_edited.jpg';
    const dt = new DataTransfer();
    dt.items.add(new File([blob], outName, { type:'image/jpeg', lastModified:Date.now() }));
    fileInput.files = dt.files;
    fileInput.dispatchEvent(new Event('change', { bubbles:true }));
  } catch {/* nicht kritisch */}

  closeEditor();
}



  function closeEditor(){
    overlay.classList.remove('open');
    setAlert(''); setStatus(false);
  }

})();

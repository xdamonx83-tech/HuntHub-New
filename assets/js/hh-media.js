// Hunthub Media Pack — adds a video trim modal and wires it to your forms.
// Works with replies (#replyPill) and the "new thread" form (#newThreadForm).

(function(){
  const API_UPLOAD = '/api/video/upload.php';

  function h(tag, attrs={}, children=[]) {
    const el = document.createElement(tag);
    for (const [k,v] of Object.entries(attrs || {})) {
      if (k === 'class') el.className = v;
      else if (k === 'style') el.setAttribute('style', v);
      else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v);
    }
    if (!Array.isArray(children)) children = [children];
    for (const c of children) {
      if (c == null) continue;
      el.append(c.nodeType ? c : document.createTextNode(String(c)));
    }
    return el;
  }

  function ensureHiddenHtmlField(form) {
    let field = form.querySelector('input[name="content_html"]');
    if (!field) {
      field = h('input',{type:'hidden',name:'content_html',id:form.id+'__content_html'});
      form.appendChild(field);
    }
    return field;
  }

  function ensurePreviewBox(form, textarea) {
    let box = form.querySelector('.hh-media-preview');
    if (!box) {
      box = h('div', {class:'hh-media-preview'});
      (textarea && textarea.parentNode ? textarea.parentNode : form).appendChild(box);
    }
    return box;
  }

  function getCSRF(form){
    return form.querySelector('input[name="csrf"]')?.value
        || document.querySelector('meta[name="csrf"]')?.content
        || '';
  }

  // -------- Trim Modal --------
  function createTrimModal(){
    const mask = h('div', {class:'vt-mask', 'aria-hidden':'true'});
    const card = h('div', {class:'vt-card'});
    const wrap = h('div', {class:'vt-wrap'}, [card]);
    mask.appendChild(wrap);

    const hd = h('div', {class:'vt-hd'}, [
      h('strong',{},'Video zuschneiden'),
      h('button',{type:'button',class:'btn vt-close'},'Schließen')
    ]);
    const bd = h('div', {class:'vt-bd'});
    const ft = h('div', {class:'vt-ft'});
    card.append(hd, bd, ft);

    const file = h('input', {type:'file',accept:'video/*',id:'vtFile'});
    const vid  = h('video', {id:'vtPreview',controls:'',style:'width:100%;max-height:50vh;margin-top:10px;background:#111'});

    // Track + dual sliders
    const track = h('div',{class:'vt-track'});
    const highlight = h('div',{id:'vtHighlight',class:'vt-highlight',style:'left:0%;right:0%'});
    track.append(highlight);

    const rStart = h('input',{type:'range',id:'vtStartRange',class:'vt-range',min:'0',max:'0',step:'0.1',value:'0'});
    const rEnd   = h('input',{type:'range',id:'vtEndRange',class:'vt-range vt-range-end',min:'0',max:'0',step:'0.1',value:'0'});
    const rangeWrap = h('div',{class:'vt-range-wrap'},[track, rStart, rEnd]);

    const times = h('div',{class:'vt-times'},[
      h('span',{},['Start: ',h('strong',{id:'vtStartLabel'},'00:00')]),
      h('span',{},['Ende: ',h('strong',{id:'vtEndLabel'},'00:00')]),
    ]);
    const len = h('div',{class:'vt-len'},['Länge: ', h('strong',{id:'vtLen'},'0:00')]);
    const meta = h('div',{class:'vt-meta'},[h('span',{id:'vtDurLabel'},'Gesamtdauer: 0:00')]);
    const prog = h('div',{id:'vtProgress',class:'vt-prog hidden', 'aria-live':'polite'}, [
      h('div',{id:'vtProgText',class:'vt-prog-text'},'Wird bearbeitet…'),
      h('div',{class:'vt-prog-bar'},[h('div',{id:'vtProgBar'})])
    ]);

    bd.append(file, vid, rangeWrap, times, len, meta, prog);
    const upBtn = h('button',{type:'button',id:'vtUpload',class:'btn btn-primary'},'Video hochladen');
    ft.append(upBtn);

    document.body.append(mask);

    // state
    let dur = 0;
    let active = null;
    const STEP = 0.1;

    function clamp(x,a,b){ return Math.min(b, Math.max(a,x)); }
    function fmt(t){
      t = Math.max(0, Math.floor(t));
      const m = Math.floor(t/60), s=t%60;
      return String(m)+':'+String(s).padStart(2,'0');
    }
    function render(){
      const s = parseFloat(rStart.value);
      const e = parseFloat(rEnd.value);
      mask.querySelector('#vtStartLabel').textContent = fmt(s);
      mask.querySelector('#vtEndLabel').textContent   = fmt(e);
      mask.querySelector('#vtLen').textContent        = fmt(Math.max(0, e-s));
      mask.querySelector('#vtDurLabel').textContent   = 'Gesamtdauer: '+fmt(dur);

      const leftPct = dur>0 ? (s/dur)*100 : 0;
      const rightPct = dur>0 ? (100-(e/dur)*100) : 0;
      highlight.style.left = leftPct+'%';
      highlight.style.right = rightPct+'%';
    }
    function onStartInput(){
      let s = parseFloat(rStart.value);
      let e = parseFloat(rEnd.value);
      s = clamp(s, 0, e-STEP);
      rStart.value = s.toFixed(1);
      render();
      if (active==='start') vid.currentTime = s;
    }
    function onEndInput(){
      let s = parseFloat(rStart.value);
      let e = parseFloat(rEnd.value);
      e = clamp(e, s+STEP, dur);
      rEnd.value = e.toFixed(1);
      render();
      if (active==='end') vid.currentTime = e;
    }

    rStart.addEventListener('input', onStartInput);
    rEnd.addEventListener('input', onEndInput);
    rStart.addEventListener('pointerdown', ()=> active='start');
    rEnd  .addEventListener('pointerdown', ()=> active='end');
    window.addEventListener('pointerup', ()=> active=null);

    track.addEventListener('click', (e)=>{
      const rect = track.getBoundingClientRect();
      const ratio = clamp((e.clientX-rect.left)/rect.width, 0, 1);
      const t = ratio * dur;
      const ds = Math.abs(t-parseFloat(rStart.value));
      const de = Math.abs(t-parseFloat(rEnd.value));
      if (ds <= de) { rStart.value = clamp(t, 0, parseFloat(rEnd.value)-STEP).toFixed(1); onStartInput(); }
      else          { rEnd.value   = clamp(t, parseFloat(rStart.value)+STEP, dur).toFixed(1); onEndInput(); }
      vid.currentTime = t;
    });

    file.addEventListener('change', () => {
      const f = file.files?.[0];
      if (!f) return;
      const url = URL.createObjectURL(f);
      vid.src = url;
      vid.onloadedmetadata = () => {
        dur = isFinite(vid.duration) ? vid.duration : 0;
        rStart.min = rEnd.min = '0';
        rStart.max = rEnd.max = String(dur.toFixed(1));
        rStart.step = rEnd.step = String(STEP);
        rStart.value = '0';
        rEnd.value = String(dur.toFixed(1));
        render();
      };
    });

    function show(){ mask.style.display='block'; document.body.style.overflow='hidden'; }
    function hide(){ mask.style.display='none'; document.body.style.overflow=''; }

    mask.querySelector('.vt-close').addEventListener('click', hide);
    mask.addEventListener('click', (e)=>{ if (e.target===mask) hide(); });

    function setProg(pct, text){
      const box = mask.querySelector('#vtProgress');
      const t = mask.querySelector('#vtProgText');
      const bar = mask.querySelector('#vtProgBar');
      if (typeof text === 'string') t.textContent = text;
      box.classList.remove('hidden');
      if (pct == null) {
        bar.classList.add('indet');
      } else {
        bar.classList.remove('indet');
        bar.style.width = Math.max(0, Math.min(100, Math.round(pct))) + '%';
      }
    }
    function hideProg(){
      const box = mask.querySelector('#vtProgress');
      const bar = mask.querySelector('#vtProgBar');
      box.classList.add('hidden'); bar.style.width='0%'; bar.classList.remove('indet');
    }

    // Upload logic; returns Promise<{video,poster}>
    function upload(csrf){
      return new Promise((resolve, reject) => {
        const f = file.files?.[0];
        if (!f) { reject(new Error('Bitte Video auswählen.')); return; }
        const start = parseFloat(rStart.value);
        const end = parseFloat(rEnd.value);
        if (!(end > start)) { reject(new Error('Ende muss größer als Start sein.')); return; }

        const fd = new FormData();
        fd.append('video', f);
        fd.append('start', String(start));
        fd.append('end', String(end));
        if (csrf) fd.append('csrf', csrf);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', API_UPLOAD, true);
        xhr.responseType = 'json';
        xhr.setRequestHeader('Accept','application/json');
        if (csrf) xhr.setRequestHeader('X-CSRF', csrf);

        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable && e.total) {
            const pct = (e.loaded / e.total) * 70; // 0-70% for upload
            setProg(pct, 'Upload… '+Math.round(pct)+'%');
          }
        };
        xhr.upload.onload = () => setProg(85, 'Verarbeite…');

        xhr.onerror = () => reject(new Error('Netzwerkfehler.'));
        xhr.onload = () => {
          try {
            const out = xhr.response ?? JSON.parse(xhr.responseText);
            if (xhr.status !== 200 || !out?.ok) {
              reject(new Error(out?.error || ('HTTP '+xhr.status)));
              return;
            }
            resolve(out);
          } catch (e) {
            reject(new Error('Ungültige Serverantwort.'));
          } finally {
            hideProg();
          }
        };
        setProg(0,'Upload…');
        xhr.send(fd);
      });
    }

    return { el: mask, show, hide, upload, file };
  }

  // Add toolbar if not present (for new thread modal)
  function ensureToolbar(form, textarea){
    let bar = form.querySelector('.hh-toolbar');
    if (bar) return bar;
    bar = h('div',{class:'hh-toolbar'});
    const imgWrap = h('label',{class:'hh-btn'},[h('i',{class:'ti ti-photo'}),' Bilder']);
    const imgInput = h('input',{type:'file',accept:'image/*',class:'hidden',name:'file'});
    imgWrap.append(imgInput);

    const videoBtn = h('button',{type:'button',class:'hh-btn',id:form.id+'__videoBtn'},[h('i',{class:'ti ti-video'}),' Video']);
    bar.append(imgWrap, videoBtn);
    textarea.parentNode.insertBefore(bar, textarea.nextSibling);
    return bar;
  }

  function addVideoSnippet(form, {video, poster}){
    const hidden = ensureHiddenHtmlField(form);
    const preview = ensurePreviewBox(form, form.querySelector('textarea'));
    const html = `<figure class="video"><video controls preload="metadata"${poster ? ` poster="${poster}"` : ''}><source src="${video}" type="video/mp4"></video></figure>`;

    // Append to hidden field (no raw HTML in visible textarea)
    hidden.value = (hidden.value ? hidden.value + "\n" : "") + html;

    // Show live preview below the textarea
    const box = document.createElement('div');
    box.innerHTML = html;
    preview.appendChild(box.firstElementChild);

    // helper UI hint
    const ta = form.querySelector('textarea, input[name="content_plain"]');
    if (ta && !ta.value) {
      ta.placeholder = (ta.placeholder || 'Text') + '  •  Video angehängt ✓';
    }
  }

  function initForm(form){
    const csrf = getCSRF(form);
    const textarea = form.querySelector('textarea, input[name="content_plain"]');
    if (!textarea) return;

    ensureHiddenHtmlField(form);
    ensurePreviewBox(form, textarea);
    const bar = ensureToolbar(form, textarea);

    const videoBtn = bar.querySelector('button.hh-btn');
    const modal = createTrimModal();

    videoBtn.addEventListener('click', () => {
      modal.file.value='';
      modal.show();
    });

    modal.el.querySelector('#vtUpload').addEventListener('click', async () => {
      try {
        modal.el.querySelector('#vtUpload').disabled = true;
        const out = await modal.upload(csrf);
        addVideoSnippet(form, out);
        modal.hide();
      } catch (err) {
        alert(err.message || err);
      } finally {
        modal.el.querySelector('#vtUpload').disabled = false;
      }
    });

    // If a dedicated existing "btnVideoTrim" exists on the page (reply pill), wire it too
    const existingBtn = document.getElementById('btnVideoTrim');
    if (existingBtn) {
      existingBtn.addEventListener('click', () => { modal.file.value=''; modal.show(); });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const forms = [];
    const replyPill = document.getElementById('replyPill');
    if (replyPill) forms.push(replyPill);
    const newThread = document.getElementById('newThreadForm');
    if (newThread) forms.push(newThread);

    // Also auto-enhance any form that posts to /api/forum/create_post.php or create_thread.php
    document.querySelectorAll('form[action$="create_post.php"], form[action$="create_thread.php"]').forEach(f => {
      if (!forms.includes(f)) forms.push(f);
    });

    forms.forEach(initForm);
  });
})();

// /js/reply-media.js
(function(){
  const form  = document.getElementById('replyPill');
  const text  = document.getElementById('replyText');
  const imgH  = document.getElementById('editedImage'); // Hidden mit data:image/...
  const vIn   = document.getElementById('replyVideo');
  const vBtn  = document.getElementById('btnVideoTrim');

  if (!form || !text) return;

  // Preview-Container (links neben Text)
  function ensurePreviewStrip(){
    let box = document.getElementById('replyMediaStrip');
    if (box) return box;
    box = document.createElement('div');
    box.id = 'replyMediaStrip';
    box.style.display = 'flex';
    box.style.gap = '8px';
    box.style.marginRight = '10px';
    form.insertBefore(box, text);
    return box;
  }

  function addThumb(el){
    const box = ensurePreviewStrip();
    el.style.width  = '40px';
    el.style.height = '40px';
    el.style.borderRadius = '8px';
    el.style.objectFit = 'cover';
    el.style.display = 'block';
    el.style.border = '1px solid rgba(255,255,255,.15)';
    box.appendChild(el);
  }

  function addImagePreview(dataUrl){
    const img = new Image();
    img.src = dataUrl; img.alt = '';
    addThumb(img);
    text.placeholder = 'Bild angehängt ✓ — hier Text schreiben…';
  }

  function addVideoPreview(file){
    const url = URL.createObjectURL(file);
    const v = document.createElement('video');
    v.src = url; v.muted = true; v.loop = true; v.playsInline = true; v.autoplay = true;
    addThumb(v);
    text.placeholder = 'Video angehängt ✓ — hier Text schreiben…';
  }

  // required dynamisch steuern
  function recomputeRequired(){
    const hasText = text.value.trim() !== '';
    const hasImg  = imgH && !!imgH.value;                // aus Image-Editor
    const hasVid  = vIn  && vIn.files && vIn.files.length > 0;
    text.required = !(hasText || hasImg || hasVid);
  }

  // Events
  text.addEventListener('input', recomputeRequired);

  if (vBtn && vIn) {
    vBtn.addEventListener('click', ()=> vIn.click());
    vIn.addEventListener('change', ()=>{
      if (vIn.files && vIn.files[0]) {
        addVideoPreview(vIn.files[0]);
        recomputeRequired();
      }
    });
  }

  // Wenn der Image-Editor gespeichert hat: Hidden-Feld bekommt Base64
  if (imgH) {
    // wir lauschen auf synthetisches 'change' (siehe Patch in image-editor.js)
    imgH.addEventListener('change', ()=>{
      if (imgH.value && imgH.value.startsWith('data:image/')) {
        addImagePreview(imgH.value);
        recomputeRequired();
      }
    });
  }

  // Initial einmal prüfen (falls Seite zurück aus bfcache etc.)
  recomputeRequired();
})();

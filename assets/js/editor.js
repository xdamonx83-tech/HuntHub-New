// /assets/js/editor.js
(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  function initTiny() {
    const ta = document.querySelector('textarea[data-editor="tiny"]');
    if (!ta) return;
    if (window.__tinyInited) return;
    window.__tinyInited = true;

    const csrfToken   = ta.dataset.csrf || '';
    const imgUploadUrl = ta.dataset.upload || '/api/upload_image_tiny.php';
    const vidUploadUrl = ta.dataset.uploadVideo || '/api/upload_video_tiny.php';

    tinymce.init({
      // Ohne Cloud-Key (jsDelivr / self-hosted)
      base_url: 'https://cdn.jsdelivr.net/npm/tinymce@7',

      selector: 'textarea[data-editor="tiny"]',
      menubar: false,
      branding: false,
      height: 420,
      plugins: 'autolink link lists table paste image media autoresize',
      toolbar: 'undo redo | bold italic underline | bullist numlist | link image media table',
      inline: false,                // iframe: keine Fokus-/CSS-Konflikte
      convert_urls: false,
      browser_spellcheck: true,
      contextmenu: false,
      statusbar: true,
      automatic_uploads: true,

      // Dark
      skin: 'oxide-dark',
      content_css: 'dark',
      content_style: `
        body { background:#111827; color:#e5e7eb; }
        a { color:#60a5fa; }
        img, video { max-width:100%; height:auto; border-radius:8px; }
        video { background:#000; }
        table { border-collapse:collapse; width:100%; }
        th, td { border:1px solid #374151; padding:.5rem; }
        blockquote { border-left:3px solid #374151; padding:.4rem .8rem; color:#cbd5e1; }
        .mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before { color:#6b7280; }
      `,

      // ---------- Bild-Upload (Promise-Handler) ----------
      images_upload_handler: (blobInfo) => {
        const fd = new FormData();
        fd.append('file', blobInfo.blob(), blobInfo.filename());
        if (csrfToken) fd.append('csrf', csrfToken);

        return fetch(imgUploadUrl, {
          method: 'POST',
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
          credentials: 'same-origin'
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(json => {
          const url = json && (json.location || json.link);
          if (!url) throw new Error((json && json.error) || 'Upload fehlgeschlagen.');
          return url; // <- TinyMCE erwartet String
        });
      },

      // ---------- Video-Upload (Media-Dialog) ----------
      file_picker_types: 'media',
      file_picker_callback: (cb, value, meta) => {
        if (meta.filetype !== 'media') return;

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'video/mp4,video/webm,video/ogg';

        input.onchange = () => {
          const file = input.files && input.files[0];
          if (!file) return;

          const fd = new FormData();
          fd.append('file', file, file.name);
          if (csrfToken) fd.append('csrf', csrfToken);

fetch(vidUploadUrl, {
  method: 'POST',
  body: fd,
  headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
  credentials: 'same-origin'
})
.then(async r => {
  const text = await r.text();
  let json = null; try { json = JSON.parse(text); } catch {}
  if (!r.ok) {
    const msg = (json && json.error) ? json.error : ('HTTP ' + r.status + ' ' + text.slice(0, 300));
    throw new Error(msg);
  }
  return json || {};
})
.then(json => {
  const url = json.location || json.link;
  if (!url) throw new Error(json.error || 'Upload fehlgeschlagen (keine URL).');
  const safeUrl = String(url).replace(/"/g, '&quot;');
  tinymce.activeEditor.insertContent(
    `<video class="js-player" controls playsinline preload="metadata" src="${safeUrl}"></video>`
  );
})
.catch(err => alert('Video-Upload: ' + (err?.message || err)));

        };

        input.click();
      },

      // ---------- Whitelist (inkl. <video>/<source>) ----------
      valid_elements:
        '@[id|class|style],a[href|title|target],strong/b,em/i,u,blockquote,ul,ol,li,p,br,span,code,pre,hr,' +
        'table,tr,td,th,thead,tbody,img[src|alt|title|width|height],' +
        'video[controls|width|height|poster|preload|src|playsinline|controlslist],source[src|type],h1,h2,h3,figure,figcaption,audio[src|controls]',
      extended_valid_elements:
        'a[name|href|target=_blank],img[id|src|alt|title|width|height],video[controls|width|height|poster|preload|src|playsinline|controlslist],source[src|type],figure[class],audio[src|controls]',

      // ---------- Inhalt beim Absenden zuverlässig bereitstellen ----------
      setup: function (editor) {
        const form = editor.targetElm && editor.targetElm.form;
        const taEl = editor.targetElm;

        const sync = () => {
          editor.save(); // -> Textarea
          const html = editor.getContent();
          if (!form) return;
          const ensure = (name) => {
            let el = form.querySelector(`[name="${name}"]`);
            if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = name; form.appendChild(el); }
            el.value = html;
          };
          ensure(taEl.name || 'content');
          ensure('content');
          ensure('body');
          ensure('message');
          ensure('content_html');
        };

        editor.on('init input change keyup NodeChange Undo Redo', sync);

        if (form) {
          form.addEventListener('submit', (ev) => {
            sync();
            const plain = editor.getContent({ format: 'text' }).trim();
            if (!plain) { ev.preventDefault(); alert('Bitte einen Text eingeben.'); }
          });

          form.addEventListener('formdata', (e) => {
            const html = editor.getContent();
            const name = taEl.name || 'content';
            e.formData.set(name, html);
            if (!e.formData.has('content')) e.formData.set('content', html);
            if (!e.formData.has('body'))    e.formData.set('body', html);
            if (!e.formData.has('message')) e.formData.set('message', html);
            e.formData.set('content_html', html);
          });

          form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(btn => {
            btn.addEventListener('click', sync);
          });
        }
      }
    });
  }

  ready(initTiny);
  window.addEventListener('load', initTiny);
})();

(function () {
  const BASE = (typeof window.APP_BASE === "string") ? window.APP_BASE : "";
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || "";

  // ===== Utils ==============================================================
  const q  = (s, el=document) => (el || document).querySelector(s);
  const qa = (s, el=document) => Array.from((el || document).querySelectorAll(s));
  const html = (s) => { const t=document.createElement("template"); t.innerHTML=String(s||"").trim(); return t.content.firstElementChild; };
  function toast(msg){ console.log("[wall]", msg); }

  // *** Styles für Vorschaubilder (neu / minimal) ****************************
  function ensurePreviewStyles(){
    if (document.getElementById('hh-preview-css')) return;
    const st = document.createElement('style');
    st.id = 'hh-preview-css';
    st.textContent = `
      /* Grundlayout */
      .hh-previews{ margin-top:10px; }
      .hh-previews.hidden{ display:none; }

      /* Grid für Post-Composer (unter der Textarea) */
      .hh-previews-grid{
        display:grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap:10px;
      }

      /* Schwebende Variante (Kommentar-Composer) – ÜBER der Textarea */
      .hh-composer{ position:relative; } /* Anker für absolute Position */
      .hh-previews.is-floating{
        --hh-thumb: 72px;
        position:absolute;
        right:0px;
        top: calc(-1 * (var(--hh-thumb) + 38px)); /* über dem Composer */
        pointer-events:none;   /* Textarea bleibt fokussierbar */
        z-index:10;
        animation: hh-pop-in .14s ease-out both;
        overflow:visible;
      }
      .hh-previews.is-floating .hh-previews-row{
        display:flex;
        gap:8px;
        flex-wrap:wrap;
        justify-content:flex-end;
      }

      /* Thumbs */
      .hh-thumb{
        position:relative;
        border-radius:12px;
        overflow:hidden;
        background:#0f1012;
        border:1px solid rgba(255,255,255,.08);
        box-shadow:0 2px 8px rgba(0,0,0,.35);
        pointer-events:auto; /* Remove-Button klickbar */
      }
      .hh-previews.is-floating .hh-thumb{
        width:var(--hh-thumb); height:var(--hh-thumb);
        animation: hh-breathe 3.2s ease-in-out infinite;
      }
      .hh-previews:not(.is-floating) .hh-thumb{ width:100%; height:90px; }

      .hh-thumb img, .hh-thumb video{
        width:100%; height:100%; object-fit:cover; display:block;
      }

      .hh-remove{
        position:absolute; top:6px; right:6px;
        width:22px; height:22px; border-radius:999px;
        background:rgba(0,0,0,.68);
        color:#fff; border:1px solid rgba(255,255,255,.28);
        display:flex; align-items:center; justify-content:center;
        font-size:14px; line-height:1; cursor:pointer;
        transition:transform .12s ease, background-color .12s ease;
      }
      .hh-remove:hover{ transform:scale(1.05); background:rgba(0,0,0,.82); }

      /* zarter Pop-in + Schweben */
      @keyframes hh-pop-in{ from{ transform:translateY(6px) scale(.95); opacity:0 } to{ transform:none; opacity:1 } }
      @keyframes hh-breathe{ 0%,100%{ transform:translateY(0) } 50%{ transform:translateY(-2px) } }

      /* Mobile: kleinere Thumbs */
      @media (max-width: 420px){
        .hh-previews.is-floating{ --hh-thumb: 58px; right:10px; top: calc(-1 * (58px + 16px)); }
      }
    `;
    document.head.appendChild(st);
  }
// ===== Emoji Picker (shared) ===============================================
// ===== Emoji Picker for COMMENTS only ======================================
(function(){
  let picker, cssDone = false, onDocClick, onEsc;

  // Keine UTF-8-Literals -> sicher gegen Dateicodierung
  const EMOJI_POINTS = (
    "1F600 1F601 1F602 1F923 1F642 1F609 1F60D 1F618 1F60E 1F929 1F973 1F914 " +
    "1F971 1F62E 1F622 1F62D 1F621 1F44D 1F44E 1F44F 1F64C 1F64F 1F4AA 1F525 " +
    "2728 1F389 2764 FE0F 1F9E1 1F49B 1F49A 1F499 1F49C 2665 FE0F 1F494 1F91D " +
    "1F440 1F3B5 1F4F7 1F4CE"
  ).split(/\s+/).map(h => String.fromCodePoint(parseInt(h,16)));

  function ensureCSS(){
    if (cssDone) return; cssDone = true;
    const st = document.createElement('style');
    st.textContent = `
      .hh-emoji-pop{position:fixed;z-index:2147483646;background:rgba(20,20,22,.98);
        border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:8px;
        box-shadow:0 10px 30px rgba(0,0,0,.4);
        display:grid;grid-template-columns:repeat(8,28px);gap:6px;
        /* wichtig: System-Emoji-Fonts, sonst Fragezeichen */
        font-family: system-ui, -apple-system, "Apple Color Emoji",
                     "Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji", sans-serif;
      }
      .hh-emoji-pop button{width:28px;height:28px;line-height:28px;
        font-size:18px;background:transparent;border:0;cursor:pointer;border-radius:8px}
      .hh-emoji-pop button:hover{background:rgba(255,255,255,.08)}
      .hh-emoji-hidden{display:none}
    `;
    document.head.appendChild(st);
  }

  function ensurePicker(){
    if (picker) return picker;
    ensureCSS();
    picker = document.createElement('div');
    picker.className = 'hh-emoji-pop hh-emoji-hidden';
    EMOJI_POINTS.forEach(e=>{
      const b=document.createElement('button'); b.type='button'; b.textContent=e; picker.appendChild(b);
    });
    document.body.appendChild(picker);
    return picker;
  }

  function insertAtCursor(ta, txt){
    const s = ta.selectionStart ?? ta.value.length;
    const e = ta.selectionEnd ?? ta.value.length;
    ta.value = ta.value.slice(0,s) + txt + ta.value.slice(e);
    const pos = s + txt.length; ta.setSelectionRange(pos,pos);
    ta.dispatchEvent(new Event('input',{bubbles:true})); ta.focus();
  }

  function place(anchor, panel, placement){
    const r = anchor.getBoundingClientRect();
    const pw = panel.offsetWidth, ph = panel.offsetHeight;
    let x = Math.round(r.left + (r.width - pw)/2);
    let y = placement === 'top' ? Math.round(r.top - ph - 8) : Math.round(r.bottom + 8);
    x = Math.max(8, Math.min(x, window.innerWidth  - pw - 8));
    y = Math.max(8, Math.min(y, window.innerHeight - ph - 8));
    panel.style.left = x + 'px'; panel.style.top = y + 'px';
  }

  function open(anchor, textarea, placement){
    const pop = ensurePicker();
    pop.classList.remove('hh-emoji-hidden');
    place(anchor, pop, placement);

    const clickOnce = (e)=>{
      const btn = e.target.closest('button'); if (!btn) return;
      insertAtCursor(textarea, btn.textContent); close();
    };
    pop.addEventListener('click', clickOnce, { once:true });

    onDocClick = (e)=>{ if(!e.target.closest('.hh-emoji-pop') && e.target!==anchor) close(); };
    onEsc = (e)=>{ if(e.key==='Escape') close(); };
    setTimeout(()=>{ document.addEventListener('mousedown', onDocClick); document.addEventListener('keydown', onEsc); },0);

    function close(){
      pop.classList.add('hh-emoji-hidden');
      document.removeEventListener('mousedown', onDocClick);
      document.removeEventListener('keydown', onEsc);
    }
  }

  // Nur Kommentar-Buttons binden
window.HHEmoji = {
  wireCommentButtons(root){
    const btns = (root || document).querySelectorAll('.hh-emoji-cmt');
    btns.forEach(btn=>{
      if (btn.__hhEmojiWired) return;
      btn.__hhEmojiWired = true;

      btn.addEventListener('click', (ev)=>{
        const ta = btn.closest('.hh-composer, .hh-comment-reply, .hh-comment-form')?.querySelector('textarea');
        if (!ta) return;

        // Textarea hat eindeutige ID -> Button zeigt explizit dorthin
        if (!ta.id) ta.id = 'hh-cmt-ta-' + Math.random().toString(36).slice(2);
        btn.setAttribute('data-emoji-target', '#' + ta.id);

        const placement = btn.dataset.emojiPlacement || 'top';

        // Wenn dein bestehender Picker existiert, benutze ihn – jetzt mit richtigem Target
        if (window.HHEmojiPicker && typeof window.HHEmojiPicker.open === 'function'){
          ev.preventDefault(); ev.stopPropagation();
          window.HHEmojiPicker.open(btn, ta, { placement });
          return;
        }

        // Fallback auf den kleinen internen Picker (falls genutzt)
        ev.preventDefault(); ev.stopPropagation();
        // 'open' kommt aus dem selben IIFE wie zuvor geliefert
        open(btn, ta, placement);
      });
    });
  }
};

})();


  // 100vh Fix (für Mobile Modals)
  (function setHHVh(){
    const apply = () => document.documentElement.style.setProperty('--hh100vh', window.innerHeight + 'px');
    apply(); window.addEventListener('resize', apply);
  })();

  // ===== Fetch helpers ======================================================
  async function postJSON(url, body){
    const res = await fetch(url, { method:"POST", body, credentials:"same-origin" });
    const text = await res.text();
    let j=null; try{ j=JSON.parse(text);}catch(_){}
    if (!res.ok || !j) throw new Error(j?.error || `HTTP ${res.status}: ${text.slice(0,200)}`);
    if (j.ok===false) throw new Error(j.error||"error");
    return j;
  }
  async function getJSON(url){
    const res = await fetch(url, { credentials:"same-origin" });
    const text = await res.text();
    let j=null; try{ j=JSON.parse(text);}catch(_){}
    if (!res.ok || !j) throw new Error(j?.error || `HTTP ${res.status}: ${text.slice(0,200)}`);
    return j;
  }

  // ===== Upload helpers =====================================================
  async function uploadImage(file){
    const fd=new FormData(); fd.append("csrf", CSRF); fd.append("image", file);
    const j = await postJSON(BASE + "/api/upload_image.php", fd);
    if (!j.url) throw new Error("image_upload_failed");
    return j.url;
  }
  async function uploadVideoViaApi(file){
    const fd=new FormData(); fd.append("csrf", CSRF); fd.append("file", file);
    return postJSON(BASE + "/api/video/upload.php", fd); // {ok, video, poster?}
  }

  // ===== Composer (POSTS – Modal/Wall) =====================================
function setupPostComposer(root){
  if (!root || root.__init) return; 
  root.__init = true;

  // Textarea + hidden HTML field
  const ta         = q("textarea.hh-input, textarea", root);
  const hiddenHtml = q('input[name="content_html"]', root) 
                  || root.appendChild(html('<input type="hidden" name="content_html" value="">'));

  // --- preview (works for both wall & modal)
  // modal has wrapper #hh-composer-preview with grid #hh-composer-preview-grid
  const modalWrap  = q('#hh-composer-preview', root);
  const grid       = q('#hh-composer-preview-grid', root);
  const previews   = grid || q(".hh-previews", root) || root.appendChild(html('<div class="hh-previews"></div>'));
  const showWrap   = (show)=> { if (modalWrap) modalWrap.classList.toggle('hidden', !show); };

  // inputs
  const fileImg    = q("input.hh-file-image", root) || root.appendChild(html('<input type="file" class="hh-file-image" accept="image/*" multiple hidden>'));
  const fileVideo  = q("input.hh-file-video", root) || root.appendChild(html('<input type="file" class="hh-file-video" accept="video/*" hidden>'));

  // buttons (existing markup)
  const pickImgBtn   = q('[data-action="pick-image"]', root);
  const pickVideoBtn = q('[data-action="pick-video"]', root);
  const clearBtn     = q('#hh-composer-clear-media', root);

  // collected attachments
  let attachments = []; // [{type:'image'|'video', url, poster?}]

  function rebuildHTML(){
    const parts = attachments.map(a => a.type==='image'
      ? `<figure class="hh-media-img"><img src="${a.url}" alt=""></figure>`
      : `<figure class="hh-media-video"><video controls playsinline ${a.poster?`poster="${a.poster}"`:''}><source src="${a.url}" type="video/mp4"></video></figure>`
    );
    hiddenHtml.value = parts.join("\n");
  }

  function renderPreviews(){
    (grid || previews).innerHTML = '';
    if (!attachments.length){ showWrap(false); return; }
    showWrap(true);
    attachments.forEach((a,i)=>{
      const card = a.type==='image'
        ? html(`<div class="hh-preview"><img src="${a.url}"><button class="hh-x" data-i="${i}" type="button">×</button></div>`)
        : html(`<div class="hh-preview hh-preview-video">
                  <video muted playsinline ${a.poster?`poster="${a.poster}"`:''} src="${a.url}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;"></video>
                  <button class="hh-x" data-i="${i}" type="button">×</button>
                </div>`);
      (grid || previews).appendChild(card);
    });
  }

  (grid || previews).addEventListener("click", (e)=>{
    const btn = e.target.closest(".hh-x"); if (!btn) return;
    attachments.splice(+btn.dataset.i,1);
    renderPreviews(); rebuildHTML();
  });
  clearBtn?.addEventListener('click', ()=>{
    attachments = [];
    renderPreviews(); rebuildHTML();
  });

  // image picking (unchanged behavior)
  pickImgBtn?.addEventListener("click", ()=> fileImg.click());
  fileImg.addEventListener("change", async ()=>{
    try{
      if (!fileImg.files?.length) return;
      for (const f of fileImg.files){ attachments.push({type:"image", url: await uploadImage(f)}); }
      fileImg.value=""; renderPreviews(); rebuildHTML();
    }catch(_){ toast("Bild-Upload fehlgeschlagen"); }
  });

  // video picking (modal + inline). Prefer trimmer if present; else fallback.
  pickVideoBtn?.addEventListener("click", async ()=>{
    if (window.HHMedia?.openTrim){
      try{
        const res = await window.HHMedia.openTrim({ uploadUrl: BASE + "/api/reels/trim_upload.php", csrf: CSRF });
        if (res?.ok && res.video){
          attachments.push({type:"video", url:res.video, poster:res.poster||""});
          renderPreviews(); rebuildHTML();
        }
      }catch(_){/* canceled */}
    } else {
      fileVideo.click();
    }
  });
  fileVideo.addEventListener("change", async ()=>{
    try{
      if (!fileVideo.files?.length) return;
      const res = await uploadReelVideo(fileVideo.files[0]);
      if (res?.ok && res.video){
        attachments.push({type:"video", url:res.video, poster:res.poster||""});
        renderPreviews(); rebuildHTML();
      }
    }catch(_){ toast("Video-Upload fehlgeschlagen"); }
    finally{ fileVideo.value=""; }
  });

  // ---------- SUBMIT (works for inline + modal)
  async function doSubmit(e){
    e?.preventDefault?.();
    const text = (ta?.value||"").trim();
    rebuildHTML();
    const mediaHTML = (hiddenHtml.value||"").trim();

    if (!text && !mediaHTML){
      toast("Bitte Text oder Medien hinzufügen.");
      return;
    }

    const fd = new FormData();
    fd.set("csrf", CSRF);
    if (text)      fd.set("content_plain", text);
    if (mediaHTML) fd.set("content_html", mediaHTML);

    // endpoint: use form action if present, otherwise data-endpoint
    const endpoint = root.getAttribute('action') || root.dataset.endpoint || (BASE + "/api/wall/post_create.php");

    try{
      const j = await postJSON(endpoint, fd);

      // reset
      if (ta) ta.value = '';
      attachments = []; renderPreviews(); rebuildHTML();

      // inject new post into feed if provided
      const feed = q("#feed");
      if (j.post?.html && feed){
        feed.insertAdjacentHTML("afterbegin", j.post.html);
        hydratePosts(feed);
        document.dispatchEvent(new CustomEvent('hh-hydrate', { detail: { root: feed } }));
      }

      // close modal if we’re in it
      q('#hh-composer-close')?.click();
    } catch(e){
      toast("Fehler: " + (e?.message || e));
    }
  }

  // handle both: normal submit and custom buttons
  root.addEventListener('submit', doSubmit);
  q('[data-action="submit"]', root)?.addEventListener('click', doSubmit);
}


  // ===== Feed (paging) ======================================================
  async function loadFeed(){
    const feed = q("#feed");
    if (!feed || feed.__loading || feed.__done) return;
    feed.__loading = true;

    try {
      const url = new URL(feed.dataset.endpoint, location.origin);

      const cursor = feed.dataset.cursor;
      if (cursor) {
        url.searchParams.set("cursor", cursor);
      } else {
        const posts = feed.querySelectorAll('article[data-post-id]');
        const lastEl = posts[posts.length - 1];
        const lastId = lastEl ? lastEl.getAttribute('data-post-id') : "";
        if (lastId) {
          url.searchParams.set("before_id", lastId);
          url.searchParams.set("after_id", lastId);
        }
      }

      const j = await getJSON(url.toString());
      if (!j || j.ok === false) { console.warn("loadFeed: Backend not ok", j); return; }

      if (j.next || j.cursor) feed.dataset.cursor = j.next || j.cursor;

      const items = Array.isArray(j.posts) ? j.posts : [];
      if (!items.length) { feed.__done = true; return; }

      items.forEach(p => {
        if (!feed.querySelector(`[data-post-id="${p.id}"]`)) {
          feed.insertAdjacentHTML("beforeend", p.html);
        }
      });

      hydratePosts(feed);
      document.dispatchEvent(new CustomEvent('hh-hydrate', { detail: { root: feed } }));
    } catch (e) {
      console.error("Feed error:", e);
    } finally {
      feed.__loading = false;
    }
  }

  function hydratePosts(root){
    qa("article.hh-post, article[data-post-id]", root).forEach(post=>{
      if (post.__hydrated) return; 
      post.__hydrated = true;

      const form = q(".hh-comment-form.hh-composer", post);
      if (form && form.offsetParent !== null) {
        setupInlineComposer(form);
      }

      const sec = q(".hh-comments", post);
      if (sec && sec.offsetParent !== null && !sec.__loaded) {
        loadComments(sec);
      }
    });
  }

  async function loadComments(section, force){
    if (!section || section.__loading) return;
    if (section.__loaded && !force) return;
    section.__loading = true;

    try {
      const j = await getJSON(section.dataset.endpoint);
      if (!j?.ok) return;

      const list = q(".hh-comment-list", section) || section.appendChild(html('<div class="hh-comment-list"></div>'));
      list.innerHTML = "";

      const all = (j.comments || []).map(c => renderComment(c));

      all.forEach((el, i) => {
        if(i >= 1){
          el.style.display = "none";
          el.classList.add("hidden-comment");
        }
        list.appendChild(el);
      });

      if(all.length > 1){
        const btnWrap = html(`<div class="comments-toggle mt-2"></div>`);
        const showBtn = html(`<button class="text-m-medium text-w-neutral-1 mb-16p block">Weitere Kommentare ansehen</button>`);
        const hideBtn = html(`<button class="text-m-medium text-w-neutral-1 mb-16p block" style="display:none">Kommentare ausblenden</button>`);

        showBtn.addEventListener("click", ()=>{
          list.querySelectorAll(".hidden-comment").forEach(c => c.style.display = "");
          showBtn.style.display = "none";
          hideBtn.style.display = "inline-block";
        });

        hideBtn.addEventListener("click", ()=>{
          list.querySelectorAll(".hidden-comment").forEach(c => c.style.display = "none");
          hideBtn.style.display = "none";
          showBtn.style.display = "inline-block";
          list.scrollIntoView({behavior: "smooth", block: "start"});
        });

        btnWrap.appendChild(showBtn);
        btnWrap.appendChild(hideBtn);
        list.appendChild(btnWrap);
      }

    } catch(e){
      console.error(e);
    }
    document.dispatchEvent(new CustomEvent('hh-hydrate', { detail: { root: section } }));
    section.__loaded = true;
    section.__loading = false;
  }

  function updateCommentCountFor(section){
    try{
      const post = section.closest('[data-post-id]');
      if (!post) return;
      const postId = post.dataset.postId;
      const list = section.querySelector('.hh-comment-list');
      const count = list ? list.querySelectorAll('[data-comment-id]').length : 0;
      const text  = (count === 1) ? '1 Kommentar' : `${count} Kommentare`;
      document.querySelectorAll(`.hh-post[data-post-id="${postId}"] .hh-comment-count .hh-count-text`)
        .forEach(el => el.textContent = text);
    }catch(_){}
  }

  async function refreshCommentsFor(section){
    if (!section) return;
    const list = section.querySelector('.hh-comment-list');
    const ep   = section.getAttribute('data-endpoint');
    if (!list || !ep) return;

    try {
      const res = await fetch(ep, {
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });

      if (res.headers.get('content-type')?.includes('application/json')) {
        const data = await res.json();
        list.innerHTML = '';
        (data?.comments || []).forEach(c => {
          list.appendChild(renderComment(c, false));
        });
      } else {
        list.innerHTML = await res.text();
      }
    } catch (e) {
      console.error('refreshCommentsFor failed:', e);
    }
    updateCommentCountFor(section);
  }

  // ===== Render Comment + Inline-Composer ===================================
  
  function renderComment(c, isChild){
    const CAN_EDIT = !!(c.can_edit || (window.ME_ID && Number(c.user_id)===Number(window.ME_ID)) || window.IS_ADMIN);

    const el = html(`
      <div class="flex items-start gap-3 ${isChild ? 'hh-comment-child' : ''}" 
           data-comment-id="${c.id}" style="padding-top: 20px;">
        
        <img class="avatar size-48p"
             src="${(c.avatar || c.user?.avatar || '/assets/images/avatar-default.png')}" 
             alt="user" />
        
        <div class="flex-1 w-full">
          <div class="bg-glass-5 w-full px-3 py-2 rounded-12">
            <a href="${BASE}/user.php?u=${(c.slug || '')}"
               class="text-m-medium text-w-neutral-1 link-1 line-clamp-1 mb-2">
               ${(c.username || 'User')}
            </a>

            <!-- Content-Wrapper -->
            <div class="flex w-full flex-col gap-2 hh-comment-content">
              ${
                c.deleted_content
                ? `<p class="hh-text text-sm italic opacity-70">Dieses Kommentar wurde entfernt.</p>`
                : `
                  ${
                    c.content_plain
                    ? `<p class="hh-text text-sm text-w-neutral-3">${String(c.content_plain)
                         .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                         .replace(/\n/g,'<br>')}</p>`
                    : ''
                  }
                  ${c.content_html ? `<div class="hh-media">${c.content_html}</div>` : '' }
                  ${c.media_html || ''} <!-- evtl. serverseitige Medien -->
                `
              }
            </div>

            <!-- Meta -->
            <div class="hh-comment-meta text-xs opacity-70 mt-1 flex items-center gap-2">
              ${c.created_at ? `<time datetime="${c.created_at}">${c.created_at_readable || c.created_at}</time>` : ''}
              ${c.has_edits ? `<a href="#" class="hh-edited-badge" data-type="comment" data-id="${c.id}">Bearbeitet</a>` : ''}

              ${CAN_EDIT
                ? `
                  <button class="hh-btn hh-btn-ghost hh-btn-edit-comment" style="padding: 8px 0px;" type="button" data-comment-id="${c.id}">
                   <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 15h-3a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v3" /><path d="M11 19h-3a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v1.25" /><path d="M18.42 15.61a2.1 2.1 0 1 1 2.97 2.97l-3.39 3.42h-3v-3z" /></svg>
                  </button>
                  <button class="hh-btn hh-btn-ghost text-red-400 hh-btn-delete-comment" style="padding: 8px 0px;" type="button" data-comment-id="${c.id}">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message-circle-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13.593 19.855a9.96 9.96 0 0 1 -5.893 -.855l-4.7 1l1.3 -3.9c-2.324 -3.437 -1.426 -7.872 2.1 -10.374c3.526 -2.501 8.59 -2.296 11.845 .48c2.128 1.816 3.053 4.363 2.693 6.813" /><path d="M22 22l-5 -5" /><path d="M17 22l5 -5" /></svg>
                  </button>
                `
                : ''
              }

              <button class="hh-btn hh-btn-ghost" style="padding:8px 0" data-report-comment data-comment-id="${c.id}"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-flag-off"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 5v16" /><path d="M19 5v9" /><path d="M7.641 3.645a5 5 0 0 1 4.359 1.355a5 5 0 0 0 7 0" /><path d="M5 14a5 5 0 0 1 7 0a4.984 4.984 0 0 0 3.437 1.429m3.019 -.966c.19 -.14 .371 -.294 .544 -.463" /><path d="M3 3l18 18" /></svg></button>

              ${!isChild
                ? `<button class="hh-btn hh-btn-ghost" style="padding: 8px 0px;" type="button" data-reply><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9h8" /><path d="M8 13h6" /><path d="M12.01 18.594l-4.01 2.406v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v5.5" /><path d="M16 19h6" /><path d="M19 16v6" /></svg></button>`
                : ''
              }
            </div>
          </div>

          <div class="flex items-center justify-between gap-4 mt-1">
            <div class="flex items-center gap-4">
              ${c.like_button_html || ''}
            </div>
            <div class="like-summary ml-auto">
              ${c.like_summary_html || ''}
            </div>
          </div>

          <div class="hh-children"></div>
        </div>
      </div>
    `);

    // Reply-Composer
    if (!isChild){
      q("[data-reply]", el)?.addEventListener("click", ()=>{
        if (el.__replyOpen){
          el.__replyOpen.remove();
          el.__replyOpen = null;
          return;
        }
        const comp = html(`
          <div class="hh-comment-reply hh-composer"
               data-endpoint="${BASE}/api/wall/comment_create.php"
               data-parent-id="${c.id}">
            <textarea class="hh-input w-full" rows="2" placeholder="Antwort schreiben…" maxlength="1000"></textarea>
            <div class="hh-previews hidden w-full"></div>
            <div class="hh-composer-actions mt-2 flex items-center gap-2 w-full">
              <button class="hh-btn hh-btn-ghost" type="button" data-action="pick-image">Bild</button>
              <button class="hh-btn ml-auto" type="button" data-action="submit">Senden</button>
            </div>
            <input type="hidden" name="content_html" value="">
            <input type="file" class="hh-file-image" accept="image/*" multiple hidden>
          </div>
        `);

        el.__replyOpen = comp;
        q(".hh-children", el).prepend(comp);
        setupComposer(comp);
      });
    }

    // Children toggle
    if (c.children && c.children.length){
      const childrenWrap = q(".hh-children", el);
      c.children.forEach(cc => {
        const childEl = renderComment(cc, true);
        childEl.classList.add("hidden-reply");
        childrenWrap.appendChild(childEl);
      });
      const toggleBtn = html(`
        <button class="text-m-medium text-w-neutral-1 mb-16p block">
          Antworten ansehen (${c.children.length})
        </button>
      `);
      toggleBtn.addEventListener("click", ()=>{
        const hidden = childrenWrap.querySelectorAll(".hidden-reply");
        const isClosed = [...hidden].some(h => !h.classList.contains("show"));
        if (isClosed){
          hidden.forEach(h => h.classList.add("show"));
          toggleBtn.textContent = "Antworten ausblenden";
        } else {
          hidden.forEach(h => h.classList.remove("show"));
          toggleBtn.textContent = `Antworten ansehen (${c.children.length})`;
        }
      });
      childrenWrap.appendChild(toggleBtn);
    }

    return el;
  }

  // ===== Inline-Composer (Kommentare) **************************************
  function setupInlineComposer(root){
    if (!root || root.__init) return; root.__init = true;
    ensurePreviewStyles();

    const ta      = q('textarea', root);
    const btnSend = q('[data-action="submit"]', root);
    const pickImg = q('[data-action="pick-image"]', root);
    const inpImg  = q('.hh-file-image', root);
    const previews= q('.hh-previews', root);

    const MAX_FILES = 10;

    const getCSRF = () =>
      document.querySelector('meta[name="csrf"]')?.content ||
      document.querySelector('meta[name="csrf-token"]')?.content ||
      document.querySelector('meta[name="x-csrf-token"]')?.content || '';

    function getPostIdFrom(root){
      let pid = root.getAttribute('data-post-id');
      if (pid) return pid;
      const sec = root.closest('.hh-comments');
      const ep  = sec?.getAttribute('data-endpoint') || '';
      if (ep){
        try { const u = new URL(ep, location.origin); pid = u.searchParams.get('post_id'); } catch {}
      }
      if (!pid && sec?.dataset.postId) pid = sec.dataset.postId;
      return pid || '';
    }

    let files = [];

    pickImg?.addEventListener('click', ()=> inpImg?.click());
    inpImg?.addEventListener('change', ()=>{
      files = Array.from(inpImg.files || []).filter(f => /^image\//.test(f.type)).slice(0, MAX_FILES);
      renderPreview();
    });

    function setHidden(el, hide){ if (el) el.classList.toggle('hidden', !!hide); }

    // Schwebende Vorschau ÜBER der Textarea
    function renderPreview(){
      if (!previews) return;
      if (!files.length){ setHidden(previews, true); previews.innerHTML=''; return; }
      setHidden(previews, false);
      previews.classList.add('is-floating');
      let h = `<div class="hh-previews-row">`;
      files.forEach((f,i)=>{
        const url = URL.createObjectURL(f);
        h += `<figure class="hh-thumb" data-i="${i}">
                <img class="hh-media-img" src="${url}" alt="">
                <button type="button" class="hh-remove" aria-label="Entfernen" title="Entfernen" data-i="${i}">×</button>
              </figure>`;
      });
      h += `</div>`;
      previews.innerHTML = h;
    }

    previews?.addEventListener('click', (e)=>{
      const btn = e.target.closest('.hh-remove');
      if (!btn) return;
      const i = +btn.dataset.i;
      if (Number.isInteger(i)){
        files.splice(i,1);
        renderPreview();
        if (!files.length && inpImg) inpImg.value = '';
      }
    });

    async function submit(){
      const text   = (ta?.value || '').trim();
      const postId = getPostIdFrom(root);
      const parent = root.getAttribute('data-parent-id') || '';

      if (!postId) { alert('post_id fehlt'); return; }
      if (!text && files.length === 0){ ta?.focus(); return; }

      btnSend.disabled = true;

      try{
        const fd = new FormData();
        fd.set('post_id', postId);
        if (parent) fd.set('parent_comment_id', parent);
        fd.set('text', text);
        fd.set('content', text);
        fd.set('message', text);
        fd.set('body', text);
        const csrf = getCSRF();
        if (csrf) fd.set('csrf', csrf);

        files.forEach(f => fd.append('files[]', f, f.name));

        const endpoint = root.getAttribute('data-endpoint') || `${BASE}/api/wall/comment_create.php`;
        const res = await fetch(endpoint, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF': csrf || '' }
        });
        const j = await res.json().catch(()=> ({}));
        if (!res.ok || j?.ok === false) throw new Error(j?.error || `http_${res.status}`);

        // Reset
        if (ta) ta.value = '';
        if (inpImg) inpImg.value = '';
        files = [];
        renderPreview();

        const sec  = root.closest('.hh-comments') || document.getElementById(root.dataset.commentsFor || '');
        if (sec) await refreshCommentsFor(sec);

      } catch(err){
        console.error('comment submit failed:', err);
        alert('Kommentar konnte nicht gesendet werden.');
      } finally {
        btnSend.disabled = false;
      }
    }

    btnSend?.addEventListener('click', submit);
	// in setupInlineComposer(root) am Ende:
window.HHEmoji?.wireCommentButtons(root);

  }

  // ===== DOM Ready: Feed + Composer + Sentinel ==============================
  document.addEventListener("DOMContentLoaded", ()=>{
    ensurePreviewStyles();
    setupPostComposer(q("#wall-composer"));
	setupPostComposer(q("#hh-composer-form")); 
    loadFeed();

    const sentinel = q("#feed-sentinel");
    if (sentinel && "IntersectionObserver" in window){
      new IntersectionObserver(
        (entries)=>entries.forEach(e=>e.isIntersecting && loadFeed()),
        {rootMargin:"800px 0px"}
      ).observe(sentinel);
    } else {
      window.addEventListener("scroll", ()=>{ if (innerHeight + scrollY + 600 >= document.body.scrollHeight) loadFeed(); });
    }
  });

  // ===== COMMENTS MODAL: self-install ======================================
  (function(){
    const MODAL_ID = 'hh-comments-modal';

    function ensureCommentsModalStyles(){
      if (document.getElementById('hhc-modal-mobile-css')) return;
      const st = document.createElement('style');
      st.id = 'hhc-modal-mobile-css';
      st.textContent = `
        #hh-comments-modal{ z-index:2147483600 !important; }
        #hh-comments-modal .hhc-backdrop{ z-index:0 !important; }
        /* Grid: Header | Body (scroll) | Footer (fix) */
        #hh-comments-modal .hhc-dialog{
          z-index:1 !important; position:relative;
          display:grid; grid-template-rows:auto 1fr auto;
          width:min(1000px,95vw); height:min(88vh, var(--hh100vh,100vh));
          margin:4vh auto; border-radius:16px;
          background:rgba(16,16,18,.98); border:1px solid rgba(255,255,255,.08);
          box-shadow:0 20px 50px rgba(0,0,0,.5);
        }
        #hh-comments-modal .hhc-header{
          position:sticky; top:0; z-index:3;
          padding:14px 18px; background:inherit;
          border-bottom:1px solid rgba(255,255,255,.08);
          display:flex; align-items:center; justify-content:space-between;
        }
        #hh-comments-modal .hhc-body{
          overflow:auto; -webkit-overflow-scrolling:touch; overscroll-behavior:contain;
          padding:14px;
        }
        #hh-comments-modal .hhc-footer{
          position:sticky; bottom:0; z-index:3;
          background:inherit; border-top:1px solid rgba(255,255,255,.08);
          padding:10px 14px calc(10px + env(safe-area-inset-bottom));
          overflow:visible; /* wichtig, damit floating Previews nicht abgeschnitten werden */
        }

        /* Wenn Comments-Modal offen: andere Overlays darüber */
        body.hhc-open .swal2-container,
        body.hhc-open .modal,
        body.hhc-open .modal-backdrop,
        body.hhc-open .hh-modal,
        body.hhc-open .hh-likers-modal,
        body.hhc-open #hh-likers-modal,
        body.hhc-open .likers-modal,
        body.hhc-open .report-modal,
        body.hhc-open .lightbox,
        body.hhc-open .pswp,
        body.hhc-open #hh-media-viewer,
        body.hhc-open #hh-media-viewer .hhmv-backdrop,
        body.hhc-open #hh-media-viewer .hhmv-panel{
          z-index:2147483647 !important;
          position:fixed !important;
        }

        @media (max-width: 768px){
          #hh-comments-modal .hhc-dialog{
            width:100vw; height:var(--hh100vh,100vh); margin:0; border-radius:0;
            border-left:0 !important; border-right:0 !important;
          }
        }
      `;
      document.head.appendChild(st);
    }

    function ensureCommentsModal(){
      ensureCommentsModalStyles();

      let modal = document.getElementById(MODAL_ID);
      if (modal) return modal;

      const tpl = document.createElement('template');
      tpl.innerHTML = `
<div id="${MODAL_ID}" class="hidden" aria-hidden="true" style="position:fixed;inset:0;z-index:99999">
  <div class="hhc-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(2px)"></div>
  <div class="hhc-dialog">
    <div class="hhc-header">
      <h3 style="margin:0;font-size:18px;font-weight:600">Kommentare</h3>
      <button type="button" class="hhc-close" style="width:34px;height:34px;border-radius:999px;background:transparent;color:#fff;border:0">?</button>
    </div>
    <div class="hhc-body"><div class="hhc-content"></div></div>
    <div class="hhc-footer"></div>
  </div>
</div>`;
      modal = tpl.content.firstElementChild;
      document.body.appendChild(modal);
      return modal;
    }

    function initCommentsModal(){
      const modal   = ensureCommentsModal();
      const backdrop= modal.querySelector('.hhc-backdrop');
      const closeBt = modal.querySelector('.hhc-close');
      const content = modal.querySelector('.hhc-content');
      const footer  = modal.querySelector('.hhc-footer');

      const open = () => {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden','false');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('hhc-open');
      };
      const close = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden','true');
        document.body.style.overflow = '';
        document.body.classList.remove('hhc-open');
        content.innerHTML = '';
        footer.innerHTML  = '';
      };

      backdrop.addEventListener('click', close);
      closeBt.addEventListener('click', close);
      document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

      function mountComposer(sectionInClone){
        const composer = sectionInClone?.querySelector('.hh-comment-form.hh-composer');
        if (!composer) return;
        if (!sectionInClone.id) sectionInClone.id = 'hhc-sec-' + Date.now();
        composer.dataset.commentsFor = sectionInClone.id;
        footer.innerHTML = '';
        footer.appendChild(composer);
        try { setupInlineComposer(composer); } catch(_){}
      }
// ---- reels upload (separate from forum video upload) ------------------------
async function uploadReelVideo(file){
  const fd = new FormData();
  fd.append("csrf", CSRF);
  fd.append("file", file);
  // returns { ok:true, video:"/path/video.mp4", poster:"/path/poster.jpg" }
  return postJSON(BASE + "/api/reels/trim_upload.php", fd);
}

      async function openForPostEl(postEl){
        if (!postEl) return;
        content.innerHTML = '';

        const clone = postEl.cloneNode(true);
        content.appendChild(clone);
        open();

        const sec = content.querySelector('.hh-comments');
        if (sec){
          sec.style.display = '';
          sec.classList.remove('hidden');
          try { await refreshCommentsFor(sec); } catch(_) {}
          mountComposer(sec);
        }
      }

      async function openForPostId(id){
        const postEl = document.querySelector(`.hh-post[data-post-id="${id}"]`);
        if (postEl) openForPostEl(postEl);
      }

      // Debug-API
      window.HHComments = { openForPostId, openForPostEl, close };

      // Globaler Klick-Handler
      const CLICK_SEL = '[data-open-comments], .hh-open-comments, .hh-comment-count, .btn-comment';
      function handler(e){
        const trg = e.target.closest(CLICK_SEL);
        if (!trg) return;
        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        const pid = trg.dataset.postId || trg.closest('[data-post-id]')?.dataset.postId;
        if (pid) openForPostId(pid);
      }
      document.addEventListener('click', handler, { capture:true });
      document.addEventListener('touchstart', handler, { capture:true, passive:false });
    }

    if (document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', initCommentsModal);
    } else {
      initCommentsModal();
    }
  })();

})();

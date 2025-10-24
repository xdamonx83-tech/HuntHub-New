// /assets/js/wall.likes.force.js
(function () {
  const BASE = (typeof window.APP_BASE === 'string') ? window.APP_BASE : '';
  const CSRF = document.querySelector('meta[name="csrf"], meta[name="csrf-token"]')?.content || '';

  const api = (p) => (BASE + (p.startsWith('/') ? p : '/' + p)).replace(/\/+/, '/');
  const $all = (s, root) => Array.from((root || document).querySelectorAll(s));

  function makeBtn(type, id, liked=false, count=0){
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-like inline-flex items-center gap-1 text-sm' + (liked ? ' is-liked' : '');
    btn.dataset.entityType = type;
    btn.dataset.entityId = String(id);

    const icon = document.createElement('span');
    icon.className = 'like-icon';
    icon.innerHTML = liked ? '❤' : '♡';

    const cnt = document.createElement('span');
    cnt.className = 'like-count';
    cnt.textContent = String(count || 0);

    btn.append(icon, cnt);
    return btn;
  }

  function ensureActions(container){
    let bar = container.querySelector('.post-actions');
    if (!bar){
      bar = document.createElement('div');
      bar.className = 'post-actions flex items-center gap-3 mt-2';
      container.parentNode.insertBefore(bar, container); // direkt VOR Composer/Kommentare
    }
    return bar;
  }

  function findPostIdFromContext(ctx){
    // 1) data-post-id am Artikel
    const article = ctx.closest?.('[data-post-id]');
    if (article){
      const id = parseInt(article.getAttribute('data-post-id') || '0',10);
      if (id>0) return id;
    }
    // 2) Geschwister: #post-comments-<id>
    const comments = ctx.parentNode?.querySelector?.('[id^="post-comments-"]');
    if (comments){
      const m = (comments.id||'').match(/^post-comments-(\d+)$/);
      if (m) return parseInt(m[1],10) || 0;
    }
    // 3) Fallback: data-wall-post-id irgendwo drüber
    const any = ctx.closest?.('[data-wall-post-id]');
    if (any){
      const id = parseInt(any.getAttribute('data-wall-post-id')||'0',10);
      if (id>0) return id;
    }
    return 0;
  }

  async function bulkSummary(postIds, commentIds){
    const res = await fetch(api('/api/wall/likes_summary.php'), {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ posts: postIds, comments: commentIds })
    });
    const j = await res.json().catch(()=>null);
    if (!res.ok || !j || j.ok===false) throw new Error(j?.error || `HTTP ${res.status}`);
    return j;
  }

  async function toggle(btn){
    const id = parseInt(btn.dataset.entityId||'0',10);
    const type = btn.dataset.entityType;
    if (!id || !type) return;
    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('type', type);
      fd.append('id', String(id));
      const r = await fetch(api('/api/wall/like_toggle.php'), { method:'POST', body:fd, credentials:'same-origin' });
      const j = await r.json();
      if (j?.ok){
        btn.querySelector('.like-icon').innerHTML = j.liked ? '❤' : '♡';
        btn.querySelector('.like-count').textContent = String(j.count || 0);
        btn.classList.toggle('is-liked', !!j.liked);
      }
    } catch(e){ console.warn('[like] toggle failed', e); }
    finally { btn.disabled = false; }
  }

  document.addEventListener('click', (ev)=>{
    const btn = ev.target.closest?.('button.btn-like[data-entity-type][data-entity-id]');
    if (btn){ ev.preventDefault(); toggle(btn); }
  });

  async function injectAll(root){
    // Posts: wir verankern vor dem Composer
    const composers = $all('.post-comment-composer, [id^="post-reply-"]', root);
    const postIds = [];
    const seen = new Set();

    composers.forEach(comp=>{
      const pid = findPostIdFromContext(comp);
      if (!pid || seen.has(pid)) return;
      seen.add(pid);
      postIds.push(pid);

      const bar = ensureActions(comp);
      // vorhandene Button duplizierung vermeiden
      if (!bar.querySelector('button.btn-like[data-entity-type="post"]')){
        bar.appendChild(makeBtn('post', pid, false, 0));
      }
    });

    // Kommentare (wenn mit data-comment-id gerendert)
    const commentEls = $all('[data-comment-id]', root);
    const commentIds = [];
    commentEls.forEach(c=>{
      const cid = parseInt(c.getAttribute('data-comment-id')||'0',10);
      if (!cid) return;
      commentIds.push(cid);
      let actions = c.querySelector('.comment-actions');
      if (!actions){
        actions = document.createElement('div');
        actions.className = 'comment-actions flex items-center gap-3 mt-1';
        c.appendChild(actions);
      }
      if (!actions.querySelector('button.btn-like[data-entity-type="comment"]')){
        actions.appendChild(makeBtn('comment', cid, false, 0));
      }
    });

    // Jetzt Counts/States laden
    if (postIds.length || commentIds.length){
      try {
        const j = await bulkSummary(postIds, commentIds);
        // Posts
        postIds.forEach(id=>{
          const btn = document.querySelector(`.post-actions button.btn-like[data-entity-type="post"][data-entity-id="${id}"]`);
          if (!btn) return;
          const st = j.posts?.[id] || {count:0,liked:false};
          btn.querySelector('.like-icon').innerHTML = st.liked ? '❤' : '♡';
          btn.querySelector('.like-count').textContent = String(st.count||0);
          btn.classList.toggle('is-liked', !!st.liked);
        });
        // Comments
        commentIds.forEach(id=>{
          const btn = document.querySelector(`.comment-actions button.btn-like[data-entity-type="comment"][data-entity-id="${id}"]`);
          if (!btn) return;
          const st = j.comments?.[id] || {count:0,liked:false};
          btn.querySelector('.like-icon').innerHTML = st.liked ? '❤' : '♡';
          btn.querySelector('.like-count').textContent = String(st.count||0);
          btn.classList.toggle('is-liked', !!st.liked);
        });
      } catch(e){ console.warn('[likes_summary] failed', e); }
    }
  }

  function init(){
    injectAll(document);
    // Neue Posts/Kommentare (Infinite Scroll) beobachten:
    const feed = document.getElementById('feed');
    if (feed){
      const mo = new MutationObserver((muts)=>{
        for (const m of muts){
          m.addedNodes.forEach(n=>{
            if (!(n instanceof Element)) return;
            if (n.querySelector?.('.post-comment-composer, [id^="post-reply-"], [data-comment-id]') || 
                n.matches?.('.post-comment-composer, [id^="post-reply-"], [data-comment-id]')) {
              injectAll(n);
            }
          });
        }
      });
      mo.observe(feed, { childList:true, subtree:true });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

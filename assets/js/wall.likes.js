(function(){
  const BASE = window.APP_BASE || '';
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

  async function toggle(btn){
    const fd = new FormData();
    fd.set('csrf', CSRF);
    fd.set('type', btn.dataset.entityType);
    fd.set('id', btn.dataset.entityId);

    const r = await fetch(BASE+'/api/wall/react.php',{method:'POST',body:fd,credentials:'same-origin'});
    const j = await r.json();
    if (!j.ok) return;
    btn.querySelector('.like-icon').innerHTML = j.liked ? '❤' : '♡';
    btn.querySelector('.like-count').textContent = j.count;
    btn.classList.toggle('is-liked', j.liked);
    const bar = btn.closest('.like-bar');
    bar.querySelector('.likers-count').textContent = j.count;
  }

async function openLikers(type,id){
    let modal=document.getElementById('likers-modal');
    if(!modal){
      modal=document.createElement('div');
      modal.id='likers-modal';
      modal.className='modal-mask';
      modal.innerHTML=`
        <div class="modal-card">
          <div class="modal-hd">
            <strong>Gefällt mir</strong>
            <button type="button" id="likers-close">×</button>
          </div>
          <div class="modal-bd" id="likersList">Lade …</div>
        </div>`;
      document.body.appendChild(modal);
      modal.addEventListener('click',e=>{
        if(e.target.id==='likers-modal' || e.target.id==='likers-close'){ modal.style.display='none'; }
      });
    }
    modal.style.display='flex';
    const list=document.getElementById('likersList');
    list.textContent='Lade …';
    try{
      const r=await fetch(`${BASE}/api/wall/likers.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
      const j=await r.json();
      if(!j.ok){ list.textContent='Fehler beim Laden'; return; }
      list.innerHTML = (j.users||[]).map(u=>`
        <div class="liker-row">
          <img src="${u.avatar||'/assets/images/avatar-default.png'}" class="avatar">
          <a href="${BASE}/user.php?u=${encodeURIComponent(u.slug||'')}">${u.username||'User'}</a>
        </div>`).join('');
    }catch(e){ list.textContent='Fehler beim Laden'; }
  }

document.addEventListener('click',e=>{
  const btn = e.target.closest('.btn-like');
  if (btn) {
    e.preventDefault();
    toggle(btn);
    return;
  }

  const who = e.target.closest('.btn-likers');
  if (who) {
    e.preventDefault();
    openLikers(who.dataset.entityType, who.dataset.entityId);
  }
});

})();

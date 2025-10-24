(function(){
  'use strict';

  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));

  const modal = $('#hh-comments-modal');
  if (!modal) return;

  // Ensure structure: backdrop + dialog(header|body|footer)
  const backdrop = $('.hh-modal-backdrop', modal) || modal.appendChild(el('<div class="hh-modal-backdrop"></div>'));
  let dialog = $('.hh-modal-dialog', modal);
  if (!dialog){
    dialog = el('<div class="hh-modal-dialog"></div>');
    // vorhandenen Inhalt in die neue Dialog-Struktur übernehmen
    const bodyOld = $('.hh-modal-body', modal) || el('<div class="hh-modal-body"></div>');
    const contentOld = $('.hh-modal-content', modal) || el('<div class="hh-modal-content"></div>');
    if (!bodyOld.contains(contentOld)) bodyOld.appendChild(contentOld);
    const header = $('.hh-modal-header', modal) || el('<div class="hh-modal-header"><h3 class="m-0 text-lg font-semibold">Kommentare</h3><button type="button" class="hh-modal-close" aria-label="Schließen">✕</button></div>');
    const footer = $('.hh-modal-footer', modal) || el('<div class="hh-modal-footer"></div>');
    dialog.appendChild(header);
    dialog.appendChild(bodyOld);
    dialog.appendChild(footer);
    modal.appendChild(dialog);
  }

  const header  = $('.hh-modal-header', dialog);
  const closeBt = $('.hh-modal-close', header) || header.appendChild(el('<button type="button" class="hh-modal-close" aria-label="Schließen">✕</button>'));
  const bodyCt  = $('.hh-modal-body', dialog);
  const cont    = $('.hh-modal-content', bodyCt) || bodyCt.appendChild(el('<div class="hh-modal-content"></div>'));
  const footer  = $('.hh-modal-footer', dialog);

  function el(html){ const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstElementChild; }

  function openModal(){
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
  }
  function closeModal(){
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden','true');
    document.body.style.overflow='';
    cont.innerHTML='';
    footer.innerHTML='';
  }

  backdrop.addEventListener('click', closeModal);
  closeBt.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !modal.classList.contains('hidden')) closeModal(); });

  // --- Kommentare in einer Section neu laden (wie gehabt)
  async function refreshCommentsFor(section){
    if (!section) return;
    const list = section.querySelector('.hh-comment-list');
    const ep   = section.getAttribute('data-endpoint');
    if (!list || !ep) return;

    try{
      const res = await fetch(ep, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
      if (res.headers.get('content-type')?.includes('application/json')){
        const data = await res.json();
        list.innerHTML = '';
        (data?.comments || []).forEach(c => list.appendChild(window.renderComment ? window.renderComment(c,false) : el(c.html)));
      } else {
        list.innerHTML = await res.text();
      }
    }catch(err){ console.error('refreshCommentsFor failed', err); }
  }

  // --- Composer unten fix mounten
  function mountComposer(sectionInClone){
    const composer = sectionInClone?.querySelector('.hh-comment-form.hh-composer');
    if (!composer) return;

    // Section identifizieren, damit submit() später weiß, wen es refreshen muss
    if (!sectionInClone.id) sectionInClone.id = 'hhc-sec-' + Date.now();
    composer.dataset.commentsFor = sectionInClone.id;

    // ins Footer schieben
    footer.innerHTML = '';
    footer.appendChild(composer);

    // Hydratisieren: entweder globale API oder Event
    if (window.setupComposer) {
      try { window.setupComposer(composer); } catch(_){}
    } else {
      document.dispatchEvent(new CustomEvent('hh-hydrate', { detail: { root: composer } }));
    }
  }

  // Öffnet das Modal für einen Post-Knoten (.hh-post)
  async function openCommentsModalFor(postEl){
    if (!postEl) return;

    // 1) Card klonen (inkl. Kommentar-Section) und in Modal einfügen
    const clone = postEl.cloneNode(true);
    cont.innerHTML = '';            // leeren
    cont.appendChild(clone);
    openModal();

    // 2) Kommentar-Sektion im Modal frisch laden
    const sec = $('.hh-comments', clone);
    if (sec) {
      await refreshCommentsFor(sec);
      mountComposer(sec);          // <- Composer in Footer
      // Beim Refresh durch submit() wieder hydrieren lassen
      document.addEventListener('hh:comments:refreshed', (e)=>{
        if (e.detail?.sectionId === sec.id) mountComposer(sec);
      }, { once: true });
    }
  }

  // Globaler Click-Handler: öffne bei Klick auf "Kommentieren", Zähler etc.
  document.addEventListener('click', (e)=>{
    const trg = e.target.closest('[data-open-comments], .hh-open-comments, .hh-comment-count, .btn-comment');
    if (!trg) return;
    const post = trg.closest('.hh-post,[data-post-id]');
    if (!post) return;
    e.preventDefault();
    openCommentsModalFor(post);
  });

})();

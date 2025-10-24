(function(){
  'use strict';

  const getCSRF = ()=> document.querySelector('meta[name="csrf"]')?.content || '';

  const escapeHtml = (s)=> (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m]));

  function fetchJSON(url, opt={}){
    const base = { headers: { 'X-CSRF': getCSRF() } };
    return fetch(url, Object.assign(base, opt)).then(r => r.json());
  }

  // Helpers for weekday mask (create form)
  function dayMaskFromDOM(container){
    let m=0; if(!container) return m;
    container.querySelectorAll('input[type="checkbox"][data-day]').forEach(cb=>{
      if (cb.checked) m |= (1 << parseInt(cb.dataset.day||'0',10));
    });
    return m >>> 0;
  }
  function setDaysFromMask(container, mask){
    if(!container) return;
    container.querySelectorAll('input[type="checkbox"][data-day]').forEach(cb=>{
      const bit = (1 << parseInt(cb.dataset.day||'0',10));
      cb.checked = ((mask>>>0) & bit) !== 0;
    });
  }

  // ---------- LIST ----------
  function initList(APP_BASE){
    const list = document.getElementById('lfg-list');
    const info = document.getElementById('pg-info');
    let page = 1, per = 20;

    function buildParams(){
      const p = new URLSearchParams();
      const v = id => document.getElementById(id)?.value || '';
      if(v('f-mode')) p.set('mode', v('f-mode'));
      if(v('f-play')) p.set('playstyle', v('f-play'));
      if(document.getElementById('f-voice')?.checked) p.set('voice','1');
      if(v('f-weapon')) p.set('weapon', v('f-weapon'));
      if(v('f-lang')) p.set('lang', v('f-lang'));
      if(v('f-region')) p.set('region', v('f-region'));
      if(v('f-mmr-min')) p.set('mmr_min', v('f-mmr-min'));
      if(v('f-mmr-max')) p.set('mmr_max', v('f-mmr-max'));
      if(v('f-kd-min')) p.set('kd_min', v('f-kd-min'));
      if(v('f-kd-max')) p.set('kd_max', v('f-kd-max'));
      if(v('f-from') && v('f-to')) { p.set('from', v('f-from')); p.set('to', v('f-to')); }
      if(v('f-q')) p.set('q', v('f-q'));
      // Weekday (first checked)
      const week = document.getElementById('f-week');
      if (week) {
        const first = Array.from(week.querySelectorAll('input[type="checkbox"][data-day]')).find(cb=>cb.checked);
        if (first) p.set('weekday', first.dataset.day);
      }
      p.set('page', String(page));
      p.set('per_page', String(per));
      return p;
    }

    async function load(){
      const res = await fetchJSON(`${APP_BASE}/api/lfg/list_listings.php?${buildParams().toString()}`);
      list.innerHTML = '';
      if (!res.ok || !Array.isArray(res.items) || res.items.length===0) {
        list.innerHTML = '<div class="lfg-empty">Keine Ergebnisse.</div>';
        if (info) info.textContent = '';
        return;
      }
      list.innerHTML = res.items.map(it => {
        const chips = [
          it.mode==='bounty_clash' ? 'Clash' : 'Bounty',
          it.playstyle,
          it.primary_weapon || '—',
          it.lang || '—',
          it.region || '—'
        ].map(c=>`<span class="lfg-chip">${escapeHtml(String(c))}</span>`).join(' ');

        const when = [it.time_from, it.time_to].filter(Boolean).join('–');

        return `
          <div class="lfg-item">
            <img src="${it.avatar_path||'/assets/images/avatars/placeholder.png'}" width="42" height="42" style="border-radius:50%">
            <div>
              <div class="title">${escapeHtml(it.display_name||'')}</div>
              <div class="meta">${chips}</div>
              ${when? `<div class="meta">${when}</div>`:''}
              ${it.note? `<div style="margin-top:6px">${escapeHtml(it.note)}</div>`:''}
            </div>
            <div class="lfg-actions">
              <button class="btn" data-action="request" data-id="${it.id}">Spielanfrage</button>
            </div>
          </div>`;
      }).join('');

      list.querySelectorAll('[data-action="request"]').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const msg = prompt('Kurze Nachricht (optional):','Lust auf Duo heute Abend?');
          if (msg===null) return;
          fetchJSON(`${APP_BASE}/api/lfg/send_request.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': getCSRF() },
            body: JSON.stringify({ listing_id: parseInt(btn.dataset.id,10), message: msg })
          }).then(r=>{
            if (r.ok) alert('Anfrage gesendet.');
            else alert('Fehler: '+(r.error||''));
          }).catch(()=> alert('Netzwerkfehler'));
        });
      });

      const pages = Math.max(1, Math.ceil((res.total||0) / per));
      if (info) info.textContent = `Seite ${page} / ${pages} (${res.total||0} Treffer)`;
    }

    document.getElementById('f-apply')?.addEventListener('click', ()=>{ page=1; load(); });
    document.getElementById('f-reset')?.addEventListener('click', ()=>{
      document.querySelectorAll('.lfg-filters input, .lfg-filters select').forEach(el=>{
        if (el instanceof HTMLInputElement && el.type==='checkbox') el.checked=false;
        else el.value='';
      });
      page=1; load();
    });
    document.getElementById('pg-prev')?.addEventListener('click', ()=>{ if(page>1){ page--; load(); } });
    document.getElementById('pg-next')?.addEventListener('click', ()=>{ page++; load(); });

    load();
  }

  // ---------- CREATE ----------
  function initCreate(APP_BASE){
    const $ = id => document.getElementById(id);

    // Load existing
    fetchJSON(`${APP_BASE}/api/lfg/get_listing.php?mine=1`).then(res=>{
      if (res && res.item) {
        const it = res.item;
        $('mode').value = it.mode||'bounty_hunt';
        $('play').value = it.playstyle||'ausgewogen';
        $('primary_weapon').value = it.primary_weapon||'';
        $('voice').value = it.voice_ready ? '1' : '0';
        $('lang').value = it.lang||'de';
        $('region').value = it.region||'';
        $('mmr_min').value = it.mmr_min||'';
        $('mmr_max').value = it.mmr_max||'';
        $('kd_min').value = it.kd_min||'';
        $('kd_max').value = it.kd_max||'';
        $('time_from').value = it.time_from||'';
        $('time_to').value = it.time_to||'';
        setDaysFromMask(document.getElementById('week'), parseInt(it.weekday_mask||'0',10));
        $('note').value = it.note||'';
        $('is_active').value = it.is_active ? '1' : '0';
        $('looking_for').value = it.looking_for||'duo';
      }
    });

    document.getElementById('save')?.addEventListener('click', ()=>{
      const payload = {
        mode: $('mode').value,
        playstyle: $('play').value,
        primary_weapon: $('primary_weapon').value.trim(),
        voice_ready: $('voice').value==='1',
        lang: $('lang').value,
        region: $('region').value.trim(),
        mmr_min: $('mmr_min').value ? parseInt($('mmr_min').value,10) : null,
        mmr_max: $('mmr_max').value ? parseInt($('mmr_max').value,10) : null,
        kd_min:  $('kd_min').value ? parseFloat($('kd_min').value) : null,
        kd_max:  $('kd_max').value ? parseFloat($('kd_max').value) : null,
        time_from: $('time_from').value || null,
        time_to:   $('time_to').value || null,
        weekday_mask: dayMaskFromDOM(document.getElementById('week')),
        note: $('note').value.trim(),
        is_active: $('is_active').value==='1',
        looking_for: $('looking_for').value
      };

      fetchJSON(`${APP_BASE}/api/lfg/create_listing.php`,{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF':getCSRF()},
        body:JSON.stringify(payload)
      }).then(res=>{
        if(res.ok) alert('Gesuch gespeichert.');
        else alert('Fehler: '+(res.error||''));
      }).catch(()=> alert('Netzwerkfehler'));
    });
  }

  // ---------- INBOX ----------
  function initInbox(APP_BASE){
    const list = document.getElementById('list');
    let type = 'incoming';

    function rowTpl(r){
      const who = type==='incoming' ? r.sender_name : r.receiver_name;
      const actions = (type==='incoming' && r.status==='pending')
        ? `<div class="actions">
             <button class="btn" data-accept="${r.id}">Annehmen</button>
             <button class="btn" data-decline="${r.id}">Ablehnen</button>
           </div>` : '';
      return `<div class="item" style="display:flex;gap:10px;align-items:flex-start;background:#101010;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:10px">
        <div class="who"><strong>${escapeHtml(who||'')}</strong></div>
        <div>
          <div style="opacity:.85">Nachricht: ${r.message? escapeHtml(r.message):'—'}</div>
          <div style="opacity:.65;font-size:.9rem;margin-top:4px">Status: ${r.status}</div>
        </div>
        ${actions}
      </div>`;
    }

    function bindActions(){
      list.querySelectorAll('[data-accept]').forEach(b=>{
        b.addEventListener('click', ()=>{
          const id = parseInt(b.dataset.accept,10);
          const msg = prompt('Kurze Antwort (optional):','Bin heute 20:15 online.');
          if (msg===null) return;
          fetchJSON(`${APP_BASE}/api/lfg/respond_request.php`,{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF':getCSRF()},
            body:JSON.stringify({request_id:id, action:'accept', message:msg})
          }).then(()=> load());
        });
      });
      list.querySelectorAll('[data-decline]').forEach(b=>{
        b.addEventListener('click', ()=>{
          const id = parseInt(b.dataset.decline,10);
          fetchJSON(`${APP_BASE}/api/lfg/respond_request.php`,{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF':getCSRF()},
            body:JSON.stringify({request_id:id, action:'decline'})
          }).then(()=> load());
        });
      });
    }

    function load(){
      const status = document.getElementById('status')?.value || '';
      const url = new URL(`${APP_BASE}/api/lfg/inbox.php`, location.origin);
      url.searchParams.set('type', type);
      if (status) url.searchParams.set('status', status);
      fetchJSON(url.toString()).then(res=>{
        list.innerHTML = (res.items||[]).map(rowTpl).join('') || '<div style="opacity:.7">Keine Einträge.</div>';
        bindActions();
      });
    }

    document.querySelectorAll('.tab')?.forEach(t=> t.addEventListener('click', ()=>{ type=t.dataset.type; load(); }));
    document.getElementById('status')?.addEventListener('change', load);

    load();
  }

  // Expose
  window.LFG = { initList, initCreate, initInbox };
})();

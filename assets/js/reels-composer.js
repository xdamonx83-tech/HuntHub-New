(() => {
  // ===== Singleton-Guard: verhindert doppelte Initialisierung =====
  if (window.__HH_REELS_COMPOSER_INIT__) return;
  window.__HH_REELS_COMPOSER_INIT__ = true;

  const csrf =
    document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const defaultCoverDataUrl =
    'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

  // ---------- kleine Utils ----------
  const h = (html) => {
    const d = document.createElement('div');
    d.innerHTML = html.trim();
    return d.firstElementChild;
  };
  const fmtBytes = (n) => {
    if (!n && n !== 0) return '';
    const u = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (n > 1024 && i < u.length - 1) {
      n /= 1024;
      i++;
    }
    return `${n.toFixed(2)}${u[i]}`;
  };

  // ---------- Filter-Helfer (CSS + Canvas) ----------
  function buildCssFilter(preset) {
    switch (preset) {
      case 'bw':    return 'grayscale(100%)';
      case 'warm':  return 'sepia(25%) saturate(1.2) contrast(1.05) brightness(1.02)';
      case 'cool':  return 'hue-rotate(200deg) saturate(0.9) contrast(1.05)';
      case 'vivid': return 'saturate(1.4) contrast(1.15)';
      case 'sepia': return 'sepia(100%)';
      default:      return 'none';
    }
  }
  function applyFilterToEl(el, preset) {
    const f = buildCssFilter(preset);
    el.style.filter = f;
    el.style.webkitFilter = f;
  }

  // =========================================================
  // ==============  Touch-Scroll (nur Modal)  ===============
  // =========================================================
  // Erzwingt Touch-Scroll im Container – auch wenn global touchmove blockiert wird.
  function enableTouchScroll(el) {
    if (!el) return;

    // natives Scrollen aktivieren
    el.style.overflowY = 'auto';
    el.style.webkitOverflowScrolling = 'touch';
    el.style.maxHeight = '75dvh';
    el.style.touchAction = 'pan-y';         // erlaubt vertikales Panning
    el.style.overscrollBehavior = 'contain'; // verhindert Durchscrollen in den Hintergrund

    // Nur manuell pannen, wenn kein UI-Element (Input/Select/Button/Video) getroffen wird
    const isPanTarget = (t) =>
      !t.closest('input, textarea, select, button, video, [data-no-pan]');

    let dragging = false;
    let lastY = 0;

    // Start
    el.addEventListener('touchstart', (e) => {
      if (e.touches.length !== 1) return;
      if (!isPanTarget(e.target)) return;
      dragging = true;
      lastY = e.touches[0].clientY;
    }, { passive: true });

    // Move: im Capture-Phase + passive:false, damit preventDefault greift
    el.addEventListener('touchmove', (e) => {
      if (!dragging) return;
      const y = e.touches[0].clientY;
      const dy = y - lastY;
      lastY = y;
      el.scrollTop -= dy;      // Finger runter -> Inhalt rauf (wie nativ)
      e.preventDefault();      // verhindert, dass irgendein globaler Listener reinfunkt
    }, { passive: false, capture: true });

    // Ende
    const end = () => { dragging = false; };
    el.addEventListener('touchend', end, { passive: true });
    el.addEventListener('touchcancel', end, { passive: true });
  }

  // =========================================================
  // ===============   Composer öffnen (UI)   ================
  // =========================================================
  function openComposer() {
    // evtl. vorhandenes Modal entfernen → nie zwei übereinander
    const existing = document.getElementById('reels-compose');
    if (existing) existing.remove();

    const el = h(`
      <div id="reels-compose" class="hh-modal">
        <div class="panel-xl">
          <div class="modal-hd">
            <div>Upload & Details</div>
            <button data-close aria-label="Schließen">✕</button>
          </div>

          <div class="modal-bd">
            <div class="reels-wrap">
              <!-- linke Spalte -->
              <div>
                <!-- Upload -->
                <div class="reel-card">
                  <div class="hd">Upload</div>
                  <div class="bd">
                    <input id="reel-file" type="file" accept="video/*" class="sr-only" data-file />
                    <label for="reel-file" class="reel-btn reel-btn-ghost">Datei auswählen</label>

                    <div class="reel-upload-status" style="margin-top:10px; display:flex; gap:8px; align-items:center;">
                      <span class="reel-ok">●</span>
                      <div>
                        <div class="reel-meta" data-filemeta>Kein Upload gestartet</div>
                        <div class="reel-meta" data-filesize></div>
                      </div>
                    </div>

                    <div class="reel-progress" data-progress hidden>
                      <div class="reel-progress-bar" data-progress-bar style="width:0%"></div>
                    </div>
                    <div class="reel-progress-label" data-progress-label hidden>0%</div>
                  </div>
                </div>

                <!-- Details -->
                <div class="reel-card" style="margin-top:16px;">
                  <div class="hd">Einzelheiten</div>
                  <div class="bd space-y-4">
                    <div>
                      <div class="reel-label">Beschreibung</div>
                      <textarea class="reel-textarea" data-desc placeholder="# Hashtags   @ Erwähnen"></textarea>
                      <div class="reel-hint"><span data-desc-count>0</span>/4000</div>
                    </div>

                    <div class="reel-cover">
                      <img data-cover src="${defaultCoverDataUrl}" alt="Cover">
                      <div class="reel-row">
                        <button class="reel-btn reel-btn-ghost" type="button" data-cover-edit>Cover bearbeiten</button>
                        <button class="reel-btn reel-btn-ghost" type="button" data-edit-video>Video bearbeiten</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Einstellungen (nur Filter) -->
                <div class="reel-card" style="margin-top:16px;">
                  <div class="hd">Einstellungen</div>
                  <div class="bd">
                    <div>
                      <div class="reel-label">Filter</div>
                      <select class="ss-main select !w-[260px] sm:py-3 py-2 px-24p rounded-full !text-base font-medium" data-filter>
                        <option value="none">Kein</option>
                        <option value="bw">Schwarzweiß</option>
                        <option value="warm">Warm</option>
                        <option value="cool">Kühl</option>
                        <option value="vivid">Vivid</option>
                        <option value="sepia">Sepia</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- (Entfernt: Überprüfungen / Sichtbarkeit / Veröffentlichungszeit) -->
              </div>

              <!-- rechte Spalte -->
              <div class="reel-side">
                <div class="reel-card preview">
                  <div class="bd">
                    <video playsinline controls muted data-preview></video>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-ft">
            <button class="reel-btn reel-btn-danger" data-discard>Verwerfen</button>
            <button class="reel-btn reel-btn-ghost" style="display:none;" data-draft>Entwurf speichern1</button>
            <button class="reel-btn reel-btn-primary" data-publish>Veröffentlichen</button>
          </div>
        </div>
      </div>
    `);

    document.body.appendChild(el);
    document.body.classList.add('hh-modal-open');

  // Nur hier: Touch-Scroll für den Inhalt aktivieren
    enableTouchScroll(el.querySelector('.modal-bd'));

    // ---------- Close handling ----------
    const close = () => {
      el.remove();
      document.body.classList.remove('hh-modal-open');
    };
    el.querySelector('[data-close]').addEventListener('click', close);
    el.querySelector('[data-discard]').addEventListener('click', close);

    // ---------- DOM-Refs ----------
    const file = el.querySelector('[data-file]');
    const meta = el.querySelector('[data-filemeta]');
    const size = el.querySelector('[data-filesize]');
    const preview = el.querySelector('[data-preview]');
    const desc = el.querySelector('[data-desc]');
    const descCount = el.querySelector('[data-desc-count]');
    const coverImg = el.querySelector('[data-cover]');
    const coverBtn = el.querySelector('[data-cover-edit]');
    const editBtn = el.querySelector('[data-edit-video]');
    const selFilter = el.querySelector('[data-filter]');
    const publishBtn = el.querySelector('[data-publish]');
    const draftBtn = el.querySelector('[data-draft]');

    const progWrap = el.querySelector('[data-progress]');
    const progBar = el.querySelector('[data-progress-bar]');
    const progLabel = el.querySelector('[data-progress-label]');

    // ---------- State ----------
    let token = '';
    let fileObj = null;
    let duration = 0;
    let startMs = 0;
    let endMs = 0;
    let posterMs = 500;
    let uploading = false;

    // Filter-State
    let currentPreset = selFilter.value || 'none';

    // ---------- Helper: Publish-Button aktiv/inaktiv ----------
    function updatePublishState(p /* optional percent */) {
      const ready = !!token && !uploading && endMs > startMs;
      publishBtn.disabled = !ready;
      publishBtn.classList.toggle('is-disabled', !ready);
      if (uploading) {
        const pct = typeof p === 'number' ? Math.max(0, Math.min(100, p)) : null;
        publishBtn.classList.add('is-uploading');
        publishBtn.style.setProperty('--btnp', `${pct ?? 0}%`);
        publishBtn.textContent =
          pct == null ? 'Wird hochgeladen…' : `Hochladen… ${pct}%`;
      } else {
        publishBtn.classList.remove('is-uploading');
        publishBtn.style.removeProperty('--btnp');
        publishBtn.textContent = 'Veröffentlichen';
      }
    }
    updatePublishState();

    // ---------- Beschreibung Counter ----------
    desc.addEventListener('input', () => {
      descCount.textContent = String(desc.value.length);
    });

    // ---------- Filter live anwenden ----------
    function applyLiveFilter() {
      applyFilterToEl(preview, currentPreset);
      // Falls Trimmer-Thumbnails gemerkt wurden, neu rendern
      if (preview._tnStrip && !isNaN(preview.duration)) {
        makeThumbnails(preview, preview._tnStrip, 10, currentPreset);
      }
    }
    applyLiveFilter();

    selFilter.addEventListener('change', () => {
      currentPreset = selFilter.value || 'none';
      applyLiveFilter();
      // Cover neu ziehen (mit Filter)
      if (preview.src) drawPosterFromVideo(preview, coverImg, posterMs, currentPreset);
    });

    // ---------- Datei wählen & SOFORT uploaden (mit Fortschritt) ----------
    file.addEventListener('change', async () => {
      if (!file.files?.[0]) return;
      fileObj = file.files[0];
      meta.textContent = fileObj.name;
      size.textContent = fmtBytes(fileObj.size);

      // lokale Preview
      const url = URL.createObjectURL(fileObj);
      preview.src = url;
      preview.onloadedmetadata = () => {
        duration = Math.round((preview.duration || 0) * 1000);
        startMs = 0;
        endMs = duration;
        posterMs = Math.max(0, Math.round(duration * 0.5));
        drawPosterFromVideo(preview, coverImg, posterMs, currentPreset);
        applyLiveFilter();
        updatePublishState();
      };

      // UI für Progress anzeigen
      uploading = true;
      token = '';
      progWrap.hidden = false;
      progLabel.hidden = false;
      progBar.style.width = '0%';
      progLabel.textContent = '0%';
      updatePublishState(0);

      // Upload via XHR, damit wir Upload-Progress haben
      try {
        const fd = new FormData();
        fd.append('file', fileObj);
        fd.append('csrf', csrf);

        await new Promise((resolve) => {
          const xhr = new XMLHttpRequest();
          xhr.open('POST', '/api/reels/upload.php');
          xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
              const p = Math.round((e.loaded / e.total) * 100);
              progBar.style.width = `${p}%`;
              progLabel.textContent = `${p}%`;
              updatePublishState(p);
            }
          };
          xhr.onload = () => {
            try {
              const j = JSON.parse(xhr.responseText || '{}');
              token = j.ok ? j.token || '' : '';
            } catch (e) {
              token = '';
            }
            resolve();
          };
          xhr.onerror = () => resolve();
          xhr.send(fd);
        });
      } catch (e) {
        console.error(e);
        token = '';
      } finally {
        uploading = false;
        progBar.style.width = '100%';
        progLabel.textContent = '100%';
        updatePublishState();
      }
    });

    // ---------- Cover bearbeiten ----------
    coverBtn.addEventListener('click', () => {
      if (!preview.src) return;
      openCoverEditor(preview, posterMs, currentPreset, (ms) => {
        posterMs = ms;
        drawPosterFromVideo(preview, coverImg, posterMs, currentPreset);
      });
    });

    // ---------- Video bearbeiten (Trim) ----------
    editBtn.addEventListener('click', () => {
      if (!preview.src) return;
      openVideoEditor(preview.src, startMs, endMs, duration, currentPreset, (res) => {
        startMs = res.startMs;
        endMs = res.endMs;
        posterMs = Math.max(0, Math.round((endMs - startMs) / 2));
        updatePublishState();
      });
    });

    // ---------- Draft (lokal) ----------
    draftBtn.addEventListener('click', () => {
      localStorage.setItem(
        'hh_reel_draft',
        JSON.stringify({
          desc: desc.value,
          filter: currentPreset,
          posterMs,
          startMs,
          endMs,
          token,
        })
      );
      alert('Entwurf lokal gespeichert.');
    });

    // ---------- Publish ----------
    publishBtn.addEventListener('click', async () => {
      if (publishBtn.disabled) return;

      const fd = new FormData();
      fd.append('token', token);
      fd.append('start_ms', String(startMs));
      fd.append('end_ms', String(endMs));
      fd.append('poster_ms', String(posterMs));
      fd.append('filter', currentPreset);
      fd.append('description', desc.value || '');
      fd.append('csrf', csrf);
      fd.append('ajax', '1');

      // optional: Cover als dataURL mitsenden
      try {
        const coverImg = document.querySelector('#reels-compose [data-cover]');
        if (coverImg && coverImg.src && coverImg.src.startsWith('data:image')) {
          fd.append('poster_dataurl', coverImg.src);
        }
      } catch(_) {}

      publishBtn.disabled = true;
      publishBtn.textContent = 'Wird veröffentlicht…';
      try {
        const r = await fetch('/api/reels/compose.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j && j.ok && j.reel) {
          // Composer schließen (bleib auf der Wall)
          const close = document.querySelector('#reels-compose [data-close]');
          close?.click();

          // Viewer informieren: neues Reel sofort anzeigen
          window.dispatchEvent(new CustomEvent('hh:reels:uploaded', { detail: j.reel }));
        } else {
          publishBtn.disabled = false;
          publishBtn.textContent = 'Veröffentlichen';
          alert('Compose fehlgeschlagen');
        }
      } catch (e) {
        console.error(e);
        publishBtn.disabled = false;
        publishBtn.textContent = 'Veröffentlichen';
        alert('Netzwerkfehler beim Veröffentlichen');
      }
    });
  }

  // =========================================================
  // =====================  Helpers  =========================
  // =========================================================
  function drawPosterFromVideo(videoEl, imgEl, ms, preset='none') {
    try {
      const c = document.createElement('canvas');
      const ctx = c.getContext('2d');
      const go = () => {
        c.width = videoEl.videoWidth || 720;
        c.height = videoEl.videoHeight || 1280;
        // Canvas-Filter (gleich wie CSS-Filter)
        ctx.filter = buildCssFilter(preset);
        ctx.drawImage(videoEl, 0, 0, c.width, c.height);
        ctx.filter = 'none';
        imgEl.src = c.toDataURL('image/jpeg', 0.8);
      };
      const cur = videoEl.currentTime;
      videoEl.onseeked = () => {
        go();
        videoEl.currentTime = cur;
        videoEl.onseeked = null;
      };
      videoEl.currentTime = Math.min(
        Math.max(0, ms / 1000),
        (videoEl.duration || 1) - 0.05
      );
    } catch (e) {}
  }

  function openCoverEditor(previewVideo, currentMs, preset, onSave) {
    const m = h(`
      <div class="hh-modal">
        <div class="panel-xl">
          <div class="modal-hd"><div>Cover wählen</div><button data-close>✕</button></div>
          <div class="modal-bd">
            <div class="editor-top"><video playsinline controls muted></video></div>
            <div class="range-wrap range-dual">
              <div class="range-values"><span>Position</span><span data-at>0 ms</span></div>
              <input type="range" min="0" max="1000" value="500" step="1" data-one>
            </div>
          </div>
          <div class="modal-ft">
            <button class="reel-btn reel-btn-ghost" data-close>Abbrechen</button>
            <button class="reel-btn reel-btn-primary" data-save>Übernehmen</button>
          </div>
        </div>
      </div>
    `);
    document.body.appendChild(m);
    document.body.classList.add('hh-modal-open');

    enableTouchScroll(m.querySelector('.modal-bd'));

    const v = m.querySelector('video');
    v.src = previewVideo.src;
    applyFilterToEl(v, preset);

    const range = m.querySelector('[data-one]');
    const at = m.querySelector('[data-at]');
    let picked = currentMs;

    v.addEventListener('loadedmetadata', () => {
      const max = Math.max(1, Math.round(v.duration * 1000));
      range.max = String(max);
      range.value = String(currentMs);
      at.textContent = `${currentMs} ms`;
      seekTo(currentMs);
    });
    range.addEventListener('input', () => {
      picked = parseInt(range.value, 10) || 0;
      at.textContent = `${picked} ms`;
      seekTo(picked);
    });

    function seekTo(ms) {
      v.currentTime = Math.min(
        Math.max(0, ms / 1000),
        (v.duration || 1) - 0.05
      );
    }

    const close = () => {
      m.remove();
      document.body.classList.remove('hh-modal-open');
    };
    m.querySelectorAll('[data-close]').forEach((b) =>
      b.addEventListener('click', close)
    );
    m.querySelector('[data-save]').addEventListener('click', () => {
      onSave(picked);
      close();
    });
  }

  function openVideoEditor(src, startMs, endMs, duration, preset, onSave) {
    const m = h(`
      <div class="hh-modal">
        <div class="panel-xl">
          <div class="modal-hd"><div>Video bearbeiten</div><button data-close>✕</button></div>
          <div class="modal-bd">
            <div class="editor-top"><video playsinline controls muted></video></div>
            <div class="timeline">
              <div class="tn-strip" data-strip></div>
              <div class="range-wrap range-dual">
                <div class="range-values"><span data-start>00:00</span><span data-end>00:00</span></div>
                <input type="range" min="0" step="10" data-a>
                <input type="range" min="0" step="10" data-b>
              </div>
            </div>
          </div>
          <div class="modal-ft">
            <button class="reel-btn reel-btn-ghost" data-close>Abbrechen</button>
            <button class="reel-btn reel-btn-primary" data-save>Bearbeitung speichern</button>
          </div>
        </div>
      </div>
    `);
    document.body.appendChild(m);
    document.body.classList.add('hh-modal-open');

    enableTouchScroll(m.querySelector('.modal-bd'));

    const v = m.querySelector('video');
    v.src = src;
    applyFilterToEl(v, preset);

    const strip = m.querySelector('[data-strip]');
    const ra = m.querySelector('[data-a]');
    const rb = m.querySelector('[data-b]');
    const sLbl = m.querySelector('[data-start]');
    const eLbl = m.querySelector('[data-end]');

    const fmt = (ms) => {
      const s = Math.floor(ms / 1000);
      const mm = Math.floor(s / 60);
      const rr = s % 60;
      return `${String(mm).padStart(2, '0')}:${String(rr).padStart(2, '0')}`;
    };

    v.addEventListener('loadedmetadata', () => {
      const max = Math.max(1, Math.round(v.duration * 1000));
      ra.max = rb.max = String(max);
      ra.value = String(Math.max(0, startMs));
      rb.value = String(Math.min(max, endMs || max));
      sLbl.textContent = fmt(parseInt(ra.value, 10));
      eLbl.textContent = fmt(parseInt(rb.value, 10));
      makeThumbnails(v, strip, 10, preset);
    });

    ra.addEventListener('input', () => {
      if (parseInt(ra.value, 10) > parseInt(rb.value, 10)) ra.value = rb.value;
      sLbl.textContent = fmt(parseInt(ra.value, 10));
      v.currentTime = parseInt(ra.value, 10) / 1000;
    });
    rb.addEventListener('input', () => {
      if (parseInt(rb.value, 10) < parseInt(ra.value, 10)) rb.value = ra.value;
      eLbl.textContent = fmt(parseInt(rb.value, 10));
      v.currentTime = parseInt(rb.value, 10) / 1000;
    });

    // Für spätere Re-Render (z. B. bei Filterwechsel)
    const preview = document.querySelector('[data-preview]');
    if (preview) preview._tnStrip = strip;

    const close = () => {
      m.remove();
      document.body.classList.remove('hh-modal-open');
    };
    m.querySelector('[data-close]').addEventListener('click', close);
    m.querySelector('[data-save]').addEventListener('click', () => {
      const a = parseInt(ra.value, 10) || 0;
      const b = parseInt(rb.value, 10) || 0;
      onSave({ startMs: a, endMs: b });
      close();
    });
  }

  async function makeThumbnails(videoEl, container, count, preset='none') {
    container.innerHTML = '';
    const dur = videoEl.duration || 0;
    const step = dur / count;
    const filt = buildCssFilter(preset);
    for (let i = 0; i < count; i++) {
      const c = document.createElement('canvas');
      c.width = 160;
      c.height = 64;
      container.appendChild(c);
      await captureFrame(videoEl, c, i * step, filt);
    }
  }
  function captureFrame(videoEl, canvas, sec, filterStr='none') {
    return new Promise((resolve) => {
      const ctx = canvas.getContext('2d');
      const onseek = () => {
        try {
          ctx.filter = filterStr;
          ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);
          ctx.filter = 'none';
        } catch (e) {}
        videoEl.removeEventListener('seeked', onseek);
        resolve();
      };
      videoEl.addEventListener('seeked', onseek);
      videoEl.currentTime = Math.min(
        Math.max(0, sec),
        (videoEl.duration || 1) - 0.05
      );
    });
  }

  // =========================================================
  // ============  Globale Trigger (einmalig)  ===============
  // =========================================================
  window.addEventListener('hh:reels:openComposer', openComposer);

  // Direkt vorhandene Buttons
  document.querySelectorAll('[data-reels-compose]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      window.dispatchEvent(new CustomEvent('hh:reels:openComposer'));
    });
  });

  // Falls Buttons dynamisch nachgeladen werden (SPA / PJAX etc.)
  const mo = new MutationObserver(() => {
    document.querySelectorAll('[data-reels-compose]').forEach((btn) => {
      if (!btn.__hhBound) {
        btn.__hhBound = true;
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          window.dispatchEvent(new CustomEvent('hh:reels:openComposer'));
        });
      }
    });
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
})();

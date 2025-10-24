// /assets/js/player.js
(function (global) {
  class HHReelPlayer {
    constructor(root, opts = {}) {
      this.root = root;
      this.opts = {
        src: null,
        poster: null,
        onDoubleTapLike: null,             // () => void
        onNeedUserGestureForSound: null,   // (need:boolean) => void
        onMuteChanged: null,               // (muted:boolean) => void
        ...opts,
      };
      this.hls = null;
      this._lastTap = 0;
      this._singleTimer = null;
      this._downXY = [0, 0];
      this._mo = null;

      this._build();
      if (this.opts.src) this.setSource(this.opts.src, this.opts.poster);
    }

    /* ---------- Public API ---------- */
    play(){ this.video?.play?.().catch(()=>{}); }
    pause(){ this.video?.pause?.(); }
    isMuted(){ return !!this.video?.muted; }
    setMuted(m){ this._setMuted(m); }
    async setSource(src, poster=null){ await this._attachSource(src, poster); }
    destroy(){
      this._unbind();
      if (this._mo){ try{ this._mo.disconnect(); }catch{} this._mo = null; }
      if (this.hls){ try{ this.hls.destroy(); }catch{} this.hls=null; }
      this.root.innerHTML = '';
    }

    /* ---------- Internals ---------- */
    _build(){
      this.root.classList.add('rp');
      this.root.innerHTML = `
        <video
          class="rp-video"
          playsinline
          webkit-playsinline
          autoplay
          loop
          preload="auto"
          disablepictureinpicture
          controlslist="nodownload noplaybackrate nofullscreen"
        ></video>
      `;
      this.video = this.root.querySelector('.rp-video');
      this.video.controls = false;

      // Markiere dieses Video, damit globales Plyr-Init es (idealerweise) ignoriert
      this.video.setAttribute('data-hh-reel', '1');

      // Falls trotzdem bereits ein Plyr-Wrapper existiert oder später auftaucht: killen
      const killPlyrIfPresent = () => {
        const wrap = this.video.closest('.plyr');
        if (wrap) {
          try { this.video.plyr?.destroy?.(); } catch {}
          // safety: falls der Wrapper noch da ist, entkoppeln
          if (this.video.closest('.plyr')) {
            const parent = wrap.parentNode;
            if (parent) parent.replaceChild(this.video, wrap);
          }
        }
      };
      // sofort + per MutationObserver absichern
      setTimeout(killPlyrIfPresent, 0);
      this._mo = new MutationObserver(killPlyrIfPresent);
      this._mo.observe(this.root, { childList:true, subtree:true });

      this._bind();
    }

    _bind(){
      this._onLoadedMeta = () => {
        this.video.loop = true;
        if (this.video.paused) this._autoplayWithSoundPreferred();
      };
      this.video.addEventListener('loadedmetadata', this._onLoadedMeta);

      // Doppel-Tap-Default (Fullscreen/Zoom) unterdrücken
      this._onDblClick = (e) => e.preventDefault();
      this.video.addEventListener('dblclick', this._onDblClick, { passive:false });

      // Tap/Doppeltap: single = play/pause, double = like
      const TAP_MOVE_MAX = 10, DBL_TAP_MS = 280, SINGLE_DELAY = 240;
      this._onPointerDown = (e) => { this._downXY = [e.clientX, e.clientY]; };
      this._onPointerUp = (e) => {
        const moved = Math.hypot(e.clientX - this._downXY[0], e.clientY - this._downXY[1]);
        if (moved > TAP_MOVE_MAX) return;
        e.preventDefault();

        const now = e.timeStamp || Date.now();
        const isDouble = (now - this._lastTap) < DBL_TAP_MS;

        if (isDouble) {
          if (this._singleTimer){ clearTimeout(this._singleTimer); this._singleTimer=null; }
          this.opts.onDoubleTapLike?.();
        } else {
          this._singleTimer = setTimeout(() => {
            if (this.video.paused) this.play(); else this.pause();
            this._singleTimer = null;
          }, SINGLE_DELAY);
        }
        this._lastTap = now;
      };
      this.video.addEventListener('pointerdown', this._onPointerDown, { passive:true });
      this.video.addEventListener('pointerup', this._onPointerUp, { passive:false });

      // Erstes Nutzer-Gesture => Sound freischalten
      this._unlockOnce = () => {
        if (this.video.muted) { this._setMuted(false); this.play(); }
        this.opts.onNeedUserGestureForSound?.(false);
        this.root.removeEventListener('pointerdown', this._unlockOnce);
      };
      this.root.addEventListener('pointerdown', this._unlockOnce, { once:true });
    }

    _unbind(){
      if (!this.video) return;
      this.video.removeEventListener('loadedmetadata', this._onLoadedMeta);
      this.video.removeEventListener('dblclick', this._onDblClick);
      this.video.removeEventListener('pointerdown', this._onPointerDown);
      this.video.removeEventListener('pointerup', this._onPointerUp);
      this.root.removeEventListener('pointerdown', this._unlockOnce);
    }

    _setMuted(m){
      const muted = !!m;
      this.video.muted = muted;
      if (muted) this.video.setAttribute('muted',''); else this.video.removeAttribute('muted');
      this.opts.onMuteChanged?.(muted);
    }

    async _autoplayWithSoundPreferred(){
      this._setMuted(false);
      try {
        await this.video.play();
        this.opts.onNeedUserGestureForSound?.(false);
      } catch {
        this._setMuted(true);
        try { await this.video.play(); } catch {}
        this.opts.onNeedUserGestureForSound?.(true);
      }
    }

    async _attachSource(src, poster){
      this.src = src;
      if (poster) this.video.poster = poster;
      if (this.hls){ try{ this.hls.destroy(); }catch{} this.hls = null; }

      const isHls = /\.m3u8(\?|$)/i.test(src);
      if (isHls){
        const canNativeHls = this.video.canPlayType('application/vnd.apple.mpegURL');
        if (canNativeHls) {
          this.video.src = src;
        } else {
          await this._ensureHls();
          if (global.Hls && global.Hls.isSupported()){
            this.hls = new global.Hls({ lowLatencyMode:true });
            this.hls.loadSource(src);
            this.hls.attachMedia(this.video);
          } else {
            this.video.src = src; // Best effort
          }
        }
      } else {
        this.video.src = src;
      }
      this._autoplayWithSoundPreferred();
    }

    _ensureHls(){
      if (global.Hls) return Promise.resolve();
      return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/hls.js@^1.5.0/dist/hls.min.js';
        s.async = true;
        s.onload = () => resolve();
        s.onerror = reject;
        document.head.appendChild(s);
      });
    }
  }

  global.HHReelPlayer = HHReelPlayer;
})(window);

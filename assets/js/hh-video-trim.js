(function(){
  // Expose a tiny helper used in board.php to show a preview and capture start/end
  window.hhVideoTrim = {
    mount(opts){
      const input = opts.fileInput;
      let state = { start:0, end:0, url:null };
      input.addEventListener('change', () => {
        const f = input.files && input.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        state.url = url;
        const video = document.createElement('video');
        video.src = url; video.preload='metadata';
        video.onloadedmetadata = () => {
          state.start = 0;
          state.end   = video.duration || 0;
          opts.onChange && opts.onChange({ start:state.start, end:state.end, previewUrl:url });
        };
      });
      return {
        get value(){ return state; }
      };
    }
  };
})();
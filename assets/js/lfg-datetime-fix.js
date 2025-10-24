/* Hunthub – datetime-local normalizer (runs before lfg.js submit)
   Purpose: Keep the nice native picker (<input type="datetime-local">)
   but send MySQL-friendly "YYYY-MM-DD HH:MM:SS" to the server.
*/
(function(){
  function normalize(val){
    if (!val) return '';
    // Typical values from <input type="datetime-local"> → "2025-09-07T20:00" or with seconds
    var m = String(val).trim();
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(m)){
      m = m.replace('T',' ');
      if (m.length === 16) m += ':00'; // add seconds if missing
      return m;
    }
    return val; // leave other formats unchanged; backend may parse
  }

  // Expose for debugging if needed
  window.hhNormalizeDatetimeLocal = normalize;

  // Capture submit BEFORE lfg.js builds FormData
  document.addEventListener('submit', function(ev){
    var f = ev.target;
    if (!f || f.id !== 'lfg-form') return;
    var el = f.querySelector('input[name="expires_at"]');
    if (el && el.value){
      el.value = normalize(el.value);
    }
  }, true);
})();

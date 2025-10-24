/* Hunthub – Date+Time Joiner
Kombiniert #expires_date & #expires_time zu MySQL-Format YYYY-MM-DD HH:MM:SS
und schreibt es in das Hidden-Feld name=expires_at, bevor lfg.js absendet. */
(function(){
function toMysql(dateStr, timeStr){
dateStr = (dateStr||'').trim();
timeStr = (timeStr||'').trim();
if (!dateStr && !timeStr) return '';
if (dateStr && !timeStr) return dateStr + ' 00:00:00';
if (!dateStr && timeStr) return '';
// time ohne Sekunden → mit :00 ergänzen
if (/^\d{2}:\d{2}$/.test(timeStr)) timeStr += ':00';
return dateStr + ' ' + timeStr; // YYYY-MM-DD HH:MM:SS
}


// Beim Laden: vorhandenen Wert (falls vorhanden) aufsplitten → Felder füllen
function prefill(){
var hidden = document.getElementById('expires_at');
if (!hidden || !hidden.value) return;
var v = hidden.value.replace('T',' ').trim();
var m = v.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::\d{2})?$/);
if (m){
var d = document.getElementById('expires_date');
var t = document.getElementById('expires_time');
if (d) d.value = m[1];
if (t) t.value = m[2];
}
}


// Vor Absenden den Hidden-Wert setzen
document.addEventListener('submit', function(ev){
var f = ev.target;
if (!f || f.id !== 'lfg-form') return;
var dEl = f.querySelector('#expires_date');
var tEl = f.querySelector('#expires_time');
var hEl = f.querySelector('#expires_at');
if (!hEl) return;
hEl.value = toMysql(dEl && dEl.value, tEl && tEl.value);
}, true);


prefill();
})();
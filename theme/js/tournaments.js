(function(){
if(!root) return;
const tid = parseInt(root.dataset.tournamentId, 10);


// Tabs
document.querySelectorAll('.tabs .tab').forEach(btn=>{
btn.addEventListener('click', ()=>{
document.querySelectorAll('.tabs .tab').forEach(b=>b.classList.remove('is-active'));
btn.classList.add('is-active');
const key = btn.getAttribute('data-tab');
document.querySelectorAll('.tab-panel').forEach(p=>p.classList.add('hidden'));
document.querySelector('.tab-panel[data-panel="'+key+'"]')?.classList.remove('hidden');
});
});


// Leaderboard Loader
const lb = document.querySelector('#leaderboard');
async function loadLB(){
if(!lb) return;
try {
const r = await fetch('/api/tournaments/leaderboard.php?tournament_id='+tid);
const j = await r.json();
if(!j.ok) return;
lb.innerHTML = renderLB(j.items || []);
} catch(e) {}
}
function renderLB(items){
if(!items.length) return '<p>Noch keine freigegebenen Runs.</p>';
let html = '<table class="w-full"><thead><tr><th>#</th><th>Team</th><th>Punkte</th></tr></thead><tbody>';
items.forEach((it,idx)=>{
html += `<tr><td>${idx+1}</td><td>${escapeHtml(it.team_name)}</td><td><b>${it.total_points}</b></td></tr>`;
});
html += '</tbody></table>';
return html;
}
function escapeHtml(s){return (s||'').toString().replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}


// Meine Runs
const myRuns = document.querySelector('#myRuns');
async function loadMyRuns(){
if(!myRuns) return;
try {
const r = await fetch('/api/tournaments/my_runs.php?tournament_id='+tid);
const j = await r.json();
if(!j.ok) return;
myRuns.innerHTML = renderRuns(j.items || []);
} catch(e) {}
}
function renderRuns(items){
if(!items.length) return '<p>Keine Einreichungen.</p>';
let html = '<div class="space-y-2">';
items.forEach(it=>{
html += `<div class="p-2 border rounded flex items-center gap-3">
<div><b>${it.raw_points}</b> Punkte</div>
${it.screenshot_path ? `<a href="${it.screenshot_path}" target="_blank">Screenshot</a>` : ''}
<span class="badge">${it.status}</span>
<span class="opacity-70">${new Date(it.created_at.replace(' ','T')+'Z').toLocaleString()}</span>
</div>`;
});
html += '</div>';
return html;
}


// Freunde für Invite laden
const friendSelect = document.querySelector('#friendSelect');
async function loadFriends(){
if(!friendSelect) return;
try {
const r = await fetch('/api/friends/list.php');
const j = await r.json();
if(!j.ok) return;
const arr = j.items || j.friends || [];
friendSelect.innerHTML = arr.map(u=>`<option value="${u.id}">${escapeHtml(u.display_name || u.name || ('User #'+u.id))}</option>`).join('');
} catch(e) {}
}


// Auto‑Refresh Leaderboard
loadLB();
setInterval(loadLB, 20000);
loadMyRuns();
loadFriends();
})();
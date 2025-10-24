document.addEventListener('click', async (e) => {
const btn = e.target.closest('.btn-report');
if (!btn) return;
const type = btn.getAttribute('data-content-type');
const id = btn.getAttribute('data-content-id');
const reason = prompt('Warum meldest du diesen Inhalt? (optional)') || '';


const csrf = document.querySelector('meta[name="csrf"]')?.content
|| document.querySelector('meta[name="csrf-token"]')?.content || '';


const res = await fetch(`${window.APP_BASE || ''}/api/forum/report.php`, {
method: 'POST',
headers: {
'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
'X-CSRF': csrf
},
body: new URLSearchParams({ content_type: type, content_id: id, reason })
});
const data = await res.json();
if (data.ok) {
alert(data.duplicate ? 'Du hast das bereits gemeldet.' : 'Danke, deine Meldung wurde übermittelt.');
// Optional: Sofort ausblenden, wenn Threshold dann erreicht wäre (Client‑Schätzung)
} else {
alert('Melden fehlgeschlagen.');
}
});
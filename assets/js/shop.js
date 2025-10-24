document.addEventListener('DOMContentLoaded', () => {
  const shopModal = document.getElementById('shopModal');
  if (!shopModal) return;

  // Delegation: alle Kauf-Buttons
  shopModal.addEventListener('click', async (e) => {
    const btn = e.target.closest('.shop-buy-btn');
    if (!btn) return;

    const itemId = btn.dataset.itemId;
    if (!itemId) return;

    btn.disabled = true;
    btn.textContent = "Kaufe...";

    try {
      const fd = new FormData();
      fd.append('item_id', itemId);

      const res = await fetch('/api/shop/buy.php', {
        method: 'POST',
        body: fd,
        credentials: 'include'
      });
      const data = await res.json();

      if (!res.ok || !data.ok) {
        alert("Fehler: " + (data.error || res.status));
        btn.disabled = false;
        btn.textContent = "Kaufen";
        return;
      }

      // ✅ Erfolg
      alert("Gekauft: " + (data.item?.title || "Item"));

      // Punkte im Header/Profil aktualisieren
      const pointsEl = document.querySelector('#pointsCounter');
      if (pointsEl && data.item.price) {
        const current = parseInt(pointsEl.textContent || "0", 10);
        pointsEl.textContent = Math.max(0, current - data.item.price);
      }

      // Button auf "Besitzen" ändern
      btn.textContent = "Besitzen ✔";
      btn.classList.add("owned");
      btn.disabled = true;

    } catch (err) {
      console.error(err);
      alert("Server-Fehler beim Kauf.");
      btn.disabled = false;
      btn.textContent = "Kaufen";
    }
  });
});

/* wall-like.js — einfach einbinden: <script src="/assets/js/wall-like.js" defer></script> */
(function () {
  'use strict';

  // Container der Feed-Posts (falls du andere ID hast, anpassen)
  const FEED_SELECTOR = '#feed, .wall-feed';
  const API_TOGGLE = '/api/wall/like_toggle.php'; // <-- anpassen falls anders

  // Helper: toast (simple)
  function toast(msg) { console && console.log('[wall]', msg); }

  // Ermittelt das nächsthöhere Like-Element (Button)
  function findLikeButton(el) {
    return el.closest('.btn-like');
  }

  // Sichert initiale Buttons (falls server schon Klasse gesetzt hat)
  function initButtons(root=document) {
    root.querySelectorAll('.btn-like').forEach(btn => {
      // accessibility
      const reacted = btn.classList.contains('is-reacted') || btn.dataset.liked === '1';
      btn.setAttribute('aria-pressed', reacted ? 'true' : 'false');
    });
  }

  // Update UI nach Server-Response
  function applyLikeState(btn, liked, count) {
    if (!btn) return;
    if (liked) btn.classList.add('is-reacted');
    else btn.classList.remove('is-reacted');

    btn.setAttribute('aria-pressed', liked ? 'true' : 'false');

    const countEl = btn.querySelector('.like-count');
    if (countEl && typeof count === 'number') countEl.textContent = String(count);
  }

  // Event-Handler für Klick
  async function handleClick(e) {
    const btn = findLikeButton(e.target);
    if (!btn) return;

    e.preventDefault();

    const entity = btn.dataset.entity; // 'post' | 'comment'
    const id = btn.dataset.id;
    if (!entity || !id) {
      toast('Like-Button falsch ausgezeichnet (data-entity / data-id fehlt)');
      return;
    }

    // CSRF token falls nötig (Meta oder data-csrf)
    let csrf = btn.dataset.csrf || document.querySelector('meta[name="csrf"]')?.content || '';
    // Optimistic UI
    const currentlyLiked = btn.classList.contains('is-reacted') || btn.dataset.liked === '1';
    const optimisticLiked = !currentlyLiked;

    // Update sofort
    const oldCount = parseInt(btn.querySelector('.like-count')?.textContent || '0', 10) || 0;
    const optimisticCount = optimisticLiked ? oldCount + 1 : Math.max(0, oldCount - 1);
    applyLikeState(btn, optimisticLiked, optimisticCount);

    // Build payload
    const form = new FormData();
    form.append('entity', entity);
    form.append('id', id);
    if (csrf) form.append('csrf', csrf);

    try {
      const res = await fetch(API_TOGGLE, {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });

      const data = await res.json().catch(()=>({ok:false, error:'invalid-json'}));

      if (!res.ok || data.ok === false) {
        // rollback
        applyLikeState(btn, currentlyLiked, oldCount);
        toast('Like konnte nicht abgeändert werden: ' + (data.error || res.statusText));
        return;
      }

      // Server sagt uns final, ob liked und wieviele
      const liked = !!data.liked;
      const count = (typeof data.count === 'number') ? data.count : optimisticCount;
      applyLikeState(btn, liked, count);

    } catch (err) {
      // rollback
      applyLikeState(btn, currentlyLiked, oldCount);
      toast('Netzwerkfehler beim Like: ' + err.message);
    }
  }

  // Delegation einrichten
  function setup() {
    const container = document.querySelector(FEED_SELECTOR) || document.body;
    container.addEventListener('click', handleClick, false);
    initButtons(document);
  }

  // DOM ready or defer
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup);
  } else {
    setup();
  }

  // Export für Hot-Reload / Tests
  window.__wall_like = { applyLikeState, initButtons };
})();

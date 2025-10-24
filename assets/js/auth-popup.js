(function () {
  const modal    = document.getElementById('register-modal');
  if (!modal) return;

  const dialog   = modal.querySelector('[role="dialog"]');
  const backdrop = modal.querySelector('[data-modal-backdrop]');
  const openers  = document.querySelectorAll('[data-open-register]');
  const closeBtn = modal.querySelector('[data-modal-close]');

  function openModal() {
    modal.classList.remove('hidden');
    // kleine Delay, damit Transition kickt
    requestAnimationFrame(() => {
      backdrop.classList.remove('opacity-0');
      backdrop.classList.add('opacity-100');
      dialog.classList.remove('opacity-0','scale-95');
      dialog.classList.add('opacity-100','scale-100');
    });
    document.documentElement.classList.add('overflow-hidden');
  }

  function closeModal() {
    // Animation rückwärts
    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
    dialog.classList.remove('opacity-100','scale-100');
    dialog.classList.add('opacity-0','scale-95');

    // nach Animation (300ms) Modal wieder verstecken
    setTimeout(() => {
      modal.classList.add('hidden');
      document.documentElement.classList.remove('overflow-hidden');
    }, 300);
  }

  // Events
  openers.forEach(btn => btn.addEventListener('click', e => {
    e.preventDefault();
    openModal();
  }));
  backdrop?.addEventListener('click', closeModal);
  closeBtn?.addEventListener('click', closeModal);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });
})();

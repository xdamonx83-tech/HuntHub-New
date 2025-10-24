// /assets/js/register.js
(function () {
  const form = document.getElementById('register-form');
  const alertBox = document.getElementById('form-alert');
  const submitBtn = document.getElementById('submit-btn');
  const loader = submitBtn.querySelector('.loader');

  function showAlert(type, msg) {
    alertBox.classList.remove('hidden');
    alertBox.classList.remove('border-green-600','text-green-100','bg-green-900/40');
    alertBox.classList.remove('border-red-600','text-red-100','bg-red-900/40');

    if (type === 'success') {
      alertBox.classList.add('border-green-600','text-green-100','bg-green-900/40');
    } else {
      alertBox.classList.add('border-red-600','text-red-100','bg-red-900/40');
    }
    alertBox.textContent = msg;
  }

  function clearAlert() {
    alertBox.classList.add('hidden');
    alertBox.textContent = '';
  }

  function setLoading(isLoading) {
    submitBtn.disabled = isLoading;
    loader.classList.toggle('hidden', !isLoading);
  }

  function validateLocal() {
    const u = form.username.value.trim();
    const e = form.email.value.trim();
    const p = form.password.value;
    const pc = form.password_confirm.value;
    const a = form.accept.checked;

    if (u.length < 3 || u.length > 32) return 'Benutzername muss 3–32 Zeichen lang sein.';
    if (!/^[A-Za-z0-9_\-]+$/.test(u)) return 'Benutzername enthält ungültige Zeichen.';
    if (!e) return 'Bitte E‑Mail angeben.';
    if (p.length < 8) return 'Passwort muss mindestens 8 Zeichen haben.';
    if (p !== pc) return 'Passwörter stimmen nicht überein.';
    if (!a) return 'Bitte AGB/Datenschutz akzeptieren.';
    return null;
    }

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    clearAlert();

    const localErr = validateLocal();
    if (localErr) {
      showAlert('error', localErr);
      return;
    }

    setLoading(true);

    try {
      const fd = new FormData(form);

      // API erwartet ggf. spezielle Feldnamen – hier Standard:
      // username, email, password, password_confirm
      // CSRF als Header (üblich in deinem Basisskript)
      const csrf = document.getElementById('csrf')?.value || '';

      const res = await fetch('/api/auth/register.php', {
        method: 'POST',
        headers: {
          'X-CSRF-Token': csrf
        },
        body: fd,
        credentials: 'include'
      });

      // Versuche JSON zu lesen
      let data = null;
      try { data = await res.json(); } catch (_) {}

      if (!res.ok) {
        const msg = (data && (data.error || data.message)) || 'Registrierung fehlgeschlagen.';
        showAlert('error', msg);
        setLoading(false);
        return;
      }

      // Erfolgsfälle im Basisskript: häufig {ok:true} oder {success:true}
      const ok = (data && (data.ok === true || data.success === true));
      if (ok) {
        showAlert('success', 'Konto erstellt! Du wirst gleich weitergeleitet …');
        // kurzer Delay, dann weiter – passe Ziel an (z.B. /profile.php)
        setTimeout(() => {
          window.location.href = '/';
        }, 800);
      } else {
        // Fehlermeldung ausgeben
        const msg = (data && (data.error || data.message)) || 'Registrierung fehlgeschlagen.';
        showAlert('error', msg);
        setLoading(false);
      }
    } catch (err) {
      console.error(err);
      showAlert('error', 'Netzwerkfehler. Bitte probier es erneut.');
      setLoading(false);
    }
  });
})();

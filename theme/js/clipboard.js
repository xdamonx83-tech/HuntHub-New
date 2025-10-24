function copyToClipboard(text) {
  navigator.clipboard.writeText(text).catch(err => {
    console.error('Fehler beim Kopieren:', err);
  });
}

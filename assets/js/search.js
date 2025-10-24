document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById("forum-search");
  const resultsBox = document.getElementById("search-results");
  let timer;

  input.addEventListener("input", () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) {
      resultsBox.innerHTML = "";
      resultsBox.classList.add("hidden");
      return;
    }

    timer = setTimeout(async () => {
      const res = await fetch(`/api/forum/search.php?q=${encodeURIComponent(q)}&limit=5`);
      const data = await res.json();

      let html = "";

      if (data.threads.length) {
        html += `<div class="p-2 font-bold text-gray-300">Threads</div>`;
        data.threads.forEach(t => {
          html += `
            <a href="/forum/thread.php?id=${t.id}" class="block px-3 py-2 hover:bg-gray-700">
              <div class="text-white">${t.title}</div>
              <div class="text-xs text-gray-400">von ${t.display_name}</div>
            </a>`;
        });
      }

      if (data.posts.length) {
        html += `<div class="p-2 font-bold text-gray-300 border-t border-gray-700">Posts</div>`;
        data.posts.forEach(p => {
          html += `
            <a href="/forum/thread.php?id=${p.thread_id}#post-${p.id}" class="block px-3 py-2 hover:bg-gray-700">
              <div class="text-gray-200">${p.content.substring(0,80)}...</div>
              <div class="text-xs text-gray-400">in ${p.title} von ${p.display_name}</div>
            </a>`;
        });
      }

      if (!html) {
        html = `<div class="p-3 text-gray-400">Keine Treffer</div>`;
      }

      resultsBox.innerHTML = html;
      resultsBox.classList.remove("hidden");
    }, 300);
  });

  // Klick außerhalb -> schließen
  document.addEventListener("click", e => {
    if (!resultsBox.contains(e.target) && e.target !== input) {
      resultsBox.classList.add("hidden");
    }
  });
});

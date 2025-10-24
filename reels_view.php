<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/lib/reels.php';
require_once __DIR__ . '/lib/layout.php';
$me = optional_auth();
$id = (int)($_GET['id'] ?? 0);
$reel = $id ? reels_get($id) : null;
if (!$reel) { http_response_code(404); render_theme_page('<div class="p-8 text-center">Reel nicht gefunden</div>','Reel'); exit; }
ob_start();
?>
<div class="min-h-[60vh] bg-black text-white flex items-center justify-center">
  <video src="<?= htmlspecialchars($reel['src']) ?>" playsinline autoplay muted controls style="height:80vh; max-width:100vw; object-fit:cover"></video>
</div>
<?php
$content = ob_get_clean();
render_theme_page($content, '@'.$reel['username'].' – Reel');
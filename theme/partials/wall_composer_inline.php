<?php
declare(strict_types=1);

// $me robust laden
$me = $GLOBALS['me'] ?? null;
if (!$me || empty($me['id'])) {
  require_once __DIR__ . '/../../auth/guards.php';
  $me = optional_auth(); // greift nur, wenn eingeloggt
}

$loggedIn = !empty($me['id']);
$displayName = htmlspecialchars($me['display_name'] ?? $me['username'] ?? 'du', ENT_QUOTES, 'UTF-8');

// Avatar-URL robust ermitteln
function hh_resolve_avatar_url(array $user): string {
  // 1) vorhandene Helferfunktionen aus deinem Projekt nutzen (falls definiert)
  if (function_exists('hh_avatar_url'))        return (string)hh_avatar_url($user);
  if (function_exists('appearance_avatar_url'))return (string)appearance_avatar_url($user);
  if (function_exists('user_avatar_url'))      return (string)user_avatar_url($user);
  if (function_exists('avatar_url'))           return (string)avatar_url($user);

  // 2) bekannte Keys durchprobieren
  $candidates = [
    $user['avatar']        ?? null,
    $user['avatar_url']    ?? null,
    $user['avatar_small']  ?? null,
    $user['avatar_thumb']  ?? null,
    $user['avatar_path']   ?? null,
  ];
  foreach ($candidates as $raw) {
    $raw = (string)$raw;
    if ($raw !== '') {
      return $raw;
    }
  }
  return '';
}

// 3) absolut machen + Fallback
$raw = hh_resolve_avatar_url($me);
$def = '/assets/images/default-avatar.png';
$base = function_exists('hh_base') ? rtrim((string)hh_base(), '/') : '';
$avatar = $raw !== '' ? $raw : $def;
if ($avatar !== $def && !preg_match('~^https?://~i', $avatar)) {
  // relativer Pfad → absolut
  $avatar = ($base ?: '') . '/' . ltrim($avatar, '/');
}
$avatar = htmlspecialchars($avatar ?: $def, ENT_QUOTES, 'UTF-8');
$defEsc = htmlspecialchars($def, ENT_QUOTES, 'UTF-8');
?>
<div class="w-full rounded-2xl p-4 flex  items-center gap-3 shadow-sm border border-white/10 bg-gradient-to-br from-neutral-900/80 to-neutral-800/70 backdrop-blur hh-card">
  <img src="<?= $avatar ?>" alt="Avatar"
       class="w-11 h-11 rounded-full object-cover ring-2 ring-white/10"
       onerror="this.onerror=null;this.src='<?= $defEsc ?>'">

  <?php if ($loggedIn): ?>
    <button id="hh-open-composer"
            type="button"
            class="flex-1 text-left rounded-full px-4 py-2 bg-neutral-800/60 hover:bg-neutral-800/80 text-neutral-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 transition hh-fake-input" 
			style="padding-left:10px;width:100%;box-shadow: 0 0 0 1px rgba(255, 255, 255, .08) inset;">
      Was machst du gerade, <?= $displayName ?>?
    </button>
    <div class="hidden sm:flex items-center gap-2">

      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" class="feather feather-emoticons">
  <!-- Gesichtskreis + Mund: weiß -->
  <g fill="none" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="11"/>
    <path d="M7.6 14.8c1.2 1.7 3 2.2 4.4 2.2s3.2-.5 4.4-2.2"/>
  </g>
  <!-- Augen: rot -->
  <rect x="8" y="9" width="2" height="2" rx="0.4" fill="#e74c3c"/>
  <rect x="14" y="9" width="2" height="2" rx="0.4" fill="#e74c3c"/>
</svg>

<svg width="16" height="18" viewBox="0 0 16 18" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M13.1579 5.60503C15.614 7.1139 15.614 10.8861 13.1579 12.395L6.52631 16.4689C4.07017 17.9778 1 16.0917 1 13.074L1 4.92602C1 1.90827 4.07018 0.0221756 6.52632 1.53105L13.1579 5.60503Z" class="icon_main" stroke-width="1.2"></path>
<path d="M6.52631 16.4689C4.07017 17.9778 1 16.0917 1 13.074L1 4.92602C1 1.90827 4.07018 0.0221756 6.52632 1.53105L13.1579 5.60503" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"></path>
</svg>
	  
     <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="feather feather-image"><rect x="11" y="5" width="4" height="4" rx="2" class="icon_main" stroke-width="1"></rect><path d="M2.71809 15.2014L4.45698 13.4625C6.08199 11.8375 8.71665 11.8375 10.3417 13.4625L12.0805 15.2014M12.0805 15.2014L12.7849 14.497C14.0825 13.1994 16.2143 13.2961 17.3891 14.7059L17.802 15.2014M12.0805 15.2014L14.6812 17.802M1.35288 13.0496C0.882374 11.0437 0.882374 8.95626 1.35288 6.95043C2.00437 4.17301 4.17301 2.00437 6.95043 1.35288C8.95626 0.882375 11.0437 0.882374 13.0496 1.35288C15.827 2.00437 17.9956 4.17301 18.6471 6.95044C19.1176 8.95626 19.1176 11.0437 18.6471 13.0496C17.9956 15.827 15.827 17.9956 13.0496 18.6471C11.0437 19.1176 8.95626 19.1176 6.95044 18.6471C4.17301 17.9956 2.00437 15.827 1.35288 13.0496Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>
    </div>
  <?php else: ?>
    <a href="/login.php" class="flex-1 text-left underline text-neutral-300">Bitte einloggen, um einen Beitrag zu erstellen</a>
  <?php endif; ?>
</div>

<?php if ($loggedIn): ?>
  <?php require __DIR__ . '/wall_composer_modal.php'; ?>
<?php endif; ?>

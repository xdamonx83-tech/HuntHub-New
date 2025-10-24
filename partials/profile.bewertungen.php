<?php
// Erwartet: $__user (Profilinhaber), optional $me
$profileUser = $__user ?? $user ?? null;
$viewerId    = isset($me['id']) ? (int)$me['id'] : 0;
$profileId   = isset($profileUser['id']) ? (int)$profileUser['id'] : 0;

// Darf bewerten: eingeloggt und nicht das eigene Profil
$canRate = ($viewerId > 0) && ($viewerId !== $profileId);
?>
<section id="tab-bewertungen" class="profile-tab-panel" role="tabpanel">
  <h2 class="sr-only"><?php echo $L['bewert']; ?></h2>

  <!-- Zusammenfassung -->
  <div class="hhr-summary"></div>

  <!-- Button zentriert (nur wenn erlaubt) -->
  <?php if ($canRate): ?>
    <div class="hhr-btn-center" style="display:flex;justify-content:center;margin-top:12px">
      <button id="hhr-open" type="button" class="btn btn-primary rounded-12">
        <?php echo $L['addbewert']; ?>
      </button>
    </div>
  <?php endif; ?>

  <!-- Liste immer vorhanden -->
  <div id="hhr-list" class="mt-3" style="margin-top:50px;margin-bottom:50px;"></div>
</section>
<!-- debug: viewer=<?= (int)($me['id']??0) ?> profile=<?= (int)($__user['id']??0) ?> -->
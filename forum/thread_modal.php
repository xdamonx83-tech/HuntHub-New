<?php
declare(strict_types=1);

/**
 * Modal zum Erstellen eines neuen Threads.
 * Benötigt: $APP_BASE, $csrf, $board (mind. id), $L
 */

if (!isset($APP_BASE)) {
  $cfg = require __DIR__ . '/../auth/config.php';
  $APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
}
if (!isset($csrf)) {
  require_once __DIR__ . '/../auth/db.php';
  require_once __DIR__ . '/../auth/csrf.php';
  $pdo = db();
  $cfg = require __DIR__ . '/../auth/config.php';
  $csrf = issue_csrf($pdo, $_COOKIE[$cfg['cookies']['session_name']] ?? '');
}
$esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

?>
<div id="threadModal" class="modal-mask" aria-hidden="true">
  <div class="modal-wrap" role="dialog" aria-modal="true" aria-labelledby="threadModalTitle">
    <div class="modal-card">
      <div class="modal-hd">
        <strong id="threadModalTitle"><?= $esc($L['thread_new'] ?? 'Neues Thema') ?></strong>
        <button type="button" class="btnx" id="closeThreadModal"><?= $esc($L['close'] ?? 'Schließen') ?></button>
      </div>

      <div class="modal-bd">
        <form id="newThreadForm"
              action="<?= $esc($APP_BASE) ?>/api/forum/create_thread.php"
              method="post"
              enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
          <input type="hidden" name="board_id" value="<?= (int)($board['id'] ?? 0) ?>">
          <input type="hidden" name="clip_start" id="clipStart" value="0">
          <input type="hidden" name="clip_end"   id="clipEnd"   value="0">

          <label class="label label-lg mb-2"><?= $esc($L['title'] ?? 'Titel') ?></label>
          <input class="box-input-3 mb-3"
                 type="text"
                 name="title"
                 id="threadTitle"
                 placeholder="<?= $esc($L['title'] ?? 'Titel') ?>"
                 required maxlength="200">

          <label class="label label-lg mb-2"><?= $esc($L['first_post'] ?? 'Erster Beitrag') ?></label>
          <textarea class="box-input-3 h-[140px] mb-4"
                    name="content"
                    id="threadContent"
                    placeholder="<?= $esc($L['first_post'] ?? 'Erster Beitrag') ?>"
                    required></textarea>

          <!-- Upload Buttons -->
          <div class="flex items-center gap-3 mb-4">
            <!-- Bild: reines Label (öffnet nativ), KEIN JS-Klick nötig -->
            <label class="btnx" title="<?= $esc($L['image_upload'] ?? 'Bild hochladen') ?>" for="threadImage">
              <i class="ti ti-photo"></i> <?= $esc($L['image_upload'] ?? 'Bild hochladen') ?>
            </label>
            <input type="file" name="image" id="threadImage" accept="image/*" hidden>

            <!-- Video: reines Label (öffnet nativ), KEIN JS-Klick nötig -->
            <label class="btnx" title="<?= $esc($L['video_upload'] ?? 'Video hochladen') ?>" for="threadVideo" id="openTrim">
              <i class="ti ti-video"></i> <?= $esc($L['video_upload'] ?? 'Video hochladen') ?>
            </label>
            <input type="file" name="video" id="threadVideo" accept="video/*" hidden>
          </div>

          <!-- kleine Inline-Vorschau -->
          <div id="threadPreview" class="space-y-3 mb-4"></div>

          <div class="flex justify-end gap-2">
            <button type="button" class="btnx" id="cancelThreadModal"><?= $esc($L['cancel'] ?? 'Abbrechen') ?></button>
            <button class="btnx primary" type="submit"><?= $esc($L['create'] ?? 'Erstellen') ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

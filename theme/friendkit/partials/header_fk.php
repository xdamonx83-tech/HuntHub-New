<?php
/** Friendkit Navbar minimal — passe Items nach Bedarf an **/
?>
<nav class="navbar is-white is-fixed-top" role="navigation" aria-label="main navigation">
  <div class="container">
    <div class="navbar-brand">
      <a class="navbar-item" href="<?= $APP_BASE ?>/">
        <img src="<?= $APP_BASE ?>/assets/friendkit/assets/img/logo.svg" alt="Hunthub" style="height:28px">
      </a>
      <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="main-nav">
        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
      </a>
    </div>

    <div id="main-nav" class="navbar-menu">
      <div class="navbar-start">
        <a class="navbar-item <?= nav_active('/index.php') ?>" href="<?= $APP_BASE ?>/">Feed</a>
        <a class="navbar-item <?= nav_active('/profile')   ?>" href="<?= $APP_BASE ?>/profile.php">Profil</a>
        <a class="navbar-item <?= nav_active('/messages')  ?>" href="<?= $APP_BASE ?>/messages.php">Nachrichten</a>
        <a class="navbar-item <?= nav_active('/reels')     ?>" href="<?= $APP_BASE ?>/reels.php">Reels</a>
        <a class="navbar-item <?= nav_active('/forum')     ?>" href="<?= $APP_BASE ?>/forum/boards.php">Forum</a>
      </div>

      <div class="navbar-end">
        <div class="navbar-item">
          <form action="<?= $APP_BASE ?>/search.php" method="get" class="control has-icons-left">
            <input class="input" type="search" name="q" placeholder="Suche…">
            <span class="icon is-left"><i data-feather="search"></i></span>
          </form>
        </div>

        <?php if ($me): ?>
          <div class="navbar-item has-dropdown is-hoverable">
            <a class="navbar-link">
              <?= htmlspecialchars($me['username'] ?? 'Konto') ?>
            </a>
            <div class="navbar-dropdown is-right">
              <a class="navbar-item" href="<?= $APP_BASE ?>/profile.php">Mein Profil</a>
              <a class="navbar-item" href="<?= $APP_BASE ?>/settings.php">Einstellungen</a>
              <hr class="navbar-divider">
              <a class="navbar-item" href="<?= $APP_BASE ?>/api/auth/logout.php">Logout</a>
            </div>
          </div>
        <?php else: ?>
          <div class="navbar-item">
            <div class="buttons">
              <a class="button is-light" href="<?= $APP_BASE ?>/login.php">Login</a>
              <a class="button is-primary" href="<?= $APP_BASE ?>/register.php">Registrieren</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

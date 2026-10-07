<?php
$currentPage = $currentPage ?? '';
$user = current_user();
$flash = get_flash();

$displayName = $user['nom'] ?? '';
$initials = '';
if ($user) {
    foreach (preg_split('/\s+/', trim($displayName)) as $part) {
        if ($part === '') continue;
        $ch = function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($part, 0, 1))
            : strtoupper(substr($part, 0, 1));
        $initials .= $ch;
        if (strlen($initials) >= 2) break;
    }
    if ($initials === '') {
        $initials = 'U';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? APP_NAME) ?> — <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=20260905n">
</head>
<body class="site">
  <header class="site-header<?= $currentPage === 'home' ? ' header-home' : '' ?>">
    <div class="container header-row">
      <a class="logo" href="<?= e(url('index.php')) ?>">
        <img class="logo-img" src="<?= e(asset(APP_LOGO)) ?>" alt="<?= e(APP_NAME) ?>" width="48" height="48">
        <span class="logo-text">
          <strong>Congo Events</strong>
        </span>
      </a>

      <nav class="main-nav desktop-only" aria-label="Navigation principale">
        <a href="<?= e(url('index.php')) ?>" class="<?= $currentPage === 'home' ? 'is-active' : '' ?>">Accueil</a>
        <a href="<?= e(url('salles.php')) ?>" class="<?= $currentPage === 'salles' ? 'is-active' : '' ?>">Salles</a>
        <?php if ($user): ?>
          <?php if (is_client()): ?>
            <a href="<?= e(url('mes-reservations.php')) ?>" class="<?= $currentPage === 'mes-reservations' ? 'is-active' : '' ?>">Mes réservations</a>
          <?php endif; ?>
          <?php if (is_proprietaire()): ?>
            <a href="<?= e(url('proprietaire/index.php')) ?>" class="<?= $currentPage === 'proprietaire' ? 'is-active' : '' ?>">Mon espace</a>
          <?php endif; ?>
          <?php if (is_admin()): ?>
            <a href="<?= e(url('admin/index.php')) ?>" class="<?= $currentPage === 'admin' ? 'is-active' : '' ?>">Admin</a>
          <?php endif; ?>
        <?php endif; ?>
      </nav>

      <div class="header-actions desktop-only">
        <?php if ($user): ?>
          <?php $notifCount = unread_notifications_count((int) $user['id']); ?>
          <a class="notif-bell <?= $currentPage === 'notifications' ? 'is-active' : '' ?>" href="<?= e(url('notifications.php')) ?>" title="Notifications" aria-label="Notifications">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0"/></svg>
            <?php if ($notifCount > 0): ?><span class="notif-badge"><?= $notifCount > 9 ? '9+' : (int) $notifCount ?></span><?php endif; ?>
          </a>
          <span class="user-label" title="<?= e($user['nom']) ?>"><?= e($user['nom']) ?></span>
          <a class="btn btn-ghost btn-logout" href="<?= e(url('logout.php')) ?>">Déconnexion</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">Connexion</a>
          <a class="btn btn-solid" href="<?= e(url('register.php')) ?>">S'inscrire</a>
        <?php endif; ?>
      </div>

      <button class="nav-burger mobile-only" type="button" aria-label="Ouvrir le menu" aria-expanded="false" data-nav-toggle>
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <div class="nav-overlay" data-nav-overlay hidden></div>
  <aside class="mobile-drawer" id="site-menu" data-nav aria-hidden="true">
    <div class="mobile-drawer-top">
      <a class="logo" href="<?= e(url('index.php')) ?>">
        <img class="logo-img" src="<?= e(asset(APP_LOGO)) ?>" alt="<?= e(APP_NAME) ?>" width="44" height="44">
        <span class="logo-text">
          <strong>Congo Events</strong>
        </span>
      </a>
      <button type="button" class="drawer-close" aria-label="Fermer" data-nav-close>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <nav class="mobile-drawer-nav">
      <a href="<?= e(url('index.php')) ?>" class="<?= $currentPage === 'home' ? 'is-active' : '' ?>">Accueil</a>
      <a href="<?= e(url('salles.php')) ?>" class="<?= $currentPage === 'salles' ? 'is-active' : '' ?>">Salles</a>
      <?php if ($user): ?>
        <?php if (is_client()): ?>
          <a href="<?= e(url('mes-reservations.php')) ?>" class="<?= $currentPage === 'mes-reservations' ? 'is-active' : '' ?>">Mes réservations</a>
        <?php endif; ?>
        <?php if (is_proprietaire()): ?>
          <a href="<?= e(url('proprietaire/index.php')) ?>" class="<?= $currentPage === 'proprietaire' ? 'is-active' : '' ?>">Mon espace</a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
          <a href="<?= e(url('admin/index.php')) ?>" class="<?= $currentPage === 'admin' ? 'is-active' : '' ?>">Admin</a>
        <?php endif; ?>
        <a href="<?= e(url('notifications.php')) ?>" class="<?= $currentPage === 'notifications' ? 'is-active' : '' ?>">
          Notifications<?php $nc = unread_notifications_count((int) $user['id']); if ($nc > 0): ?> (<?= (int) $nc ?>)<?php endif; ?>
        </a>
      <?php endif; ?>
    </nav>

    <div class="mobile-drawer-foot">
      <?php if ($user): ?>
        <div class="mobile-user">
          <div class="mobile-avatar"><?= e($initials) ?></div>
          <div>
            <strong><?= e($displayName) ?></strong>
            <span><?= e(role_label($user['role'] ?? 'client')) ?></span>
          </div>
        </div>
        <a class="mobile-logout" href="<?= e(url('logout.php')) ?>">Déconnexion</a>
      <?php else: ?>
        <a class="btn btn-solid" href="<?= e(url('login.php')) ?>">Connexion</a>
        <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>" style="color:#fff!important;border-color:rgba(255,255,255,.25)">S'inscrire</a>
      <?php endif; ?>
    </div>
  </aside>

  <?php if ($flash): ?>
    <div class="container flash-slot">
      <div class="alert alert-<?= e($flash['type']) ?>" role="alert" data-auto-dismiss>
        <?= e($flash['message']) ?>
      </div>
    </div>
  <?php endif; ?>

  <main>

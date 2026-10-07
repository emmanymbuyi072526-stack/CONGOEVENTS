<?php
require_admin();

$adminPage = $adminPage ?? 'dashboard';
$user = current_user();
$flash = get_flash();

$displayName = $user['nom'] ?? 'Admin';
$initials = '';
foreach (preg_split('/\s+/', trim($displayName)) as $part) {
    if ($part === '') continue;
    $ch = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($part, 0, 1))
        : strtoupper(substr($part, 0, 1));
    $initials .= $ch;
    if (strlen($initials) >= 2) break;
}
if ($initials === '') {
    $initials = 'AD';
}

$pageTitles = [
    'dashboard' => ['Tableau de bord', 'Gestion des réservations'],
    'reservations' => ['Réservations', 'Validation des demandes'],
    'salles' => ['Salles', 'Catalogue des espaces'],
    'utilisateurs' => ['Utilisateurs', 'Clients, propriétaires et admins'],
    'abonnements' => ['Abonnements', 'Plans et paiements propriétaires'],
];
[$dashTitle, $dashSubtitle] = $pageTitles[$adminPage] ?? ['Administration', 'Congo Events'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Admin') ?> — <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=20260820">
  <link rel="stylesheet" href="<?= e(asset('css/dashboard.css')) ?>?v=20260821s">
</head>
<body class="dash-body">
  <aside class="dash-sidebar" data-dash-sidebar>
    <div class="dash-sidebar-top">
      <a class="dash-brand" href="<?= e(url('admin/index.php')) ?>">
        <img class="dash-brand-logo" src="<?= e(asset(APP_LOGO)) ?>" alt="<?= e(APP_NAME) ?>" width="36" height="36">
        <span>Congo Events <em>Admin</em></span>
      </a>
      <button type="button" class="dash-close" aria-label="Fermer le menu" data-dash-close>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <nav class="dash-nav">
      <a href="<?= e(url('admin/index.php')) ?>" class="<?= $adminPage === 'dashboard' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-5H4v5Zm10-9h6V4h-6v7Z"/></svg>
        Tableau de bord
      </a>
      <a href="<?= e(url('admin/salles.php')) ?>" class="<?= $adminPage === 'salles' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/></svg>
        Salles
      </a>
      <a href="<?= e(url('admin/utilisateurs.php')) ?>" class="<?= $adminPage === 'utilisateurs' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Utilisateurs
      </a>
      <a href="<?= e(url('admin/abonnements.php')) ?>" class="<?= $adminPage === 'abonnements' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M7 15h2"/></svg>
        Abonnements
      </a>
      <?php $adminNotif = unread_notifications_count((int) ($user['id'] ?? 0)); ?>
      <a href="<?= e(url('notifications.php')) ?>" class="<?= $adminPage === 'notifications' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0"/></svg>
        Notifications<?= $adminNotif > 0 ? ' (' . (int) $adminNotif . ')' : '' ?>
      </a>
      <a href="<?= e(url('index.php')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 7V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-1"/><path d="M10 12h11m0 0-3-3m3 3-3 3"/></svg>
        Voir le site
      </a>
    </nav>

    <div class="dash-user">
      <div class="dash-user-row">
        <div class="dash-avatar"><?= e($initials) ?></div>
        <div>
          <strong><?= e($displayName) ?></strong>
          <span>Administrateur</span>
        </div>
      </div>
      <a class="dash-logout" href="<?= e(url('logout.php')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 7V6a2 2 0 0 1 2-2h7v16h-7a2 2 0 0 1-2-2v-1"/><path d="M15 12H3m0 0 3-3m-3 3 3 3"/></svg>
        Déconnexion
      </a>
    </div>
  </aside>

  <div class="dash-overlay" data-dash-overlay hidden></div>

  <div class="dash-main">
    <div class="dash-topbar">
      <button class="dash-menu-btn" type="button" aria-label="Menu" data-dash-toggle>
        <span></span><span></span><span></span>
      </button>
      <div class="dash-topbar-brand">Congo Events Admin</div>
      <?php $adminBell = unread_notifications_count((int) ($user['id'] ?? 0)); ?>
      <a class="notif-bell dash-topbar-bell" href="<?= e(url('notifications.php')) ?>" title="Notifications" aria-label="Notifications">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0"/></svg>
        <?php if ($adminBell > 0): ?><span class="notif-badge"><?= $adminBell > 9 ? '9+' : (int) $adminBell ?></span><?php endif; ?>
      </a>
    </div>

    <?php if ($flash): ?>
      <div class="dash-flash">
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert" data-auto-dismiss>
          <?= e($flash['message']) ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="dash-content">
      <header class="dash-page-head">
        <h1><?= e($dashTitle) ?></h1>
        <p><?= e($dashSubtitle) ?></p>
      </header>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$adminPage = $adminPage ?? 'dashboard';
?>
<aside class="admin-side">
  <p class="muted" style="margin-top:0;text-transform:uppercase;letter-spacing:.08em;font-size:.75rem">Administration</p>
  <a href="<?= e(url('admin/index.php')) ?>" class="<?= $adminPage === 'dashboard' ? 'active' : '' ?>">Tableau de bord</a>
  <a href="<?= e(url('admin/salles.php')) ?>" class="<?= $adminPage === 'salles' ? 'active' : '' ?>">Salles</a>
  <a href="<?= e(url('admin/utilisateurs.php')) ?>" class="<?= $adminPage === 'utilisateurs' ? 'active' : '' ?>">Utilisateurs</a>
  <a href="<?= e(url('admin/abonnements.php')) ?>" class="<?= $adminPage === 'abonnements' ? 'active' : '' ?>">Abonnements</a>
  <a href="<?= e(url('index.php')) ?>">← Retour au site</a>
</aside>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_subscription_schema();

$stats = [
    'salles' => (int) db()->query("SELECT COUNT(*) FROM salles WHERE statut = 'active'")->fetchColumn(),
    'attente' => (int) db()->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn(),
    'users' => (int) db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(),
    'abos' => (int) db()->query("SELECT COUNT(*) FROM abonnements WHERE statut = 'actif' AND date_fin >= NOW()")->fetchColumn(),
];

$salles = db()->query(
    "SELECT id, nom, ville, commune, capacite, tarif_jour
     FROM salles
     WHERE statut = 'active'
     ORDER BY nom
     LIMIT 8"
)->fetchAll();

$recent = db()->query(
    "SELECT r.*, s.nom AS salle_nom, u.nom AS user_nom
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     JOIN utilisateurs u ON u.id = r.utilisateur_id
     ORDER BY r.date_demande DESC
     LIMIT 6"
)->fetchAll();

$pageTitle = 'Tableau de bord';
$adminPage = 'dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="dash-stats">
  <article class="dash-stat dash-stat-blue">
    <div class="dash-stat-label">Salles actives</div>
    <div class="dash-stat-value"><?= $stats['salles'] ?></div>
    <div class="dash-stat-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/></svg>
    </div>
  </article>

  <article class="dash-stat dash-stat-green">
    <div class="dash-stat-label">Utilisateurs</div>
    <div class="dash-stat-value"><?= $stats['users'] ?></div>
    <div class="dash-stat-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
  </article>

  <article class="dash-stat dash-stat-amber">
    <div class="dash-stat-label">Abonnements actifs</div>
    <div class="dash-stat-value"><?= $stats['abos'] ?></div>
    <div class="dash-stat-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
    </div>
  </article>
</div>

<?php if ($stats['attente'] > 0): ?>
  <div class="dash-stats" style="grid-template-columns:1fr;margin-top:-0.35rem">
    <a class="dash-stat dash-stat-amber" href="<?= e(url('admin/reservations.php?statut=en_attente')) ?>" style="text-decoration:none;color:#fff">
      <div class="dash-stat-label">Demandes en attente</div>
      <div class="dash-stat-value"><?= $stats['attente'] ?></div>
      <div class="dash-stat-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
      </div>
    </a>
  </div>
<?php endif; ?>

<div class="dash-panel" style="margin-bottom:1.25rem">
  <div class="dash-panel-head">
    <h2>Liste des salles</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(url('admin/salles.php')) ?>">Gérer</a>
  </div>
  <div class="dash-list-label">Salle</div>
  <?php if (!$salles): ?>
    <p class="dash-empty">Aucune salle enregistrée.</p>
  <?php else: ?>
    <ul class="dash-list">
      <?php foreach ($salles as $s): ?>
        <li class="dash-list-item">
          <div class="dash-list-main">
            <strong><?= e($s['nom']) ?></strong>
            <span><?= e($s['ville']) ?><?= $s['commune'] ? ' · ' . e($s['commune']) : '' ?> · <?= (int) $s['capacite'] ?> places</span>
          </div>
          <a class="dash-more" href="<?= e(url('admin/salles.php?edit=' . (int) $s['id'])) ?>" title="Options" aria-label="Modifier">⋯</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="dash-panel">
  <div class="dash-panel-head">
    <h2>Dernières réservations</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reservations.php')) ?>">Tout voir</a>
  </div>
  <div class="dash-list-label">Demande</div>
  <?php if (!$recent): ?>
    <p class="dash-empty">Aucune réservation pour le moment.</p>
  <?php else: ?>
    <ul class="dash-list">
      <?php foreach ($recent as $r): ?>
        <li class="dash-list-item">
          <div class="dash-list-main">
            <strong><?= e($r['titre_evenement']) ?></strong>
            <span><?= e($r['user_nom']) ?> · <?= e($r['salle_nom']) ?> · <?= e(statut_label($r['statut'])) ?></span>
          </div>
          <a class="dash-more" href="<?= e(url('admin/reservations.php')) ?>" title="Voir" aria-label="Voir">⋯</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>

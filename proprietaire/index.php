<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();

$userId = (int) current_user()['id'];
ensure_subscription_schema();

$abo = get_active_subscription();
$hasOwnerCol = salle_column_exists('proprietaire_id');

$stats = [
    'salles' => 0,
    'attente' => 0,
    'confirmees' => 0,
];
$recent = [];
$salles = [];

if ($hasOwnerCol) {
    $stmtSalles = db()->prepare('SELECT COUNT(*) FROM salles WHERE proprietaire_id = ?');
    $stmtSalles->execute([$userId]);
    $stats['salles'] = (int) $stmtSalles->fetchColumn();

    $stmtList = db()->prepare('SELECT id, nom, ville, statut FROM salles WHERE proprietaire_id = ? ORDER BY nom LIMIT 6');
    $stmtList->execute([$userId]);
    $salles = $stmtList->fetchAll();

    $stmtAttente = db()->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE s.proprietaire_id = ? AND r.statut = 'en_attente'"
    );
    $stmtAttente->execute([$userId]);
    $stats['attente'] = (int) $stmtAttente->fetchColumn();

    $stmtConf = db()->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE s.proprietaire_id = ? AND r.statut = 'confirmee'"
    );
    $stmtConf->execute([$userId]);
    $stats['confirmees'] = (int) $stmtConf->fetchColumn();

    $stmtRecent = db()->prepare(
        "SELECT r.*, s.nom AS salle_nom, u.nom AS client_nom
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         JOIN utilisateurs u ON u.id = r.utilisateur_id
         WHERE s.proprietaire_id = ?
         ORDER BY r.date_demande DESC
         LIMIT 5"
    );
    $stmtRecent->execute([$userId]);
    $recent = $stmtRecent->fetchAll();
}

$pageTitle = 'Tableau de bord';
$propPage = 'dashboard';
require __DIR__ . '/../includes/proprietaire-header.php';
?>

<?php if (!$abo): ?>
  <div class="alert alert-warning" style="margin-bottom:1.25rem">
    Vous n'avez pas d'abonnement actif. Vos salles ne seront pas visibles dans le catalogue public.
    <a href="<?= e(url('proprietaire/abonnement.php')) ?>">Souscrire un abonnement →</a>
  </div>
<?php else: ?>
  <div class="alert alert-info" style="margin-bottom:1.25rem">
    Abonnement <strong><?= e(subscription_plans()[$abo['plan']]['label'] ?? $abo['plan']) ?></strong>
    actif jusqu'au <?= e(format_datetime($abo['date_fin'])) ?>.
  </div>
<?php endif; ?>

<div class="dash-stats">
  <article class="dash-stat dash-stat-blue">
    <div class="dash-stat-label">Mes salles</div>
    <div class="dash-stat-value"><?= (int) $stats['salles'] ?></div>
  </article>
  <article class="dash-stat dash-stat-amber">
    <div class="dash-stat-label">En attente</div>
    <div class="dash-stat-value"><?= (int) $stats['attente'] ?></div>
  </article>
  <article class="dash-stat dash-stat-green">
    <div class="dash-stat-label">Confirmées</div>
    <div class="dash-stat-value"><?= (int) $stats['confirmees'] ?></div>
  </article>
</div>

<div class="dash-panel" style="margin-top:1.25rem">
  <div class="dash-panel-head">
    <h2>Démarrer</h2>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:.75rem;padding:.25rem 0 1rem">
    <?php if (!$abo): ?>
      <a class="btn btn-primary" href="<?= e(url('proprietaire/abonnement.php')) ?>">1. Souscrire un abonnement</a>
    <?php endif; ?>
    <a class="btn <?= $abo ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('proprietaire/salles.php')) ?>">
      <?= $abo ? '2. Publier / gérer mes salles' : 'Gérer mes salles' ?>
    </a>
    <a class="btn btn-ghost" href="<?= e(url('proprietaire/reservations.php')) ?>">Voir les réservations</a>
  </div>
</div>

<div class="dash-panel" style="margin-top:1.25rem">
  <div class="dash-panel-head">
    <h2>Mes salles</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(url('proprietaire/salles.php')) ?>">Gérer</a>
  </div>
  <?php if (!$salles): ?>
    <p class="dash-empty">
      Aucune salle liée à votre compte pour le moment.
      <?php if ($abo): ?>
        <a href="<?= e(url('proprietaire/salles.php')) ?>">Publier une salle</a>
      <?php else: ?>
        Souscrivez d'abord un <a href="<?= e(url('proprietaire/abonnement.php')) ?>">abonnement</a>, puis publiez votre salle.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nom</th>
            <th>Ville</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($salles as $s): ?>
            <tr>
              <td><strong><?= e($s['nom']) ?></strong></td>
              <td><?= e($s['ville']) ?></td>
              <td><span class="badge"><?= e(statut_label($s['statut'] ?? 'active')) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="dash-panel" style="margin-top:1.25rem">
  <div class="dash-panel-head">
    <h2>Dernières demandes</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(url('proprietaire/reservations.php')) ?>">Voir tout</a>
  </div>
  <?php if (!$recent): ?>
    <p class="dash-empty">Aucune réservation pour vos salles pour le moment.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Événement</th>
            <th>Client</th>
            <th>Salle</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><strong><?= e($r['titre_evenement']) ?></strong></td>
              <td><?= e($r['client_nom']) ?></td>
              <td><?= e($r['salle_nom']) ?></td>
              <td><span class="badge"><?= e(statut_label($r['statut'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>

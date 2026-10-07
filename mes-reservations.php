<?php
require_once __DIR__ . '/includes/functions.php';
require_client();

$userId = current_user()['id'];

if (isset($_POST['annuler_id'])) {
    $rid = (int) $_POST['annuler_id'];
    $stmt = db()->prepare(
        "SELECT * FROM reservations WHERE id = ? AND utilisateur_id = ? AND statut IN ('en_attente','confirmee')"
    );
    $stmt->execute([$rid, $userId]);
    $res = $stmt->fetch();
    if ($res) {
        $upd = db()->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = ?");
        $upd->execute([$rid]);
        flash('success', 'Réservation annulée.');
    } else {
        flash('error', 'Impossible d\'annuler cette réservation.');
    }
    redirect('mes-reservations.php');
}

$stmt = db()->prepare(
    "SELECT r.*, s.nom AS salle_nom, s.ville
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     WHERE r.utilisateur_id = ?
     ORDER BY r.date_demande DESC"
);
$stmt->execute([$userId]);
$reservations = $stmt->fetchAll();

$counts = ['total' => count($reservations), 'en_attente' => 0, 'confirmee' => 0, 'refusee' => 0, 'annulee' => 0];
foreach ($reservations as $resRow) {
    if (isset($counts[$resRow['statut']])) {
        $counts[$resRow['statut']]++;
    }
}

$pageTitle = 'Mes réservations';
$currentPage = 'mes-reservations';
require __DIR__ . '/includes/header.php';
?>

<section class="section res-page">
  <div class="container">
    <div class="page-intro">
      <h1>Mes réservations</h1>
      <p>Suivez le statut de vos demandes d'événements.</p>
    </div>

    <?php if ($reservations): ?>
      <div class="res-summary res-summary-public">
        <article class="res-summary-card">
          <span>Total</span>
          <strong><?= (int) $counts['total'] ?></strong>
        </article>
        <article class="res-summary-card res-summary-warn">
          <span>En attente</span>
          <strong><?= (int) $counts['en_attente'] ?></strong>
        </article>
        <article class="res-summary-card res-summary-ok">
          <span>Confirmées</span>
          <strong><?= (int) $counts['confirmee'] ?></strong>
        </article>
        <article class="res-summary-card res-summary-muted">
          <span>Autres</span>
          <strong><?= (int) $counts['refusee'] + (int) $counts['annulee'] ?></strong>
        </article>
      </div>
    <?php endif; ?>

    <?php if (!$reservations): ?>
      <div class="res-empty res-empty-public">
        <div class="res-empty-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
        </div>
        <h3>Aucune réservation</h3>
        <p>Vous n'avez encore aucune demande. Parcourez le catalogue pour réserver une salle.</p>
        <a class="btn btn-primary" href="<?= e(url('salles.php')) ?>">Parcourir les salles</a>
      </div>
    <?php else: ?>
      <div class="res-cards res-cards-public">
        <?php foreach ($reservations as $r): ?>
          <?php
          $badgeClass = match ($r['statut']) {
              'confirmee' => 'badge-success',
              'en_attente' => 'badge-warning',
              'refusee' => 'badge-danger',
              default => 'badge-muted',
          };
          ?>
          <article class="res-card res-card--<?= e($r['statut']) ?>">
            <div class="res-card-top">
              <div class="res-card-event">
                <span class="res-type"><?= e(type_evenement_label($r['type_evenement'])) ?></span>
                <h3><?= e($r['titre_evenement']) ?></h3>
              </div>
              <span class="badge <?= $badgeClass ?>"><?= e(statut_label($r['statut'])) ?></span>
            </div>

            <div class="res-card-grid">
              <div class="res-card-block">
                <span class="res-label">Salle</span>
                <strong class="res-value"><?= e($r['salle_nom']) ?></strong>
                <small class="res-subvalue"><?= e($r['ville']) ?></small>
              </div>

              <div class="res-card-block res-card-block-wide">
                <span class="res-label">Période</span>
                <div class="res-period">
                  <time><?= e(format_datetime($r['date_debut'])) ?></time>
                  <span class="res-period-arrow">→</span>
                  <time><?= e(format_datetime($r['date_fin'])) ?></time>
                </div>
              </div>
            </div>

            <?php if ($r['statut'] === 'refusee' && $r['motif_refus']): ?>
              <p class="res-refusal"><strong>Motif :</strong> <?= e($r['motif_refus']) ?></p>
            <?php endif; ?>

            <?php if (in_array($r['statut'], ['en_attente', 'confirmee'], true)): ?>
              <div class="res-card-actions">
                <form method="post">
                  <input type="hidden" name="annuler_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Annuler cette réservation ?">Annuler</button>
                </form>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

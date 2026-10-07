<?php
require_once __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM salles WHERE id = ?');
$stmt->execute([$id]);
$salle = $stmt->fetch();

if (!$salle || !salle_is_public($salle)) {
    flash('error', 'Salle introuvable ou non disponible.');
    redirect('salles.php');
}

$pageTitle = $salle['nom'];
$currentPage = 'salles';
require __DIR__ . '/includes/header.php';
?>

<section class="detail-hero">
  <img src="<?= e(salle_image($salle)) ?>" alt="<?= e($salle['nom']) ?>" width="1600" height="900">
</section>

<section class="container detail-layout">
  <div class="detail-main">
    <p class="muted"><?= e($salle['ville']) ?><?= $salle['commune'] ? ' · ' . e($salle['commune']) : '' ?> · RDC</p>
    <h1><?= e($salle['nom']) ?></h1>
    <p><?= nl2br(e($salle['description'] ?? '')) ?></p>

    <div class="meta-row">
      <span class="meta-pill"><?= (int) $salle['capacite'] ?> places</span>
      <span class="meta-pill"><?= e(format_salle_tarif($salle)) ?> / jour</span>
      <?php if (!empty($salle['adresse'])): ?>
        <span class="meta-pill"><?= e($salle['adresse']) ?></span>
      <?php endif; ?>
    </div>

    <?php if (!empty($salle['equipements'])): ?>
      <p><strong>Équipements :</strong> <?= e($salle['equipements']) ?></p>
    <?php endif; ?>
  </div>

  <aside class="detail-side">
    <h2>Réserver cette salle</h2>
    <p class="muted">Déposez une demande ; le propriétaire ou l'administration confirmera la disponibilité.</p>
    <?php if (is_logged_in() && is_client()): ?>
      <a class="btn btn-solid" href="<?= e(url('reservation.php?salle_id=' . (int) $salle['id'])) ?>">Demander une réservation</a>
    <?php elseif (is_logged_in() && is_proprietaire()): ?>
      <p class="muted">Connectez-vous avec un compte client pour réserver une salle.</p>
      <a class="btn btn-ghost" href="<?= e(url('proprietaire/index.php')) ?>">Mon espace propriétaire</a>
    <?php elseif (is_logged_in() && is_admin()): ?>
      <p class="muted">Les administrateurs gèrent les réservations depuis l'espace admin.</p>
      <a class="btn btn-ghost" href="<?= e(url('admin/reservations.php')) ?>">Voir les réservations</a>
    <?php else: ?>
      <a class="btn btn-solid" href="<?= e(url('login.php')) ?>">Se connecter pour réserver</a>
      <p class="muted" style="margin-top:1rem">Pas encore de compte ? <a href="<?= e(url('register.php')) ?>">S'inscrire comme client</a></p>
    <?php endif; ?>
  </aside>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

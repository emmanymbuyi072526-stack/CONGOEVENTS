<?php
$sid = (int) $salle['id'];
$img = salle_image($salle);
$cap = (int) $salle['capacite'];
?>
<article class="venue-card">
  <a class="venue-media" href="<?= e(url('salle.php?id=' . $sid)) ?>">
    <img src="<?= e($img) ?>" alt="<?= e($salle['nom']) ?>" loading="lazy" width="640" height="420">
    <span class="venue-cap"><?= $cap ?> places</span>
  </a>
  <div class="venue-body">
    <p class="venue-loc"><?= e($salle['ville']) ?><?= !empty($salle['commune']) ? ' · ' . e($salle['commune']) : '' ?></p>
    <h3><a href="<?= e(url('salle.php?id=' . $sid)) ?>"><?= e($salle['nom']) ?></a></h3>
    <p class="venue-desc"><?= e(excerpt($salle['description'] ?? '', 100)) ?></p>
    <div class="venue-foot">
      <strong><?= e(format_salle_tarif($salle)) ?> <span>/ jour</span></strong>
      <a class="btn btn-line" href="<?= e(url('salle.php?id=' . $sid)) ?>">Réserver</a>
    </div>
  </div>
</article>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();

$userId = (int) current_user()['id'];
$statutFilter = $_GET['statut'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['res_id'], $_POST['action'])) {
    $rid = (int) $_POST['res_id'];
    $action = $_POST['action'];

    $stmt = db()->prepare(
        "SELECT r.* FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE r.id = ? AND s.proprietaire_id = ? AND r.statut = 'en_attente'"
    );
    $stmt->execute([$rid, $userId]);
    $res = $stmt->fetch();

    if ($res) {
        if ($action === 'confirm') {
            $result = confirm_reservation_and_block($rid);
            if ($result['ok']) {
                flash('success', 'Réservation validée. La salle est bloquée sur ce créneau. Notification envoyée au client.');
            } else {
                flash('error', $result['error'] ?? 'Impossible de confirmer.');
            }
        } elseif ($action === 'refuse') {
            $motif = trim($_POST['motif_refus'] ?? 'Non disponible.');
            $result = refuse_reservation_and_notify($rid, $motif);
            if ($result['ok']) {
                flash('success', 'Réservation refusée. Notification envoyée au client.');
            } else {
                flash('error', $result['error'] ?? 'Impossible de refuser.');
            }
        }
    }
    redirect('proprietaire/reservations.php' . ($statutFilter ? '?statut=' . urlencode($statutFilter) : ''));
}

$sql = "SELECT r.*, s.nom AS salle_nom, u.nom AS client_nom, u.email AS client_email
        FROM reservations r
        JOIN salles s ON s.id = r.salle_id
        JOIN utilisateurs u ON u.id = r.utilisateur_id
        WHERE s.proprietaire_id = ?";
$params = [$userId];

if ($statutFilter !== '' && in_array($statutFilter, ['en_attente', 'confirmee', 'refusee', 'annulee'], true)) {
    $sql .= ' AND r.statut = ?';
    $params[] = $statutFilter;
}
$sql .= ' ORDER BY r.date_demande DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$countStmt = db()->prepare(
    "SELECT r.statut, COUNT(*) AS n
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     WHERE s.proprietaire_id = ?
     GROUP BY r.statut"
);
$countStmt->execute([$userId]);
$counts = ['total' => 0, 'en_attente' => 0, 'confirmee' => 0, 'refusee' => 0, 'annulee' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['statut']] = (int) $row['n'];
    $counts['total'] += (int) $row['n'];
}

$pageTitle = 'Réservations';
$propPage = 'reservations';
require __DIR__ . '/../includes/proprietaire-header.php';
?>

<div class="res-summary">
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
    <span>Refusées / annulées</span>
    <strong><?= (int) $counts['refusee'] + (int) $counts['annulee'] ?></strong>
  </article>
</div>

<div class="dash-panel res-panel">
  <div class="dash-panel-head res-panel-head">
    <div>
      <h2>Demandes de réservation</h2>
      <p class="res-panel-sub">Gérez les demandes reçues pour vos salles.</p>
    </div>
    <div class="res-filters">
      <a class="res-filter <?= $statutFilter === '' ? 'is-active' : '' ?>" href="<?= e(url('proprietaire/reservations.php')) ?>">
        Toutes <em><?= (int) $counts['total'] ?></em>
      </a>
      <a class="res-filter <?= $statutFilter === 'en_attente' ? 'is-active' : '' ?>" href="<?= e(url('proprietaire/reservations.php?statut=en_attente')) ?>">
        En attente <em><?= (int) $counts['en_attente'] ?></em>
      </a>
      <a class="res-filter <?= $statutFilter === 'confirmee' ? 'is-active' : '' ?>" href="<?= e(url('proprietaire/reservations.php?statut=confirmee')) ?>">
        Confirmées <em><?= (int) $counts['confirmee'] ?></em>
      </a>
    </div>
  </div>

  <?php if (!$reservations): ?>
    <div class="res-empty">
      <div class="res-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
      </div>
      <h3>Aucune réservation</h3>
      <p>Les demandes de vos clients apparaîtront ici dès qu'une salle sera réservée.</p>
    </div>
  <?php else: ?>
    <div class="res-cards">
      <?php foreach ($reservations as $r): ?>
        <?php
        $badgeClass = match ($r['statut']) {
            'confirmee' => 'badge-success',
            'en_attente' => 'badge-warning',
            'refusee' => 'badge-danger',
            default => 'badge-muted',
        };
        $initials = '';
        foreach (preg_split('/\s+/', trim($r['client_nom'])) as $part) {
            if ($part === '') continue;
            $initials .= function_exists('mb_substr')
                ? mb_strtoupper(mb_substr($part, 0, 1))
                : strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) break;
        }
        if ($initials === '') {
            $initials = 'CL';
        }
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
              <span class="res-label">Client</span>
              <div class="res-client">
                <span class="res-avatar"><?= e($initials) ?></span>
                <div>
                  <strong><?= e($r['client_nom']) ?></strong>
                  <small><?= e($r['client_email']) ?></small>
                </div>
              </div>
            </div>

            <div class="res-card-block">
              <span class="res-label">Salle</span>
              <strong class="res-value"><?= e($r['salle_nom']) ?></strong>
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

          <?php if ($r['statut'] === 'en_attente'): ?>
            <div class="res-card-actions">
              <form method="post">
                <input type="hidden" name="res_id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn btn-primary btn-sm">Confirmer</button>
              </form>
              <form method="post">
                <input type="hidden" name="res_id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="action" value="refuse">
                <input type="hidden" name="motif_refus" value="Créneau non disponible.">
                <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Refuser cette demande ?">Refuser</button>
              </form>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>

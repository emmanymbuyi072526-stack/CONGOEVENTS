<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_subscription_schema();

$plans = subscription_plans();
$statutFilter = $_GET['statut'] ?? '';
$userFilter = (int) ($_GET['user_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $userId = (int) ($_POST['utilisateur_id'] ?? 0);
        $planKey = $_POST['plan'] ?? '';
        $moyen = $_POST['moyen_paiement'] ?? '';

        $owner = db()->prepare("SELECT id, nom FROM utilisateurs WHERE id = ? AND role = 'proprietaire'");
        $owner->execute([$userId]);
        $ownerRow = $owner->fetch();

        if (!$ownerRow) {
            $errors[] = 'Propriétaire invalide.';
        } elseif (!isset($plans[$planKey])) {
            $errors[] = 'Plan invalide.';
        } else {
            $plan = $plans[$planKey];
            $debut = date('Y-m-d H:i:s');
            $fin = date('Y-m-d H:i:s', strtotime('+' . (int) $plan['jours'] . ' days'));

            db()->prepare(
                "UPDATE abonnements SET statut = 'expire'
                 WHERE utilisateur_id = ? AND statut = 'actif'"
            )->execute([$userId]);

            try {
                $col = db()->query("SHOW COLUMNS FROM abonnements LIKE 'moyen_paiement'")->fetch();
                if (!$col) {
                    db()->exec("ALTER TABLE abonnements ADD COLUMN moyen_paiement VARCHAR(40) DEFAULT NULL AFTER statut");
                }
            } catch (Throwable $e) {
            }

            try {
                db()->prepare(
                    "INSERT INTO abonnements (utilisateur_id, plan, montant, date_debut, date_fin, statut, moyen_paiement)
                     VALUES (?, ?, ?, ?, ?, 'actif', ?)"
                )->execute([
                    $userId,
                    $planKey,
                    $plan['montant'],
                    $debut,
                    $fin,
                    $moyen !== '' ? $moyen : 'autre',
                ]);
            } catch (Throwable $e) {
                db()->prepare(
                    "INSERT INTO abonnements (utilisateur_id, plan, montant, date_debut, date_fin, statut)
                     VALUES (?, ?, ?, ?, ?, 'actif')"
                )->execute([$userId, $planKey, $plan['montant'], $debut, $fin]);
            }

            notify_subscription_activated($userId, $planKey, (float) $plan['montant']);

            flash('success', 'Abonnement activé pour ' . $ownerRow['nom'] . '. Notification envoyée.');
            redirect('admin/abonnements.php');
        }
    }

    if ($action === 'expire' && isset($_POST['abo_id'])) {
        $aboId = (int) $_POST['abo_id'];
        db()->prepare("UPDATE abonnements SET statut = 'expire' WHERE id = ?")->execute([$aboId]);
        flash('success', 'Abonnement marqué comme expiré.');
        redirect('admin/abonnements.php' . ($statutFilter ? '?statut=' . urlencode($statutFilter) : ''));
    }

    if ($action === 'activate' && isset($_POST['abo_id'])) {
        $aboId = (int) $_POST['abo_id'];
        $abo = db()->prepare('SELECT * FROM abonnements WHERE id = ?');
        $abo->execute([$aboId]);
        $row = $abo->fetch();
        if ($row) {
            db()->prepare(
                "UPDATE abonnements SET statut = 'expire'
                 WHERE utilisateur_id = ? AND statut = 'actif' AND id != ?"
            )->execute([(int) $row['utilisateur_id'], $aboId]);
            db()->prepare("UPDATE abonnements SET statut = 'actif' WHERE id = ?")->execute([$aboId]);
            flash('success', 'Abonnement réactivé.');
        }
        redirect('admin/abonnements.php' . ($statutFilter ? '?statut=' . urlencode($statutFilter) : ''));
    }
}

$owners = db()->query(
    "SELECT id, nom, email FROM utilisateurs WHERE role = 'proprietaire' ORDER BY nom"
)->fetchAll();

$sql = "SELECT a.*, u.nom AS user_nom, u.email AS user_email
        FROM abonnements a
        JOIN utilisateurs u ON u.id = a.utilisateur_id
        WHERE 1=1";
$params = [];

if ($statutFilter !== '' && in_array($statutFilter, ['actif', 'expire', 'en_attente', 'annule'], true)) {
    $sql .= ' AND a.statut = ?';
    $params[] = $statutFilter;
}
if ($userFilter > 0) {
    $sql .= ' AND a.utilisateur_id = ?';
    $params[] = $userFilter;
}
$sql .= ' ORDER BY a.date_creation DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$abonnements = $stmt->fetchAll();

$countStmt = db()->query(
    "SELECT statut, COUNT(*) AS n FROM abonnements GROUP BY statut"
);
$counts = ['total' => 0, 'actif' => 0, 'expire' => 0, 'en_attente' => 0, 'annule' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['statut']] = (int) $row['n'];
    $counts['total'] += (int) $row['n'];
}

$pageTitle = 'Abonnements';
$adminPage = 'abonnements';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="dash-stats" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:1rem">
  <article class="dash-stat dash-stat-blue">
    <div class="dash-stat-label">Total</div>
    <div class="dash-stat-value"><?= (int) $counts['total'] ?></div>
  </article>
  <article class="dash-stat dash-stat-green">
    <div class="dash-stat-label">Actifs</div>
    <div class="dash-stat-value"><?= (int) $counts['actif'] ?></div>
  </article>
  <article class="dash-stat dash-stat-amber">
    <div class="dash-stat-label">Expirés</div>
    <div class="dash-stat-value"><?= (int) $counts['expire'] ?></div>
  </article>
  <article class="dash-stat dash-stat-red">
    <div class="dash-stat-label">Propriétaires</div>
    <div class="dash-stat-value"><?= count($owners) ?></div>
  </article>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="dash-panel" style="margin-bottom:1.25rem">
  <div class="dash-panel-head">
    <h2>Activer un abonnement</h2>
  </div>
  <form method="post" class="form-panel" style="padding:1.1rem 1.25rem 1.25rem">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-group">
        <label for="utilisateur_id">Propriétaire</label>
        <select id="utilisateur_id" name="utilisateur_id" required>
          <option value="">Choisir…</option>
          <?php foreach ($owners as $o): ?>
            <option value="<?= (int) $o['id'] ?>" <?= $userFilter === (int) $o['id'] ? 'selected' : '' ?>>
              <?= e($o['nom']) ?> — <?= e($o['email']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="plan">Plan</label>
        <select id="plan" name="plan" required>
          <?php foreach ($plans as $key => $plan): ?>
            <option value="<?= e($key) ?>">
              <?= e($plan['label']) ?> — <?= e(format_usd((float) $plan['montant'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group" style="max-width:320px">
      <label for="moyen_paiement">Moyen de paiement</label>
      <select id="moyen_paiement" name="moyen_paiement">
        <?php foreach (payment_methods() as $key => $method): ?>
          <option value="<?= e($key) ?>"><?= e($method['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Activer l'abonnement</button>
    </div>
  </form>
</div>

<div class="dash-panel">
  <div class="dash-panel-head" style="flex-wrap:wrap">
    <h2>Historique des abonnements</h2>
    <div class="chip-row" style="margin:0">
      <a class="chip <?= $statutFilter === '' ? 'is-active' : '' ?>" href="<?= e(url('admin/abonnements.php')) ?>">Tous</a>
      <a class="chip <?= $statutFilter === 'actif' ? 'is-active' : '' ?>" href="<?= e(url('admin/abonnements.php?statut=actif')) ?>">Actifs</a>
      <a class="chip <?= $statutFilter === 'expire' ? 'is-active' : '' ?>" href="<?= e(url('admin/abonnements.php?statut=expire')) ?>">Expirés</a>
    </div>
  </div>

  <?php if (!$abonnements): ?>
    <p class="dash-empty">Aucun abonnement enregistré.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Propriétaire</th>
            <th>Plan</th>
            <th>Montant</th>
            <th>Paiement</th>
            <th>Période</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($abonnements as $a): ?>
            <tr>
              <td>
                <strong><?= e($a['user_nom']) ?></strong><br>
                <span class="muted"><?= e($a['user_email']) ?></span>
              </td>
              <td><?= e($plans[$a['plan']]['label'] ?? $a['plan']) ?></td>
              <td><?= e(format_usd(subscription_amount_for_display($a['plan'], (float) $a['montant']))) ?></td>
              <td><?= !empty($a['moyen_paiement']) ? e(payment_method_label($a['moyen_paiement'])) : '—' ?></td>
              <td><?= e(format_datetime($a['date_debut'])) ?> → <?= e(format_datetime($a['date_fin'])) ?></td>
              <td>
                <span class="badge <?= $a['statut'] === 'actif' ? 'badge-success' : 'badge-muted' ?>">
                  <?= e(abonnement_statut_label($a['statut'])) ?>
                </span>
              </td>
              <td>
                <?php if ($a['statut'] === 'actif'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="expire">
                    <input type="hidden" name="abo_id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Expirer cet abonnement ?">Expirer</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="abo_id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Réactiver</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();

$userId = (int) current_user()['id'];
$plans = subscription_plans();
$methods = payment_methods();
$errors = [];
$selectedPlan = $_GET['plan'] ?? ($_POST['plan'] ?? '');
if (!isset($plans[$selectedPlan])) {
    $selectedPlan = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'], $_POST['moyen_paiement'])) {
    $planKey = $_POST['plan'] ?? '';
    $moyen = $_POST['moyen_paiement'] ?? '';
    $reference = trim($_POST['reference'] ?? '');

    if (!isset($plans[$planKey])) {
        $errors[] = 'Plan invalide.';
        $selectedPlan = '';
    } elseif (!isset($methods[$moyen])) {
        $errors[] = 'Choisissez un moyen de paiement.';
        $selectedPlan = $planKey;
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
            $colRef = db()->query("SHOW COLUMNS FROM abonnements LIKE 'reference_paiement'")->fetch();
            if (!$colRef) {
                db()->exec("ALTER TABLE abonnements ADD COLUMN reference_paiement VARCHAR(120) DEFAULT NULL AFTER moyen_paiement");
            }
        } catch (Throwable $e) {
        }

        try {
            db()->prepare(
                "INSERT INTO abonnements (utilisateur_id, plan, montant, date_debut, date_fin, statut, moyen_paiement, reference_paiement)
                 VALUES (?, ?, ?, ?, ?, 'actif', ?, ?)"
            )->execute([
                $userId,
                $planKey,
                $plan['montant'],
                $debut,
                $fin,
                $moyen,
                $reference !== '' ? $reference : null,
            ]);
        } catch (Throwable $e) {
            db()->prepare(
                "INSERT INTO abonnements (utilisateur_id, plan, montant, date_debut, date_fin, statut)
                 VALUES (?, ?, ?, ?, ?, 'actif')"
            )->execute([$userId, $planKey, $plan['montant'], $debut, $fin]);
        }

        notify_subscription_activated($userId, $planKey, (float) $plan['montant']);

        flash(
            'success',
            'Vous venez de souscrire un abonnement ' . plan_abonnement_phrase($planKey) . ' de '
            . format_usd((float) $plan['montant'])
            . ' sur Congo Events. Vous pouvez actuellement mettre vos salles en ligne. Merci pour votre confiance.'
        );
        redirect('proprietaire/abonnement.php');
    }
}

$active = get_active_subscription();
$history = db()->prepare(
    'SELECT * FROM abonnements WHERE utilisateur_id = ? ORDER BY date_creation DESC LIMIT 10'
);
$history->execute([$userId]);
$historyRows = $history->fetchAll();

$pageTitle = 'Abonnement';
$propPage = 'abonnement';
require __DIR__ . '/../includes/proprietaire-header.php';
?>

<?php
$planThemes = [
    'mensuel' => ['tone' => 'abo-plan-silver', 'eyebrow' => 'Essentiel', 'accent' => 'Pour démarrer'],
    'trimestriel' => ['tone' => 'abo-plan-blue', 'eyebrow' => 'Populaire', 'accent' => 'Le meilleur rythme'],
    'annuel' => ['tone' => 'abo-plan-gold', 'eyebrow' => 'Premium', 'accent' => 'Visibilité maximale'],
];
?>

<section class="abo-hero">
  <div class="abo-hero-copy">
    <span class="abo-hero-kicker">Abonnement Pro</span>
    <h2>Donnez plus de visibilité à vos salles</h2>
    <p>
      Choisissez une formule claire, activez-la en quelques secondes et publiez vos salles
      avec une présentation plus professionnelle sur Congo Events.
    </p>
  </div>

  <?php if ($active): ?>
    <aside class="abo-hero-side">
      <span class="abo-hero-side-label">Abonnement actif</span>
      <strong><?= e(subscription_plans()[$active['plan']]['label'] ?? $active['plan']) ?></strong>
      <span class="abo-hero-side-price"><?= e(format_usd(subscription_amount_for_display($active['plan'], (float) $active['montant']))) ?></span>
      <small>Actif jusqu'au <?= e(format_datetime($active['date_fin'])) ?></small>
    </aside>
  <?php endif; ?>
</section>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($selectedPlan === ''): ?>
  <section class="abo-section abo-pricing-showcase">
    <div class="abo-section-head">
      <div>
        <span class="abo-section-kicker">Nos formules</span>
        <h3>Choisissez le plan adapté à votre activité</h3>
      </div>
    </div>

    <div class="abo-plan-grid">
      <?php foreach ($plans as $key => $plan): ?>
        <?php $theme = $planThemes[$key] ?? ['tone' => 'abo-plan-silver', 'eyebrow' => 'Plan', 'accent' => 'Abonnement']; ?>
        <article class="abo-plan-card <?= e($theme['tone']) ?> <?= $key === 'trimestriel' ? 'is-featured' : '' ?>">
          <div class="abo-plan-cap">
            <span class="abo-plan-eyebrow"><?= e($theme['eyebrow']) ?></span>
            <h3><?= e($plan['label']) ?></h3>
          </div>

          <div class="abo-plan-body">
            <div class="abo-plan-price">
              <strong><?= e(format_usd((float) $plan['montant'])) ?></strong>
              <span><?= $key === 'mensuel' ? 'Par mois' : ($key === 'trimestriel' ? 'Par 3 mois' : 'Par année') ?></span>
            </div>

            <ul class="abo-plan-features">
              <li class="is-ok">Diffusion de votre salle</li>
              <li class="is-ok">Demandes de réservation</li>
              <li class="is-ok"><?= e($plan['desc']) ?></li>
              <li class="<?= $key === 'mensuel' ? 'is-off' : 'is-ok' ?>">Priorité d'exposition</li>
              <li class="<?= $key === 'annuel' ? 'is-ok' : 'is-off' ?>">Présence prolongée sans interruption</li>
            </ul>

            <a class="btn btn-primary abo-plan-btn" href="<?= e(url('proprietaire/abonnement.php?plan=' . urlencode($key))) ?>">
            <?= $active ? 'Renouveler' : 'Choisir ce plan' ?>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php else: ?>
  <?php
  $plan = $plans[$selectedPlan];
  $theme = $planThemes[$selectedPlan] ?? ['tone' => 'abo-plan-silver', 'eyebrow' => 'Plan', 'accent' => 'Abonnement'];
  ?>
  <section class="abo-pay-wrap">
    <div class="abo-pay-summary <?= e($theme['tone']) ?>">
      <span class="abo-plan-eyebrow"><?= e($theme['eyebrow']) ?></span>
      <h3><?= e($plan['label']) ?></h3>
      <div class="abo-pay-price"><?= e(format_usd((float) $plan['montant'])) ?></div>
      <p><?= e($plan['desc']) ?></p>
      <ul class="abo-plan-features">
        <li>Activation immédiate après validation</li>
        <li>Compatible carte bancaire et mobile money</li>
        <li>Renouvellement simple à tout moment</li>
      </ul>
    </div>

    <div class="dash-panel abo-pay-panel">
      <div class="dash-panel-head">
        <h2>Paiement — <?= e($plan['label']) ?></h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('proprietaire/abonnement.php')) ?>">Retour</a>
      </div>

      <div class="abo-pay-body">
        <p class="abo-pay-lead">
          Sélectionnez votre moyen de paiement pour confirmer l'abonnement
          <strong><?= e($plan['label']) ?></strong> à <strong><?= e(format_usd((float) $plan['montant'])) ?></strong>.
        </p>

        <form method="post" class="pay-form">
          <input type="hidden" name="plan" value="<?= e($selectedPlan) ?>">

          <div class="pay-methods">
            <?php foreach ($methods as $key => $method): ?>
              <label class="pay-method">
                <input type="radio" name="moyen_paiement" value="<?= e($key) ?>" required <?= $key === 'mpesa' ? 'checked' : '' ?>>
                <span class="pay-method-card">
                  <strong><?= e($method['label']) ?></strong>
                  <small><?= e($method['desc']) ?></small>
                </span>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="form-group abo-ref-field">
            <label for="reference">Référence / numéro de transaction (optionnel)</label>
            <input type="text" id="reference" name="reference" placeholder="Ex: numéro M-Pesa, 4 derniers chiffres de carte...">
          </div>

          <div class="form-actions" style="margin-top:1rem">
            <button type="submit" class="btn btn-primary" data-confirm="Confirmer le paiement de <?= e(format_usd((float) $plan['montant'])) ?> ?">
              Payer <?= e(format_usd((float) $plan['montant'])) ?>
            </button>
            <a class="btn btn-ghost" href="<?= e(url('proprietaire/abonnement.php')) ?>">Annuler</a>
          </div>
        </form>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="abo-lower-grid">
  <?php if ($historyRows): ?>
    <div class="dash-panel abo-history-panel">
      <div class="dash-panel-head">
        <div>
          <span class="abo-section-kicker">Historique</span>
          <h2>Vos abonnements récents</h2>
        </div>
      </div>
      <div class="table-wrap abo-history-table">
        <table>
          <thead>
            <tr>
              <th>Plan</th>
              <th>Montant</th>
              <th>Paiement</th>
              <th>Période</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historyRows as $h): ?>
              <tr>
                <td>
                  <strong><?= e(subscription_plans()[$h['plan']]['label'] ?? $h['plan']) ?></strong>
                </td>
                <td><?= e(format_usd(subscription_amount_for_display($h['plan'], (float) $h['montant']))) ?></td>
                <td><?= !empty($h['moyen_paiement']) ? e(payment_method_label($h['moyen_paiement'])) : '—' ?></td>
                <td><?= e(format_datetime($h['date_debut'])) ?> → <?= e(format_datetime($h['date_fin'])) ?></td>
                <td><span class="badge"><?= e(abonnement_statut_label($h['statut'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <section class="abo-page-foot">
    <div class="abo-foot-intro">
      <span class="abo-section-kicker">Publication</span>
      <h3>Publiez vos salles sur la plateforme</h3>
      <p class="muted abo-intro">
        Choisissez un plan, puis sélectionnez votre moyen de paiement :
        carte bancaire, M-Pesa, Orange Money, Airtel Money ou autre.
      </p>
    </div>

    <?php if ($active): ?>
      <div class="abo-active-card">
        <div class="abo-active-top">
          <div>
            <p class="abo-active-kicker">Abonnement en cours</p>
            <h2><?= e(subscription_plans()[$active['plan']]['label'] ?? $active['plan']) ?></h2>
          </div>
          <span class="abo-active-price"><?= e(format_usd(subscription_amount_for_display($active['plan'], (float) $active['montant']))) ?></span>
        </div>

        <div class="abo-active-grid">
          <div class="abo-active-item">
            <span>Période</span>
            <strong>Du <?= e(format_datetime($active['date_debut'])) ?> au <?= e(format_datetime($active['date_fin'])) ?></strong>
          </div>
          <div class="abo-active-item">
            <span>Paiement</span>
            <strong><?= !empty($active['moyen_paiement']) ? e(payment_method_label($active['moyen_paiement'])) : 'Non renseigné' ?></strong>
          </div>
        </div>

        <div class="abo-active-actions">
          <a class="btn btn-primary btn-sm" href="<?= e(url('proprietaire/abonnement.php?plan=' . urlencode($active['plan']))) ?>">
            Renouveler cet abonnement
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="abo-empty-state">
        <strong>Aucun abonnement actif</strong>
        <p>Choisissez l'une des formules ci-dessus pour commencer à publier vos salles.</p>
      </div>
    <?php endif; ?>
  </section>
</section>

<style>
.abo-hero {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(260px, 360px);
  gap: 1.2rem;
  margin-bottom: 1.5rem;
}
.abo-hero-copy,
.abo-hero-side {
  border-radius: 24px;
  overflow: hidden;
}
.abo-hero-copy {
  padding: 1.65rem 1.75rem;
  background:
    radial-gradient(circle at top right, rgba(96, 165, 250, 0.28), transparent 28%),
    linear-gradient(135deg, #0f172a 0%, #172554 55%, #1d4ed8 100%);
  color: #fff;
  box-shadow: 0 22px 50px rgba(15, 23, 42, 0.18);
}
.abo-hero-kicker,
.abo-section-kicker,
.abo-plan-eyebrow,
.abo-active-kicker,
.abo-hero-side-label {
  display: inline-block;
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.abo-hero-kicker {
  margin-bottom: 0.7rem;
  color: rgba(255, 255, 255, 0.72);
}
.abo-hero-copy h2 {
  margin: 0;
  font-size: clamp(1.7rem, 3vw, 2.5rem);
  line-height: 1.1;
  color: #fff;
}
.abo-hero-copy p {
  margin: 0.9rem 0 0;
  max-width: 40rem;
  color: rgba(255, 255, 255, 0.78);
  line-height: 1.7;
}
.abo-hero-side {
  padding: 1.35rem 1.25rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  border: 1px solid #dbeafe;
  box-shadow: 0 12px 32px rgba(59, 130, 246, 0.08);
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.abo-hero-side-label {
  color: #2563eb;
  margin-bottom: 0.55rem;
}
.abo-hero-side strong {
  font-size: 1.35rem;
  color: #111827;
}
.abo-hero-side-price {
  margin: 0.45rem 0 0.35rem;
  font-size: 1.45rem;
  font-weight: 800;
  color: #1d4ed8;
}
.abo-hero-side small {
  color: #6b7280;
  line-height: 1.5;
}
.abo-section {
  margin-bottom: 1.5rem;
}
.abo-pricing-showcase {
  overflow: visible;
}
.abo-section-head {
  margin-bottom: 1rem;
}
.abo-section-kicker {
  color: #2563eb;
  margin-bottom: 0.3rem;
}
.abo-section-head h3,
.abo-foot-intro h3 {
  margin: 0;
  font-size: 1.45rem;
  color: #111827;
}
.abo-plan-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.25rem;
  align-items: stretch;
}
.abo-plan-card {
  position: relative;
  min-height: 510px;
  border-radius: 22px;
  border: 1px solid #dbe2f1;
  background: #fff;
  box-shadow: 0 16px 32px rgba(15, 23, 42, 0.09);
  overflow: hidden;
  color: #2d5bff;
}
.abo-plan-card.is-featured {
  min-height: 540px;
  transform: translateY(-10px);
  box-shadow: 0 22px 48px rgba(37, 99, 235, 0.18);
}
.abo-plan-silver { color: #475569; }
.abo-plan-blue { color: #2563eb; }
.abo-plan-gold { color: #d97706; }
.abo-plan-cap {
  position: relative;
  padding: 1.25rem 1rem 4.5rem;
  text-align: center;
  color: #fff;
  background: linear-gradient(135deg, #2948ff 0%, #5a73ff 100%);
}
.abo-plan-cap::after {
  content: "";
  position: absolute;
  left: -10%;
  right: -10%;
  bottom: -42px;
  height: 90px;
  background: #fff;
  border-radius: 0 0 50% 50% / 0 0 100% 100%;
}
.abo-plan-card h3 {
  position: relative;
  z-index: 1;
  margin: 0.35rem 0 0;
  font-size: 1.55rem;
  color: #fff;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.abo-plan-eyebrow {
  position: relative;
  z-index: 1;
  color: rgba(255, 255, 255, 0.86);
}
.abo-plan-body {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  min-height: calc(100% - 128px);
  padding: 0 1.25rem 1.25rem;
}
.abo-plan-price {
  margin: -0.15rem 0 0.8rem;
  text-align: center;
}
.abo-plan-price strong {
  display: block;
  font-size: 3rem;
  line-height: 1;
  color: #2d5bff;
}
.abo-plan-price span {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #5b6474;
  text-transform: uppercase;
}
.abo-plan-features {
  list-style: none;
  margin: 0 0 1.35rem;
  padding: 0;
  display: grid;
  gap: 0.8rem;
}
.abo-plan-features li {
  position: relative;
  padding-left: 1.6rem;
  color: #4b5563;
  font-size: 0.9rem;
  line-height: 1.45;
}
.abo-plan-features li::before {
  position: absolute;
  left: 0;
  top: 0.05rem;
  font-weight: 900;
}
.abo-plan-features li.is-ok::before {
  content: "✓";
  color: #38a169;
}
.abo-plan-features li.is-off::before {
  content: "×";
  color: #ef4444;
}
.abo-plan-btn {
  width: 100%;
  justify-content: center;
  margin-top: auto;
  border-radius: 12px;
  min-height: 3rem;
  background: linear-gradient(135deg, #2948ff 0%, #5a73ff 100%);
  border-color: #2948ff;
}
.abo-pay-wrap {
  display: grid;
  grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.abo-pay-summary {
  padding: 1.35rem;
  border-radius: 24px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  border: 1px solid #e5e7eb;
  box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
}
.abo-pay-summary h3 {
  margin: 0.2rem 0 0.65rem;
  font-size: 1.45rem;
  color: #0f172a;
}
.abo-pay-price {
  font-size: 2rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.8rem;
}
.abo-pay-summary p {
  margin: 0 0 1rem;
  color: #475569;
  line-height: 1.65;
}
.abo-pay-panel {
  overflow: hidden;
}
.abo-pay-body {
  padding: 1.2rem 1.25rem 1.25rem;
}
.abo-pay-lead {
  margin: 0 0 1rem;
  color: #475569;
  line-height: 1.7;
}
.abo-ref-field {
  margin-top: 1rem;
  max-width: 420px;
}
.abo-lower-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}
.abo-history-panel,
.abo-page-foot {
  border-radius: 22px;
  overflow: hidden;
}
.abo-history-panel {
  order: 2;
}
.abo-page-foot {
  order: 1;
}
.abo-history-panel h2 {
  margin: 0.2rem 0 0;
}
.abo-history-table table td strong {
  color: #111827;
}
.abo-page-foot {
  padding: 1.25rem;
  border: 1px solid #e5e7eb;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
}
.abo-foot-intro {
  margin-bottom: 1rem;
}
.abo-intro {
  margin: 0.45rem 0 0;
  line-height: 1.7;
}
.abo-active-card {
  margin-bottom: 0;
  padding: 1.3rem;
  border-radius: 20px;
  background:
    radial-gradient(circle at top right, rgba(96, 165, 250, 0.22), transparent 26%),
    linear-gradient(135deg, #0f172a 0%, #172554 55%, #1d4ed8 100%);
  color: #fff;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
}
.abo-active-top {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  margin-bottom: 1rem;
}
.abo-active-kicker {
  margin: 0 0 0.35rem;
  color: rgba(255, 255, 255, 0.75);
}
.abo-active-top h2 {
  margin: 0;
  font-size: 1.55rem;
  color: #fff;
}
.abo-active-price {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.6rem 0.95rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.16);
  font-size: 1rem;
  font-weight: 700;
  white-space: nowrap;
}
.abo-active-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.85rem;
}
.abo-active-item {
  padding: 0.95rem 1rem;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.12);
}
.abo-active-item span {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.72);
}
.abo-active-item strong {
  color: #fff;
  line-height: 1.55;
}
.abo-active-actions {
  margin-top: 1rem;
}
.abo-active-actions .btn {
  background: #fff;
  color: #1d4ed8;
  border-color: #fff;
}
.abo-active-actions .btn:hover {
  background: #eff6ff;
  border-color: #eff6ff;
}
.abo-empty-state {
  padding: 1rem 1.05rem;
  border-radius: 16px;
  background: #eff6ff;
  color: #1e3a8a;
}
.abo-empty-state strong {
  display: block;
  margin-bottom: 0.35rem;
}
.abo-empty-state p {
  margin: 0;
  line-height: 1.6;
}
.pay-methods {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
}
.pay-method {
  display: block;
  cursor: pointer;
}
.pay-method input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.pay-method-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 1rem 1rem;
  border: 1.5px solid #dbe4f0;
  border-radius: 16px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  min-height: 96px;
  transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
.pay-method-card strong {
  color: #111827;
  font-size: 0.98rem;
}
.pay-method-card small {
  color: #6b7280;
  font-size: 0.82rem;
  line-height: 1.45;
}
.pay-method input:checked + .pay-method-card {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
  transform: translateY(-1px);
}
.pay-method:hover .pay-method-card {
  border-color: #93c5fd;
  transform: translateY(-1px);
}
@media (max-width: 1100px) {
  .abo-pay-wrap,
  .abo-hero {
    grid-template-columns: 1fr;
  }
  .abo-plan-grid {
    grid-template-columns: 1fr;
    max-width: 430px;
    margin: 0 auto;
  }
}
@media (max-width: 768px) {
  .abo-active-top,
  .abo-plan-top {
    flex-direction: column;
  }
  .abo-hero-copy,
  .abo-hero-side,
  .abo-plan-card,
  .abo-page-foot,
  .abo-pay-summary {
    border-radius: 20px;
  }
  .abo-plan-card,
  .abo-plan-card.is-featured {
    min-height: 0;
    transform: none;
  }
}
</style>

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>

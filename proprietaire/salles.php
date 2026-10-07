<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();
ensure_salle_devise_schema();

$userId = (int) current_user()['id'];
$hasAbo = has_active_subscription();
$edit = null;
$errors = [];

if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM salles WHERE id = ? AND proprietaire_id = ?');
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $edit = $stmt->fetch() ?: null;
}

if (isset($_POST['toggle_id'])) {
    $tid = (int) $_POST['toggle_id'];
    $check = db()->prepare('SELECT id FROM salles WHERE id = ? AND proprietaire_id = ?');
    $check->execute([$tid, $userId]);
    if ($check->fetch()) {
        db()->prepare(
            "UPDATE salles SET statut = IF(statut = 'active', 'inactive', 'active') WHERE id = ?"
        )->execute([$tid]);
        flash('success', 'Statut de la salle mis à jour.');
    }
    redirect('proprietaire/salles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_salle'])) {
    if (!$hasAbo) {
        $errors[] = 'Un abonnement actif est requis pour publier une salle.';
    }

    $id = (int) ($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $capacite = (int) ($_POST['capacite'] ?? 0);
    $ville = trim($_POST['ville'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $equipements = trim($_POST['equipements'] ?? '');
    $tarif = (float) str_replace([' ', ','], ['', '.'], $_POST['tarif_jour'] ?? '0');
    $devise = strtoupper(trim($_POST['devise'] ?? 'FC')) === 'USD' ? 'USD' : 'FC';
    $currentImage = trim($_POST['image_actuelle'] ?? '');

    if ($id > 0) {
        $own = db()->prepare('SELECT id FROM salles WHERE id = ? AND proprietaire_id = ?');
        $own->execute([$id, $userId]);
        if (!$own->fetch()) {
            $errors[] = 'Salle introuvable.';
        }
    }

    if ($nom === '' || $ville === '' || $capacite < 1) {
        $errors[] = 'Nom, ville et capacité (> 0) sont obligatoires.';
    }

    $imagePath = $currentImage !== '' ? $currentImage : null;
    $upload = upload_salle_photo($_FILES['photo'] ?? [], $currentImage !== '' ? $currentImage : null);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    } elseif ($upload['path']) {
        $imagePath = $upload['path'];
    }

    if (!$errors) {
        if ($id > 0) {
            $sql = "UPDATE salles SET nom=?, description=?, capacite=?, ville=?, commune=?, adresse=?, equipements=?, tarif_jour=?, devise=?, image=? WHERE id=? AND proprietaire_id=?";
            db()->prepare($sql)->execute([
                $nom, $description ?: null, $capacite, $ville,
                $commune ?: null, $adresse ?: null, $equipements ?: null, $tarif, $devise,
                $imagePath, $id, $userId
            ]);
            flash('success', 'Salle mise à jour.');
        } else {
            $sql = "INSERT INTO salles (nom, description, capacite, ville, commune, adresse, equipements, tarif_jour, devise, image, statut, proprietaire_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";
            db()->prepare($sql)->execute([
                $nom, $description ?: null, $capacite, $ville,
                $commune ?: null, $adresse ?: null, $equipements ?: null, $tarif, $devise,
                $imagePath, $userId
            ]);
            flash('success', 'Salle publiée dans le catalogue.');
        }
        redirect('proprietaire/salles.php');
    }

    $edit = [
        'id' => $id,
        'nom' => $nom,
        'description' => $description,
        'capacite' => $capacite,
        'ville' => $ville,
        'commune' => $commune,
        'adresse' => $adresse,
        'equipements' => $equipements,
        'tarif_jour' => $tarif,
        'devise' => $devise,
        'image' => $imagePath,
    ];
}

$stmt = db()->prepare('SELECT * FROM salles WHERE proprietaire_id = ? ORDER BY statut, ville, nom');
$stmt->execute([$userId]);
$salles = $stmt->fetchAll();

$pageTitle = 'Mes salles';
$propPage = 'salles';
require __DIR__ . '/../includes/proprietaire-header.php';

$previewSrc = !empty($edit['image']) ? salle_image($edit) : '';
?>

<?php if (!$hasAbo): ?>
  <div class="alert alert-warning" style="margin-bottom:1.25rem">
    Abonnement requis pour ajouter des salles au catalogue.
    <a href="<?= e(url('proprietaire/abonnement.php')) ?>">Souscrire →</a>
  </div>
<?php endif; ?>

<?php if ($hasAbo): ?>
<div class="dash-panel" style="margin-bottom:1.25rem">
  <div class="dash-panel-head">
    <h2><?= $edit ? 'Modifier la salle' : 'Nouvelle salle' ?></h2>
  </div>
  <div class="form-panel wide">
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="save_salle" value="1">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <input type="hidden" name="image_actuelle" value="<?= e($edit['image'] ?? '') ?>">

      <div class="form-group">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" value="<?= e($edit['nom'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"><?= e($edit['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="capacite">Capacité</label>
          <input type="number" id="capacite" name="capacite" min="1" value="<?= e((string) ($edit['capacite'] ?? '50')) ?>" required>
        </div>
        <div class="form-group">
          <label for="devise">Devise</label>
          <select id="devise" name="devise" required>
            <?php $deviseEdit = ($edit['devise'] ?? 'FC') === 'USD' ? 'USD' : 'FC'; ?>
            <option value="FC" <?= $deviseEdit === 'FC' ? 'selected' : '' ?>>Franc congolais (FC)</option>
            <option value="USD" <?= $deviseEdit === 'USD' ? 'selected' : '' ?>>Dollar américain (USD)</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="tarif_jour">Tarif / jour</label>
        <input type="number" id="tarif_jour" name="tarif_jour" min="0" step="any" value="<?= e((string) ($edit['tarif_jour'] ?? '0')) ?>" required>
        <small class="muted">Indiquez le montant dans la devise choisie ci-dessus.</small>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="ville">Ville</label>
          <input type="text" id="ville" name="ville" value="<?= e($edit['ville'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="commune">Commune</label>
          <input type="text" id="commune" name="commune" value="<?= e($edit['commune'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label for="adresse">Adresse</label>
        <input type="text" id="adresse" name="adresse" value="<?= e($edit['adresse'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="equipements">Équipements</label>
        <input type="text" id="equipements" name="equipements" value="<?= e($edit['equipements'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="photo">Photo</label>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
        <img src="<?= e($previewSrc) ?>" alt="Aperçu" class="photo-preview" data-photo-preview <?= $previewSrc === '' ? 'hidden' : '' ?>>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit ? 'Enregistrer' : 'Publier' ?></button>
        <?php if ($edit): ?>
          <a class="btn btn-ghost" href="<?= e(url('proprietaire/salles.php')) ?>">Annuler</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="dash-panel">
  <div class="dash-panel-head">
    <h2>Mes salles (<?= count($salles) ?>)</h2>
  </div>
  <?php if (!$salles): ?>
    <p class="dash-empty">Aucune salle publiée.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nom</th>
            <th>Localisation</th>
            <th>Capacité</th>
            <th>Tarif</th>
            <th>Visibilité</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($salles as $s): ?>
            <?php $visible = $hasAbo && $s['statut'] === 'active'; ?>
            <tr>
              <td><strong><?= e($s['nom']) ?></strong></td>
              <td><?= e($s['ville']) ?><?= $s['commune'] ? ' · ' . e($s['commune']) : '' ?></td>
              <td><?= (int) $s['capacite'] ?></td>
              <td><?= e(format_salle_tarif($s)) ?></td>
              <td><span class="badge"><?= $visible ? 'Catalogue' : 'Masquée' ?></span></td>
              <td><span class="badge"><?= e(statut_label($s['statut'])) ?></span></td>
              <td class="actions">
                <?php if ($hasAbo): ?>
                  <a class="btn btn-ghost btn-sm" href="<?= e(url('proprietaire/salles.php?edit=' . (int) $s['id'])) ?>">Modifier</a>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="toggle_id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"><?= $s['statut'] === 'active' ? 'Désactiver' : 'Activer' ?></button>
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

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>

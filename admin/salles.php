<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_salle_devise_schema();

$edit = null;
$errors = [];
$statutFilter = $_GET['statut'] ?? '';

if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM salles WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

if (isset($_POST['toggle_id'])) {
    $tid = (int) $_POST['toggle_id'];
    db()->prepare(
        "UPDATE salles SET statut = IF(statut = 'active', 'inactive', 'active') WHERE id = ?"
    )->execute([$tid]);
    flash('success', 'Statut de la salle mis à jour.');
    redirect('admin/salles.php' . ($statutFilter !== '' ? '?statut=' . urlencode($statutFilter) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_salle'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $capacite = (int) ($_POST['capacite'] ?? 0);
    $ville = trim($_POST['ville'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $equipements = trim($_POST['equipements'] ?? '');
    $tarif = (float) str_replace([' ', ','], ['', '.'], $_POST['tarif_jour'] ?? '0');
    $currentImage = trim($_POST['image_actuelle'] ?? '');

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
            $sql = "UPDATE salles SET nom=?, description=?, capacite=?, ville=?, commune=?, adresse=?, equipements=?, tarif_jour=?, image=? WHERE id=?";
            db()->prepare($sql)->execute([
                $nom, $description ?: null, $capacite, $ville,
                $commune ?: null, $adresse ?: null, $equipements ?: null, $tarif,
                $imagePath, $id
            ]);
            flash('success', 'Salle mise à jour.');
        } else {
            $sql = "INSERT INTO salles (nom, description, capacite, ville, commune, adresse, equipements, tarif_jour, image, statut)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            db()->prepare($sql)->execute([
                $nom, $description ?: null, $capacite, $ville,
                $commune ?: null, $adresse ?: null, $equipements ?: null, $tarif,
                $imagePath
            ]);
            flash('success', 'Salle créée.');
        }
        redirect('admin/salles.php');
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
        'image' => $imagePath,
    ];
}

$sql = 'SELECT * FROM salles';
$params = [];
if (in_array($statutFilter, ['active', 'inactive'], true)) {
    $sql .= ' WHERE statut = ?';
    $params[] = $statutFilter;
}
$sql .= ' ORDER BY statut ASC, ville ASC, nom ASC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$salles = $stmt->fetchAll();

$counts = ['all' => 0, 'active' => 0, 'inactive' => 0];
foreach (db()->query('SELECT statut, COUNT(*) AS n FROM salles GROUP BY statut')->fetchAll() as $row) {
    $counts[$row['statut']] = (int) $row['n'];
    $counts['all'] += (int) $row['n'];
}

$showForm = $edit !== null || $errors !== [];

$pageTitle = 'Salles';
$adminPage = 'salles';
require __DIR__ . '/../includes/admin-header.php';

$previewSrc = '';
if (!empty($edit['image'])) {
    $previewSrc = salle_image($edit);
}
?>

<section class="salles-page">
  <div class="dash-stats salles-stats">
    <a class="dash-stat dash-stat-blue <?= $statutFilter === '' ? 'is-selected' : '' ?>" href="<?= e(url('admin/salles.php')) ?>">
      <div class="dash-stat-label">Tous</div>
      <div class="dash-stat-value"><?= (int) $counts['all'] ?></div>
    </a>
    <a class="dash-stat dash-stat-green <?= $statutFilter === 'active' ? 'is-selected' : '' ?>" href="<?= e(url('admin/salles.php?statut=active')) ?>">
      <div class="dash-stat-label">Actives</div>
      <div class="dash-stat-value"><?= (int) $counts['active'] ?></div>
    </a>
    <a class="dash-stat dash-stat-amber <?= $statutFilter === 'inactive' ? 'is-selected' : '' ?>" href="<?= e(url('admin/salles.php?statut=inactive')) ?>">
      <div class="dash-stat-label">Inactives</div>
      <div class="dash-stat-value"><?= (int) $counts['inactive'] ?></div>
    </a>
  </div>

  <div class="users-toolbar">
    <div>
      <h2 class="users-title">Catalogue des salles</h2>
      <p class="users-sub"><?= count($salles) ?> salle<?= count($salles) > 1 ? 's' : '' ?> affichée<?= count($salles) > 1 ? 's' : '' ?></p>
    </div>
    <?php if (!$edit): ?>
      <button type="button" class="btn btn-primary" data-salles-toggle-form aria-expanded="<?= $showForm ? 'true' : 'false' ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Nouvelle salle
      </button>
    <?php endif; ?>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="salles-form-panel <?= $showForm ? 'is-open' : '' ?>" data-salles-form>
    <div class="users-create-head">
      <h3><?= $edit ? 'Modifier la salle' : 'Nouvelle salle' ?></h3>
      <p><?= $edit ? 'Mettez à jour les informations de l’espace.' : 'Ajoutez un nouvel espace au catalogue.' ?></p>
    </div>
    <form method="post" enctype="multipart/form-data" class="salles-form">
      <input type="hidden" name="save_salle" value="1">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <input type="hidden" name="image_actuelle" value="<?= e($edit['image'] ?? '') ?>">

      <div class="salles-form-grid">
        <div class="form-group salles-span-2">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" value="<?= e($edit['nom'] ?? '') ?>" required placeholder="Ex. Salle Lumumba">
        </div>
        <div class="form-group">
          <label for="capacite">Capacité</label>
          <input type="number" id="capacite" name="capacite" min="1" value="<?= e((string) ($edit['capacite'] ?? '50')) ?>" required>
        </div>
        <div class="form-group">
          <label for="tarif_jour">Tarif / jour (FC)</label>
          <input type="number" id="tarif_jour" name="tarif_jour" min="0" step="1000" value="<?= e((string) ($edit['tarif_jour'] ?? '0')) ?>">
        </div>
        <div class="form-group">
          <label for="ville">Ville</label>
          <input type="text" id="ville" name="ville" value="<?= e($edit['ville'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="commune">Commune</label>
          <input type="text" id="commune" name="commune" value="<?= e($edit['commune'] ?? '') ?>">
        </div>
        <div class="form-group salles-span-2">
          <label for="adresse">Adresse</label>
          <input type="text" id="adresse" name="adresse" value="<?= e($edit['adresse'] ?? '') ?>">
        </div>
        <div class="form-group salles-span-2">
          <label for="equipements">Équipements</label>
          <input type="text" id="equipements" name="equipements" value="<?= e($edit['equipements'] ?? '') ?>" placeholder="Sono, projecteur, climatisation…">
        </div>
        <div class="form-group salles-span-full">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group salles-span-full">
          <label for="photo">Photo de la salle</label>
          <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
          <small class="muted">JPG, PNG ou WEBP — max 3 Mo. Laissez vide pour conserver la photo actuelle.</small>
          <div class="photo-preview-wrap">
            <img
              src="<?= e($previewSrc) ?>"
              alt="Aperçu"
              class="photo-preview"
              data-photo-preview
              <?= $previewSrc === '' ? 'hidden' : '' ?>
            >
          </div>
        </div>
      </div>

      <div class="users-create-actions">
        <?php if ($edit): ?>
          <a class="btn btn-ghost" href="<?= e(url('admin/salles.php')) ?>">Annuler</a>
        <?php else: ?>
          <button type="button" class="btn btn-ghost" data-salles-toggle-form>Annuler</button>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><?= $edit ? 'Enregistrer' : 'Ajouter la salle' ?></button>
      </div>
    </form>
  </div>

  <div class="users-panel">
    <?php if (!$salles): ?>
      <div class="users-empty">
        <strong>Aucune salle</strong>
        <p>Aucune salle ne correspond à ce filtre.</p>
      </div>
    <?php else: ?>
      <div class="salles-table-head" aria-hidden="true">
        <span>Salle</span>
        <span>Localisation</span>
        <span>Capacité</span>
        <span>Tarif / jour</span>
        <span>Statut</span>
        <span>Actions</span>
      </div>

      <ul class="salles-list">
        <?php foreach ($salles as $s): ?>
          <?php $isActive = ($s['statut'] ?? '') === 'active'; ?>
          <li class="salles-row">
            <div class="salles-identity">
              <img class="salles-thumb" src="<?= e(salle_image($s)) ?>" alt="<?= e($s['nom']) ?>" width="72" height="54">
              <div class="users-id-text">
                <strong><?= e($s['nom']) ?></strong>
                <?php if (!empty($s['equipements'])): ?>
                  <?php
                    $eq = (string) $s['equipements'];
                    if (function_exists('mb_strlen') && mb_strlen($eq) > 48) {
                        $eq = mb_substr($eq, 0, 47) . '…';
                    } elseif (strlen($eq) > 48) {
                        $eq = substr($eq, 0, 47) . '…';
                    }
                  ?>
                  <span><?= e($eq) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="users-cell users-meta" data-label="Localisation">
              <?= e($s['ville']) ?><?= !empty($s['commune']) ? ' · ' . e($s['commune']) : '' ?>
            </div>

            <div class="users-cell users-meta" data-label="Capacité">
              <?= (int) $s['capacite'] ?> places
            </div>

            <div class="users-cell salles-tarif" data-label="Tarif">
              <?= e(format_salle_tarif($s)) ?>
            </div>

            <div class="users-cell" data-label="Statut">
              <span class="users-pill <?= $isActive ? 'is-ok' : 'is-off' ?>">
                <?= e(statut_label($s['statut'])) ?>
              </span>
            </div>

            <div class="users-actions" data-label="Actions">
              <a class="users-icon-btn" href="<?= e(url('admin/salles.php?edit=' . (int) $s['id'])) ?>" title="Modifier">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                <span>Modifier</span>
              </a>
              <form method="post">
                <input type="hidden" name="toggle_id" value="<?= (int) $s['id'] ?>">
                <button
                  type="submit"
                  class="users-icon-btn <?= $isActive ? 'is-danger' : '' ?>"
                  title="<?= $isActive ? 'Désactiver' : 'Activer' ?>"
                  data-confirm="<?= $isActive ? 'Désactiver cette salle ?' : 'Activer cette salle ?' ?>"
                >
                  <?php if ($isActive): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                    <span>Désactiver</span>
                  <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"/></svg>
                    <span>Activer</span>
                  <?php endif; ?>
                </button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<script>
(function () {
  const panel = document.querySelector('[data-salles-form]');
  const toggles = document.querySelectorAll('[data-salles-toggle-form]');
  if (!panel || !toggles.length) return;
  toggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      const open = panel.classList.toggle('is-open');
      toggles.forEach((b) => b.setAttribute('aria-expanded', open ? 'true' : 'false'));
    });
  });
})();
</script>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>

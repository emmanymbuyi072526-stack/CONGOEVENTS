<?php
require_once __DIR__ . '/includes/functions.php';
require_client();

$salleId = (int) ($_GET['salle_id'] ?? $_POST['salle_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM salles WHERE id = ?');
$stmt->execute([$salleId]);
$salle = $stmt->fetch();

if (!$salle || !salle_is_public($salle)) {
    flash('error', 'Salle introuvable ou inactive.');
    redirect('salles.php');
}

$errors = [];
$titre = $type = $description = '';
$debut = $fin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre_evenement'] ?? '');
    $type = $_POST['type_evenement'] ?? 'conference';
    $debutRaw = $_POST['date_debut'] ?? '';
    $finRaw = $_POST['date_fin'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $allowedTypes = ['conference', 'concert', 'exposition', 'atelier', 'ceremonie', 'reunion', 'autre'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'autre';
    }

    if ($titre === '') {
        $errors[] = 'Le titre de l\'événement est requis.';
    }

    $debutDt = DateTime::createFromFormat('Y-m-d\TH:i', $debutRaw);
    $finDt = DateTime::createFromFormat('Y-m-d\TH:i', $finRaw);
    $debut = $debutDt ? $debutDt->format('Y-m-d H:i:s') : '';
    $fin = $finDt ? $finDt->format('Y-m-d H:i:s') : '';

    if (!$debutDt || !$finDt) {
        $errors[] = 'Dates invalides.';
    } elseif ($finDt <= $debutDt) {
        $errors[] = 'La date de fin doit être après la date de début.';
    } elseif ($debutDt < new DateTime('now')) {
        $errors[] = 'La date de début doit être dans le futur.';
    }

    if (!$errors && has_conflict($salleId, $debut, $fin)) {
        $errors[] = 'Cette salle a déjà une réservation confirmée sur ce créneau.';
    }

    if (!$errors) {
        $ins = db()->prepare(
            "INSERT INTO reservations
            (utilisateur_id, salle_id, titre_evenement, type_evenement, date_debut, date_fin, description, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')"
        );
        $ins->execute([
            current_user()['id'],
            $salleId,
            $titre,
            $type,
            $debut,
            $fin,
            $description ?: null,
        ]);
        $reservationId = (int) db()->lastInsertId();
        notify_new_reservation_request($reservationId);
        flash('success', 'Demande de réservation envoyée. Elle est en attente de validation.');
        redirect('mes-reservations.php');
    }

    $debut = $debutRaw;
    $fin = $finRaw;
}

$pageTitle = 'Réserver';
$currentPage = 'salles';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="form-panel wide">
    <h1>Demande de réservation</h1>
    <p class="lead">Salle : <strong><?= e($salle['nom']) ?></strong> — <?= e($salle['ville']) ?></p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
      <input type="hidden" name="salle_id" value="<?= (int) $salle['id'] ?>">

      <div class="form-group">
        <label for="titre_evenement">Titre de l'événement</label>
        <input type="text" id="titre_evenement" name="titre_evenement" value="<?= e($titre) ?>" required>
      </div>

      <div class="form-group">
        <label for="type_evenement">Type d'événement</label>
        <select id="type_evenement" name="type_evenement" required>
          <?php
          $types = [
              'conference' => 'Conférence',
              'concert' => 'Concert',
              'exposition' => 'Exposition',
              'atelier' => 'Atelier',
              'ceremonie' => 'Cérémonie',
              'reunion' => 'Réunion',
              'autre' => 'Autre',
          ];
          foreach ($types as $val => $label):
          ?>
            <option value="<?= $val ?>" <?= $type === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="date_debut">Début</label>
          <input type="datetime-local" id="date_debut" name="date_debut" value="<?= e($debut) ?>" required>
        </div>
        <div class="form-group">
          <label for="date_fin">Fin</label>
          <input type="datetime-local" id="date_fin" name="date_fin" value="<?= e($fin) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" placeholder="Précisez le programme, le nombre d'invités, etc."><?= e($description) ?></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Envoyer la demande</button>
        <a class="btn btn-ghost" href="<?= e(url('salle.php?id=' . (int) $salle['id'])) ?>">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

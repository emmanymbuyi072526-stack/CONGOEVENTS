<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect_after_login();
}

$errors = [];
$nom = $email = $telephone = '';
$accountType = ($_GET['type'] ?? '') === 'proprietaire' ? 'proprietaire' : 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $accountType = $_POST['account_type'] ?? 'client';

    if ($nom === '' || mb_strlen($nom) < 2) {
        $errors[] = 'Le nom est requis (2 caractères minimum).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email invalide.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    }
    if ($password !== $password2) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    if (!in_array($accountType, ['client', 'proprietaire'], true)) {
        $accountType = 'client';
    }

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM utilisateurs WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'Cet email est déjà utilisé.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare(
                'INSERT INTO utilisateurs (nom, email, telephone, mot_de_passe, role)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nom, $email, $telephone ?: null, $hash, $accountType]);
            $id = (int) db()->lastInsertId();
            $_SESSION['user'] = [
                'id'    => $id,
                'nom'   => $nom,
                'email' => $email,
                'role'  => $accountType,
            ];
            flash('success', 'Compte créé avec succès. Bienvenue !');
            redirect_after_login();
        }
    }
}

$pageTitle = 'Inscription';
$currentPage = 'register';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="form-panel">
    <h1>Inscription</h1>
    <p class="lead">Créez un compte client pour réserver des salles, ou un compte propriétaire pour publier vos espaces. Utilisez votre adresse Gmail : les confirmations d’abonnement et de réservation y seront envoyées.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <div class="form-group">
        <label for="account_type">Type de compte</label>
        <select id="account_type" name="account_type" required>
          <option value="client" <?= $accountType === 'client' ? 'selected' : '' ?>>Client — réserver des salles</option>
          <option value="proprietaire" <?= $accountType === 'proprietaire' ? 'selected' : '' ?>>Propriétaire — publier mes salles</option>
        </select>
      </div>
      <div class="form-group">
        <label for="nom">Nom complet</label>
        <input type="text" id="nom" name="nom" value="<?= e($nom) ?>" required>
      </div>
      <div class="form-group">
        <label for="email">Adresse e-mail (Gmail recommandé)</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" placeholder="ex. vous@gmail.com">
        <small class="muted">Cet e-mail servira pour la connexion et pour recevoir les messages (abonnement, réservation).</small>
      </div>
      <div class="form-group">
        <label for="telephone">Téléphone</label>
        <input type="tel" id="telephone" name="telephone" value="<?= e($telephone) ?>" placeholder="+243...">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
          <label for="password2">Confirmation</label>
          <input type="password" id="password2" name="password2" required>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Créer mon compte</button>
        <a href="<?= e(url('login.php')) ?>">Déjà inscrit ?</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

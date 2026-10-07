<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect_after_login();
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email et mot de passe requis.';
    } else {
        $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user'] = [
                'id'    => (int) $user['id'],
                'nom'   => $user['nom'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            flash('success', 'Bienvenue, ' . $user['nom'] . ' !');
            redirect_after_login();
        }
        $errors[] = 'Identifiants incorrects.';
    }
}

$pageTitle = 'Connexion';
$currentPage = 'login';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="form-panel">
    <h1>Connexion</h1>
    <p class="lead">Connectez-vous en tant que client, propriétaire ou administrateur.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
      </div>
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Se connecter</button>
        <a href="<?= e(url('register.php')) ?>">Créer un compte</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

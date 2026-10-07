<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_subscription_schema();

$allowedRoles = ['admin', 'client', 'proprietaire'];
$roleFilter = $_GET['role'] ?? '';
$errors = [];
$showCreate = !empty($_POST['action']) && $_POST['action'] === 'create' && !empty($errors);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', $allowedRoles, true) ? $_POST['role'] : 'client';
        $showCreate = true;

        if ($nom === '' || mb_strlen($nom) < 2) {
            $errors[] = 'Le nom est requis (2 caractères minimum).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
        }

        if (!$errors) {
            $check = db()->prepare('SELECT id FROM utilisateurs WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'Cet email est déjà utilisé.';
            } else {
                db()->prepare(
                    'INSERT INTO utilisateurs (nom, email, telephone, mot_de_passe, role)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $nom,
                    $email,
                    $telephone !== '' ? $telephone : null,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                ]);
                flash('success', 'Utilisateur créé avec succès.');
                redirect('admin/utilisateurs.php' . ($roleFilter ? '?role=' . urlencode($roleFilter) : ''));
            }
        }
    }

    if ($action === 'update_role' && isset($_POST['user_id'], $_POST['role'])) {
        $uid = (int) $_POST['user_id'];
        $role = in_array($_POST['role'], $allowedRoles, true) ? $_POST['role'] : 'client';

        if ($uid === (int) current_user()['id'] && $role !== 'admin') {
            flash('error', 'Vous ne pouvez pas retirer votre propre rôle admin.');
        } else {
            db()->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')->execute([$role, $uid]);
            flash('success', 'Rôle mis à jour.');
        }
        redirect('admin/utilisateurs.php' . ($roleFilter ? '?role=' . urlencode($roleFilter) : ''));
    }

    if ($action === 'delete' && isset($_POST['user_id'])) {
        $uid = (int) $_POST['user_id'];
        if ($uid === (int) current_user()['id']) {
            flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        } else {
            db()->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$uid]);
            flash('success', 'Utilisateur supprimé.');
        }
        redirect('admin/utilisateurs.php' . ($roleFilter ? '?role=' . urlencode($roleFilter) : ''));
    }
}

$sql = 'SELECT u.*,
      (SELECT COUNT(*) FROM reservations r WHERE r.utilisateur_id = u.id) AS nb_reservations,
      (SELECT COUNT(*) FROM salles s WHERE s.proprietaire_id = u.id) AS nb_salles,
      (SELECT COUNT(*) FROM abonnements a WHERE a.utilisateur_id = u.id AND a.statut = \'actif\' AND a.date_fin >= NOW()) AS abo_actif
     FROM utilisateurs u';
$params = [];

if (in_array($roleFilter, $allowedRoles, true)) {
    $sql .= ' WHERE u.role = ?';
    $params[] = $roleFilter;
}
$sql .= ' ORDER BY u.date_creation DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$counts = ['all' => 0, 'admin' => 0, 'client' => 0, 'proprietaire' => 0];
foreach (db()->query('SELECT role, COUNT(*) AS n FROM utilisateurs GROUP BY role')->fetchAll() as $row) {
    $counts[$row['role']] = (int) $row['n'];
    $counts['all'] += (int) $row['n'];
}

$filters = [
    '' => ['label' => 'Tous', 'count' => $counts['all']],
    'client' => ['label' => 'Clients', 'count' => $counts['client']],
    'proprietaire' => ['label' => 'Propriétaires', 'count' => $counts['proprietaire']],
    'admin' => ['label' => 'Admins', 'count' => $counts['admin']],
];

function admin_user_initials(string $name): string
{
    $initials = '';
    foreach (preg_split('/\s+/', trim($name)) as $part) {
        if ($part === '') {
            continue;
        }
        $ch = function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($part, 0, 1))
            : strtoupper(substr($part, 0, 1));
        $initials .= $ch;
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials !== '' ? $initials : 'U';
}

$pageTitle = 'Utilisateurs';
$adminPage = 'utilisateurs';
require __DIR__ . '/../includes/admin-header.php';
?>

<section class="users-page">
  <div class="dash-stats users-stats">
    <a class="dash-stat dash-stat-blue <?= $roleFilter === '' ? 'is-selected' : '' ?>" href="<?= e(url('admin/utilisateurs.php')) ?>">
      <div class="dash-stat-label">Tous</div>
      <div class="dash-stat-value"><?= (int) $counts['all'] ?></div>
    </a>
    <a class="dash-stat dash-stat-green <?= $roleFilter === 'client' ? 'is-selected' : '' ?>" href="<?= e(url('admin/utilisateurs.php?role=client')) ?>">
      <div class="dash-stat-label">Clients</div>
      <div class="dash-stat-value"><?= (int) $counts['client'] ?></div>
    </a>
    <a class="dash-stat dash-stat-amber <?= $roleFilter === 'proprietaire' ? 'is-selected' : '' ?>" href="<?= e(url('admin/utilisateurs.php?role=proprietaire')) ?>">
      <div class="dash-stat-label">Propriétaires</div>
      <div class="dash-stat-value"><?= (int) $counts['proprietaire'] ?></div>
    </a>
    <a class="dash-stat dash-stat-red <?= $roleFilter === 'admin' ? 'is-selected' : '' ?>" href="<?= e(url('admin/utilisateurs.php?role=admin')) ?>">
      <div class="dash-stat-label">Admins</div>
      <div class="dash-stat-value"><?= (int) $counts['admin'] ?></div>
    </a>
  </div>

  <div class="users-toolbar">
    <div>
      <h2 class="users-title">Liste des utilisateurs</h2>
      <p class="users-sub"><?= count($users) ?> compte<?= count($users) > 1 ? 's' : '' ?> affiché<?= count($users) > 1 ? 's' : '' ?></p>
    </div>
    <button type="button" class="btn btn-primary" data-users-toggle-create aria-expanded="<?= $showCreate || $errors ? 'true' : 'false' ?>">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      Nouvel utilisateur
    </button>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="users-create <?= ($showCreate || $errors) ? 'is-open' : '' ?>" data-users-create>
    <div class="users-create-head">
      <h3>Créer un utilisateur</h3>
      <p>Le compte pourra se connecter immédiatement avec le mot de passe défini.</p>
    </div>
    <form method="post" class="users-create-form">
      <input type="hidden" name="action" value="create">
      <div class="users-create-grid">
        <div class="form-group">
          <label for="nom">Nom complet</label>
          <input type="text" id="nom" name="nom" required value="<?= e($_POST['nom'] ?? '') ?>" placeholder="Ex. Marie Kabila">
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="marie@email.cd">
        </div>
        <div class="form-group">
          <label for="telephone">Téléphone</label>
          <input type="tel" id="telephone" name="telephone" value="<?= e($_POST['telephone'] ?? '') ?>" placeholder="+243…">
        </div>
        <div class="form-group">
          <label for="role_create">Rôle</label>
          <select id="role_create" name="role" required>
            <option value="client" <?= ($_POST['role'] ?? 'client') === 'client' ? 'selected' : '' ?>>Client</option>
            <option value="proprietaire" <?= ($_POST['role'] ?? '') === 'proprietaire' ? 'selected' : '' ?>>Propriétaire</option>
            <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
          </select>
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" required minlength="6" placeholder="6 caractères min.">
        </div>
      </div>
      <div class="users-create-actions">
        <button type="button" class="btn btn-ghost" data-users-toggle-create>Annuler</button>
        <button type="submit" class="btn btn-primary">Créer le compte</button>
      </div>
    </form>
  </div>

  <div class="users-panel">
    <?php if (!$users): ?>
      <div class="users-empty">
        <strong>Aucun utilisateur</strong>
        <p>Aucun compte ne correspond à ce filtre.</p>
      </div>
    <?php else: ?>
      <div class="users-table-head" aria-hidden="true">
        <span>Utilisateur</span>
        <span>Rôle</span>
        <span>Activité</span>
        <span>Abonnement</span>
        <span>Inscription</span>
        <span>Actions</span>
      </div>

      <ul class="users-list">
        <?php foreach ($users as $u): ?>
          <?php
            $isSelf = (int) $u['id'] === (int) current_user()['id'];
            $roleClass = 'role-client';
            if ($u['role'] === 'admin') {
                $roleClass = 'role-admin';
            } elseif ($u['role'] === 'proprietaire') {
                $roleClass = 'role-owner';
            }
            $activity = '—';
            if ($u['role'] === 'client') {
                $n = (int) $u['nb_reservations'];
                $activity = $n . ' réservation' . ($n > 1 ? 's' : '');
            } elseif ($u['role'] === 'proprietaire') {
                $n = (int) $u['nb_salles'];
                $activity = $n . ' salle' . ($n > 1 ? 's' : '');
            }
          ?>
          <li class="users-row">
            <div class="users-identity">
              <div class="users-avatar <?= e($roleClass) ?>"><?= e(admin_user_initials($u['nom'])) ?></div>
              <div class="users-id-text">
                <strong><?= e($u['nom']) ?></strong>
                <span><?= e($u['email']) ?></span>
                <?php if (!empty($u['telephone'])): ?>
                  <span class="users-phone"><?= e($u['telephone']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="users-cell" data-label="Rôle">
              <span class="users-role <?= e($roleClass) ?>"><?= e(role_label($u['role'])) ?></span>
            </div>

            <div class="users-cell users-meta" data-label="Activité">
              <?= e($activity) ?>
            </div>

            <div class="users-cell" data-label="Abonnement">
              <?php if ($u['role'] === 'proprietaire'): ?>
                <?php if ((int) $u['abo_actif'] > 0): ?>
                  <span class="users-pill is-ok">Actif</span>
                <?php else: ?>
                  <span class="users-pill is-off">Aucun</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="users-dash">—</span>
              <?php endif; ?>
            </div>

            <div class="users-cell users-meta" data-label="Inscription">
              <?= e(format_datetime($u['date_creation'])) ?>
            </div>

            <div class="users-actions" data-label="Actions">
              <form method="post" class="users-role-form">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <label class="sr-only" for="role_<?= (int) $u['id'] ?>">Changer le rôle</label>
                <select id="role_<?= (int) $u['id'] ?>" name="role" onchange="this.form.submit()">
                  <?php foreach ($allowedRoles as $r): ?>
                    <option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>

              <div class="users-action-btns">
                <?php if ($u['role'] === 'proprietaire'): ?>
                  <a class="users-icon-btn" href="<?= e(url('admin/abonnements.php?user_id=' . (int) $u['id'])) ?>" title="Gérer l’abonnement">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    <span>Abo</span>
                  </a>
                <?php endif; ?>
                <?php if (!$isSelf): ?>
                  <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" class="users-icon-btn is-danger" title="Supprimer" data-confirm="Supprimer cet utilisateur ?">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                      <span>Suppr.</span>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<script>
(function () {
  const panel = document.querySelector('[data-users-create]');
  const toggles = document.querySelectorAll('[data-users-toggle-create]');
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

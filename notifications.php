<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_notifications_schema();

$user = current_user();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'read_all') {
        mark_notifications_read($userId);
        flash('success', 'Toutes les notifications ont été marquées comme lues.');
    } elseif ($action === 'read_one' && isset($_POST['id'])) {
        mark_notifications_read($userId, (int) $_POST['id']);
    }
    redirect('notifications.php');
}

$notifications = get_user_notifications($userId, 50);
$unread = unread_notifications_count($userId);

$pageTitle = 'Notifications';
$currentPage = 'notifications';
require __DIR__ . '/includes/header.php';
?>

<main class="section">
  <div class="container" style="max-width:820px">
    <div class="notif-page-head">
      <div>
        <h1>Notifications</h1>
        <p class="muted"><?= $unread > 0 ? $unread . ' non lue' . ($unread > 1 ? 's' : '') : 'Tout est à jour' ?></p>
      </div>
      <?php if ($unread > 0): ?>
        <form method="post">
          <input type="hidden" name="action" value="read_all">
          <button type="submit" class="btn btn-ghost btn-sm">Tout marquer comme lu</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$notifications): ?>
      <div class="notif-empty">
        <strong>Aucune notification</strong>
        <p>Les confirmations d’abonnement et de réservation apparaîtront ici.</p>
      </div>
    <?php else: ?>
      <ul class="notif-list">
        <?php foreach ($notifications as $n): ?>
          <li class="notif-item <?= (int) $n['lu'] === 0 ? 'is-unread' : '' ?>">
            <div class="notif-item-main">
              <strong><?= e($n['titre']) ?></strong>
              <p><?= nl2br(e($n['message'])) ?></p>
              <time><?= e(format_datetime($n['date_creation'])) ?></time>
            </div>
            <div class="notif-item-actions">
              <?php if (!empty($n['lien'])): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url($n['lien'])) ?>">Voir</a>
              <?php endif; ?>
              <?php if ((int) $n['lu'] === 0): ?>
                <form method="post">
                  <input type="hidden" name="action" value="read_one">
                  <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">Lu</button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</main>

<style>
.notif-page-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.notif-page-head h1 { margin: 0; font-size: 1.6rem; }
.notif-page-head p { margin: 0.25rem 0 0; }
.notif-empty {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 2rem 1.25rem;
  text-align: center;
}
.notif-empty p { color: #6b7280; margin: 0.35rem 0 0; }
.notif-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.75rem; }
.notif-item {
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  align-items: flex-start;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 1rem 1.15rem;
}
.notif-item.is-unread {
  border-color: #93c5fd;
  background: #f8fbff;
}
.notif-item-main { min-width: 0; flex: 1; }
.notif-item-main strong { display: block; margin-bottom: 0.35rem; }
.notif-item-main p { margin: 0; color: #374151; font-size: 0.95rem; line-height: 1.5; white-space: pre-wrap; }
.notif-item-main time { display: block; margin-top: 0.55rem; font-size: 0.8rem; color: #9ca3af; }
.notif-item-actions { display: flex; flex-direction: column; gap: 0.35rem; flex-shrink: 0; }
@media (max-width: 640px) {
  .notif-item { flex-direction: column; }
  .notif-item-actions { flex-direction: row; }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>

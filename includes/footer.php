</main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <div class="logo">
          <img class="logo-img" src="<?= e(asset(APP_LOGO)) ?>" alt="<?= e(APP_NAME) ?>" width="48" height="48">
          <span class="logo-text">
            <strong>Congo Events</strong>
          </span>
        </div>
        <p>La plateforme professionnelle de réservation de salles pour conférences et événements culturels en République Démocratique du Congo.</p>
      </div>
      <div>
        <h4>Navigation</h4>
        <a href="<?= e(url('salles.php')) ?>">Catalogue des salles</a>
        <a href="<?= e(url('register.php')) ?>">Créer un compte</a>
        <a href="<?= e(url('login.php')) ?>">Connexion</a>
        <a href="<?= e(url('register.php?type=proprietaire')) ?>">Publier ma salle</a>
      </div>
      <div>
        <h4>Couverture</h4>
        <p>Kinshasa · Lubumbashi<br>Kisangani · Matadi</p>
      </div>
    </div>
    <div class="container footer-bottom">
      <span>&copy; <?= date('Y') ?> Congo Events</span>
      <span>Événements culturels &amp; conférences</span>
    </div>
  </footer>

  <script src="<?= e(asset('js/app.js')) ?>?v=20260813"></script>
</body>
</html>

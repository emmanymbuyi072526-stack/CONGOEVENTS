<?php
/**
 * Installation ResaSalle RDC
 * Ouvrir une fois : http://localhost/focus/install.php
 * Puis supprimer ou protéger ce fichier.
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'resasalle_rdc';

$messages = [];
$ok = false;

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS utilisateurs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          nom VARCHAR(120) NOT NULL,
          email VARCHAR(180) NOT NULL UNIQUE,
          telephone VARCHAR(30) DEFAULT NULL,
          mot_de_passe VARCHAR(255) NOT NULL,
          role ENUM('admin', 'client', 'proprietaire') NOT NULL DEFAULT 'client',
          date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    // Migration depuis l'ancien schéma (organisateur → client)
    try {
        $pdo->exec("ALTER TABLE utilisateurs MODIFY role ENUM('admin','client','proprietaire','organisateur') NOT NULL DEFAULT 'client'");
        $pdo->exec("UPDATE utilisateurs SET role = 'client' WHERE role = 'organisateur'");
        $pdo->exec("ALTER TABLE utilisateurs MODIFY role ENUM('admin','client','proprietaire') NOT NULL DEFAULT 'client'");
        $messages[] = 'Rôles utilisateurs migrés (admin, client, propriétaire).';
    } catch (Throwable $e) {
        // Déjà à jour
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS abonnements (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          utilisateur_id INT UNSIGNED NOT NULL,
          plan ENUM('mensuel', 'trimestriel', 'annuel') NOT NULL,
          montant DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
          date_debut DATETIME NOT NULL,
          date_fin DATETIME NOT NULL,
          statut ENUM('actif', 'expire', 'en_attente', 'annule') NOT NULL DEFAULT 'en_attente',
          date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_abo_utilisateur FOREIGN KEY (utilisateur_id)
            REFERENCES utilisateurs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    try {
        $pdo->exec('CREATE INDEX idx_abonnements_user_statut ON abonnements (utilisateur_id, statut, date_fin)');
    } catch (Throwable $e) {
        // Index déjà présent
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS salles (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          nom VARCHAR(150) NOT NULL,
          description TEXT,
          capacite INT UNSIGNED NOT NULL DEFAULT 50,
          ville VARCHAR(100) NOT NULL,
          commune VARCHAR(100) DEFAULT NULL,
          adresse VARCHAR(255) DEFAULT NULL,
          equipements TEXT,
          tarif_jour DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
          image VARCHAR(255) DEFAULT NULL,
          statut ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
          proprietaire_id INT UNSIGNED DEFAULT NULL,
          date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    try {
        $col = $pdo->query("SHOW COLUMNS FROM salles LIKE 'proprietaire_id'")->fetch();
        if (!$col) {
            $pdo->exec('ALTER TABLE salles ADD COLUMN proprietaire_id INT UNSIGNED DEFAULT NULL AFTER statut');
            $messages[] = 'Colonne proprietaire_id ajoutée aux salles.';
        }
    } catch (Throwable $e) {
        // Ignorer
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservations (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          utilisateur_id INT UNSIGNED NOT NULL,
          salle_id INT UNSIGNED NOT NULL,
          titre_evenement VARCHAR(200) NOT NULL,
          type_evenement ENUM(
            'conference','concert','exposition','atelier','ceremonie','reunion','autre'
          ) NOT NULL DEFAULT 'conference',
          date_debut DATETIME NOT NULL,
          date_fin DATETIME NOT NULL,
          description TEXT,
          statut ENUM('en_attente', 'confirmee', 'refusee', 'annulee') NOT NULL DEFAULT 'en_attente',
          motif_refus TEXT DEFAULT NULL,
          date_demande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_res_utilisateur FOREIGN KEY (utilisateur_id)
            REFERENCES utilisateurs(id) ON DELETE CASCADE,
          CONSTRAINT fk_res_salle FOREIGN KEY (salle_id)
            REFERENCES salles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $hash = password_hash('Admin123!', PASSWORD_DEFAULT);

    $check = $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    if ((int) $check === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO utilisateurs (nom, email, telephone, mot_de_passe, role)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute(['Admin ResaSalle', 'admin@resasalle.cd', '+243810000001', $hash, 'admin']);
        $stmt->execute(['Marie Kabongo', 'marie.kabongo@email.cd', '+243970000002', $hash, 'client']);
        $stmt->execute(['Jean Mukendi', 'jean.mukendi@email.cd', '+243990000003', $hash, 'proprietaire']);
        $messages[] = 'Comptes de démonstration créés.';
    } else {
        $stmt = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE email IN (?, ?, ?)');
        $stmt->execute([$hash, 'admin@resasalle.cd', 'marie.kabongo@email.cd', 'jean.mukendi@email.cd']);
        $messages[] = 'Mots de passe des comptes démo mis à jour.';
    }

    // Compte propriétaire démo + abonnement actif
    $prop = $pdo->query("SELECT id FROM utilisateurs WHERE email = 'jean.mukendi@email.cd' LIMIT 1")->fetch();
    if (!$prop) {
        $pdo->prepare(
            "INSERT INTO utilisateurs (nom, email, telephone, mot_de_passe, role) VALUES (?, ?, ?, ?, 'proprietaire')"
        )->execute(['Jean Mukendi', 'jean.mukendi@email.cd', '+243990000003', $hash]);
        $propId = (int) $pdo->lastInsertId();
        $messages[] = 'Compte propriétaire démo créé.';
    } else {
        $propId = (int) $prop['id'];
        $pdo->prepare("UPDATE utilisateurs SET role = 'proprietaire' WHERE id = ?")->execute([$propId]);
    }

    $stmtAbo = $pdo->prepare('SELECT COUNT(*) FROM abonnements WHERE utilisateur_id = ?');
    $stmtAbo->execute([$propId]);
    if ((int) $stmtAbo->fetchColumn() === 0) {
        $debut = date('Y-m-d H:i:s');
        $fin = date('Y-m-d H:i:s', strtotime('+365 days'));
        $pdo->prepare(
            "INSERT INTO abonnements (utilisateur_id, plan, montant, date_debut, date_fin, statut)
             VALUES (?, 'annuel', 60, ?, ?, 'actif')"
        )->execute([$propId, $debut, $fin]);
        $messages[] = 'Abonnement démo actif pour jean.mukendi@email.cd.';
    }

    $sallesCount = (int) $pdo->query('SELECT COUNT(*) FROM salles')->fetchColumn();
    $images = [
        'https://images.unsplash.com/photo-1431540015161-0bf868a2d407?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04?auto=format&fit=crop&w=1200&q=80',
    ];

    if ($sallesCount === 0) {
        $salles = [
            ['Salle Lumumba', 'Grande salle polyvalente idéale pour conférences nationales et événements culturels majeurs.', 500, 'Kinshasa', 'Gombe', 'Avenue du Commerce, Gombe', 'Sonorisation, projecteur, scène, climatisation, Wi-Fi', 850000, $images[0]],
            ['Espace Culturel Ndjili', 'Salle intimiste pour ateliers, lectures et petites expositions.', 120, 'Kinshasa', 'Ndjili', 'Avenue de la Libération, Ndjili', 'Éclairage scénique, tables, chaises, microphones', 250000, $images[1]],
            ['Centre de Conférences Lubumbashi', 'Centre moderne pour séminaires et conférences académiques.', 300, 'Lubumbashi', 'Kampemba', 'Boulevard M\'siri, Kampemba', 'Vidéoprojecteur, traduction simultanée, parking, Wi-Fi', 600000, $images[2]],
            ['Auditorium Kisangani', 'Auditorium pour concerts, projections et cérémonies culturelles.', 400, 'Kisangani', 'Mangobo', 'Avenue du Fleuve, Mangobo', 'Scène, sonorisation, gradins, loges', 450000, $images[3]],
            ['Salle Héritage Matadi', 'Espace polyvalent pour réunions institutionnelles et événements locaux.', 80, 'Matadi', 'Ville', 'Avenue Métropole', 'Écran, sono portable, climatisation', 180000, $images[4]],
        ];
        $ins = $pdo->prepare(
            "INSERT INTO salles (nom, description, capacite, ville, commune, adresse, equipements, tarif_jour, image, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        foreach ($salles as $s) {
            $ins->execute($s);
        }
        $messages[] = '5 salles de démonstration insérées.';
    } else {
        // Mettre à jour les images manquantes
        $rows = $pdo->query('SELECT id, image FROM salles ORDER BY id')->fetchAll();
        $upd = $pdo->prepare('UPDATE salles SET image = ? WHERE id = ?');
        $i = 0;
        foreach ($rows as $row) {
            if (empty($row['image'])) {
                $upd->execute([$images[$i % count($images)], $row['id']]);
            }
            $i++;
        }
        $messages[] = 'Images des salles vérifiées / mises à jour.';
    }

    // Attribuer 2 salles démo au propriétaire Jean Mukendi
    $pdo->prepare(
        "UPDATE salles SET proprietaire_id = ?
         WHERE proprietaire_id IS NULL
         ORDER BY id LIMIT 2"
    )->execute([$propId]);
    $messages[] = 'Salles démo liées au compte propriétaire.';

    $ok = true;
    $messages[] = 'Installation terminée avec succès.';
} catch (Throwable $e) {
    $messages[] = 'Erreur : ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installation — ResaSalle RDC</title>
  <style>
    :root { --bg:#0f2a1f; --card:#1a3d2e; --accent:#c4a35a; --text:#f3efe6; }
    body { margin:0; font-family: Georgia, 'Times New Roman', serif; background:linear-gradient(160deg,#0f2a1f,#1a4a35 50%,#0c2218); color:var(--text); min-height:100vh; display:grid; place-items:center; padding:2rem; }
    .box { max-width:520px; background:var(--card); padding:2rem; border:1px solid rgba(196,163,90,.35); }
    h1 { font-size:1.6rem; margin:0 0 1rem; color:var(--accent); }
    ul { padding-left:1.2rem; line-height:1.7; }
    a { color:var(--accent); }
    .ok { color:#8fd9a8; }
    .err { color:#f0a8a8; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Installation ResaSalle RDC</h1>
    <ul class="<?= $ok ? 'ok' : 'err' ?>">
      <?php foreach ($messages as $m): ?>
        <li><?= htmlspecialchars($m) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($ok): ?>
      <p><strong>Admin :</strong> admin@resasalle.cd / Admin123!</p>
      <p><strong>Client :</strong> marie.kabongo@email.cd / Admin123!</p>
      <p><strong>Propriétaire :</strong> jean.mukendi@email.cd / Admin123!</p>
      <p><a href="index.php">Accéder à l'application →</a></p>
      <p><small>Supprimez install.php après installation.</small></p>
    <?php endif; ?>
  </div>
</body>
</html>

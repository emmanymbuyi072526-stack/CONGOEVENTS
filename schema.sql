-- ResaSalle RDC - Schéma de base de données
-- Compatible MySQL / MariaDB (XAMPP)

CREATE DATABASE IF NOT EXISTS resasalle_rdc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE resasalle_rdc;

-- --------------------------------------------------------
-- Table: utilisateurs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS utilisateurs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  telephone VARCHAR(30) DEFAULT NULL,
  mot_de_passe VARCHAR(255) NOT NULL,
  role ENUM('admin', 'client', 'proprietaire') NOT NULL DEFAULT 'client',
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table: abonnements (propriétaires de salles)
-- --------------------------------------------------------
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
) ENGINE=InnoDB;

CREATE INDEX idx_abonnements_user_statut ON abonnements (utilisateur_id, statut, date_fin);

-- --------------------------------------------------------
-- Table: salles
-- --------------------------------------------------------
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
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_salle_proprietaire FOREIGN KEY (proprietaire_id)
    REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table: reservations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT UNSIGNED NOT NULL,
  salle_id INT UNSIGNED NOT NULL,
  titre_evenement VARCHAR(200) NOT NULL,
  type_evenement ENUM(
    'conference',
    'concert',
    'exposition',
    'atelier',
    'ceremonie',
    'reunion',
    'autre'
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
    REFERENCES salles(id) ON DELETE CASCADE,
  CONSTRAINT chk_dates CHECK (date_fin > date_debut)
) ENGINE=InnoDB;

CREATE INDEX idx_reservations_salle_dates ON reservations (salle_id, date_debut, date_fin);
CREATE INDEX idx_reservations_statut ON reservations (statut);

-- --------------------------------------------------------
-- Données de démonstration
-- Mot de passe pour tous les comptes démo: Admin123!
-- Hash généré avec password_hash('Admin123!', PASSWORD_DEFAULT)
-- --------------------------------------------------------
INSERT INTO utilisateurs (nom, email, telephone, mot_de_passe, role) VALUES
(
  'Admin ResaSalle',
  'admin@resasalle.cd',
  '+243810000001',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin'
),
(
  'Marie Kabongo',
  'marie.kabongo@email.cd',
  '+243970000002',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'client'
),
(
  'Jean Mukendi',
  'jean.mukendi@email.cd',
  '+243990000003',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'proprietaire'
);

-- Note: le hash ci-dessus est celui de "password" (Laravel demo).
-- Le script d'installation PHP régénère les mots de passe correctement.
-- Voir install.php pour créer admin@resasalle.cd / Admin123!

INSERT INTO salles (nom, description, capacite, ville, commune, adresse, equipements, tarif_jour, statut) VALUES
(
  'Salle Lumumba',
  'Grande salle polyvalente idéale pour conférences nationales et événements culturels majeurs.',
  500,
  'Kinshasa',
  'Gombe',
  'Avenue du Commerce, Gombe',
  'Sonorisation, projecteur, scène, climatisation, Wi-Fi',
  850000.00,
  'active'
),
(
  'Espace Culturel Ndjili',
  'Salle intimiste pour ateliers, lectures et petites expositions.',
  120,
  'Kinshasa',
  'Ndjili',
  'Avenue de la Libération, Ndjili',
  'Éclairage scénique, tables, chaises, microphones',
  250000.00,
  'active'
),
(
  'Centre de Conférences Lubumbashi',
  'Centre moderne pour séminaires et conférences académiques.',
  300,
  'Lubumbashi',
  'Kampemba',
  'Boulevard M’siri, Kampemba',
  'Vidéoprojecteur, traduction simultanée, parking, Wi-Fi',
  600000.00,
  'active'
),
(
  'Auditorium Kisangani',
  'Auditorium pour concerts, projections et cérémonies culturelles.',
  400,
  'Kisangani',
  'Mangobo',
  'Avenue du Fleuve, Mangobo',
  'Scène, sonorisation, gradins, loges',
  450000.00,
  'active'
),
(
  'Salle Héritage Matadi',
  'Espace polyvalent pour réunions institutionnelles et événements locaux.',
  80,
  'Matadi',
  'Ville',
  'Avenue Métropole',
  'Écran, sono portable, climatisation',
  180000.00,
  'active'
);

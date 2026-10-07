# Cahier des charges

## Système numérique de réservation des salles pour les événements culturels et conférences en RDC

---

### 1. Présentation du projet

| Élément | Description |
|--------|-------------|
| **Nom** | ResaSalle RDC |
| **Objet** | Plateforme web de réservation de salles pour l’organisation d’événements culturels et de conférences en République Démocratique du Congo |
| **Client / Contexte** | Organisateurs d’événements, institutions culturelles, centres de conférences, administrations locales |
| **Environnement technique** | XAMPP (Apache + MySQL + PHP), navigateur web moderne |

### 2. Objectifs

1. Digitaliser la réservation des salles (disponibilité, demande, validation).
2. Réduire les conflits de double réservation et les échanges papier / téléphone.
3. Offrir une vue claire du calendrier des salles et des événements.
4. Permettre aux administrateurs de gérer salles, réservations et utilisateurs.
5. Adapter le parcours aux besoins des organisateurs culturels et des conférenciers en RDC (Kinshasa, provinces, etc.).

### 3. Périmètre fonctionnel

#### 3.1 Acteurs

| Acteur | Rôle |
|--------|------|
| **Visiteur** | Consulte les salles et informations publiques |
| **Organisateur** | Crée un compte, réserve une salle, suit ses demandes |
| **Administrateur** | Valide / refuse les réservations, gère salles et utilisateurs |

#### 3.2 Fonctionnalités

**Authentification**
- Inscription (nom, email, téléphone, mot de passe)
- Connexion / déconnexion
- Rôles : `organisateur` | `admin`

**Gestion des salles**
- Liste des salles avec capacité, localisation (ville / commune), équipements, tarif journalier
- Fiche détail d’une salle
- CRUD salles (admin)

**Réservations**
- Demande de réservation (salle, dates, type d’événement, description)
- Contrôle anti-chevauchement des créneaux confirmés
- Statuts : `en_attente` | `confirmee` | `refusee` | `annulee`
- Suivi « Mes réservations » (organisateur)
- Validation / refus / annulation (admin)

**Tableau de bord admin**
- Statistiques (salles, réservations en attente, confirmées)
- Gestion utilisateurs (liste, changement de rôle)

**Interface publique**
- Accueil présentant le service
- Catalogue des salles
- Contact / informations pratiques

### 4. Exigences non fonctionnelles

| Critère | Exigence |
|---------|----------|
| **Usabilité** | Interface en français, claire sur desktop et mobile |
| **Sécurité** | Mots de passe hashés (password_hash), sessions PHP, requêtes préparées PDO |
| **Performance** | Temps de réponse acceptable en local (XAMPP) |
| **Disponibilité** | Déploiement local ; prêt pour hébergement mutualisé PHP/MySQL |
| **Maintenabilité** | Code structuré (config, includes, pages, assets) |

### 5. Spécifications techniques

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.x |
| Frontend | HTML5, CSS3, JavaScript (vanilla) |
| Base de données | MySQL 8 / MariaDB (via XAMPP) |
| Serveur | Apache (XAMPP) |
| Accès données | PDO (PHP Data Objects) |

### 6. Modèle de données (résumé)

- **utilisateurs** : id, nom, email, telephone, mot_de_passe, role, date_creation
- **salles** : id, nom, description, capacite, ville, commune, adresse, equipements, tarif_jour, image, statut
- **reservations** : id, utilisateur_id, salle_id, titre_evenement, type_evenement, date_debut, date_fin, description, statut, motif_refus, date_demande

Types d’événements prévus : conférence, concert, exposition, atelier, cérémonie, réunion, autre.

### 7. Contraintes métier

1. Une salle ne peut avoir deux réservations **confirmées** qui se chevauchent.
2. Seul un administrateur peut confirmer ou refuser une demande.
3. L’organisateur peut annuler une réservation en attente ou confirmée (avant la date de début).
4. Les salles inactives ne sont pas réservables.

### 8. Livrables

1. Cahier des charges (présent document)
2. Script SQL d’initialisation (`database/schema.sql`)
3. Application web complète (PHP / HTML / CSS / JS)
4. Guide d’installation XAMPP (`README.md`)
5. Compte administrateur de démonstration

### 9. Planning indicatif de réalisation

| Phase | Contenu |
|-------|---------|
| 1 | Cahier des charges et schéma BDD |
| 2 | Authentification et structure de base |
| 3 | Catalogue salles + réservations |
| 4 | Espace administrateur |
| 5 | Tests et documentation |

### 10. Critères d’acceptation

- [ ] Un organisateur peut s’inscrire et se connecter
- [ ] Il peut consulter les salles disponibles et demander une réservation
- [ ] Le système empêche le chevauchement de réservations confirmées
- [ ] L’admin peut valider / refuser les demandes
- [ ] L’application tourne sous XAMPP (Apache + MySQL + PHP)

---

*Document établi pour le projet ResaSalle RDC — événements culturels et conférences.*

# ResaSalle RDC

Système numérique de réservation des salles pour les événements culturels et conférences en République Démocratique du Congo.

**Stack :** PHP · HTML · CSS · JavaScript · MySQL · XAMPP

## Installation (XAMPP)

1. **Démarrer XAMPP**  
   Lancez **Apache** et **MySQL** depuis le panneau de contrôle XAMPP.

2. **Placer le projet**  
   Copiez le dossier `focus` dans :
   ```
   C:\xampp\htdocs\focus
   ```
   Si le projet est déjà dans `Documents\focus`, créez un lien symbolique ou copiez-le vers `htdocs`.

3. **Configurer l’URL**  
   Ouvrez `config/database.php` et vérifiez :
   - `DB_USER` = `root`
   - `DB_PASS` = `` (vide par défaut sous XAMPP)
   - `APP_URL` = `/focus` (ou le nom du dossier sous `htdocs`)

   Pour activer l’envoi SMTP, définissez les variables d’environnement Windows
   `MAIL_USER` (adresse Gmail d’envoi) et `MAIL_PASS` (mot de passe d’application),
   puis redémarrez Apache. Ne placez pas ces identifiants dans les fichiers du projet.

4. **Installer la base**  
   Ouvrez dans le navigateur :
   ```
   http://localhost/focus/install.php
   ```
   Le script crée la base `resasalle_rdc`, les tables et les données de démo.

   *Alternative :* importer `database/schema.sql` via phpMyAdmin, puis lancer `install.php` pour corriger les mots de passe.

5. **Sécurité**  
   Supprimez ou renommez `install.php` après l’installation.

## Comptes de démonstration

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Admin | `admin@resasalle.cd` | `Admin123!` |
| Organisateur | `marie.kabongo@email.cd` | `Admin123!` |

## Fonctionnalités

- Inscription / connexion (rôles organisateur & admin)
- Catalogue de salles (filtre ville / recherche)
- Demande de réservation avec contrôle anti-chevauchement
- Suivi « Mes réservations » + annulation
- Admin : validation / refus, CRUD salles, gestion des rôles

## Structure

```
focus/
├── CAHIER_DES_CHARGES.md
├── README.md
├── install.php
├── index.php, login.php, register.php, …
├── config/database.php
├── includes/
├── assets/css, assets/js
├── admin/
└── database/schema.sql
```

## Documentation

Voir le fichier **[CAHIER_DES_CHARGES.md](CAHIER_DES_CHARGES.md)** pour le périmètre fonctionnel et les exigences.

<?php
/**
 * Fonctions utilitaires - ResaSalle RDC
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notify.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    $base = rtrim(APP_URL, '/');
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . $base . '/' . ltrim($path, '/'));
    }
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin';
}

function is_client(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'client';
}

function is_proprietaire(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'proprietaire';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Veuillez vous connecter pour continuer.');
        redirect('login.php');
    }

    // Resynchroniser le rôle depuis la BDD (évite une session obsolète)
    try {
        $uid = (int) (current_user()['id'] ?? 0);
        if ($uid > 0) {
            $stmt = db()->prepare('SELECT role, nom, email FROM utilisateurs WHERE id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if ($row) {
                $_SESSION['user']['role'] = $row['role'];
                $_SESSION['user']['nom'] = $row['nom'];
                $_SESSION['user']['email'] = $row['email'];
            }
        }
    } catch (Throwable $e) {
        // Ignorer si la BDD n'est pas prête
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Accès réservé aux administrateurs.');
        redirect('index.php');
    }
}

function require_client(): void
{
    require_login();
    if (!is_client()) {
        flash('error', 'Cette action est réservée aux clients.');
        redirect('index.php');
    }
}

function require_proprietaire(): void
{
    require_login();
    if (!is_proprietaire()) {
        flash('error', 'Accès réservé aux propriétaires de salles.');
        redirect('index.php');
    }
}

function role_label(string $role): string
{
    $labels = [
        'admin'        => 'Administrateur',
        'client'       => 'Client',
        'proprietaire' => 'Propriétaire',
        'organisateur' => 'Client',
    ];
    return $labels[$role] ?? $role;
}

function redirect_after_login(): void
{
    $role = current_user()['role'] ?? 'client';
    if ($role === 'admin') {
        redirect('admin/index.php');
    }
    if ($role === 'proprietaire') {
        redirect('proprietaire/index.php');
    }
    redirect('index.php');
}

/** Plans d'abonnement pour les propriétaires (montants en USD). */
function subscription_plans(): array
{
    return [
        'mensuel' => [
            'label'   => '1 mois',
            'montant' => 5,
            'jours'   => 30,
            'desc'    => 'Visibilité de votre salle pendant 30 jours.',
        ],
        'trimestriel' => [
            'label'   => '3 mois',
            'montant' => 15,
            'jours'   => 90,
            'desc'    => 'Pack trimestriel au même tarif mensuel.',
        ],
        'annuel' => [
            'label'   => '1 année',
            'montant' => 60,
            'jours'   => 365,
            'desc'    => 'Présence continue sur toute l\'année.',
        ],
    ];
}

/** Moyens de paiement acceptés pour les abonnements. */
function payment_methods(): array
{
    return [
        'carte' => [
            'label' => 'Carte bancaire',
            'desc'  => 'Visa, Mastercard ou carte locale.',
        ],
        'mpesa' => [
            'label' => 'M-Pesa',
            'desc'  => 'Paiement mobile Vodacom.',
        ],
        'orange_money' => [
            'label' => 'Orange Money',
            'desc'  => 'Paiement mobile Orange.',
        ],
        'airtel_money' => [
            'label' => 'Airtel Money',
            'desc'  => 'Paiement mobile Airtel.',
        ],
        'autre' => [
            'label' => 'Autres',
            'desc'  => 'Virement bancaire ou autre opérateur.',
        ],
    ];
}

function payment_method_label(string $method): string
{
    return payment_methods()[$method]['label'] ?? $method;
}

function format_usd(float $amount): string
{
    if (fmod($amount, 1.0) === 0.0) {
        return number_format($amount, 0, '.', ' ') . ' $';
    }
    return number_format($amount, 2, '.', ' ') . ' $';
}

/** Montant affiché pour un abonnement (catalogue USD, pas l'ancien montant FC en base). */
function subscription_amount_for_display(string $planKey, float $storedAmount): float
{
    $plans = subscription_plans();
    if (isset($plans[$planKey])) {
        return (float) $plans[$planKey]['montant'];
    }
    return $storedAmount > 100 ? $storedAmount : $storedAmount;
}

function abonnement_statut_label(string $statut): string
{
    $labels = [
        'actif'      => 'Actif',
        'expire'     => 'Expiré',
        'en_attente' => 'En attente',
        'annule'     => 'Annulé',
    ];
    return $labels[$statut] ?? $statut;
}

/**
 * Crée la table abonnements (et colonne proprietaire_id) si la base a été
 * installée avant l'ajout des propriétaires.
 */
function ensure_subscription_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = db();
        $table = $pdo->query("SHOW TABLES LIKE 'abonnements'")->fetchColumn();
        if (!$table) {
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
            }
        }

        $statutCol = $pdo->query("SHOW COLUMNS FROM salles LIKE 'statut'")->fetch();
        if (!$statutCol) {
            try {
                $pdo->exec("ALTER TABLE salles ADD COLUMN statut ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            } catch (Throwable $e) {
            }
        }

        $col = $pdo->query("SHOW COLUMNS FROM salles LIKE 'proprietaire_id'")->fetch();
        if (!$col) {
            try {
                $pdo->exec('ALTER TABLE salles ADD COLUMN proprietaire_id INT UNSIGNED DEFAULT NULL');
            } catch (Throwable $e) {
            }
        }
    } catch (Throwable $e) {
        // Base non prête (ex. avant install.php).
    }
}

function salle_column_exists(string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    try {
        $cache[$column] = (bool) db()->query("SHOW COLUMNS FROM salles LIKE " . db()->quote($column))->fetch();
    } catch (Throwable $e) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

function get_active_subscription(?int $userId = null): ?array
{
    ensure_subscription_schema();

    $userId = $userId ?? (int) (current_user()['id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT * FROM abonnements
         WHERE utilisateur_id = ?
           AND statut = 'actif'
           AND date_fin >= NOW()
         ORDER BY date_fin DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function has_active_subscription(?int $userId = null): bool
{
    return get_active_subscription($userId) !== null;
}

/**
 * Condition SQL : salle visible dans le catalogue public.
 * $alias = '' pour "FROM salles", ou 's' pour "FROM salles s".
 */
function public_salles_sql(string $alias = ''): string
{
    ensure_subscription_schema();

    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $parts = [];

    if (salle_column_exists('statut')) {
        $parts[] = "{$prefix}statut = 'active'";
    }

    if (salle_column_exists('proprietaire_id')) {
        $parts[] = "(
            {$prefix}proprietaire_id IS NULL
            OR EXISTS (
                SELECT 1 FROM abonnements a
                WHERE a.utilisateur_id = {$prefix}proprietaire_id
                  AND a.statut = 'actif'
                  AND a.date_fin >= NOW()
            )
        )";
    }

    return $parts ? '(' . implode(' AND ', $parts) . ')' : '1=1';
}

function salle_is_public(array $salle): bool
{
    if (array_key_exists('statut', $salle) && ($salle['statut'] ?? '') !== 'active') {
        return false;
    }
    $ownerId = $salle['proprietaire_id'] ?? null;
    if ($ownerId === null || $ownerId === '') {
        return true;
    }
    return has_active_subscription((int) $ownerId);
}

function format_money(float $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' FC';
}

/** Assure la colonne devise (FC / USD) sur les salles. */
function ensure_salle_devise_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = db()->query("SHOW COLUMNS FROM salles LIKE 'devise'")->fetch();
        if (!$col) {
            db()->exec("ALTER TABLE salles ADD COLUMN devise ENUM('FC','USD') NOT NULL DEFAULT 'FC' AFTER tarif_jour");
        }
    } catch (Throwable $e) {
    }
}

/** Affiche un montant selon la devise (FC ou USD). */
function format_price(float $amount, string $devise = 'FC'): string
{
    $devise = strtoupper($devise) === 'USD' ? 'USD' : 'FC';
    if ($devise === 'USD') {
        return format_usd($amount);
    }
    return format_money($amount);
}

/** Tarif d'une salle avec sa devise. */
function format_salle_tarif(array $salle): string
{
    ensure_salle_devise_schema();
    return format_price((float) ($salle['tarif_jour'] ?? 0), (string) ($salle['devise'] ?? 'FC'));
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime)
        ?: DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
    if (!$dt) {
        return $datetime;
    }
    return $dt->format('d/m/Y H:i');
}

function type_evenement_label(string $type): string
{
    $labels = [
        'conference'  => 'Conférence',
        'concert'     => 'Concert',
        'exposition'  => 'Exposition',
        'atelier'     => 'Atelier',
        'ceremonie'   => 'Cérémonie',
        'reunion'     => 'Réunion',
        'autre'       => 'Autre',
    ];
    return $labels[$type] ?? $type;
}

function statut_label(string $statut): string
{
    $labels = [
        'en_attente' => 'En attente',
        'confirmee'  => 'Confirmée',
        'refusee'    => 'Refusée',
        'annulee'    => 'Annulée',
        'active'     => 'Active',
        'inactive'   => 'Inactive',
        'actif'      => 'Actif',
        'expire'     => 'Expiré',
        'annule'     => 'Annulé',
    ];
    return $labels[$statut] ?? $statut;
}

/**
 * Vérifie s'il existe déjà une réservation confirmée qui chevauche
 * l'intervalle demandé pour la salle donnée.
 */
function has_conflict(int $salleId, string $debut, string $fin, ?int $excludeId = null): bool
{
    $sql = "SELECT COUNT(*) FROM reservations
            WHERE salle_id = :salle_id
              AND statut = 'confirmee'
              AND date_debut < :fin
              AND date_fin > :debut";

    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
    }

    $stmt = db()->prepare($sql);
    $params = [
        'salle_id' => $salleId,
        'debut'    => $debut,
        'fin'      => $fin,
    ];
    if ($excludeId !== null) {
        $params['exclude_id'] = $excludeId;
    }
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

function asset(string $path): string
{
    return rtrim(APP_URL, '/') . '/assets/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function excerpt(?string $text, int $max = 120): string
{
    $text = (string) $text;
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, '…');
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max - 1) . '…';
}

/**
 * Image d'une salle (fichier local, URL BDD, ou photo de secours).
 */
function salle_image(?array $salle): string
{
    $fallbacks = [
        1 => 'https://images.unsplash.com/photo-1431540015161-0bf868a2d407?auto=format&fit=crop&w=1200&q=80',
        2 => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=80',
        3 => 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80',
        4 => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=1200&q=80',
        5 => 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04?auto=format&fit=crop&w=1200&q=80',
    ];

    $img = trim((string) ($salle['image'] ?? ''));
    if ($img !== '') {
        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            return $img;
        }
        return asset($img);
    }

    $id = (int) ($salle['id'] ?? 0);
    if (isset($fallbacks[$id])) {
        return $fallbacks[$id];
    }

    $pool = array_values($fallbacks);
    return $pool[$id % count($pool)];
}

/**
 * Images du carrousel d'accueil (photos locales).
 */
function hero_images(): array
{
    return [
        [
            'url' => asset('img/hero-1.png'),
            'alt' => 'Couple en tenue traditionnelle lors d\'une cérémonie',
        ],
        [
            'url' => asset('img/hero-2.png'),
            'alt' => 'Réception dans une grande salle décorée',
        ],
        [
            'url' => asset('img/hero-3.png'),
            'alt' => 'Salle de conférence professionnelle prête pour un séminaire',
        ],
    ];
}

function hero_image(): string
{
    return hero_images()[0]['url'];
}

/**
 * Upload photo de salle. Retourne le chemin relatif (ex: uploads/salles/xxx.jpg) ou null.
 * @return array{ok:bool,path:?string,error:?string}
 */
function upload_salle_photo(array $file, ?string $oldRelativePath = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'error' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Échec du téléversement de la photo.'];
    }

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return ['ok' => false, 'path' => null, 'error' => 'La photo ne doit pas dépasser 3 Mo.'];
    }

    $finfoMime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $finfoMime = $finfo->file($file['tmp_name']);
    } else {
        $info = @getimagesize($file['tmp_name']);
        $finfoMime = $info['mime'] ?? null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!$finfoMime || !isset($allowed[$finfoMime])) {
        return ['ok' => false, 'path' => null, 'error' => 'Formats acceptés : JPG, PNG ou WEBP.'];
    }

    $dir = __DIR__ . '/../assets/uploads/salles';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'path' => null, 'error' => 'Impossible de créer le dossier uploads.'];
    }

    $name = 'salle_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$finfoMime];
    $dest = $dir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => null, 'error' => 'Impossible d\'enregistrer la photo.'];
    }

    // Supprimer l'ancienne image locale si remplacée
    if ($oldRelativePath && !str_starts_with($oldRelativePath, 'http')) {
        $oldFull = __DIR__ . '/../assets/' . ltrim(str_replace('\\', '/', $oldRelativePath), '/');
        if (is_file($oldFull)) {
            @unlink($oldFull);
        }
    }

    return ['ok' => true, 'path' => 'uploads/salles/' . $name, 'error' => null];
}


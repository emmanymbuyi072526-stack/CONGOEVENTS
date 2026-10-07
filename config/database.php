<?php
/**
 * Configuration base de données - ResaSalle RDC
 * Adaptez ces valeurs selon votre installation XAMPP.
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'RESASALLES_DRC');
define('DB_USER', 'root');
define('DB_PASS', ''); // Mot de passe MySQL XAMPP (souvent vide)
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Congo Events');
define('APP_BRAND', 'Congo Events');
define('APP_LOGO', 'img/logo-congo-events.png');

/**
 * E-mail plateforme (expéditeur SMTP).
 * MAIL_USER / MAIL_PASS = compte Gmail de Congo Events qui ENVOIE les messages.
 * Les destinataires = l'e-mail de chaque utilisateur (celui saisi à l'inscription).
 *
 * 1. Activez la validation en 2 étapes sur le compte Gmail d'envoi
 * 2. Créez un « mot de passe d'application »
 * 3. Remplissez MAIL_USER / MAIL_PASS ci-dessous
 */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', getenv('MAIL_USER') ?: '');
define('MAIL_PASS', getenv('MAIL_PASS') ?: '');
define('MAIL_FROM', 'exaucempoyi542@gmail.com');
define('MAIL_FROM_NAME', 'Congo Events');

// Détection fiable du chemin public (ex: /focus)
if (!defined('APP_URL')) {
    $appUrl = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: '')
        : '';
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');

    if ($docRoot !== '' && $projectRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
        $appUrl = substr($projectRoot, strlen($docRoot));
    } else {
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_ends_with($script, '/admin') || str_ends_with($script, '/proprietaire')) {
            $script = dirname($script);
        }
        $appUrl = $script;
    }

    if ($appUrl === '/' || $appUrl === '\\' || $appUrl === '.') {
        $appUrl = '';
    }
    define('APP_URL', $appUrl);
}

date_default_timezone_set('Africa/Kinshasa');

/**
 * Connexion PDO unique
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=3306;dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        $last = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                break;
            } catch (PDOException $e) {
                $last = $e;
                usleep(400000); // 0.4s
            }
        }

        if ($pdo === null) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Base de données indisponible</title>';
            echo '<style>body{font-family:Segoe UI,sans-serif;max-width:640px;margin:3rem auto;padding:0 1rem;color:#1f2937}';
            echo 'code{background:#f3f4f6;padding:.15rem .4rem;border-radius:6px}</style></head><body>';
            echo '<h1>MySQL n’est pas démarré</h1>';
            echo '<p>Congo Events ne peut pas se connecter à la base de données (<code>' . htmlspecialchars(DB_HOST) . ':3306</code>).</p>';
            echo '<ol>';
            echo '<li>Ouvre <strong>XAMPP Control Panel</strong> (en administrateur).</li>';
            echo '<li>Vérifie que le service Windows <strong>MySQL96</strong> est arrêté.</li>';
            echo '<li>Clique <strong>Start</strong> sur <strong>MySQL</strong> (module XAMPP).</li>';
            echo '<li>Quand MySQL est vert, recharge cette page.</li>';
            echo '</ol>';
            echo '<p>Ou double-clique : <code>C:\\xamppss\\htdocs\\focus\\start-mysql.bat</code></p>';
            if ($last) {
                echo '<p style="color:#6b7280;font-size:.9rem">Détail : ' . htmlspecialchars($last->getMessage()) . '</p>';
            }
            echo '</body></html>';
            exit;
        }
    }

    return $pdo;
}

<?php
/**
 * Notifications in-app + e-mail (Gmail SMTP).
 */

function ensure_notifications_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = db();
        $table = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchColumn();
        if (!$table) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  utilisateur_id INT UNSIGNED NOT NULL,
                  type VARCHAR(40) NOT NULL DEFAULT 'info',
                  titre VARCHAR(180) NOT NULL,
                  message TEXT NOT NULL,
                  lien VARCHAR(255) DEFAULT NULL,
                  lu TINYINT(1) NOT NULL DEFAULT 0,
                  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  CONSTRAINT fk_notif_user FOREIGN KEY (utilisateur_id)
                    REFERENCES utilisateurs(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            try {
                $pdo->exec('CREATE INDEX idx_notifications_user_lu ON notifications (utilisateur_id, lu, date_creation)');
            } catch (Throwable $e) {
            }
        }
    } catch (Throwable $e) {
    }
}

function create_notification(int $userId, string $titre, string $message, string $type = 'info', ?string $lien = null): void
{
    ensure_notifications_schema();
    try {
        db()->prepare(
            'INSERT INTO notifications (utilisateur_id, type, titre, message, lien) VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $type, $titre, $message, $lien]);
    } catch (Throwable $e) {
    }
}

function unread_notifications_count(?int $userId = null): int
{
    ensure_notifications_schema();
    $userId = $userId ?? (int) (current_user()['id'] ?? 0);
    if ($userId < 1) {
        return 0;
    }
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lu = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function get_user_notifications(int $userId, int $limit = 40): array
{
    ensure_notifications_schema();
    $stmt = db()->prepare(
        'SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_creation DESC LIMIT ' . (int) $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function mark_notifications_read(int $userId, ?int $notifId = null): void
{
    ensure_notifications_schema();
    if ($notifId) {
        db()->prepare('UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?')
            ->execute([$notifId, $userId]);
        return;
    }
    db()->prepare('UPDATE notifications SET lu = 1 WHERE utilisateur_id = ? AND lu = 0')
        ->execute([$userId]);
}

/**
 * Envoi e-mail via SMTP Gmail (si configuré) ou mail() PHP.
 */
function send_app_mail(string $to, string $subject, string $bodyText): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Compte SMTP de la plateforme (expéditeur). Le destinataire = e-mail du compte utilisateur.
    $smtpUser = defined('MAIL_USER') ? trim((string) MAIL_USER) : '';
    $smtpPass = defined('MAIL_PASS') ? (string) MAIL_PASS : '';
    $host = defined('MAIL_HOST') ? MAIL_HOST : '';
    $port = defined('MAIL_PORT') ? (int) MAIL_PORT : 587;

    $fromEmail = $smtpUser !== '' && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)
        ? $smtpUser
        : (defined('MAIL_FROM') ? MAIL_FROM : ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'congoevents.local')));
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'Congo Events');

    if ($host !== '' && $smtpUser !== '' && $smtpPass !== '') {
        return smtp_send_mail($host, $port, $smtpUser, $smtpPass, $fromEmail, $fromName, $to, $subject, $bodyText);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail),
        'Reply-To: ' . $fromEmail,
        'X-Mailer: CongoEvents',
    ];

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyText, implode("\r\n", $headers));
}

function smtp_send_mail(
    string $host,
    int $port,
    string $username,
    string $password,
    string $fromEmail,
    string $fromName,
    string $to,
    string $subject,
    string $body
): bool {
    $errno = 0;
    $errstr = '';
    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        return false;
    }

    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 512)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $ok = static function (string $resp, string $code): bool {
        return str_starts_with($resp, $code);
    };

    try {
        $banner = $read();
        if (!$ok($banner, '220')) {
            fclose($fp);
            return false;
        }

        $write('EHLO congoevents.local');
        $ehlo = $read();
        if (!$ok($ehlo, '250')) {
            $write('HELO congoevents.local');
            $read();
        }

        if ($port !== 465) {
            $write('STARTTLS');
            $tls = $read();
            if (!$ok($tls, '220')) {
                fclose($fp);
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $write('EHLO congoevents.local');
            $read();
        }

        $write('AUTH LOGIN');
        if (!$ok($read(), '334')) {
            fclose($fp);
            return false;
        }
        $write(base64_encode($username));
        if (!$ok($read(), '334')) {
            fclose($fp);
            return false;
        }
        $write(base64_encode($password));
        if (!$ok($read(), '235')) {
            fclose($fp);
            return false;
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        if (!$ok($read(), '250')) {
            fclose($fp);
            return false;
        }
        $write('RCPT TO:<' . $to . '>');
        if (!$ok($read(), '250')) {
            fclose($fp);
            return false;
        }
        $write('DATA');
        if (!$ok($read(), '354')) {
            fclose($fp);
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $payload = [
            'Date: ' . date('r'),
            'From: "' . addslashes($fromName) . '" <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $body,
            '.',
        ];
        $write(implode("\r\n", $payload));
        if (!$ok($read(), '250')) {
            fclose($fp);
            return false;
        }

        $write('QUIT');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        fclose($fp);
        return false;
    }
}

function notify_user(int $userId, string $email, string $titre, string $message, string $type = 'info', ?string $lien = null, bool $sendEmail = true): void
{
    // Toujours utiliser l'e-mail enregistré du compte (ex. Gmail à l'inscription)
    $accountEmail = get_user_email($userId);
    if ($accountEmail !== '') {
        $email = $accountEmail;
    }

    create_notification($userId, $titre, $message, $type, $lien);

    if ($sendEmail && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_app_mail($email, $titre . ' — ' . (defined('APP_NAME') ? APP_NAME : 'Congo Events'), $message);
    }
}

/** E-mail du compte utilisateur (celui saisi à l'inscription). */
function get_user_email(int $userId): string
{
    if ($userId < 1) {
        return '';
    }
    try {
        $stmt = db()->prepare('SELECT email FROM utilisateurs WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $email = trim((string) ($stmt->fetchColumn() ?: ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    } catch (Throwable $e) {
        return '';
    }
}

function plan_abonnement_phrase(string $planKey): string
{
    $map = [
        'mensuel' => 'mensuel',
        'trimestriel' => 'trimestriel',
        'annuel' => 'annuel',
    ];
    return $map[$planKey] ?? $planKey;
}

/**
 * Notification abonnement propriétaire (app + e-mail).
 */
function notify_subscription_activated(int $userId, string $planKey, float $montant): void
{
    $stmt = db()->prepare('SELECT id, nom, email FROM utilisateurs WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }

    $plans = subscription_plans();
    $label = $plans[$planKey]['label'] ?? $planKey;
    $typeAbo = plan_abonnement_phrase($planKey);
    $amount = format_usd($montant);

    $titre = 'Abonnement ' . $label . ' activé';
    $message = "Bonjour " . $user['nom'] . ",\n\n"
        . "Vous venez de souscrire un abonnement " . $typeAbo . " de " . $amount
        . " sur la plateforme Congo Events en tant que propriétaire des salles.\n\n"
        . "Vous pouvez actuellement mettre vos salles en ligne.\n\n"
        . "Merci pour votre confiance.\n\n"
        . "— L'équipe Congo Events";

    notify_user((int) $user['id'], (string) $user['email'], $titre, $message, 'abonnement', 'proprietaire/salles.php', false);

    // L'administrateur reçoit aussi une notification in-app (+ e-mail)
    notify_admins_subscription_payment((int) $user['id'], (string) $user['nom'], $planKey, $montant);
}

/**
 * Notifie tous les admins qu'un propriétaire a payé un abonnement.
 */
function notify_admins_subscription_payment(int $ownerId, string $ownerNom, string $planKey, float $montant): void
{
    $plans = subscription_plans();
    $label = $plans[$planKey]['label'] ?? $planKey;
    $typeAbo = plan_abonnement_phrase($planKey);
    $amount = format_usd($montant);

    $titre = 'Nouvel abonnement propriétaire';
    $message = "Le propriétaire « " . $ownerNom . " » (ID #" . $ownerId . ") vient de souscrire un abonnement "
        . $typeAbo . " de " . $amount . " sur Congo Events.\n\n"
        . "Plan : " . $label . "\n"
        . "Vous pouvez consulter les abonnements dans l'espace admin.\n\n"
        . "— Congo Events";

    try {
        $admins = db()->query("SELECT id, nom, email FROM utilisateurs WHERE role = 'admin'")->fetchAll();
    } catch (Throwable $e) {
        return;
    }

    foreach ($admins as $admin) {
        notify_user(
            (int) $admin['id'],
            (string) ($admin['email'] ?? ''),
            $titre,
            $message,
            'abonnement_admin',
            'admin/abonnements.php',
            true
        );
    }
}

function format_salle_adresse(array $salle): string
{
    $parts = array_filter([
        trim((string) ($salle['adresse'] ?? '')),
        trim((string) ($salle['commune'] ?? '')),
        trim((string) ($salle['ville'] ?? '')),
    ], static fn ($v) => $v !== '');

    return $parts ? implode(', ', $parts) : 'Adresse non renseignée';
}

/**
 * Charge une réservation avec salle + client pour les notifications.
 */
function fetch_reservation_notify_context(int $reservationId): ?array
{
    $stmt = db()->prepare(
        "SELECT r.*,
                s.nom AS salle_nom, s.adresse AS salle_adresse, s.commune AS salle_commune, s.ville AS salle_ville,
                u.id AS client_id, u.nom AS client_nom, u.email AS client_email
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         JOIN utilisateurs u ON u.id = r.utilisateur_id
         WHERE r.id = ?"
    );
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Nouvelle demande de réservation → propriétaire (app + Gmail).
 */
function notify_new_reservation_request(int $reservationId): void
{
    $stmt = db()->prepare(
        "SELECT r.*,
                s.nom AS salle_nom, s.proprietaire_id,
                s.adresse AS salle_adresse, s.commune AS salle_commune, s.ville AS salle_ville,
                c.nom AS client_nom, c.email AS client_email, c.telephone AS client_tel,
                p.id AS owner_id, p.nom AS owner_nom, p.email AS owner_email
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         JOIN utilisateurs c ON c.id = r.utilisateur_id
         LEFT JOIN utilisateurs p ON p.id = s.proprietaire_id
         WHERE r.id = ?"
    );
    $stmt->execute([$reservationId]);
    $ctx = $stmt->fetch();
    if (!$ctx || empty($ctx['owner_id'])) {
        return;
    }

    $salleNom = $ctx['salle_nom'] ?? 'votre salle';
    $adresse = format_salle_adresse([
        'adresse' => $ctx['salle_adresse'] ?? '',
        'commune' => $ctx['salle_commune'] ?? '',
        'ville' => $ctx['salle_ville'] ?? '',
    ]);
    $periode = format_datetime($ctx['date_debut']) . ' → ' . format_datetime($ctx['date_fin']);

    $titre = 'Nouvelle demande de réservation — ' . $salleNom;
    $message = "Bonjour " . ($ctx['owner_nom'] ?? '') . ",\n\n"
        . "Un client vient de faire une demande de réservation pour votre salle « " . $salleNom . " ».\n\n"
        . "Événement : " . ($ctx['titre_evenement'] ?? '') . "\n"
        . "Client : " . ($ctx['client_nom'] ?? '') . "\n"
        . "Email client : " . ($ctx['client_email'] ?? '') . "\n"
        . (!empty($ctx['client_tel']) ? "Téléphone : " . $ctx['client_tel'] . "\n" : '')
        . "Période : " . $periode . "\n"
        . "Adresse de la salle : " . $adresse . "\n\n"
        . "Connectez-vous à Congo Events pour valider ou refuser cette demande.\n\n"
        . "— L'équipe Congo Events";

    notify_user(
        (int) $ctx['owner_id'],
        (string) ($ctx['owner_email'] ?? ''),
        $titre,
        $message,
        'reservation_demande',
        'proprietaire/reservations.php',
        true // application + Gmail du propriétaire
    );
}

/**
 * Notification confirmation / refus de réservation (app + e-mail) → client.
 */
function notify_reservation_decision(int $reservationId, string $decision): void
{
    $ctx = fetch_reservation_notify_context($reservationId);
    if (!$ctx) {
        return;
    }

    $salleNom = $ctx['salle_nom'] ?? 'la salle';
    $adresse = format_salle_adresse([
        'adresse' => $ctx['salle_adresse'] ?? '',
        'commune' => $ctx['salle_commune'] ?? '',
        'ville' => $ctx['salle_ville'] ?? '',
    ]);

    if ($decision === 'confirmee') {
        $titre = 'Réservation validée — ' . $salleNom;
        $statutTxt = 'validée';
    } else {
        $titre = 'Réservation refusée — ' . $salleNom;
        $statutTxt = 'refusée';
    }

    $message = "Bonjour " . ($ctx['client_nom'] ?? '') . ",\n\n"
        . "La réservation de la salle « " . $salleNom . " » a été " . $statutTxt . ".\n"
        . "Veuillez passer en présentiel afin d'effectuer le paiement exigé.\n\n"
        . "Adresse de la salle : " . $adresse . "\n\n"
        . "Merci pour votre confiance.\n\n"
        . "— L'équipe Congo Events";
    $type = $decision === 'confirmee' ? 'reservation_ok' : 'reservation_ko';

    notify_user(
        (int) $ctx['client_id'],
        (string) $ctx['client_email'],
        $titre,
        $message,
        $type,
        'mes-reservations.php',
        true // app + Gmail
    );
}

/**
 * Confirme une réservation, bloque le créneau (refuse les conflits en attente)
 * et notifie le client.
 */
function confirm_reservation_and_block(int $reservationId): array
{
    $stmt = db()->prepare('SELECT * FROM reservations WHERE id = ?');
    $stmt->execute([$reservationId]);
    $res = $stmt->fetch();
    if (!$res) {
        return ['ok' => false, 'error' => 'Réservation introuvable.'];
    }
    if (($res['statut'] ?? '') !== 'en_attente') {
        return ['ok' => false, 'error' => 'Cette réservation n\'est plus en attente.'];
    }

    $salleId = (int) $res['salle_id'];
    $debut = $res['date_debut'];
    $fin = $res['date_fin'];

    if (has_conflict($salleId, $debut, $fin, $reservationId)) {
        return ['ok' => false, 'error' => 'Conflit de créneau avec une autre réservation confirmée.'];
    }

    db()->prepare("UPDATE reservations SET statut = 'confirmee', motif_refus = NULL WHERE id = ?")
        ->execute([$reservationId]);

    // Bloquer la salle sur ce créneau : refuser les autres demandes qui chevauchent
    $conflictStmt = db()->prepare(
        "SELECT id FROM reservations
         WHERE salle_id = ?
           AND statut = 'en_attente'
           AND id != ?
           AND date_debut < ?
           AND date_fin > ?"
    );
    $conflictStmt->execute([$salleId, $reservationId, $fin, $debut]);
    $conflicts = $conflictStmt->fetchAll();

    if ($conflicts) {
        $refuse = db()->prepare(
            "UPDATE reservations SET statut = 'refusee', motif_refus = ?
             WHERE id = ?"
        );
        foreach ($conflicts as $c) {
            $refuse->execute([
                'Créneau indisponible : la salle est déjà réservée (bloquée) pour ces dates.',
                (int) $c['id'],
            ]);
            notify_reservation_decision((int) $c['id'], 'refusee');
        }
    }

    notify_reservation_decision($reservationId, 'confirmee');

    return ['ok' => true, 'error' => null];
}

function refuse_reservation_and_notify(int $reservationId, string $motif = ''): array
{
    $stmt = db()->prepare('SELECT * FROM reservations WHERE id = ?');
    $stmt->execute([$reservationId]);
    $res = $stmt->fetch();
    if (!$res) {
        return ['ok' => false, 'error' => 'Réservation introuvable.'];
    }

    $motif = trim($motif) !== '' ? trim($motif) : 'Non disponible.';
    db()->prepare("UPDATE reservations SET statut = 'refusee', motif_refus = ? WHERE id = ?")
        ->execute([$motif, $reservationId]);

    notify_reservation_decision($reservationId, 'refusee');

    return ['ok' => true, 'error' => null];
}

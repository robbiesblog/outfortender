<?php
/**
 * Alert sign-up, confirmation and unsubscribe.
 *
 * The public site reads the database read-only; this is the one place that
 * writes, so it opens its own connection rather than loosening that rule
 * everywhere else.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const OFT_ALERT_MAX_PER_IP_PER_DAY = 10;

function oft_db_write(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = getenv('OFT_DB') ?: dirname(dirname(__DIR__)) . '/data/outfortender.sqlite';
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 10000');
    return $pdo;
}

function oft_alert_token(): string
{
    return bin2hex(random_bytes(20));
}

/** A hash, not an address: enough to rate-limit, nothing to leak. */
function oft_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return substr(hash('sha256', 'oft-alerts|' . $ip), 0, 32);
}

function oft_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254;
}

/**
 * Record a sign-up. Returns [ok, message-key, token].
 * Never reveals whether an address is already subscribed: that would turn the
 * form into a way of testing whether somebody uses this site.
 */
function oft_alert_subscribe(string $email, array $filter, string $lang): array
{
    $email = trim(mb_strtolower($email));
    if (!oft_valid_email($email)) {
        return [false, 'invalid_email', null];
    }

    $pdo = oft_db_write();
    $hash = oft_ip_hash();

    $recent = $pdo->prepare(
        "SELECT COUNT(*) FROM alerts WHERE ip_hash = :hash AND created_at > :since");
    $recent->execute([':hash' => $hash, ':since' => gmdate('c', time() - 86400)]);
    if ((int) $recent->fetchColumn() >= OFT_ALERT_MAX_PER_IP_PER_DAY) {
        return [false, 'too_many', null];
    }

    $token = oft_alert_token();
    try {
        $pdo->prepare(
            'INSERT INTO alerts (email, token, filter_country, filter_division, filter_q,
                                 lang, created_at, ip_hash)
             VALUES (:email, :token, :country, :division, :q, :lang, :now, :hash)'
        )->execute([
            ':email' => $email,
            ':token' => $token,
            ':country' => $filter['country'] ?: null,
            ':division' => $filter['division'] ?: null,
            ':q' => $filter['q'] ?: null,
            ':lang' => $lang,
            ':now' => gmdate('c'),
            ':hash' => $hash,
        ]);
    } catch (PDOException $e) {
        // Already signed up for exactly this. Say the same thing either way.
        return [true, 'check_inbox', null];
    }

    return [true, 'check_inbox', $token];
}

function oft_alert_confirm(string $token): bool
{
    $statement = oft_db_write()->prepare(
        'UPDATE alerts SET confirmed_at = :now
          WHERE token = :token AND confirmed_at IS NULL AND unsubscribed_at IS NULL');
    $statement->execute([':now' => gmdate('c'), ':token' => $token]);
    if ($statement->rowCount() > 0) {
        return true;
    }
    // Clicking twice should look like success, not an error.
    $check = oft_db_write()->prepare(
        'SELECT COUNT(*) FROM alerts WHERE token = :token AND confirmed_at IS NOT NULL');
    $check->execute([':token' => $token]);
    return (bool) $check->fetchColumn();
}

function oft_alert_unsubscribe(string $token): bool
{
    $statement = oft_db_write()->prepare(
        'UPDATE alerts SET unsubscribed_at = :now WHERE token = :token AND unsubscribed_at IS NULL');
    $statement->execute([':now' => gmdate('c'), ':token' => $token]);
    return $statement->rowCount() > 0 || (bool) oft_value(
        'SELECT COUNT(*) FROM alerts WHERE token = :token', [':token' => $token]);
}

function oft_alert_url(string $path, string $token): string
{
    return OFT_BASE . $path . '?token=' . urlencode($token);
}

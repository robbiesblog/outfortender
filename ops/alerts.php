<?php
/**
 * Build the alert emails - the confirmations people are waiting for, and the
 * daily digests of matching tenders.
 *
 *   php ~/ops/alerts.php              # write what WOULD be sent, send nothing
 *   php ~/ops/alerts.php --send       # actually send
 *
 * It does not send by default, and that is deliberate. A new domain that starts
 * pushing bulk mail before SPF and DKIM are in place gets its reputation burned
 * quickly, and the damage is slow to undo. So this writes each message to
 * ~/ops/log/outbox/ as a plain file for inspection, and only sends once
 * somebody has looked at the output and passed --send.
 *
 * In dry-run nothing is marked as sent, so the same run can be repeated safely.
 */

declare(strict_types=1);

$options = getopt('', ['db::', 'send', 'outbox::', 'limit::']);
$home = getenv('HOME') ?: dirname(__DIR__);
$db = $options['db'] ?? $home . '/data/outfortender.sqlite';
$outbox = $options['outbox'] ?? $home . '/ops/log/outbox';
$send = isset($options['send']);
$limit = (int) ($options['limit'] ?? 500);

const SITE = 'https://outfortender.com';
const FROM = 'Out For Tender <alerts@outfortender.com>';
const DIGEST_INTERVAL_HOURS = 24;
const MAX_TENDERS_PER_EMAIL = 25;

@mkdir($outbox, 0755, true);

$pdo = new PDO('sqlite:' . $db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA busy_timeout = 20000');

/** Write a message out, or send it. Returns true if it was handled. */
function deliver(string $to, string $subject, string $body, bool $send, string $outbox): bool
{
    $headers = [
        'From: ' . FROM,
        'Content-Type: text/plain; charset=utf-8',
        'Auto-Submitted: auto-generated',
        'Precedence: bulk',
    ];

    if (!$send) {
        $name = sprintf('%s-%s.txt', gmdate('Ymd-His'), substr(sha1($to . $subject), 0, 8));
        file_put_contents(
            $outbox . '/' . $name,
            "To: $to\nSubject: $subject\n" . implode("\n", $headers) . "\n\n" . $body
        );
        return true;
    }

    return mail($to, $subject, $body, implode("\r\n", $headers));
}

// ---------------------------------------------------------------- confirmations
$pending = $pdo->prepare(
    "SELECT * FROM alerts
      WHERE confirmed_at IS NULL AND unsubscribed_at IS NULL AND send_count = 0
      ORDER BY id LIMIT :limit");
$pending->bindValue(':limit', $limit, PDO::PARAM_INT);
$pending->execute();

$confirmations = 0;
foreach ($pending->fetchAll() as $row) {
    $link = SITE . '/alerts?do=confirm&token=' . urlencode($row['token']);
    $body = "Somebody - we hope you - asked for email alerts from Out For Tender.\n\n"
        . "Confirm your alert by opening this link:\n$link\n\n"
        . "If that was not you, ignore this email. Nothing will be sent unless the\n"
        . "link above is opened, and we will delete the request.\n\n"
        . "Out For Tender - free public tenders from around the world\n"
        . SITE . "\n";

    if (deliver($row['email'], 'Confirm your Out For Tender alert', $body, $send, $outbox)) {
        $confirmations++;
        if ($send) {
            $pdo->prepare('UPDATE alerts SET send_count = send_count + 1 WHERE id = :id')
                ->execute([':id' => $row['id']]);
        }
    }
}

// ---------------------------------------------------------------------- digests
$due = $pdo->prepare(
    "SELECT * FROM alerts
      WHERE confirmed_at IS NOT NULL AND unsubscribed_at IS NULL
        AND (last_sent_at IS NULL OR last_sent_at < :cutoff)
      ORDER BY id LIMIT :limit");
$due->bindValue(':cutoff', gmdate('c', time() - DIGEST_INTERVAL_HOURS * 3600));
$due->bindValue(':limit', $limit, PDO::PARAM_INT);
$due->execute();

$digests = 0;
$tendersSent = 0;

foreach ($due->fetchAll() as $row) {
    $where = ["status = 'open'", 'first_seen_at > :since'];
    $params = [':since' => $row['last_sent_at'] ?: gmdate('c', time() - 7 * 86400)];

    if ($row['filter_country']) {
        $where[] = 'country = :country';
        $params[':country'] = $row['filter_country'];
    }
    if ($row['filter_division']) {
        $where[] = 'cpv_division = :division';
        $params[':division'] = $row['filter_division'];
    }
    if ($row['filter_q']) {
        $where[] = '(title LIKE :q OR description LIKE :q OR buyer_name LIKE :q)';
        $params[':q'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $row['filter_q']) . '%';
    }

    $matches = $pdo->prepare(
        'SELECT id, title, buyer_name, country_name, category, deadline_at
           FROM tenders WHERE ' . implode(' AND ', $where) . '
          ORDER BY CASE WHEN deadline_utc IS NULL THEN 1 ELSE 0 END, deadline_utc
          LIMIT ' . MAX_TENDERS_PER_EMAIL);
    $matches->execute($params);
    $tenders = $matches->fetchAll();

    if (!$tenders) {
        continue;               // nothing new: silence is better than an empty email
    }

    $lines = [];
    foreach ($tenders as $tender) {
        $lines[] = sprintf(
            "%s\n  %s%s%s\n  %s/tender/%s\n",
            $tender['title'],
            $tender['buyer_name'] ? $tender['buyer_name'] . ' - ' : '',
            $tender['country_name'] ?: '',
            $tender['deadline_at'] ? ' - closes ' . substr($tender['deadline_at'], 0, 10) : '',
            SITE,
            str_replace(':', '-', $tender['id'])
        );
    }

    $what = array_filter([
        $row['filter_q'] ? '"' . $row['filter_q'] . '"' : null,
        $row['filter_division'] ? 'category ' . $row['filter_division'] : null,
        $row['filter_country'] ?: null,
    ]);
    $describe = $what ? implode(', ', $what) : 'everything';
    $unsubscribe = SITE . '/alerts?do=unsubscribe&token=' . urlencode($row['token']);
    $count = count($tenders);

    $body = "New public tenders matching your alert ($describe):\n\n"
        . implode("\n", $lines)
        . "\nSearch everything, free and without an account: " . SITE . "\n\n"
        . "---\nYou asked for these alerts at " . SITE . "/alerts\n"
        . "Unsubscribe in one click: $unsubscribe\n";

    $subject = sprintf('%d new tender%s matching your alert', $count, $count === 1 ? '' : 's');

    if (deliver($row['email'], $subject, $body, $send, $outbox)) {
        $digests++;
        $tendersSent += $count;
        if ($send) {
            $pdo->prepare(
                'UPDATE alerts SET last_sent_at = :now, send_count = send_count + 1 WHERE id = :id'
            )->execute([':now' => gmdate('c'), ':id' => $row['id']]);
        }
    }
}

printf(
    "%s: %d confirmation(s), %d digest(s) covering %d tenders%s\n",
    $send ? 'sent' : 'DRY RUN (nothing sent, nothing marked)',
    $confirmations, $digests, $tendersSent,
    $send ? '' : " -> $outbox"
);

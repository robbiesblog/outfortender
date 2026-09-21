<?php
/** Add the browse-page covering indexes to a live database. Safe to rerun. */
declare(strict_types=1);
$pdo = new PDO('sqlite:' . getenv('HOME') . '/data/outfortender.sqlite', null, null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA busy_timeout = 30000');
$pdo->exec('CREATE INDEX IF NOT EXISTS ix_open_published ON tenders (status, published_at DESC, id)');
// Replace the narrow version if an earlier run created it.
$existing = $pdo->query("SELECT sql FROM sqlite_master WHERE name = 'ix_open_country'")->fetchColumn();
if ($existing && stripos($existing, 'buyer_name') === false) {
    $pdo->exec('DROP INDEX ix_open_country');
}
$pdo->exec('CREATE INDEX IF NOT EXISTS ix_open_country ON tenders (status, country, deadline_utc, country_name, cpv_division, category, source, value_amount, published_at, deadline_at, buyer_name)');
$pdo->exec('CREATE INDEX IF NOT EXISTS ix_open_division ON tenders (status, cpv_division, category)');
$pdo->exec('ANALYZE');
echo "covering indexes in place, statistics refreshed\n";

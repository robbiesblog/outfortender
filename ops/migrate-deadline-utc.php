<?php
/**
 * Add deadline_utc to a live database and correct every tender's open/closed
 * status against it. Safe to run more than once.
 */
declare(strict_types=1);
$db = getenv('HOME') . '/data/outfortender.sqlite';
$pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA busy_timeout = 30000');

$columns = array_column($pdo->query('PRAGMA table_info(tenders)')->fetchAll(), 'name');
if (!in_array('deadline_utc', $columns, true)) {
    $pdo->exec('ALTER TABLE tenders ADD COLUMN deadline_utc TEXT');
    echo "added deadline_utc\n";
}
$pdo->exec('UPDATE tenders SET deadline_utc = datetime(deadline_at) WHERE deadline_at IS NOT NULL');
foreach (['ix_open_deadline', 'ix_country', 'ix_division'] as $index) {
    $pdo->exec("DROP INDEX IF EXISTS $index");
}
$pdo->exec('CREATE INDEX ix_open_deadline ON tenders (status, deadline_utc)');
$pdo->exec('CREATE INDEX ix_country ON tenders (country, status, deadline_utc)');
$pdo->exec('CREATE INDEX ix_division ON tenders (cpv_division, status, deadline_utc)');

$now = gmdate('c');
$reopened = $pdo->exec("UPDATE tenders SET status = 'open', updated_at = '$now'
    WHERE status = 'closed' AND deadline_utc > datetime('now')");
$closed = $pdo->exec("UPDATE tenders SET status = 'closed', updated_at = '$now'
    WHERE status = 'open' AND deadline_utc < datetime('now')");
echo "reopened $reopened wrongly closed tenders, closed $closed wrongly open ones\n";

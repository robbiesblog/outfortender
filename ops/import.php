<?php
/**
 * Fold a delta file into the tender database. Run hourly by cron:
 *
 *   php ~/ops/import.php
 *
 * Reads every *.ndjson in ~/incoming, imports it, moves it to ~/incoming/done.
 * Idempotent: re-importing the same delta changes nothing, which is what makes
 * a missed hour recoverable by simply widening the next fetch window.
 */

declare(strict_types=1);

$options = getopt('', ['db::', 'incoming::', 'delta::', 'schema::', 'keep']);
$home     = getenv('HOME') ?: dirname(__DIR__);
$db       = $options['db']       ?? $home . '/data/outfortender.sqlite';
$incoming = $options['incoming'] ?? $home . '/incoming';
$schema   = $options['schema']   ?? __DIR__ . '/schema.sql';

$files = isset($options['delta'])
    ? [$options['delta']]
    : glob(rtrim($incoming, '/') . '/*.ndjson');

if (!$files) {
    fwrite(STDOUT, "nothing to import\n");
    exit(0);
}

@mkdir(dirname($db), 0755, true);
$fresh = !file_exists($db);

$pdo = new PDO('sqlite:' . $db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA busy_timeout = 10000');

if ($fresh || isset($options['schema'])) {
    $pdo->exec(file_get_contents($schema));
}

const COLUMNS = [
    'id', 'source', 'source_ref', 'url', 'title', 'title_lang', 'titles_json',
    'description', 'description_lang', 'buyer_name', 'country', 'country_name',
    'cpv', 'cpv_division', 'category', 'value_amount', 'value_currency',
    'procedure', 'contract_nature', 'published_at', 'deadline_at', 'content_hash',
];

$insert = $pdo->prepare(
    'INSERT INTO tenders (' . implode(',', COLUMNS) . ', status, first_seen_at, last_seen_at, updated_at) ' .
    'VALUES (:' . implode(', :', COLUMNS) . ", 'open', :now, :now2, :now3)"
);
$update = $pdo->prepare(
    'UPDATE tenders SET ' .
    implode(', ', array_map(fn($c) => "$c = :$c", array_slice(COLUMNS, 1))) .
    ', last_seen_at = :now, updated_at = :now2 WHERE id = :id'
);
$touch   = $pdo->prepare('UPDATE tenders SET last_seen_at = :now WHERE id = :id');
$lookup  = $pdo->prepare('SELECT content_hash FROM tenders WHERE id = :id');

$now = gmdate('c');
$totals = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
$manifest = null;

foreach ($files as $file) {
    $handle = fopen($file, 'r');
    if (!$handle) {
        fwrite(STDERR, "cannot read $file\n");
        continue;
    }

    $pdo->beginTransaction();
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $row = json_decode($line, true);
        if (!is_array($row)) {
            $totals['skipped']++;
            continue;
        }
        if (!empty($row['_manifest'])) {
            $manifest = $line;
            continue;
        }
        if (empty($row['id']) || empty($row['title']) || empty($row['url'])) {
            $totals['skipped']++;
            continue;
        }

        $values = [];
        foreach (COLUMNS as $column) {
            $values[$column] = $row[$column] ?? null;
        }

        $lookup->execute([':id' => $row['id']]);
        $existing = $lookup->fetch();

        if ($existing === false) {
            $insert->execute($values + [':now' => $now, ':now2' => $now, ':now3' => $now]);
            $totals['inserted']++;
        } elseif (($existing['content_hash'] ?? null) !== ($row['content_hash'] ?? null)) {
            $update->execute($values + [':now' => $now, ':now2' => $now]);
            $totals['updated']++;
        } else {
            $touch->execute([':id' => $row['id'], ':now' => $now]);
            $totals['unchanged']++;
        }
    }
    $pdo->commit();
    fclose($handle);

    if (!isset($options['delta']) && !isset($options['keep'])) {
        $done = dirname($file) . '/done';
        @mkdir($done, 0755, true);
        @rename($file, $done . '/' . basename($file));
    }
}

// A tender whose deadline has passed is closed, not deleted: the page stays as a
// permanent record, and Phase 4 links it to the award on ContractAwarded.com.
$close = $pdo->prepare(
    "UPDATE tenders SET status = 'closed', updated_at = :now
      WHERE status = 'open' AND deadline_at IS NOT NULL AND deadline_at < :cutoff"
);
$close->execute([':now' => $now, ':cutoff' => gmdate('c')]);
$closed = $close->rowCount();

$pdo->prepare(
    'INSERT INTO imports (ran_at, delta_file, inserted, updated, unchanged, closed, skipped, manifest)
     VALUES (:ran_at, :delta_file, :inserted, :updated, :unchanged, :closed, :skipped, :manifest)'
)->execute([
    ':ran_at' => $now,
    ':delta_file' => implode(',', array_map('basename', $files)),
    ':inserted' => $totals['inserted'],
    ':updated' => $totals['updated'],
    ':unchanged' => $totals['unchanged'],
    ':closed' => $closed,
    ':skipped' => $totals['skipped'],
    ':manifest' => $manifest,
]);

$open = $pdo->query("SELECT COUNT(*) FROM tenders WHERE status = 'open'")->fetchColumn();
$all  = $pdo->query('SELECT COUNT(*) FROM tenders')->fetchColumn();

printf(
    "imported %d file(s): +%d new, %d updated, %d unchanged, %d closed, %d skipped | %d open of %d total\n",
    count($files), $totals['inserted'], $totals['updated'], $totals['unchanged'],
    $closed, $totals['skipped'], $open, $all
);

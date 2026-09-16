<?php
/**
 * Add the FTS5 index to a database that already holds tenders.
 * Safe to run repeatedly. Usage: php ~/ops/migrate-fts.php
 *
 * The statements are spelled out here rather than parsed out of schema.sql,
 * because a trigger body contains semicolons and naive splitting breaks it.
 */
declare(strict_types=1);

$options = getopt('', ['db::']);
$db = $options['db'] ?? (getenv('HOME') . '/data/outfortender.sqlite');

$pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA busy_timeout = 30000');

$statements = [
    "CREATE VIRTUAL TABLE IF NOT EXISTS tenders_fts USING fts5(
        title, description, buyer_name, country_name, category,
        content = 'tenders', content_rowid = 'rowid',
        tokenize = 'unicode61 remove_diacritics 2')",

    "CREATE TRIGGER IF NOT EXISTS tenders_ai AFTER INSERT ON tenders BEGIN
        INSERT INTO tenders_fts (rowid, title, description, buyer_name, country_name, category)
        VALUES (new.rowid, new.title, new.description, new.buyer_name, new.country_name, new.category);
    END",

    "CREATE TRIGGER IF NOT EXISTS tenders_ad AFTER DELETE ON tenders BEGIN
        INSERT INTO tenders_fts (tenders_fts, rowid, title, description, buyer_name, country_name, category)
        VALUES ('delete', old.rowid, old.title, old.description, old.buyer_name, old.country_name, old.category);
    END",

    "CREATE TRIGGER IF NOT EXISTS tenders_au AFTER UPDATE ON tenders BEGIN
        INSERT INTO tenders_fts (tenders_fts, rowid, title, description, buyer_name, country_name, category)
        VALUES ('delete', old.rowid, old.title, old.description, old.buyer_name, old.country_name, old.category);
        INSERT INTO tenders_fts (rowid, title, description, buyer_name, country_name, category)
        VALUES (new.rowid, new.title, new.description, new.buyer_name, new.country_name, new.category);
    END",
];

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

$pdo->exec("INSERT INTO tenders_fts (tenders_fts) VALUES ('rebuild')");
$rows = $pdo->query('SELECT COUNT(*) FROM tenders_fts')->fetchColumn();
echo "tenders_fts ready, holding $rows rows\n";

<?php
/** Read-only database access for the public site. */

declare(strict_types=1);

function oft_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = getenv('OFT_DB') ?: dirname(dirname(__DIR__)) . '/data/outfortender.sqlite';
    if (!file_exists($path)) {
        http_response_code(503);
        exit('The tender database is being rebuilt. Please try again shortly.');
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA query_only = 1');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function oft_query(string $sql, array $params = []): array
{
    $statement = oft_db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function oft_one(string $sql, array $params = []): ?array
{
    $rows = oft_query($sql, $params);
    return $rows[0] ?? null;
}

function oft_value(string $sql, array $params = [])
{
    $statement = oft_db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

/** Tenders list with optional filters. Returns [rows, total]. */
function oft_tenders(array $filter = [], int $limit = 50, int $offset = 0): array
{
    $where = ["status = 'open'"];
    $params = [];

    if (!empty($filter['country'])) {
        $where[] = 'country = :country';
        $params[':country'] = strtoupper($filter['country']);
    }
    if (!empty($filter['division'])) {
        $where[] = 'cpv_division = :division';
        $params[':division'] = $filter['division'];
    }
    if (!empty($filter['q'])) {
        $where[] = '(title LIKE :q OR buyer_name LIKE :q OR description LIKE :q)';
        $params[':q'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $filter['q']) . '%';
    }
    if (!empty($filter['closing_within_days'])) {
        $where[] = 'deadline_at IS NOT NULL AND deadline_at <= :until';
        $params[':until'] = gmdate('c', time() + 86400 * (int) $filter['closing_within_days']);
    }

    $clause = implode(' AND ', $where);
    $total = (int) oft_value("SELECT COUNT(*) FROM tenders WHERE $clause", $params);

    $order = ($filter['order'] ?? 'deadline') === 'published'
        ? 'published_at DESC, id DESC'
        : 'CASE WHEN deadline_at IS NULL THEN 1 ELSE 0 END, deadline_at ASC';

    $rows = oft_query(
        "SELECT * FROM tenders WHERE $clause ORDER BY $order LIMIT $limit OFFSET $offset",
        $params
    );
    return [$rows, $total];
}

function oft_countries(): array
{
    return oft_query(
        "SELECT country, country_name, COUNT(*) AS n FROM tenders
          WHERE status = 'open' AND country IS NOT NULL
          GROUP BY country ORDER BY n DESC"
    );
}

function oft_categories(): array
{
    return oft_query(
        "SELECT cpv_division, category, COUNT(*) AS n FROM tenders
          WHERE status = 'open' AND cpv_division IS NOT NULL
          GROUP BY cpv_division ORDER BY n DESC"
    );
}

function oft_stats(): array
{
    return [
        'open' => (int) oft_value("SELECT COUNT(*) FROM tenders WHERE status = 'open'"),
        'total' => (int) oft_value('SELECT COUNT(*) FROM tenders'),
        'countries' => (int) oft_value("SELECT COUNT(DISTINCT country) FROM tenders WHERE status = 'open'"),
        'categories' => (int) oft_value("SELECT COUNT(DISTINCT cpv_division) FROM tenders WHERE status = 'open'"),
        'last_import' => oft_value('SELECT ran_at FROM imports ORDER BY id DESC LIMIT 1') ?: null,
    ];
}

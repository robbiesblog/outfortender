<?php
/**
 * The public API. Free, no key, CORS-open: being quotable is the strategy.
 *   /api/tenders.json?country=lt&category=45&q=road&limit=50&page=1
 *   /api/tender/ted-632803-2026.json
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=900');

function oft_api_shape(array $t): array
{
    return array_filter([
        'id' => $t['id'],
        'url' => OFT_BASE . oft_tender_url($t),
        'official_notice' => $t['url'],
        'title' => $t['title'],
        'titles' => $t['titles_json'] ? json_decode($t['titles_json'], true) : null,
        'description' => $t['description'],
        'description_language' => $t['description_lang'],
        'buyer' => $t['buyer_name'],
        'country' => $t['country'],
        'country_name' => $t['country_name'],
        'category' => $t['category'],
        'cpv' => $t['cpv'],
        'value' => $t['value_amount'] ? ['amount' => (float) $t['value_amount'], 'currency' => $t['value_currency']] : null,
        'procedure' => $t['procedure'],
        'published' => $t['published_at'],
        'deadline' => $t['deadline_at'],
        'status' => $t['status'],
        'source' => $t['source'],
    ], fn($v) => $v !== null && $v !== '');
}

$slug = $_GET['id'] ?? null;

if ($slug) {
    $id = preg_replace('/^([a-z0-9]+)-/', '$1:', (string) $slug, 1);
    $t = oft_one('SELECT * FROM tenders WHERE id = :id', [':id' => $id]);
    if (!$t) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found', 'id' => $id], JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo json_encode(oft_api_shape($t), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$page = max(1, (int) ($_GET['page'] ?? 1));
[$rows, $total] = oft_tenders([
    'country' => $_GET['country'] ?? null,
    'division' => preg_replace('/[^0-9]/', '', (string) ($_GET['category'] ?? '')) ?: null,
    'q' => $_GET['q'] ?? null,
    'closing_within_days' => $_GET['closing_within_days'] ?? null,
    'order' => $_GET['order'] ?? 'deadline',
], $limit, ($page - 1) * $limit);

echo json_encode([
    'licence' => 'Open data from official sources. Attribution required: see ' . OFT_BASE . '/api',
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'tenders' => array_map('oft_api_shape', $rows),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

<?php
/** Sitemap index, plus one sitemap per country. Regenerated on request, cached by the CDN-less world for an hour. */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$part = $_GET['part'] ?? null;
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

if (!$part) {
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '<sitemap><loc>' . OFT_BASE . '/sitemap-pages.xml</loc></sitemap>' . "\n";
    foreach (oft_countries() as $c) {
        echo '<sitemap><loc>' . OFT_BASE . '/sitemap-' . strtolower($c['country']) . '.xml</loc></sitemap>' . "\n";
    }
    echo '</sitemapindex>';
    exit;
}

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

if ($part === 'pages') {
    $urls = ['/', '/countries', '/categories', '/api'];
    foreach (oft_countries() as $c) {
        $urls[] = oft_country_url($c['country']);
    }
    foreach (oft_categories() as $c) {
        $urls[] = oft_category_url($c['cpv_division']);
    }
    foreach ($urls as $url) {
        echo '<url><loc>' . e(OFT_BASE . $url) . '</loc><changefreq>hourly</changefreq></url>' . "\n";
    }
} else {
    $code = strtoupper(preg_replace('/[^a-zA-Z]/', '', $part));
    $rows = oft_query(
        "SELECT id, updated_at FROM tenders WHERE country = :c ORDER BY published_at DESC LIMIT 45000",
        [':c' => $code]
    );
    foreach ($rows as $row) {
        echo '<url><loc>' . e(OFT_BASE . '/tender/' . str_replace(':', '-', $row['id'])) . '</loc>'
           . '<lastmod>' . e(substr($row['updated_at'], 0, 10)) . '</lastmod></url>' . "\n";
    }
}
echo '</urlset>';

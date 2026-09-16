<?php
/** Shared listing page for country, category and search. */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';

$perPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
$mode = $_GET['mode'] ?? 'search';
$filter = ['order' => 'deadline'];
$noindex = false;

if ($mode === 'country') {
    $code = strtoupper(preg_replace('/[^a-zA-Z]/', '', (string) ($_GET['c'] ?? '')));
    $row = oft_one('SELECT country_name FROM tenders WHERE country = :c LIMIT 1', [':c' => $code]);
    if (!$code || !$row) {
        http_response_code(404);
        oft_head('Country not found', 'No tenders for that country.', ['noindex' => true]);
        echo '<section class="hero"><h1>No tenders for that country yet</h1><p class="lede">We add sources continuously. <a href="/countries">See the countries we cover</a>.</p></section>';
        oft_foot();
        exit;
    }
    $name = $row['country_name'] ?: $code;
    $filter['country'] = $code;
    $heading = "Public tenders in $name";
    $intro = "Open tenders published by public buyers in $name, updated hourly from official sources.";
    $base = oft_country_url($code);
    $title = "$name public tenders - open contract opportunities";
} elseif ($mode === 'category') {
    $division = preg_replace('/[^0-9]/', '', (string) ($_GET['d'] ?? ''));
    $row = oft_one('SELECT category FROM tenders WHERE cpv_division = :d LIMIT 1', [':d' => $division]);
    if (!$division || !$row) {
        http_response_code(404);
        oft_head('Category not found', 'No tenders in that category.', ['noindex' => true]);
        echo '<section class="hero"><h1>No tenders in that category yet</h1><p class="lede"><a href="/categories">See all categories</a>.</p></section>';
        oft_foot();
        exit;
    }
    $name = $row['category'];
    $filter['division'] = $division;
    $heading = $name;
    $intro = "Open public tenders for $name from every country we cover, updated hourly.";
    $base = oft_category_url($division);
    $title = "$name - public tenders and contract opportunities";
} else {
    $q = trim((string) ($_GET['q'] ?? ''));
    $filter['q'] = $q;
    $heading = $q === '' ? 'Search tenders' : 'Tenders matching "' . $q . '"';
    $intro = $q === '' ? 'Search every open tender we hold.' : '';
    $base = '/search?q=' . urlencode($q);
    $title = $q === '' ? 'Search public tenders' : 'Tenders matching "' . $q . '"';
    $noindex = true;   // search result pages are for people, not for the index
}

[$rows, $total] = oft_tenders($filter, $perPage, ($page - 1) * $perPage);

oft_head($title, $intro ?: $heading, ['noindex' => $noindex, 'canonical' => OFT_BASE . $base]);
?>
<section class="hero compact">
  <h1><?= e($heading) ?></h1>
  <?php if ($intro): ?><p class="lede"><?= e($intro) ?></p><?php endif; ?>
  <p class="count"><?= number_format($total) ?> open <?= $total === 1 ? 'tender' : 'tenders' ?></p>
</section>

<?php if ($mode === 'search'): ?>
<form class="find wide" action="/search" method="get" role="search">
  <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Try: hospital equipment, road resurfacing, software" aria-label="Search tenders">
  <button type="submit">Search</button>
</form>
<?php endif; ?>

<div class="list">
  <?php foreach ($rows as $t) { oft_card($t); } ?>
  <?php if (!$rows): ?><p class="empty">Nothing matches yet. Try a broader word, or <a href="/">browse everything open</a>.</p><?php endif; ?>
</div>
<?php oft_pager($page, $total, $perPage, $base); ?>
<?php oft_foot(); ?>

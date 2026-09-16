<?php
/** Shared listing page for country, category and search. */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';
require __DIR__ . '/lib/guides.php';

if (($_GET['mode'] ?? '') !== 'search') {
    oft_cache_start(900);
}

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

<?php if ($mode === 'country'):
    $guide = oft_guide($code);
    $stats = oft_country_stats($code);
?>
<section class="guide">
  <h2>How public tendering works in <?= e($name) ?></h2>
  <?php if ($guide): ?>
  <p class="lede"><?= e($guide['intro']) ?></p>
  <ul>
    <?php foreach ($guide['notes'] as $note): ?><li><?= e($note) ?></li><?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="lede">
    Tenders for <?= e($name) ?> reach us through
    <?php $labels = array_map(fn($s) => oft_source_label($s['source']), $stats['sources']); ?>
    <?= e(implode(' and ', $labels)) ?>.
    Each listing links to the official notice, which is where bidding happens and
    which is always the authoritative version.
  </p>
  <?php endif; ?>

  <dl class="figures">
    <div><dt>Open now</dt><dd><?= number_format($stats['open']) ?></dd></div>
    <div><dt>Closing this week</dt><dd><?= number_format($stats['closing_this_week']) ?></dd></div>
    <?php if ($stats['median_lead_days'] !== null): ?>
    <div><dt>Typical time to bid</dt><dd><?= (int) $stats['median_lead_days'] ?> days</dd></div>
    <?php endif; ?>
    <div><dt>With a published value</dt><dd><?= $stats['open'] ? round(100 * $stats['with_value'] / $stats['open']) : 0 ?>%</dd></div>
  </dl>
  <p class="muted">
    Figures are counted from the <?= number_format($stats['open']) ?> open
    <?= e($name) ?> tenders we hold right now, and change as notices are published and close.
    "Typical time to bid" is the median gap between publication and deadline.
  </p>

  <?php if ($stats['categories']): ?>
  <p><strong>Most common right now:</strong>
    <?php $bits = [];
      foreach ($stats['categories'] as $c) {
          $bits[] = '<a href="' . e(oft_category_url($c['cpv_division'])) . '">' . e($c['category']) . '</a> (' . number_format($c['n']) . ')';
      }
      echo implode(', ', $bits); ?>
  </p>
  <?php endif; ?>
  <?php if ($stats['buyers']): ?>
  <p><strong>Buyers publishing most often:</strong>
    <?= e(implode(', ', array_map(fn($b) => $b['buyer_name'], $stats['buyers']))) ?>.
  </p>
  <?php endif; ?>
</section>
<h2>Open tenders in <?= e($name) ?></h2>
<?php endif; ?>

<div class="list">
  <?php foreach ($rows as $t) { oft_card($t); } ?>
  <?php if (!$rows): ?><p class="empty">Nothing matches yet. Try a broader word, or <a href="/">browse everything open</a>.</p><?php endif; ?>
</div>
<?php oft_pager($page, $total, $perPage, $base); ?>
<?php oft_foot(); oft_cache_end(); ?>

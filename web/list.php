<?php
/** Shared listing page for country, category and search. */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';

oft_set_lang($_GET['lang'] ?? null);
require __DIR__ . '/lib/guides.php';

if (($_GET['mode'] ?? '') !== 'search') {
    oft_cache_start(3600);   // the importer changes the key; the TTL is only a backstop
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
    $name = oft_country_name($code, $row['country_name'] ?: $code);
    $filter['country'] = $code;
    $heading = t('Public tenders in %s', $name);
    $intro = "Open tenders published by public buyers in $name, updated hourly from official sources.";
    $base = oft_country_url($code);
    $title = t('Public tenders in %s', $name) . ' - Out For Tender';
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
    $name = oft_category_name($division, $row['category']);
    $filter['division'] = $division;
    $heading = $name;
    $intro = "Open public tenders for $name from every country we cover, updated hourly.";
    $base = oft_category_url($division);
    $title = $name . ' - ' . t('open tenders');
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
  <p class="count"><?= number_format($total) ?> <?= e($total === 1 ? t('open tender') : t('open tenders')) ?></p>
</section>

<?php if ($mode === 'search'): ?>
<form class="find wide" action="/search" method="get" role="search">
  <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Try: hospital equipment, road resurfacing, software" aria-label="Search tenders">
  <button type="submit">Search</button>
</form>
<?php endif; ?>

<?php if ($mode === 'country'):
    $guide = oft_lang() === 'en' ? oft_guide($code) : null;
    $stats = oft_country_stats($code);
?>
<section class="guide">
  <h2><?= e(t('How public tendering works in %s', $name)) ?></h2>
  <?php if ($guide): ?>
  <p class="lede"><?= e($guide['intro']) ?></p>
  <ul>
    <?php foreach ($guide['notes'] as $note): ?><li><?= e($note) ?></li><?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="lede">
    <?php $labels = array_map(fn($s) => oft_source_label($s['source']), $stats['sources']); ?>
    <?= e(t('Tenders for %s reach us through %s.', $name, implode(', ', $labels))) ?>
    <?= e(t('Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.')) ?>
  </p>
  <?php endif; ?>

  <dl class="figures">
    <div><dt><?= e(t('Open now')) ?></dt><dd><?= number_format($stats['open']) ?></dd></div>
    <div><dt><?= e(t('Closing this week')) ?></dt><dd><?= number_format($stats['closing_this_week']) ?></dd></div>
    <?php if ($stats['median_lead_days'] !== null): ?>
    <div><dt><?= e(t('Typical time to bid')) ?></dt><dd><?= e(t('%d days', (int) $stats['median_lead_days'])) ?></dd></div>
    <?php endif; ?>
    <div><dt><?= e(t('With a published value')) ?></dt><dd><?= $stats['open'] ? round(100 * $stats['with_value'] / $stats['open']) : 0 ?>%</dd></div>
  </dl>
  <?php if (oft_lang() === 'en'): ?>
  <p class="muted">
    Figures are counted from the <?= number_format($stats['open']) ?> open
    <?= e($name) ?> tenders we hold right now, and change as notices are published and close.
    "Typical time to bid" is the median gap between publication and deadline.
  </p>
  <?php endif; ?>

  <?php if ($stats['categories']): ?>
  <p><strong><?= e(t('Most common right now')) ?>:</strong>
    <?php $bits = [];
      foreach ($stats['categories'] as $c) {
          $bits[] = '<a href="' . e(oft_category_url($c['cpv_division'])) . '">' . e($c['category']) . '</a> (' . number_format($c['n']) . ')';
      }
      echo implode(', ', $bits); ?>
  </p>
  <?php endif; ?>
  <?php if ($stats['buyers']): ?>
  <p><strong><?= e(t('Buyers publishing most often')) ?>:</strong>
    <?= e(implode(', ', array_map(fn($b) => $b['buyer_name'], $stats['buyers']))) ?>.
  </p>
  <?php endif; ?>
</section>
<h2><?= e(t('Public tenders in %s', $name)) ?></h2>
<?php endif; ?>

<div class="list">
  <?php foreach ($rows as $t) { oft_card($t); } ?>
  <?php if (!$rows): ?><p class="empty">Nothing matches yet. Try a broader word, or <a href="/">browse everything open</a>.</p><?php endif; ?>
</div>
<?php oft_pager($page, $total, $perPage, $base); ?>
<?php oft_foot(); oft_cache_end(); ?>

<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';

oft_cache_start(900);

$stats = oft_stats();
[$closing, ] = oft_tenders(['closing_within_days' => 14], 12);
[$newest, ]  = oft_tenders(['order' => 'published'], 20);
$countries = array_slice(oft_countries(), 0, 14);
$categories = array_slice(oft_categories(), 0, 14);

oft_head(
    'Out For Tender - free public tenders from around the world',
    sprintf('%s open public tenders from %d countries, updated hourly. Free to read, no registration.',
        number_format($stats['open']), $stats['countries'])
);
?>
<section class="hero">
  <h1>Public tenders from around the world, free to read</h1>
  <p class="lede">
    <strong><?= number_format($stats['open']) ?></strong> open tenders from
    <strong><?= $stats['countries'] ?></strong> countries across
    <strong><?= $stats['categories'] ?></strong> categories, taken straight from official
    sources and updated every hour. No paywall, no registration, no sales call.
  </p>
</section>

<?php if ($closing): ?>
<section>
  <h2>Closing soon</h2>
  <div class="list">
    <?php foreach ($closing as $t) { oft_card($t); } ?>
  </div>
</section>
<?php endif; ?>

<section>
  <h2>Just published</h2>
  <div class="list">
    <?php foreach ($newest as $t) { oft_card($t); } ?>
  </div>
</section>

<section class="browse">
  <div>
    <h2>By country</h2>
    <ul class="facets">
      <?php foreach ($countries as $c): ?>
      <li><a href="<?= e(oft_country_url($c['country'])) ?>"><?= e($c['country_name'] ?: $c['country']) ?><span><?= number_format($c['n']) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <p><a href="/countries">All countries &rarr;</a></p>
  </div>
  <div>
    <h2>By category</h2>
    <ul class="facets">
      <?php foreach ($categories as $c): ?>
      <li><a href="<?= e(oft_category_url($c['cpv_division'])) ?>"><?= e($c['category'] ?: $c['cpv_division']) ?><span><?= number_format($c['n']) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <p><a href="/categories">All categories &rarr;</a></p>
  </div>
</section>
<?php oft_foot(); oft_cache_end(); ?>

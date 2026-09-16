<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';

oft_set_lang($_GET['lang'] ?? null);

oft_cache_start(900);

$stats = oft_stats();
[$closing, ] = oft_tenders(['closing_within_days' => 14], 12);
[$newest, ]  = oft_tenders(['order' => 'published'], 20);
$countries = array_slice(oft_countries(), 0, 14);
$categories = array_slice(oft_categories(), 0, 14);

oft_head(
    t('Public tenders from around the world, free to read') . ' - Out For Tender',
    sprintf('%s %s, %d %s. %s',
        number_format($stats['open']), t('open tenders'), $stats['countries'], t('Countries'),
        t('Public tenders from around the world, free to read'))
);
?>
<section class="hero">
  <h1><?= e(t('Public tenders from around the world, free to read')) ?></h1>
  <p class="lede">
    <strong><?= number_format($stats['open']) ?></strong> <?= e(t('open tenders')) ?>,
    <strong><?= $stats['countries'] ?></strong> <?= e(mb_strtolower(t('Countries'))) ?>,
    <strong><?= $stats['categories'] ?></strong> <?= e(mb_strtolower(t('Categories'))) ?>.
  </p>
</section>

<?php if ($closing): ?>
<section>
  <h2><?= e(t('Closing soon')) ?></h2>
  <div class="list">
    <?php foreach ($closing as $t) { oft_card($t); } ?>
  </div>
</section>
<?php endif; ?>

<section>
  <h2><?= e(t('Just published')) ?></h2>
  <div class="list">
    <?php foreach ($newest as $t) { oft_card($t); } ?>
  </div>
</section>

<section class="browse">
  <div>
    <h2><?= e(t('By country')) ?></h2>
    <ul class="facets">
      <?php foreach ($countries as $c): ?>
      <li><a href="<?= e(oft_country_url($c['country'])) ?>"><?= e(oft_country_name($c['country'], $c['country_name'])) ?><span><?= number_format($c['n']) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <p><a href="<?= e(oft_path('/countries')) ?>"><?= e(t('All countries')) ?> &rarr;</a></p>
  </div>
  <div>
    <h2><?= e(t('By category')) ?></h2>
    <ul class="facets">
      <?php foreach ($categories as $c): ?>
      <li><a href="<?= e(oft_category_url($c['cpv_division'])) ?>"><?= e(oft_category_name($c['cpv_division'], $c['category'])) ?><span><?= number_format($c['n']) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <p><a href="<?= e(oft_path('/categories')) ?>"><?= e(t('All categories')) ?> &rarr;</a></p>
  </div>
</section>
<?php oft_foot(); oft_cache_end(); ?>

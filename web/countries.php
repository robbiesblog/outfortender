<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';

oft_set_lang($_GET['lang'] ?? null);

oft_cache_start(1800);
$countries = oft_countries();
oft_head(t('Countries') . ' - Out For Tender', 'Every country we currently hold open public tenders for.');
?>
<section class="hero compact">
  <h1><?= e(t('Countries')) ?></h1>
  <p class="lede">Every country we hold open tenders for today. The list grows as we add sources.</p>
</section>
<ul class="facets grid">
  <?php foreach ($countries as $c): ?>
  <li><a href="<?= e(oft_country_url($c['country'])) ?>"><?= e(oft_country_name($c['country'], $c['country_name'])) ?><span><?= number_format($c['n']) ?></span></a></li>
  <?php endforeach; ?>
</ul>
<?php oft_foot(); oft_cache_end(); ?>

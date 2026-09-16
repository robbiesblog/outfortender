<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';

oft_set_lang($_GET['lang'] ?? null);

oft_cache_start(1800);
$categories = oft_categories();
oft_head(t('Categories') . ' - Out For Tender', 'Every category of public tender we currently hold, from construction to software to school meals.');
?>
<section class="hero compact">
  <h1><?= e(t('Categories')) ?></h1>
  <p class="lede">Categories follow the EU Common Procurement Vocabulary, which is published in 24 languages &mdash; the basis for multilingual category pages in Phase 2.</p>
</section>
<ul class="facets grid">
  <?php foreach ($categories as $c): ?>
  <li><a href="<?= e(oft_category_url($c['cpv_division'])) ?>"><?= e(oft_category_name($c['cpv_division'], $c['category'])) ?><span><?= number_format($c['n']) ?></span></a></li>
  <?php endforeach; ?>
</ul>
<?php oft_foot(); oft_cache_end(); ?>

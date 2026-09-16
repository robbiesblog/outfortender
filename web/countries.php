<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
$countries = oft_countries();
oft_head('Public tenders by country', 'Every country we currently hold open public tenders for.');
?>
<section class="hero compact">
  <h1>Tenders by country</h1>
  <p class="lede">Every country we hold open tenders for today. The list grows as we add sources.</p>
</section>
<ul class="facets grid">
  <?php foreach ($countries as $c): ?>
  <li><a href="<?= e(oft_country_url($c['country'])) ?>"><?= e($c['country_name'] ?: $c['country']) ?><span><?= number_format($c['n']) ?></span></a></li>
  <?php endforeach; ?>
</ul>
<?php oft_foot(); ?>

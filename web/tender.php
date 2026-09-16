<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';

oft_set_lang($_GET['lang'] ?? null);

oft_cache_start(3600);

$slug = $_GET['id'] ?? '';
$id = preg_replace('/^([a-z0-9]+)-/', '$1:', (string) $slug, 1);
$t = oft_one('SELECT * FROM tenders WHERE id = :id', [':id' => $id]);

if (!$t) {
    http_response_code(404);
    oft_head('Tender not found', 'This tender is not in our database.', ['noindex' => true]);
    echo '<section class="hero"><h1>Tender not found</h1><p class="lede">It may have been withdrawn by the buyer, or the address may be mistyped. <a href="/">Browse open tenders</a>.</p></section>';
    oft_foot();
    oft_cache_end();
    exit;
}

[$deadlineLabel, $deadlineState] = oft_deadline_label($t['deadline_at']);
[$title, $titleLang] = oft_title_for($t);
$countryName = oft_country_name($t['country'], $t['country_name']);
$categoryName = oft_category_name($t['cpv_division'], $t['category']);
$money = oft_money($t['value_amount'] !== null ? (float) $t['value_amount'] : null, $t['value_currency']);
$titles = $t['titles_json'] ? json_decode($t['titles_json'], true) : [];
$summary = $t['description'] ? mb_substr($t['description'], 0, 200) : $t['title'];

// schema.org/Demand: the closest standard type to "an organisation seeking goods or services".
$jsonld = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Demand',
    'name' => $title,
    'description' => $t['description'] ?: null,
    'url' => OFT_BASE . oft_tender_url($t),
    'availabilityEnds' => $t['deadline_at'] ?: null,
    'areaServed' => $t['country'] ? ['@type' => 'Country', 'identifier' => $t['country'], 'name' => $countryName] : null,
    'seeks' => $t['category'] ? ['@type' => 'Product', 'category' => $categoryName] : null,
    'seller' => $t['buyer_name'] ? ['@type' => 'Organization', 'name' => $t['buyer_name']] : null,
]);

oft_head(
    $title . ' - ' . ($countryName ?: 'Tender'),
    sprintf('%s. %s. %s', $t['buyer_name'] ?: 'Public tender', $deadlineLabel, $summary),
    ['jsonld' => $jsonld, 'noindex' => $t['status'] !== 'open' ? false : false]
);
?>
<article class="detail">
  <p class="crumbs">
    <a href="<?= e(oft_path('/')) ?>">Out For Tender</a>
    <?php if ($t['country']): ?> &rsaquo; <a href="<?= e(oft_country_url($t['country'])) ?>"><?= e($countryName) ?></a><?php endif; ?>
    <?php if ($t['cpv_division']): ?> &rsaquo; <a href="<?= e(oft_category_url($t['cpv_division'])) ?>"><?= e($categoryName) ?></a><?php endif; ?>
  </p>

  <h1<?= $titleLang !== oft_lang() ? ' lang="' . e($titleLang) . '"' : '' ?>><?= e($title) ?></h1>

  <?php if ($t['status'] !== 'open'): ?>
  <p class="notice closed">This tender has closed. It stays online as a record of what was advertised.</p>
  <?php endif; ?>

  <dl class="facts">
    <?php if ($t['buyer_name']): ?><dt><?= e(t('Buyer')) ?></dt><dd><?= e($t['buyer_name']) ?></dd><?php endif; ?>
    <?php if ($t['country_name']): ?><dt><?= e(t('Country')) ?></dt><dd><a href="<?= e(oft_country_url($t['country'])) ?>"><?= e($countryName) ?></a></dd><?php endif; ?>
    <?php if ($t['category']): ?><dt><?= e(t('Category')) ?></dt><dd><a href="<?= e(oft_category_url($t['cpv_division'])) ?>"><?= e($categoryName) ?></a></dd><?php endif; ?>
    <?php if ($money): ?><dt><?= e(t('Value')) ?></dt><dd><?= e($money) ?><?php if ($t['value_currency']): ?> <span class="muted"><?= e($t['value_currency']) ?></span><?php endif; ?></dd><?php endif; ?>
    <dt><?= e(t('Deadline')) ?></dt><dd class="deadline <?= e($deadlineState) ?>"><?= e($deadlineLabel) ?><?php if ($t['deadline_at']): ?> <span class="muted">(<?= e(oft_local_time($t['deadline_at'])) ?>)</span><?php endif; ?></dd>
    <?php if ($t['published_at']): ?><dt><?= e(t('Published')) ?></dt><dd><?= e(oft_date($t['published_at'])) ?></dd><?php endif; ?>
    <?php if ($t['procedure']): ?><dt><?= e(t('Procedure')) ?></dt><dd><?= e(ucfirst($t['procedure'])) ?></dd><?php endif; ?>
    <?php if ($t['cpv']): ?><dt><?= e(t('CPV code')) ?></dt><dd class="mono"><?= e($t['cpv']) ?></dd><?php endif; ?>
  </dl>

  <p class="official">
    <a class="button" href="<?= e($t['url']) ?>" rel="nofollow noopener" target="_blank"><?= e(t('Read the official notice and bid')) ?> &rarr;</a>
    <span class="muted"><?= e(t('Bidding always happens on the buyer\'s own portal, never here.')) ?></span>
  </p>

  <?php if ($t['description']): ?>
  <section>
    <h2><?= e(t('What the buyer is asking for')) ?></h2>
    <p class="description" lang="<?= e($t['description_lang'] ?: 'en') ?>"><?= nl2br(e($t['description'])) ?></p>
    <?php if ($t['description_lang'] && $t['description_lang'] !== oft_lang()): ?>
    <p class="muted"><?= e(t('Language of this notice: %s. The title and description are shown exactly as the buyer published them.', oft_lang_name($t['description_lang']))) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php
    // Only genuinely different wordings reach this list: a source publishing the
    // same string under 24 language codes is not a translation.
    $titles = array_filter($titles ?: [], fn($text, $code) => $code !== $titleLang && $text !== $title, ARRAY_FILTER_USE_BOTH);
  ?>
  <?php if ($titles): ?>
  <section>
    <h2><?= e(t('This tender in other languages')) ?></h2>
    <ul class="langs">
      <?php foreach ($titles as $code => $text): ?>
      <li><span class="code"><?= e(strtoupper($code)) ?></span> <span lang="<?= e($code) ?>"><?= e($text) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <p class="source muted">
    Source: <?= e(strtoupper($t['source'])) ?> &middot; reference <span class="mono"><?= e($t['source_ref']) ?></span> &middot;
    <a href="/api/tender/<?= e(str_replace(':', '-', $t['id'])) ?>.json">JSON</a>
  </p>
</article>
<?php oft_foot(); oft_cache_end(); ?>

<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
require __DIR__ . '/lib/cache.php';

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
$money = oft_money($t['value_amount'] !== null ? (float) $t['value_amount'] : null, $t['value_currency']);
$titles = $t['titles_json'] ? json_decode($t['titles_json'], true) : [];
$summary = $t['description'] ? mb_substr($t['description'], 0, 200) : $t['title'];

// schema.org/Demand: the closest standard type to "an organisation seeking goods or services".
$jsonld = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Demand',
    'name' => $t['title'],
    'description' => $t['description'] ?: null,
    'url' => OFT_BASE . oft_tender_url($t),
    'availabilityEnds' => $t['deadline_at'] ?: null,
    'areaServed' => $t['country'] ? ['@type' => 'Country', 'identifier' => $t['country'], 'name' => $t['country_name']] : null,
    'seeks' => $t['category'] ? ['@type' => 'Product', 'category' => $t['category']] : null,
    'seller' => $t['buyer_name'] ? ['@type' => 'Organization', 'name' => $t['buyer_name']] : null,
]);

oft_head(
    $t['title'] . ' - ' . ($t['country_name'] ?: 'Tender'),
    sprintf('%s. %s. %s', $t['buyer_name'] ?: 'Public tender', $deadlineLabel, $summary),
    ['jsonld' => $jsonld, 'noindex' => $t['status'] !== 'open' ? false : false]
);
?>
<article class="detail">
  <p class="crumbs">
    <a href="/">Tenders</a>
    <?php if ($t['country']): ?> &rsaquo; <a href="<?= e(oft_country_url($t['country'])) ?>"><?= e($t['country_name']) ?></a><?php endif; ?>
    <?php if ($t['cpv_division']): ?> &rsaquo; <a href="<?= e(oft_category_url($t['cpv_division'])) ?>"><?= e($t['category']) ?></a><?php endif; ?>
  </p>

  <h1><?= e($t['title']) ?></h1>

  <?php if ($t['status'] !== 'open'): ?>
  <p class="notice closed">This tender has closed. It stays online as a record of what was advertised.</p>
  <?php endif; ?>

  <dl class="facts">
    <?php if ($t['buyer_name']): ?><dt>Buyer</dt><dd><?= e($t['buyer_name']) ?></dd><?php endif; ?>
    <?php if ($t['country_name']): ?><dt>Country</dt><dd><a href="<?= e(oft_country_url($t['country'])) ?>"><?= e($t['country_name']) ?></a></dd><?php endif; ?>
    <?php if ($t['category']): ?><dt>Category</dt><dd><a href="<?= e(oft_category_url($t['cpv_division'])) ?>"><?= e($t['category']) ?></a></dd><?php endif; ?>
    <?php if ($money): ?><dt>Value</dt><dd><?= e($money) ?><?php if ($t['value_currency']): ?> <span class="muted"><?= e($t['value_currency']) ?></span><?php endif; ?></dd><?php endif; ?>
    <dt>Deadline</dt><dd class="deadline <?= e($deadlineState) ?>"><?= e($deadlineLabel) ?><?php if ($t['deadline_at']): ?> <span class="muted">(<?= e(oft_local_time($t['deadline_at'])) ?>)</span><?php endif; ?></dd>
    <?php if ($t['published_at']): ?><dt>Published</dt><dd><?= e(oft_date($t['published_at'])) ?></dd><?php endif; ?>
    <?php if ($t['procedure']): ?><dt>Procedure</dt><dd><?= e(ucfirst($t['procedure'])) ?></dd><?php endif; ?>
    <?php if ($t['cpv']): ?><dt>CPV code</dt><dd class="mono"><?= e($t['cpv']) ?></dd><?php endif; ?>
  </dl>

  <p class="official">
    <a class="button" href="<?= e($t['url']) ?>" rel="nofollow noopener" target="_blank">Read the official notice and bid &rarr;</a>
    <span class="muted">Bidding always happens on the buyer's own portal, never here.</span>
  </p>

  <?php if ($t['description']): ?>
  <section>
    <h2>What the buyer is asking for</h2>
    <p class="description" lang="<?= e($t['description_lang'] ?: 'en') ?>"><?= nl2br(e($t['description'])) ?></p>
    <?php if ($t['description_lang'] && $t['description_lang'] !== 'en'): ?>
    <p class="muted">This description is in the language the buyer published it in. Translations are coming in Phase 2.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (count($titles) > 1): ?>
  <section>
    <h2>This tender in other languages</h2>
    <ul class="langs">
      <?php foreach ($titles as $code => $text): if ($code === 'en') continue; ?>
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

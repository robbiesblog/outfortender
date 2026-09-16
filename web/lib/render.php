<?php
/** Shared rendering: layout, formatting, URLs. */

declare(strict_types=1);

require_once __DIR__ . '/i18n.php';

const OFT_SITE = 'Out For Tender';
const OFT_BASE = 'https://outfortender.com';

function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Stylesheet fingerprint.
 *
 * The server sends assets with a 30-day cache, so without this a returning
 * visitor keeps the old stylesheet for a month after a design change. The
 * file's modification time changes the URL, so a deploy is picked up at once.
 */
function oft_asset_version(): string
{
    static $version = null;
    if ($version === null) {
        $path = dirname(__DIR__) . '/assets/style.css';
        $version = (string) (@filemtime($path) ?: 1);
    }
    return $version;
}

function oft_tender_url(array $t, ?string $lang = null): string
{
    return oft_path('/tender/' . str_replace(':', '-', $t['id']), $lang);
}

function oft_country_url(string $code, ?string $lang = null): string
{
    return oft_path('/country/' . strtolower($code), $lang);
}

function oft_category_url(string $division, ?string $lang = null): string
{
    return oft_path('/category/' . $division, $lang);
}

/** "in 6 days", "tomorrow", "today", "closed" - the thing a bidder actually wants to know. */
function oft_deadline_label(?string $deadline): array
{
    if (!$deadline) {
        return [t('No deadline given'), 'none'];
    }
    $time = strtotime($deadline);
    if ($time === false) {
        return [t('No deadline given'), 'none'];
    }
    $days = (int) floor(($time - time()) / 86400);
    if ($time < time()) {
        return [t('Closed'), 'closed'];
    }
    if ($days === 0) {
        return [t('Closes today'), 'urgent'];
    }
    if ($days === 1) {
        return [t('Closes tomorrow'), 'urgent'];
    }
    if ($days <= 30) {
        return [t('Closes in %d days', $days), $days <= 7 ? 'urgent' : 'soon'];
    }
    return [t('Closes %s', date('j M Y', $time)), 'later'];
}

/** Format an ISO datetime in the offset it arrived with - a Helsinki deadline
 *  must read as Helsinki time, not as whatever timezone the server runs in. */
function oft_local_time(?string $value, string $format = 'j M Y H:i'): ?string
{
    if (!$value) {
        return null;
    }
    try {
        $when = new DateTimeImmutable($value);
    } catch (Exception $e) {
        return null;
    }
    $offset = $when->getOffset();
    $sign = $offset < 0 ? '-' : '+';
    $label = sprintf('UTC%s%d', $sign, abs(intdiv($offset, 3600)));
    if (abs($offset) % 3600) {
        $label .= sprintf(':%02d', (abs($offset) % 3600) / 60);
    }
    return $when->format($format) . ' ' . $label;
}

function oft_money(?float $amount, ?string $currency): ?string
{
    if (!$amount || $amount <= 0) {
        return null;
    }
    $symbols = ['EUR' => "\u{20AC}", 'GBP' => "\u{A3}", 'USD' => '$'];
    $prefix = $symbols[$currency] ?? (($currency ? $currency . ' ' : ''));
    if ($amount >= 1000000) {
        return $prefix . rtrim(rtrim(number_format($amount / 1000000, 1), '0'), '.') . 'm';
    }
    if ($amount >= 1000) {
        return $prefix . number_format($amount / 1000, 0) . 'k';
    }
    return $prefix . number_format($amount, 0);
}

function oft_date(?string $value): ?string
{
    if (!$value) {
        return null;
    }
    $time = strtotime($value);
    return $time ? date('j M Y', $time) : null;
}

function oft_head(string $title, string $description, array $options = []): void
{
    $canonical = $options['canonical'] ?? (OFT_BASE . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
    $noindex = !empty($options['noindex']);
    $bare = oft_bare_path();
    ?><!doctype html>
<html lang="<?= e(oft_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php foreach (array_keys(OFT_LANGS) as $code): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(OFT_BASE . oft_path($bare, $code)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(OFT_BASE . $bare) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex,follow">
<?php endif; ?>
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<link rel="stylesheet" href="/assets/style.css?v=<?= e(oft_asset_version()) ?>">
<?php if (!empty($options['jsonld'])): ?>
<script type="application/ld+json"><?= json_encode($options['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
</head>
<body>
<header class="site">
  <div class="bar">
    <a class="brand" href="<?= e(oft_path('/')) ?>">Out For Tender</a>
    <form class="find" action="<?= e(oft_path('/search')) ?>" method="get" role="search">
      <input type="search" name="q" placeholder="<?= e(t('Search tenders')) ?>" value="<?= e($_GET['q'] ?? '') ?>" aria-label="<?= e(t('Search tenders')) ?>">
      <button type="submit"><?= e(t('Search')) ?></button>
    </form>
    <nav class="langs-nav" aria-label="<?= e(t('Language')) ?>">
      <?php foreach (OFT_LANGS as $code => $label): ?>
        <?php if ($code === oft_lang()): ?><span aria-current="true"><?= e(strtoupper($code)) ?></span>
        <?php else: ?><a href="<?= e(oft_path($bare, $code)) ?>" hreflang="<?= e($code) ?>" title="<?= e($label) ?>"><?= e(strtoupper($code)) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main>
<?php
}

function oft_foot(): void
{
    $stats = function_exists('oft_stats') ? oft_stats() : [];
    ?>
</main>
<footer class="site">
  <p class="lede">Out For Tender lists public tenders from official open-data sources worldwide. Free to read, updated hourly, no registration.</p>
  <p>Always check the official notice before bidding &mdash; every tender page links to it.</p>
  <p class="credits">
    Contains information from TED (Tenders Electronic Daily), &copy; European Union, reused under the terms permitted for TED data &middot;
    UK data from Find a Tender and Contracts Finder, licensed under the <a href="http://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/">Open Government Licence v3.0</a> &middot;
    World Bank procurement notices, &copy; The World Bank, licensed <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a> &middot;
    Canadian data from CanadaBuys under the Open Government Licence &ndash; Canada &middot;
    French national notices from BOAMP under the Licence Ouverte (Etalab) &middot;
    Colombian data from SECOP II via datos.gov.co.
    Out For Tender is not affiliated with any of these bodies.
  </p>
  <?php if (!empty($stats['last_import'])): ?>
  <p class="credits">Last updated <?= e(date('j M Y H:i', strtotime($stats['last_import']))) ?> UTC.</p>
  <?php endif; ?>
  <nav class="footnav">
    <a href="<?= e(oft_path('/')) ?>"><?= e(t('Home')) ?></a> &middot;
    <a href="<?= e(oft_path('/countries')) ?>"><?= e(t('Countries')) ?></a> &middot;
    <a href="<?= e(oft_path('/categories')) ?>"><?= e(t('Categories')) ?></a> &middot;
    <a href="/api">API</a>
  </nav>
</footer>
</body>
</html>
<?php
}

/** One row in a tender list. */
function oft_card(array $t): void
{
    [$label, $state] = oft_deadline_label($t['deadline_at']);
    [$title, $titleLang] = oft_title_for($t);
    $money = oft_money($t['value_amount'] !== null ? (float) $t['value_amount'] : null, $t['value_currency']);
    ?>
<article class="tender">
  <h3><a href="<?= e(oft_tender_url($t)) ?>" <?= $titleLang !== oft_lang() ? 'lang="' . e($titleLang) . '"' : '' ?>><?= e($title) ?></a></h3>
  <p class="meta">
    <?php if ($t['country']): ?><a class="tag" href="<?= e(oft_country_url($t['country'])) ?>"><?= e(oft_country_name($t['country'], $t['country_name'])) ?></a><?php endif; ?>
    <?php if ($t['cpv_division']): ?><a class="tag" href="<?= e(oft_category_url($t['cpv_division'])) ?>"><?= e(oft_category_name($t['cpv_division'], $t['category'])) ?></a><?php endif; ?>
    <?php if ($money): ?><span class="tag money"><?= e($money) ?></span><?php endif; ?>
    <span class="tag deadline <?= e($state) ?>"><?= e($label) ?></span>
  </p>
  <?php if ($t['buyer_name']): ?><p class="buyer"><?= e($t['buyer_name']) ?></p><?php endif; ?>
</article>
<?php
}

function oft_pager(int $page, int $total, int $perPage, string $base): void
{
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) {
        return;
    }
    $join = str_contains($base, '?') ? '&' : '?';
    ?>
<nav class="pager">
  <?php if ($page > 1): ?><a rel="prev" href="<?= e($base . $join . 'page=' . ($page - 1)) ?>">&larr; Previous</a><?php endif; ?>
  <span>Page <?= $page ?> of <?= $pages ?></span>
  <?php if ($page < $pages): ?><a rel="next" href="<?= e($base . $join . 'page=' . ($page + 1)) ?>">Next &rarr;</a><?php endif; ?>
</nav>
<?php
}

<?php
/**
 * Traffic portal. Password-protected by web/analytics/.htaccess.
 *
 * Two sources, deliberately kept apart:
 *   people  - the browser beacon. Crawlers do not run JavaScript, so this is
 *             the honest count of readers.
 *   logs    - every request the server saw, which is how we count crawlers,
 *             including the AI ones, and the scrapers wearing browser clothes.
 */

declare(strict_types=1);

$analytics = getenv('OFT_ANALYTICS_DB') ?: dirname(dirname(__DIR__)) . '/data/analytics.sqlite';
if (!file_exists($analytics)) {
    http_response_code(503);
    exit('No analytics yet. Run ops/analytics.py.');
}
$db = new PDO('sqlite:' . $analytics, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA query_only = 1');

$days = max(1, min(90, (int) ($_GET['days'] ?? 30)));
$from = gmdate('Y-m-d', time() - $days * 86400);
$bind = [':from' => $from];

function q(PDO $db, string $sql, array $bind = []): array
{
    $s = $db->prepare($sql);
    $s->execute($bind);
    return $s->fetchAll();
}
function e(?string $t): string { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function n($v): string { return number_format((float) $v); }

$hasBeacon = (bool) q($db, "SELECT name FROM sqlite_master WHERE name = 'people'");

$daily = q($db, 'SELECT day, lines, people AS log_people, unverified, bots FROM days
                  WHERE day >= :from ORDER BY day', $bind);
$beaconDaily = $hasBeacon
    ? q($db, 'SELECT day, SUM(views) AS views FROM people WHERE day >= :from GROUP BY day ORDER BY day', $bind)
    : [];
$beaconByDay = array_column($beaconDaily, 'views', 'day');

$audience = q($db, 'SELECT audience, SUM(hits) AS hits FROM traffic WHERE day >= :from
                     GROUP BY audience ORDER BY hits DESC', $bind);
$aiAgents = q($db, "SELECT agent, SUM(hits) AS hits FROM agents
                     WHERE day >= :from AND audience = 'ai' GROUP BY agent ORDER BY hits DESC", $bind);
$otherAgents = q($db, "SELECT audience, agent, SUM(hits) AS hits FROM agents
                        WHERE day >= :from AND audience IN ('search','seo','other_bot','scanner')
                        GROUP BY audience, agent ORDER BY hits DESC LIMIT 14", $bind);
$sources = $hasBeacon ? q($db, 'SELECT source, SUM(views) AS views FROM people WHERE day >= :from
                                 GROUP BY source ORDER BY views DESC', $bind) : [];
$countries = $hasBeacon ? q($db, 'SELECT country, SUM(views) AS views FROM people WHERE day >= :from
                                   GROUP BY country ORDER BY views DESC LIMIT 15', $bind) : [];
$langs = $hasBeacon ? q($db, 'SELECT lang, SUM(views) AS views FROM people WHERE day >= :from
                               GROUP BY lang ORDER BY views DESC', $bind) : [];
$sections = $hasBeacon ? q($db, 'SELECT section, SUM(views) AS views FROM people WHERE day >= :from
                                  GROUP BY section ORDER BY views DESC LIMIT 12', $bind) : [];
$topPages = q($db, "SELECT path, SUM(hits) AS hits FROM pages
                     WHERE day >= :from AND audience IN ('people','unverified')
                     GROUP BY path ORDER BY hits DESC LIMIT 20", $bind);
$aiPages = q($db, "SELECT path, SUM(hits) AS hits FROM pages WHERE day >= :from AND audience = 'ai'
                    GROUP BY path ORDER BY hits DESC LIMIT 12", $bind);
$crawlCountries = q($db, "SELECT country, SUM(hits) AS hits FROM traffic
                           WHERE day >= :from AND audience IN ('ai','search')
                           GROUP BY country ORDER BY hits DESC LIMIT 8", $bind);
$lastRun = q($db, 'SELECT MAX(processed_at) AS at FROM days')[0]['at'] ?? null;

$totalBeacon = array_sum($beaconByDay);
$byAudience = array_column($audience, 'hits', 'audience');
$aiTotal = (int) ($byAudience['ai'] ?? 0);
$allHits = array_sum($byAudience);

$labels = [
    'people' => 'People (log estimate)', 'unverified' => 'Browser-like, unverified',
    'ai' => 'AI crawlers', 'search' => 'Search engines', 'seo' => 'SEO crawlers',
    'scanner' => 'Vulnerability scanners', 'other_bot' => 'Other robots',
];
$sourceLabels = [
    'direct' => 'Direct or app', 'search' => 'Organic search', 'ai_assistant' => 'AI assistants',
    'referral' => 'Other sites', 'internal' => 'Within the site',
];
$maxDay = max(1, max(array_merge([1], array_map(
    fn($d) => (int) $d['lines'], $daily))));
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Traffic - Out For Tender</title>
<style>
  :root { --ink:#16191d; --soft:#5a6470; --faint:#8b95a1; --rule:#e3e6ea; --bg:#f7f8f9;
          --card:#fff; --people:#14504a; --ai:#7b4397; --bot:#b0b7bf; --warn:#8a2f22; }
  @media (prefers-color-scheme: dark) { :root { --ink:#e9edf2; --soft:#aab4c0; --faint:#7f8b99;
          --rule:#2a313a; --bg:#12151a; --card:#181c22; --people:#6fbfb2; --ai:#c5a3e0; --bot:#49525c; } }
  * { box-sizing:border-box } body { margin:0; background:var(--bg); color:var(--ink);
    font:15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
  .wrap { max-width:1060px; margin:0 auto; padding:24px 20px 72px }
  h1 { font-size:24px; margin:0 0 4px; letter-spacing:-.02em }
  h2 { font-size:16px; margin:34px 0 10px; letter-spacing:-.01em }
  .muted { color:var(--faint); font-size:13px }
  .range a { margin-right:10px; font-size:13px; text-decoration:none; color:var(--soft) }
  .range a.on { color:var(--ink); font-weight:600; text-decoration:underline }
  .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px; margin:18px 0 0 }
  .card { background:var(--card); border:1px solid var(--rule); border-radius:8px; padding:14px 16px }
  .card .big { font-size:28px; font-weight:650; letter-spacing:-.02em; font-variant-numeric:tabular-nums }
  .card .label { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:var(--faint) }
  .card .note { font-size:12px; color:var(--soft); margin-top:4px }
  table { border-collapse:collapse; width:100%; background:var(--card); border:1px solid var(--rule); border-radius:8px; overflow:hidden }
  th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--faint);
       padding:9px 12px; border-bottom:1px solid var(--rule); font-weight:600 }
  td { padding:8px 12px; border-bottom:1px solid var(--rule); font-size:14px }
  tr:last-child td { border-bottom:0 }
  td.n { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap }
  .bar { height:7px; border-radius:4px; background:var(--bot); display:inline-block; vertical-align:middle }
  .cols { display:grid; grid-template-columns:repeat(auto-fit,minmax(310px,1fr)); gap:18px }
  .chart { display:flex; gap:3px; align-items:flex-end; height:130px; padding:12px; background:var(--card);
           border:1px solid var(--rule); border-radius:8px; overflow-x:auto }
  .chart .col { flex:1 1 14px; min-width:9px; display:flex; flex-direction:column; justify-content:flex-end; gap:1px }
  .chart .seg { width:100% ; border-radius:2px 2px 0 0 }
  .legend span { font-size:12px; color:var(--soft); margin-right:14px }
  .dot { display:inline-block; width:9px; height:9px; border-radius:2px; margin-right:4px }
  .flag { font-size:12px; color:var(--warn); background:rgba(138,47,34,.09); border-radius:6px;
          padding:10px 12px; margin:12px 0 }
  code { font-family:ui-monospace, Menlo, monospace; font-size:12.5px }
</style></head><body><div class="wrap">

<h1>Out For Tender traffic</h1>
<p class="muted">
  Last read from the logs <?= $lastRun ? e(gmdate('j M Y H:i', strtotime($lastRun))) . ' UTC' : 'never' ?>.
  <span class="range" style="margin-left:10px">
    <?php foreach ([7, 30, 90] as $d): ?>
      <a class="<?= $d === $days ? 'on' : '' ?>" href="?days=<?= $d ?>">last <?= $d ?> days</a>
    <?php endforeach; ?>
  </span>
</p>

<div class="cards">
  <div class="card">
    <div class="label">People</div>
    <div class="big" style="color:var(--people)"><?= $hasBeacon ? n($totalBeacon) : '&mdash;' ?></div>
    <div class="note"><?= $hasBeacon ? 'page views that ran the page script' : 'beacon not reporting yet' ?></div>
  </div>
  <div class="card">
    <div class="label">AI crawlers</div>
    <div class="big" style="color:var(--ai)"><?= n($aiTotal) ?></div>
    <div class="note"><?= $allHits ? round(100 * $aiTotal / $allHits) : 0 ?>% of all requests</div>
  </div>
  <div class="card">
    <div class="label">Browser-like, unverified</div>
    <div class="big"><?= n($byAudience['unverified'] ?? 0) ?></div>
    <div class="note">claim to be browsers, never run the script</div>
  </div>
  <div class="card">
    <div class="label">All requests</div>
    <div class="big"><?= n($allHits) ?></div>
    <div class="note">everything the server answered</div>
  </div>
</div>

<h2>Every day</h2>
<div class="chart">
  <?php foreach ($daily as $d): $h = 106 / $maxDay; ?>
  <div class="col" title="<?= e($d['day']) ?>: <?= n($beaconByDay[$d['day']] ?? 0) ?> people, <?= n($d['bots']) ?> bots, <?= n($d['unverified']) ?> unverified">
    <div class="seg" style="height:<?= max(1, round((int) $d['bots'] * $h)) ?>px;background:var(--bot)"></div>
    <div class="seg" style="height:<?= max(1, round((int) $d['unverified'] * $h)) ?>px;background:#c9a227"></div>
    <div class="seg" style="height:<?= max(2, round(max((int) ($beaconByDay[$d['day']] ?? 0), (int) $d['log_people']) * $h * 6)) ?>px;background:var(--people)"></div>
  </div>
  <?php endforeach; ?>
</div>
<p class="legend">
  <span><i class="dot" style="background:var(--people)"></i>People (shown 6&times; taller to be visible)</span>
  <span><i class="dot" style="background:#c9a227"></i>Unverified</span>
  <span><i class="dot" style="background:var(--bot)"></i>Bots</span>
</p>

<?php if ($hasBeacon && $totalBeacon < 50): ?>
<div class="flag">
  The people figure counts only visits since the page script went live, so it starts near zero
  and builds. The log-based estimate beside it undercounts returning readers, because a browser
  that already has the stylesheet cached never asks for it again.
</div>
<?php endif; ?>

<div class="cols">
  <div>
    <h2>Where people came from</h2>
    <table>
      <tr><th>Source</th><th class="n">Views</th></tr>
      <?php foreach ($sources as $s): ?>
      <tr><td><?= e($sourceLabels[$s['source']] ?? $s['source']) ?></td><td class="n"><?= n($s['views']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$sources): ?><tr><td colspan="2" class="muted">Nothing yet.</td></tr><?php endif; ?>
    </table>

    <h2>Countries (people)</h2>
    <table>
      <tr><th>Country</th><th class="n">Views</th></tr>
      <?php foreach ($countries as $c): ?>
      <tr><td><?= $c['country'] === '??' ? 'Unknown' : e($c['country']) ?></td><td class="n"><?= n($c['views']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$countries): ?><tr><td colspan="2" class="muted">Nothing yet.</td></tr><?php endif; ?>
    </table>

    <h2>Languages read</h2>
    <table>
      <tr><th>Language</th><th class="n">Views</th></tr>
      <?php foreach ($langs as $l): ?>
      <tr><td><?= e(strtoupper($l['lang'])) ?></td><td class="n"><?= n($l['views']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$langs): ?><tr><td colspan="2" class="muted">Nothing yet.</td></tr><?php endif; ?>
    </table>
  </div>

  <div>
    <h2>AI crawlers, by name</h2>
    <table>
      <tr><th>Crawler</th><th class="n">Requests</th></tr>
      <?php foreach ($aiAgents as $a): ?>
      <tr><td><?= e($a['agent']) ?></td><td class="n"><?= n($a['hits']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$aiAgents): ?><tr><td colspan="2" class="muted">None yet.</td></tr><?php endif; ?>
    </table>

    <h2>What the AI crawlers read</h2>
    <table>
      <tr><th>Page</th><th class="n">Requests</th></tr>
      <?php foreach ($aiPages as $p): ?>
      <tr><td><code><?= e(mb_strimwidth($p['path'], 0, 46, '…')) ?></code></td><td class="n"><?= n($p['hits']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$aiPages): ?><tr><td colspan="2" class="muted">None yet.</td></tr><?php endif; ?>
    </table>

    <h2>Other robots</h2>
    <table>
      <tr><th>Robot</th><th>Kind</th><th class="n">Requests</th></tr>
      <?php foreach ($otherAgents as $a): ?>
      <tr><td><?= e($a['agent']) ?></td><td class="muted"><?= e($labels[$a['audience']] ?? $a['audience']) ?></td><td class="n"><?= n($a['hits']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<h2>Most requested pages (people and browser-like)</h2>
<table>
  <tr><th>Page</th><th class="n">Requests</th></tr>
  <?php foreach ($topPages as $p): ?>
  <tr><td><code><?= e($p['path']) ?></code></td><td class="n"><?= n($p['hits']) ?></td></tr>
  <?php endforeach; ?>
</table>

<h2>Everything, by audience</h2>
<table>
  <tr><th>Audience</th><th class="n">Requests</th><th style="width:40%"></th></tr>
  <?php foreach ($audience as $a): $pct = $allHits ? 100 * $a['hits'] / $allHits : 0; ?>
  <tr>
    <td><?= e($labels[$a['audience']] ?? $a['audience']) ?></td>
    <td class="n"><?= n($a['hits']) ?></td>
    <td><span class="bar" style="width:<?= round($pct) ?>%;background:<?= $a['audience'] === 'ai' ? 'var(--ai)' : ($a['audience'] === 'people' ? 'var(--people)' : 'var(--bot)') ?>"></span>
      <span class="muted"><?= round($pct) ?>%</span></td>
  </tr>
  <?php endforeach; ?>
</table>

<p class="muted" style="margin-top:28px">
  People are counted by a script that runs in the browser; crawlers do not run it. Everything else
  is read from the web server's own logs. No cookies, no identifiers, and no IP addresses are kept &mdash;
  an address is used to look up a country and then discarded.
  Country data: IP to Country Lite by <a href="https://db-ip.com">DB-IP</a> (CC BY 4.0).
</p>
</div></body></html>

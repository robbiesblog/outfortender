<?php
/** The standing pages: about, privacy, terms, contact. */
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/lib/cache.php';

oft_set_lang($_GET['lang'] ?? null);
oft_cache_start(3600);

$which = preg_replace('/[^a-z]/', '', (string) ($_GET['p'] ?? ''));
$stats = oft_stats();
$updated = '16 September 2026';

$titles = [
    'about' => 'About Out For Tender',
    'privacy' => 'Privacy',
    'terms' => 'Terms and disclaimer',
    'contact' => 'Contact',
];
if (!isset($titles[$which])) {
    http_response_code(404);
    oft_head('Page not found', 'That page does not exist.', ['noindex' => true]);
    echo '<section class="hero"><h1>Page not found</h1><p class="lede"><a href="/">Back to the tenders</a>.</p></section>';
    oft_foot();
    oft_cache_end();
    exit;
}

oft_head($titles[$which] . ' - Out For Tender', match ($which) {
    'about' => 'What Out For Tender is, where the data comes from, and what it is not.',
    'privacy' => 'What we collect, what we do not, and how to get rid of it.',
    'terms' => 'Terms of use and the limits of what this data can tell you.',
    'contact' => 'How to reach us, report an error, or ask us to add a source.',
});
?>
<article class="detail prose">
<?php if ($which === 'about'): ?>
  <h1>About Out For Tender</h1>
  <p class="lede">Out For Tender publishes public tenders from around the world, free to read, updated every hour.</p>

  <h2>Why it exists</h2>
  <p>Public procurement notices are public documents. Governments publish them so that anyone can bid, which is the whole point of putting contracts out to tender. Yet most of the sites that gather them up put a login in front, charge a subscription, and block search engines from reading what was public in the first place.</p>
  <p>This site does the opposite. Every tender we hold is readable without an account, quotable by anyone including language models, and available as JSON through a <a href="/api">free API</a>.</p>

  <h2>Where the data comes from</h2>
  <p>We take tenders only from official open-data sources: the European Union's TED, the UK's Find a Tender and Contracts Finder, the World Bank, CanadaBuys, France's BOAMP, Colombia's SECOP II and the US government's SAM.gov. We add more as we find sources that publish openly and under a licence we can honour. Every listing links back to the official notice.</p>
  <p>We currently hold <?= number_format($stats['open']) ?> open tenders from <?= $stats['countries'] ?> countries.</p>

  <h2>What this site is not</h2>
  <ul>
    <li><strong>It is not a bidding platform.</strong> Bids are submitted on the buyer's own portal. We link you there and stop.</li>
    <li><strong>It is not a consultancy or a lead broker.</strong> We do not sell your details, because we do not collect them.</li>
    <li><strong>It is not authoritative.</strong> The official notice is. Ours is a copy, made at a point in time; deadlines get extended and notices get withdrawn.</li>
  </ul>

  <h2>Mistakes</h2>
  <p>If a tender here is wrong, out of date, or should not be listed, tell us at <a href="mailto:corrections@outfortender.com">corrections@outfortender.com</a> and we will fix it.</p>

<?php elseif ($which === 'privacy'): ?>
  <h1>Privacy</h1>
  <p class="lede">The short version: we collect almost nothing, and you can read the entire site without giving us anything at all.</p>

  <h2>If you just read the site</h2>
  <p>No account, no tracking cookie from us, no newsletter wall. Our web server keeps standard access logs, which include your IP address, for security and troubleshooting.</p>

  <h2>If you sign up for alerts</h2>
  <p>We store your email address, the filter you chose, and the language you were reading in. We also store a one-way hash of your IP address, used only to stop one person creating hundreds of alerts. We cannot recover an IP address from that hash.</p>
  <p>Alerts use confirmed opt-in: nothing is ever sent to an address until somebody opens the confirmation link, so your address cannot be signed up by anyone else. Every email carries a one-click unsubscribe link, which works immediately and needs no login. To have your address deleted outright rather than unsubscribed, email <a href="mailto:hello@outfortender.com">hello@outfortender.com</a>.</p>
  <p>We do not sell, rent, or share subscriber lists. There is no third party in this: the alerts are generated and sent by this site.</p>

  <h2>Advertising</h2>
  <p><strong>This site does not currently carry advertising, and sets no advertising cookies.</strong> We intend to run advertising through Google AdSense, and this section will describe it accurately before a single ad appears.</p>
  <p>When that happens: Google may set cookies to select and measure ads, as described in <a href="https://policies.google.com/technologies/ads" rel="nofollow">Google's advertising policies</a>. Readers in the EEA and the UK will be asked for consent through a certified consent tool before any personalised advertising cookie is set, and will be able to change or withdraw that choice at any time.</p>

  <h2>Your rights</h2>
  <p>If you are in the EEA or the UK, you have the right to see what we hold about you, correct it, or have it deleted. Since the only thing we hold is an email address and a filter, deletion is immediate and complete. Write to <a href="mailto:hello@outfortender.com">hello@outfortender.com</a>.</p>
  <p class="muted">Last updated <?= e($updated) ?>.</p>

<?php elseif ($which === 'terms'): ?>
  <h1>Terms and disclaimer</h1>
  <p class="lede">Use the site freely. Just do not treat it as the official record, because it is not.</p>

  <h2>The official notice always wins</h2>
  <p>Everything here is a copy of a public notice, made at a point in time and reproduced in good faith. Deadlines are extended, notices are corrected, and tenders are withdrawn. Before you spend money or time on a bid, read the official notice we link to. If the two disagree, the official notice is right and we are wrong.</p>

  <h2>No advice</h2>
  <p>Nothing here is legal, financial or procurement advice. Whether you are eligible to bid, and what a notice requires of you, are questions for the buyer and for your own advisers.</p>

  <h2>Reusing our data</h2>
  <p>The underlying notices come from public bodies under open licences, and those licences are credited in the footer of every page. You are welcome to use our <a href="/api">API</a> and to quote and summarise what you find. We ask two things: link to the tender page you used, and tell your readers to check the official notice.</p>

  <h2>No warranty</h2>
  <p>The site is provided as it is, without guarantee of accuracy, completeness or availability. We are not liable for a bid that was missed, a deadline that moved, or a tender we did not list.</p>
  <p class="muted">Last updated <?= e($updated) ?>.</p>

<?php else: ?>
  <h1>Contact</h1>
  <p class="lede">Real people read these.</p>
  <dl class="facts">
    <dt>Something is wrong</dt>
    <dd><a href="mailto:corrections@outfortender.com">corrections@outfortender.com</a><br>
      <span class="muted">A wrong deadline, a tender that should not be listed, a buyer name mangled by our importer.</span></dd>
    <dt>Add a source</dt>
    <dd><a href="mailto:hello@outfortender.com">hello@outfortender.com</a><br>
      <span class="muted">If your country publishes tenders as open data and we are not carrying them, tell us where to look.</span></dd>
    <dt>Anything else</dt>
    <dd><a href="mailto:hello@outfortender.com">hello@outfortender.com</a></dd>
  </dl>
  <p>We do not take payment to list, promote or rank a tender, and there is no way to buy a better position here.</p>
<?php endif; ?>
</article>
<?php oft_foot(); oft_cache_end(); ?>

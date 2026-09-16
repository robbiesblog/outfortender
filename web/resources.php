<?php
/**
 * Resources: the things a bidder actually needs that we do not publish ourselves.
 *
 * Mostly links to official registers, which cost nothing and help people. A
 * short shelf of books carries an Amazon affiliate tag; it is marked as such,
 * sits below the free material, and is not allowed to dress itself up as
 * editorial recommendation.
 */
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/lib/cache.php';

oft_set_lang($_GET['lang'] ?? null);
oft_cache_start(3600);

const AMAZON_TAG = 'fergusonmed00-20';

/** Amazon search links, tagged. No prices and no invented review claims. */
function amazon(string $search): string
{
    return 'https://www.amazon.com/s?k=' . rawurlencode($search) . '&tag=' . AMAZON_TAG;
}

$registers = [
    ['TED', 'https://ted.europa.eu', 'The European Union\'s official register. Every EU tender above the thresholds is here first.'],
    ['Find a Tender', 'https://www.find-tender.service.gov.uk', 'UK contracts above threshold.'],
    ['Contracts Finder', 'https://www.contractsfinder.service.gov.uk', 'UK contracts below threshold, including a lot of small local work.'],
    ['SAM.gov', 'https://sam.gov', 'US federal contracting. You need a free account, and a UEI number before you can bid.'],
    ['CanadaBuys', 'https://canadabuys.canada.ca', 'Canadian federal and participating provincial tenders.'],
    ['BOAMP', 'https://www.boamp.fr', 'French national notices, including contracts below the EU thresholds.'],
    ['SECOP II', 'https://www.colombiacompra.gov.co', 'Colombian public procurement.'],
    ['UNGM', 'https://www.ungm.org', 'The United Nations marketplace: 30-odd agencies, one registration.'],
    ['World Bank', 'https://projects.worldbank.org', 'Work funded by the Bank, mostly in developing economies.'],
];

$books = [
    ['Bid and tender writing', 'bid writing tender proposal', 'How to structure a response, answer the question asked, and score against published criteria.'],
    ['Public procurement law', 'public procurement law', 'What the rules require of buyers, and therefore what you can hold them to.'],
    ['Cost estimating and pricing', 'construction cost estimating tendering', 'Pricing work so that winning it does not cost you money.'],
];

oft_head(
    'Resources for bidders - Out For Tender',
    'Official tender registers worldwide, and the small number of things worth reading before you bid.'
);
?>
<article class="detail prose">
  <h1>Resources for bidders</h1>
  <p class="lede">We list tenders. These are the other things worth knowing about.</p>

  <h2>Go straight to the official registers</h2>
  <p>You never have to come through us. These are the sources we read, and you can read them directly. Our job is to save you visiting nine sites to find out whether anything relevant was published today.</p>
  <ul class="links">
    <?php foreach ($registers as [$name, $url, $note]): ?>
    <li><a href="<?= e($url) ?>" rel="nofollow noopener" target="_blank"><?= e($name) ?></a> &mdash; <?= e($note) ?></li>
    <?php endforeach; ?>
  </ul>

  <h2>Before your first bid</h2>
  <ul>
    <li><strong>Register early.</strong> Most portals need an account, and some need a national identifier that takes days to get. Do it before a deadline depends on it, not after.</li>
    <li><strong>Read the award criteria first.</strong> They tell you how the buyer is obliged to score you, which is usually more useful than the specification.</li>
    <li><strong>Ask questions in writing.</strong> Clarification answers normally go to every bidder, so a good question costs you nothing and may win you the contract.</li>
    <li><strong>Watch the clock, not the date.</strong> Deadlines are timed, often to the minute, in the buyer's own timezone. Every deadline on this site shows its timezone for that reason.</li>
  </ul>

  <h2>Books</h2>
  <p class="muted">These are Amazon search links and carry an affiliate tag, which means we may earn a commission if you buy something. They are categories worth reading, not specific recommendations &mdash; we have no way to test which bid-writing book is best, and we are not going to pretend otherwise.</p>
  <ul class="links">
    <?php foreach ($books as [$name, $search, $note]): ?>
    <li><a href="<?= e(amazon($search)) ?>" rel="nofollow sponsored noopener" target="_blank"><?= e($name) ?></a> &mdash; <?= e($note) ?></li>
    <?php endforeach; ?>
  </ul>

  <h2>Where awards are published</h2>
  <p>A tender that closes becomes a contract award, and awards are published too. <a href="https://contractawarded.com" rel="noopener">ContractAwarded.com</a> follows UK awards daily, which is a useful way to see who won the work you bid for &mdash; and who your competitors are next time.</p>
</article>
<?php oft_foot(); oft_cache_end(); ?>

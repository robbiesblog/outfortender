<?php
/** Alert sign-up, confirmation and unsubscribe - all three, one page. */
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/lib/alerts.php';

oft_set_lang($_GET['lang'] ?? null);

$action = $_GET['do'] ?? '';
$notice = null;
$noticeKind = 'ok';
$token = (string) ($_GET['token'] ?? '');

if ($action === 'confirm' && $token !== '') {
    $ok = oft_alert_confirm($token);
    $notice = $ok
        ? t('Your alert is active. We will email you when a matching tender appears.')
        : t('That confirmation link is not valid. It may have already been used.');
    $noticeKind = $ok ? 'ok' : 'warn';
} elseif ($action === 'unsubscribe' && $token !== '') {
    $ok = oft_alert_unsubscribe($token);
    $notice = $ok
        ? t('You have been unsubscribed. No further emails will be sent.')
        : t('That link is not valid.');
    $noticeKind = $ok ? 'ok' : 'warn';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // A field no person can see; anything that fills it in is a bot.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $notice = t('Thanks. Check your inbox for a confirmation link.');
    } else {
        [$ok, $message, $newToken] = oft_alert_subscribe(
            (string) ($_POST['email'] ?? ''),
            [
                'country' => strtoupper(preg_replace('/[^a-zA-Z]/', '', (string) ($_POST['country'] ?? ''))) ?: null,
                'division' => preg_replace('/[^0-9]/', '', (string) ($_POST['division'] ?? '')) ?: null,
                'q' => trim((string) ($_POST['q'] ?? '')) ?: null,
            ],
            oft_lang()
        );
        $notice = match ($message) {
            'invalid_email' => t('That does not look like an email address.'),
            'too_many' => t('That is a lot of alerts from one place. Try again tomorrow.'),
            default => t('Thanks. Check your inbox for a confirmation link.'),
        };
        $noticeKind = $ok ? 'ok' : 'warn';
        // The confirmation email is queued by the hourly job, which is also what
        // sends the alerts themselves - see ops/alerts.php.
    }
}

$countries = oft_countries();
$categories = oft_categories();

oft_head(
    t('Free tender alerts') . ' - Out For Tender',
    t('Tell us what you are looking for and we will email you when a matching public tender is published. Free, no account, unsubscribe in one click.')
);
?>
<section class="hero compact">
  <h1><?= e(t('Free tender alerts')) ?></h1>
  <p class="lede"><?= e(t('Tell us what you are looking for and we will email you when a matching public tender is published. Free, no account, unsubscribe in one click.')) ?></p>
</section>

<?php if ($notice): ?>
<p class="notice <?= $noticeKind === 'ok' ? 'good' : 'closed' ?>"><?= e($notice) ?></p>
<?php endif; ?>

<form class="alert-form" method="post" action="<?= e(oft_path('/alerts')) ?>">
  <div class="field">
    <label for="email"><?= e(t('Your email')) ?></label>
    <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@company.com">
  </div>
  <div class="field">
    <label for="country"><?= e(t('Country')) ?></label>
    <select id="country" name="country">
      <option value=""><?= e(t('Anywhere')) ?></option>
      <?php foreach ($countries as $c): ?>
      <option value="<?= e($c['country']) ?>"><?= e(oft_country_name($c['country'], $c['country_name'])) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="division"><?= e(t('Category')) ?></label>
    <select id="division" name="division">
      <option value=""><?= e(t('Everything')) ?></option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= e($c['cpv_division']) ?>"><?= e(oft_category_name($c['cpv_division'], $c['category'])) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="q"><?= e(t('Keywords (optional)')) ?></label>
    <input type="text" id="q" name="q" placeholder="<?= e(t('e.g. road resurfacing')) ?>">
  </div>
  <p class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
  <button type="submit"><?= e(t('Create my alert')) ?></button>
  <p class="muted"><?= e(t('We send a confirmation link first, so nobody can sign up an address that is not theirs. We store your address and your filter, nothing else, and every email has a one-click unsubscribe.')) ?>
    <a href="<?= e(oft_path('/privacy')) ?>"><?= e(t('Privacy')) ?></a>.</p>
</form>
<?php oft_foot(); ?>

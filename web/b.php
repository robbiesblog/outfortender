<?php
/**
 * The people counter.
 *
 * A one-line script on each page calls this. It matters because the log-based
 * count cannot be trusted on its own: the stylesheet is cached for 30 days, so
 * a returning reader never re-fetches it and looks exactly like a scraper.
 * Crawlers and scrapers do not run JavaScript, so a call to this endpoint is
 * decent evidence of a person.
 *
 * What is stored: the day, the kind of page, the language, the country (from
 * the IP, which is then discarded), and where they came from. No cookies, no
 * identifiers, no IP addresses, nothing that follows anybody between pages.
 */

declare(strict_types=1);

header('Content-Type: image/gif');
header('Cache-Control: no-store, max-age=0');
header('Access-Control-Allow-Origin: *');
// A 1x1 transparent GIF, sent before any work so the page never waits.
$pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
header('Content-Length: ' . strlen($pixel));
echo $pixel;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// b.php sits in the web root, so home is one level up - not two, as it is for
// the files in lib/.
$db = getenv('OFT_ANALYTICS_DB') ?: dirname(__DIR__) . '/data/analytics.sqlite';
if (!file_exists($db)) {
    exit;
}

/** Which kind of page, from the path the browser reported. */
function beacon_section(string $path): array
{
    $parts = array_values(array_filter(explode('/', strtok($path, '?'))));
    $lang = 'en';
    if ($parts && in_array($parts[0], ['de', 'fr', 'es', 'it', 'pl', 'pt', 'nl'], true)) {
        $lang = array_shift($parts);
    }
    $head = $parts[0] ?? '';
    $known = ['tender', 'country', 'category', 'search', 'alerts', 'countries',
              'categories', 'resources', 'about', 'privacy', 'terms', 'contact', 'api'];
    if ($head === '') {
        return ['home', $lang];
    }
    return [in_array($head, $known, true) ? $head : 'other', $lang];
}

function beacon_source(string $ref): string
{
    if ($ref === '') {
        return 'direct';
    }
    $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
    if ($host === '' || str_contains($host, 'outfortender.com')) {
        return 'internal';
    }
    foreach (['chatgpt.com', 'openai.com', 'perplexity.ai', 'claude.ai', 'copilot.microsoft.com',
              'gemini.google.com', 'you.com', 'phind.com', 'poe.com', 'grok.com', 'mistral.ai',
              'deepseek.com'] as $ai) {
        if (str_contains($host, $ai)) {
            return 'ai_assistant';
        }
    }
    foreach (['google.', 'bing.com', 'duckduckgo.com', 'yandex.', 'baidu.com', 'ecosia.org',
              'search.brave.com', 'qwant.com', 'startpage.com', 'yahoo.'] as $engine) {
        if (str_contains($host, $engine)) {
            return 'search';
        }
    }
    return 'referral';
}

/** Country from the address, which is used and then forgotten. */
function beacon_country(PDO $pdo): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $packed = @inet_pton($ip);
    if ($packed === false) {
        return '??';
    }
    if (strlen($packed) === 4) {
        $value = sprintf('%u', ip2long($ip));
        $row = $pdo->prepare('SELECT country, end FROM geo4 WHERE start <= :n ORDER BY start DESC LIMIT 1');
        $row->execute([':n' => $value]);
    } else {
        $value = '';
        foreach (str_split(bin2hex($packed), 8) as $chunk) {
            $value .= str_pad(base_convert($chunk, 16, 10), 10, '0', STR_PAD_LEFT);
        }
        $value = substr(str_pad($value, 39, '0', STR_PAD_LEFT), -39);
        $row = $pdo->prepare('SELECT country, end FROM geo6 WHERE start <= :n ORDER BY start DESC LIMIT 1');
        $row->execute([':n' => $value]);
    }
    $found = $row->fetch(PDO::FETCH_NUM);
    return ($found && (string) $value <= (string) $found[1]) ? (string) $found[0] : '??';
}

try {
    $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 4000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS people (
        day TEXT, section TEXT, lang TEXT, country TEXT, source TEXT,
        views INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (day, section, lang, country, source))');

    [$section, $lang] = beacon_section((string) ($_GET['p'] ?? '/'));
    $pdo->prepare(
        'INSERT INTO people (day, section, lang, country, source, views) VALUES (:d,:s,:l,:c,:r,1)
         ON CONFLICT(day, section, lang, country, source) DO UPDATE SET views = views + 1'
    )->execute([
        ':d' => gmdate('Y-m-d'),
        ':s' => $section,
        ':l' => $lang,
        ':c' => beacon_country($pdo),
        ':r' => beacon_source((string) ($_GET['r'] ?? '')),
    ]);
} catch (Throwable $e) {
    // Counting must never break a page.
}

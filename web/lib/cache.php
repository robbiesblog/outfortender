<?php
/**
 * Whole-page cache.
 *
 * Pages change only when an import runs, so there is no point rebuilding them
 * per visitor. Each page is written to disk and replayed until the next import
 * or the TTL, whichever comes first. The database's last import time is part of
 * the key, so a fresh import invalidates everything without a purge step.
 */

declare(strict_types=1);

function oft_cache_dir(): string
{
    $dir = getenv('OFT_CACHE') ?: dirname(dirname(__DIR__)) . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Serve this page from cache if we can, otherwise start recording it.
 * Call once at the top of a page; oft_cache_end() at the bottom.
 */
function oft_cache_start(int $ttl = 900): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    $stamp = oft_value('SELECT ran_at FROM imports ORDER BY id DESC LIMIT 1') ?: 'none';
    $key = sha1(($_SERVER['REQUEST_URI'] ?? '/') . '|' . $stamp);
    $file = oft_cache_dir() . '/' . substr($key, 0, 2) . '/' . $key;

    if (is_readable($file) && (time() - filemtime($file)) < $ttl) {
        header('X-Cache: hit');
        readfile($file);
        exit;
    }

    $GLOBALS['oft_cache_file'] = $file;
    header('X-Cache: miss');
    ob_start();
}

function oft_cache_end(): void
{
    $file = $GLOBALS['oft_cache_file'] ?? null;
    if (!$file || ob_get_level() === 0) {
        return;
    }
    $html = ob_get_contents();
    ob_end_flush();

    if (http_response_code() !== 200 || strlen($html) < 200) {
        return;                       // never cache an error or an empty page
    }
    @mkdir(dirname($file), 0755, true);
    $temp = $file . '.' . getmypid();
    if (file_put_contents($temp, $html) !== false) {
        @rename($temp, $file);        // atomic: a reader never sees a half-written page
    }
}

/** Drop cached pages older than a day. Called by the importer. */
function oft_cache_sweep(int $maxAgeSeconds = 86400): int
{
    $removed = 0;
    foreach (glob(oft_cache_dir() . '/*/*') ?: [] as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAgeSeconds) {
            @unlink($file);
            $removed++;
        }
    }
    return $removed;
}

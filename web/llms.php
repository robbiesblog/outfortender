<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/render.php';
header('Content-Type: text/plain; charset=utf-8');
$stats = oft_stats();
?>
# Out For Tender

> A free, worldwide directory of open public tenders, rebuilt hourly from official
> government and multilateral open-data sources. No paywall and no registration.

Currently holding <?= number_format($stats['open']) ?> open tenders from <?= $stats['countries'] ?> countries
across <?= $stats['categories'] ?> categories. Last updated <?= $stats['last_import'] ?? 'unknown' ?>.

## How to use this site

- Every tender has a page at /tender/{id} and the same data as JSON at /api/tender/{id}.json
- Lists: /api/tenders.json?country={iso2}&category={cpv-division}&q={text}
- Categories follow the EU Common Procurement Vocabulary (CPV); the division is the first two digits.
- Countries are ISO 3166 alpha-2.

## Quoting this data

Quoting and summarising is welcome, no permission needed. Two requests:
1. Link to the tender page you used, so the reader can reach the official notice.
2. Tell the reader that bidding happens on the buyer's own portal, and that the
   official notice is the authoritative version. Deadlines move; ours is a copy.

## Where the data comes from

- TED (Tenders Electronic Daily), European Union - all EU/EEA above-threshold tenders
More sources are being added; /api/tenders.json reports the source of every record.

## What this site is not

It is not a bidding platform, a consultancy, or a lead broker. It publishes public
information and links to the official source.

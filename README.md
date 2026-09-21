# OutForTender.com

Free, global, hourly-updating directory of open public tenders. All categories, all countries
we can reach through open data.

## How it runs

**Hourly, on DreamHost's own cron** (`ops/fetch.sh`): fetch every source, write
the delta, import it. SAM.gov runs separately twice a day because its extract is
225 MB and changes daily. GitHub Actions still runs the same code every six hours
as a backup - it was the primary until measurement showed GitHub skipping most
"hourly" schedules (one run every ~4 hours). Both paths feed the same idempotent,
locked importer, so overlap costs nothing.

The original design, kept below for the Actions path:

    GitHub Actions (hourly)          DreamHost (shared)
    ─────────────────────            ──────────────────
    ingest/run.py                    ops/import.php   (cron, every hour)
      fetch each source                reads ~/incoming/*.ndjson
      normalise to one record          upserts into ~/data/outfortender.sqlite
      write delta NDJSON               marks past-deadline tenders closed
      scp delta to ~/incoming/       web/  serves pages from that database

Heavy work happens on Actions. The server only imports a small delta and serves pages.

## Layout

    ingest/          Python 3.12, no dependencies beyond requests
      sources/       one reader per data source
      record.py      the normalised tender record + reference data
      run.py         orchestrator, writes the delta
    ops/             server-side, outside the web root
      schema.sql     SQLite schema
      import.php     delta importer, run by cron
    web/             the public site (rsynced to the web root)
    tools/deploy.sh  rsync web/ and ops/ to DreamHost

## Local use

    python3 ingest/run.py --since-days 2 --out /tmp/delta.ndjson
    php ops/import.php --db /tmp/oft.sqlite --delta /tmp/delta.ndjson
    php -S localhost:8080 -t web/            # with OFT_DB=/tmp/oft.sqlite

## SAM.gov is read from the bulk extract, not the API

The API needs a key registered to a named person. The Contract Opportunities
extract on SAM.gov's Data Services page does not: it is one public CSV of every
active notice (~225 MB), regenerated daily, served via a signed S3 redirect.
Because it changes once a day it has its own workflow, `daily-sam.yml`, twice a
day, and the hourly job passes `--skip sam` rather than downloading 225 MB every
hour to find the same file.

## Alerts

Free email alerts at `/alerts`: filter by country, category and keywords.

Confirmed opt-in - a row is worthless until the person opens the link, so an
address can never be signed up by somebody else. One-click unsubscribe, no
account, and we store a hash of the sign-up IP rather than the address itself.

`ops/alerts.php` builds the mail. **It does not send by default.** It writes each
message to `~/ops/log/outbox/` for inspection and marks nothing as sent, so the
same run repeats safely. Sending needs `--send`, and should not be switched on
until SPF and DKIM are configured for outfortender.com - a new domain that
starts pushing bulk mail without them burns its reputation quickly.

A daily cron runs it in dry-run mode at 07:25.

## Languages

Published in English (at the root) plus German, French, Spanish, Italian, Polish,
Portuguese and Dutch at `/{lang}/`. Regenerate the reference data with
`python3 tools/build-i18n.py` after adding a language to its LANGS list.

Genuinely in the reader's language: the interface, category names (the EU's own
official CPV wording), country names (CLDR), and a tender's title wherever the
publishing body issued one in that language.

Not translated: the tender's description, which stays in the language the buyer
wrote it in and is labelled as such. **Nothing on this site is machine-translated.**

A caution worth keeping: TED returns notice titles under all 24 EU language
codes, but it only translates the "Country - CPV label - " prefix it adds. The
buyer's own title is left in the original language, so those 24 versions collapse
to one string once the prefix is stripped. Only CanadaBuys publishes genuinely
translated titles and descriptions today.

## Sources

| Source | Covers | Access | Licence |
|---|---|---|---|
| TED | EU/EEA, above threshold | open API, no key | EU reuse terms |
| Find a Tender | UK, above threshold | open API, OCDS | OGL v3.0 |
| Contracts Finder | UK, below threshold | open API, OCDS | OGL v3.0 |
| World Bank | bank-funded work, ~150 countries | open API | CC BY 4.0 |
| CanadaBuys | Canada, federal + provincial | open CSV | OGL - Canada |
| BOAMP | France, below EU threshold | open API | Licence Ouverte (Etalab) |
| SECOP II | Colombia, national platform | open API (Socrata) | Datos Abiertos Colombia |
| SAM.gov | US federal | public bulk CSV, no key | public domain |

Categories: Canada and Colombia classify with UNSPSC, which `ingest/unspsc.py`
bridges to CPV divisions at segment level. The World Bank publishes no product
classification at all, so most of its notices stay uncategorised - only civil
works maps cleanly.

Sources probed and NOT built, with the reason:

| Source | Why not |
|---|---|
| Brazil PNCP | API returned HTTP 500 "Erro na comunicacao com o banco de dados" all day |
| Ukraine Prozorro | the search API carries no deadline or CPV, and the detail API is one request per tender - thousands a day |
| AusTender (AU) | requires an authentication token |
| ADB, IDB | bot protection returns 403/526 to any automated client |
| Ireland eTenders, NZ GETS | no machine-readable feed found, HTML only |

More to follow in Phase 3. Every source must be open data with a licence we can honour;
attribution lives in `web/lib/render.php`.

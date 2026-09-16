# OutForTender.com

Free, global, hourly-updating directory of open public tenders. All categories, all countries
we can reach through open data.

## How it runs

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

## Sources

| Source | Covers | Access |
|---|---|---|
| TED | EU, above threshold | open API, no key |

More to follow in Phase 3. Every source must be open data with a licence we can honour;
attribution lives in `web/lib/render.php`.

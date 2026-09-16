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

| Source | Covers | Access | Licence |
|---|---|---|---|
| TED | EU/EEA, above threshold | open API, no key | EU reuse terms |
| Find a Tender | UK, above threshold | open API, OCDS | OGL v3.0 |
| Contracts Finder | UK, below threshold | open API, OCDS | OGL v3.0 |
| World Bank | bank-funded work, ~150 countries | open API | CC BY 4.0 |
| CanadaBuys | Canada, federal + provincial | open CSV | OGL - Canada |

Known gaps: World Bank and CanadaBuys publish no CPV code, so their tenders carry
no category until a UNSPSC-to-CPV crosswalk lands in Phase 3. Brazil's PNCP is
not built: their API returned "Erro na comunicacao com o banco de dados" (HTTP 500)
on every attempt on 16 Sep 2026, so there was nothing to verify a reader against.

More to follow in Phase 3. Every source must be open data with a licence we can honour;
attribution lives in `web/lib/render.php`.

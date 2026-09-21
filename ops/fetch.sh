#!/bin/sh
# Fetch tenders on the server itself, then import them.
#
#   ~/ops/fetch.sh hourly    every source except SAM.gov, last 2 days
#   ~/ops/fetch.sh sam       SAM.gov's daily extract, last 3 days
#
# This used to run only on GitHub Actions, but GitHub skips scheduled runs under
# load: "hourly" was measured at one run every ~4 hours. DreamHost's cron fires
# on time. Actions stays on as a less frequent backup; the importer is
# idempotent, so the two overlapping costs nothing.

set -u
MODE="${1:-hourly}"
LOG="$HOME/ops/log/fetch.log"
mkdir -p "$HOME/ops/log" "$HOME/incoming"

stamp() { date -u +%Y-%m-%dT%H:%M:%SZ; }

# One run of each mode at a time. A slow run is skipped over, not stacked up.
exec 9>"$HOME/ops/log/fetch-$MODE.lock"
if ! flock -n 9; then
    echo "$(stamp) $MODE: previous run still going, skipped" >> "$LOG"
    exit 0
fi

case "$MODE" in
    sam)    ARGS="--only sam --since-days 3" ;;
    hourly) ARGS="--skip sam --since-days 2" ;;
    *)      echo "unknown mode: $MODE" >&2; exit 2 ;;
esac

OUT="$HOME/incoming/delta-server-$MODE-$(date -u +%Y%m%dT%H%M%SZ).ndjson"
# Written as .part and renamed when complete: the importer only picks up
# *.ndjson, so it can never read half a file.
TMP="$OUT.part"

echo "$(stamp) $MODE: start" >> "$LOG"
python3 "$HOME/ingest/run.py" $ARGS --out "$TMP" >> "$LOG" 2>&1
CODE=$?

if [ -s "$TMP" ]; then
    mv "$TMP" "$OUT"
else
    rm -f "$TMP"
fi

php "$HOME/ops/import.php" >> "$LOG" 2>&1

# Each import changes the cache key, so every page's next visitor would pay for
# a fresh build. Pay it here instead, for the pages most people land on: the
# homepage in every language, the index pages and the busiest countries.
warm() {
    SITE="https://outfortender.com"
    PAGES="/ /de/ /fr/ /es/ /it/ /pl/ /pt/ /nl/ /countries /categories"
    TOP=$(sqlite3 "$HOME/data/outfortender.sqlite" \
        "SELECT lower(country) FROM tenders WHERE status='open' GROUP BY country ORDER BY COUNT(*) DESC LIMIT 12;" 2>/dev/null)
    for c in $TOP; do PAGES="$PAGES /country/$c"; done
    START=$(date +%s); N=0
    for p in $PAGES; do
        curl -s -o /dev/null -m 30 -A "OutForTender-cache-warmer" "$SITE$p" && N=$((N + 1))
    done
    echo "$(stamp) warmed $N pages in $(( $(date +%s) - START ))s" >> "$LOG"
}
warm

case "$CODE" in
    0) echo "$(stamp) $MODE: done" >> "$LOG" ;;
    2) echo "$(stamp) $MODE: done, BUT SOME SOURCES FAILED - see above" >> "$LOG" ;;
    *) echo "$(stamp) $MODE: FETCH FAILED (exit $CODE)" >> "$LOG" ;;
esac

# Keep the log to a sensible size.
if [ "$(wc -c < "$LOG")" -gt 5000000 ]; then
    tail -n 5000 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi
exit "$CODE"

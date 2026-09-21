#!/usr/bin/env python3
"""Fetch every source, normalise, and write one delta file for the server to import.

    python3 ingest/run.py --since-days 2 --out delta.ndjson

Output is newline-delimited JSON: one tender record per line, plus a final
manifest line describing the run. The importer is idempotent, so re-running
over the same window is safe and is how we recover from a missed hour.
"""
from __future__ import annotations

import argparse
import datetime as dt
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import record  # noqa: E402
from sources import (boamp, canadabuys, contracts_finder, find_a_tender,  # noqa: E402
                     sam, secop, ted, worldbank)

SOURCES = {
    "ted": ted,                          # EU, above threshold
    "fts": find_a_tender,                # UK, above threshold
    "cf": contracts_finder,              # UK, below threshold
    "worldbank": worldbank,              # bank-funded work worldwide
    "canadabuys": canadabuys,            # Canada federal and provincial
    "boamp": boamp,                      # France, below EU threshold
    "secop": secop,                      # Colombia, national platform
    "sam": sam,                          # United States federal (daily extract)
}


def main():
    parser = argparse.ArgumentParser(description="Build a tender delta file.")
    parser.add_argument("--since-days", type=int, default=2,
                        help="how far back to look (default 2; overlap is deliberate)")
    parser.add_argument("--out", default="delta.ndjson", help="output file")
    parser.add_argument("--only", help="comma-separated source names")
    parser.add_argument("--skip", help="comma-separated source names to leave out")
    parser.add_argument("--limit", type=int, help="stop after this many records per source")
    args = parser.parse_args()

    wanted = args.only.split(",") if args.only else list(SOURCES)
    if args.skip:
        wanted = [name for name in wanted if name not in args.skip.split(",")]
    started = dt.datetime.now(dt.timezone.utc)
    counts, dropped, failures = {}, {}, {}

    with open(args.out, "w", encoding="utf-8") as handle:
        for name in wanted:
            module = SOURCES.get(name)
            if not module:
                print(f"! unknown source: {name}", file=sys.stderr)
                continue
            print(f"- {name}: fetching")
            kept = bad = 0
            try:
                # Some sources publish late; they set a floor on the window.
                window = max(args.since_days, getattr(module, "MIN_SINCE_DAYS", 0))
                for item in module.fetch(since_days=window, limit=args.limit):
                    problems = record.validate(item)
                    if problems:
                        bad += 1
                        if bad <= 3:
                            print(f"  dropped {item.get('id')}: {', '.join(problems)}")
                        continue
                    handle.write(json.dumps(item, ensure_ascii=False) + "\n")
                    kept += 1
            except Exception as error:
                # One bad source must not sink the run, but it must never pass
                # quietly either: a half-fetched source looks exactly like a
                # quiet day unless we say so.
                failures[name] = str(error)
                print(f"! {name} FAILED after {kept} records: {error}", file=sys.stderr)
            counts[name] = kept
            dropped[name] = bad
            print(f"  {name}: {kept} records, {bad} dropped")

        manifest = {
            "_manifest": True,
            "started_at": started.isoformat(timespec="seconds"),
            "finished_at": dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds"),
            "since_days": args.since_days,
            "counts": counts,
            "dropped": dropped,
            "failures": failures,
            "total": sum(counts.values()),
        }
        handle.write(json.dumps(manifest) + "\n")

    total = sum(counts.values())
    print(f"\n{total} records -> {args.out}")
    if failures:
        print(f"WARNING: {len(failures)} source(s) failed: {', '.join(failures)}", file=sys.stderr)
        for name, error in failures.items():
            print(f"  {name}: {error}", file=sys.stderr)
    # A failed source is worth a red run even when the others delivered: the
    # delta is still imported, but somebody should look.
    if not total:
        return 1
    return 2 if failures else 0


if __name__ == "__main__":
    sys.exit(main())

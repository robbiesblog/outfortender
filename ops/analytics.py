#!/usr/bin/env python3
"""Turn the web server's access logs into traffic figures we can trust.

    python3 ~/ops/analytics.py            # process today and any unfinished days
    python3 ~/ops/analytics.py --all      # reprocess every log still on disk

Why this exists rather than a counter in the page: almost nothing that reaches
this site is a person. On 4-5 October, 19,395 requests arrived carrying a
google.com referrer - and only 18 of them came from an address that ever loaded
the stylesheet. The rest were one scraper farm spoofing the referrer. Any
analytics that counts "sessions with a search referrer" would report 19,000
visits from Google where there were almost none.

So traffic is split three ways, and the dashboard never blurs them:

  people       a browser user agent AND the address loaded a page asset
               (stylesheet). Real browsers fetch the CSS; scrapers rarely do.
  unverified   a browser user agent, but no asset ever fetched. Probably a
               scraper wearing a browser's clothes. Shown, never counted as
               people.
  bots         the user agent says what it is. Broken out by name, because
               "which AI crawlers read us" is a question worth answering.

No IP addresses are stored. The database holds daily counts only.

Country data: IP to Country Lite by DB-IP (https://db-ip.com), CC BY 4.0.
"""
from __future__ import annotations

import argparse
import csv
import datetime as dt
import glob
import gzip
import io
import ipaddress
import os
import re
import sqlite3
import sys
import urllib.request

HOME = os.path.expanduser("~")
LOG_DIR = os.path.join(HOME, "logs/outfortender.com/https")
DB_PATH = os.path.join(HOME, "data/analytics.sqlite")
# DB-IP publish the free country file monthly, but refuse automated downloads
# from this server (403). The ip-location-db project mirrors the same data on a
# CDN as plain CSV, which is also easier to parse.
GEO_SOURCES = [
    ("https://cdn.jsdelivr.net/npm/@ip-location-db/dbip-country/dbip-country-ipv4.csv", 4),
    ("https://cdn.jsdelivr.net/npm/@ip-location-db/dbip-country/dbip-country-ipv6.csv", 6),
]

LINE = re.compile(
    r'^(?P<ip>\S+) \S+ \S+ \[(?P<when>[^\]]+)\] '
    r'"(?P<method>\S+) (?P<path>\S+)[^"]*" (?P<status>\d+) (?P<size>\S+) '
    r'"(?P<ref>[^"]*)" "(?P<ua>[^"]*)"'
)

# --- who is asking -------------------------------------------------------
# Matched against the user agent, first hit wins. Name is what we display.
BOTS = [
    # AI crawlers and assistants: the ones worth watching
    ("ai", "ClaudeBot", ("claudebot",)),
    ("ai", "Claude-User", ("claude-user",)),
    ("ai", "Claude-SearchBot", ("claude-searchbot",)),
    ("ai", "GPTBot", ("gptbot",)),
    ("ai", "ChatGPT-User", ("chatgpt-user",)),
    ("ai", "OAI-SearchBot", ("oai-searchbot",)),
    ("ai", "PerplexityBot", ("perplexitybot",)),
    ("ai", "Perplexity-User", ("perplexity-user",)),
    ("ai", "Google-Extended", ("google-extended",)),
    ("ai", "Applebot-Extended", ("applebot-extended",)),
    ("ai", "Amazonbot", ("amazonbot",)),
    ("ai", "Bytespider", ("bytespider",)),
    ("ai", "meta-externalagent", ("meta-externalagent", "facebookbot")),
    ("ai", "CCBot", ("ccbot",)),
    ("ai", "ExaSearchBot", ("exasearchbot",)),
    ("ai", "Diffbot", ("diffbot",)),
    ("ai", "cohere-ai", ("cohere-ai",)),
    ("ai", "YouBot", ("youbot",)),
    ("ai", "Timpibot", ("timpibot",)),
    ("ai", "ImagesiftBot", ("imagesiftbot",)),
    # Search engines
    ("search", "Googlebot", ("googlebot",)),
    ("search", "Bingbot", ("bingbot", "adidxbot")),
    ("search", "DuckDuckBot", ("duckduckbot", "duckassistbot")),
    ("search", "YandexBot", ("yandexbot", "yandex.com/bots")),
    ("search", "Baiduspider", ("baiduspider",)),
    ("search", "Applebot", ("applebot",)),
    ("search", "Seznam", ("seznambot",)),
    ("search", "Qwantbot", ("qwantbot",)),
    ("search", "Mojeek", ("mojeekbot",)),
    ("search", "Google-InspectionTool", ("google-inspectiontool",)),
    # SEO and marketing crawlers: huge volume, no value to us
    ("seo", "AhrefsBot", ("ahrefsbot",)),
    ("seo", "SERankingBot", ("seranking",)),
    ("seo", "SemrushBot", ("semrushbot",)),
    ("seo", "MJ12bot", ("mj12bot",)),
    ("seo", "DotBot", ("dotbot",)),
    ("seo", "DataForSeoBot", ("dataforseo",)),
    ("seo", "BLEXBot", ("blexbot",)),
    ("seo", "Barkrowler", ("barkrowler",)),
    ("seo", "KeenableBot", ("keenable",)),
    ("seo", "ReflectBot", ("reflect",)),
    ("seo", "QlyzeBot", ("qlyze",)),
    ("seo", "SiteAuditBot", ("siteauditbot",)),
    # Everything else that admits to being a robot
    ("other", "UptimeMonitor", ("uptimerobot", "pingdom", "statuscake", "better uptime")),
    ("other", "DreamHost", ("dreamhost",)),
    ("other", "Feedreader", ("feedly", "inoreader", "rss")),
    ("other", "Scripted", ("python-requests", "curl/", "wget", "go-http-client",
                           "scrapy", "libwww", "java/", "okhttp", "axios", "node-fetch")),
    ("other", "HeadlessBrowser", ("headlesschrome", "phantomjs", "puppeteer", "playwright")),
    ("other", "Prefetch", ("prefetch proxy",)),
]

OURS = ("outfortender-cache-warmer",)

SEARCH_HOSTS = ("google.", "bing.com", "duckduckgo.com", "yandex.", "baidu.com",
                "ecosia.org", "search.brave.com", "qwant.com", "startpage.com",
                "mojeek.com", "yahoo.")
AI_HOSTS = ("chatgpt.com", "chat.openai.com", "perplexity.ai", "claude.ai",
            "copilot.microsoft.com", "gemini.google.com", "bard.google.com",
            "you.com", "phind.com", "poe.com", "duckduckgo.com/aichat",
            "grok.com", "x.ai", "mistral.ai", "deepseek.com")

SCANNER_HINTS = ("/wp-admin", "/wp-login", "/wp-content", "/xmlrpc.php", "/.env",
                 "/phpmyadmin", "/.git", "/vendor/", "/administrator", "/shell",
                 "/autodiscover", "/cgi-bin", "/.aws", "/config.json")

LANG_PREFIXES = ("de", "fr", "es", "it", "pl", "pt", "nl")


def section_of(path):
    bare = path.split("?", 1)[0]
    parts = [p for p in bare.split("/") if p]
    lang = "en"
    if parts and parts[0] in LANG_PREFIXES:
        lang = parts[0]
        parts = parts[1:]
    head = parts[0] if parts else ""
    if head.startswith("assets") or head.endswith((".css", ".js", ".ico", ".png", ".svg")):
        return ("asset", lang)
    for name in ("tender", "country", "category", "search", "api", "alerts",
                 "countries", "categories", "resources", "about", "privacy", "terms", "contact"):
        if head == name:
            return (name, lang)
    if head.startswith("sitemap") or head in ("robots.txt", "llms.txt", "ads.txt"):
        return ("machine", lang)
    if not head:
        return ("home", lang)
    return ("other", lang)


def classify_agent(ua):
    low = ua.lower()
    if any(o in low for o in OURS):
        return ("internal", "cache warmer")
    for kind, name, needles in BOTS:
        if any(n in low for n in needles):
            return (kind, name)
    if "mozilla" in low or "safari" in low or "chrome" in low or "firefox" in low:
        return ("browser", None)
    return ("other", "Unknown agent")


def source_of(ref):
    if not ref or ref == "-":
        return "direct"
    low = ref.lower()
    if "outfortender.com" in low:
        return "internal"
    if any(h in low for h in AI_HOSTS):
        return "ai_assistant"
    if any(h in low for h in SEARCH_HOSTS):
        return "search"
    return "referral"


# --- country lookup ------------------------------------------------------
def ensure_geo(db, log=print):
    """Load the free IP-to-country ranges, once a month."""
    month = dt.date.today().strftime("%Y-%m")
    have = db.execute("SELECT value FROM meta WHERE key = 'geo_month'").fetchone()
    stocked = db.execute("SELECT COUNT(*) FROM geo4").fetchone()[0]
    if have and have[0] == month and stocked:
        return

    rows4, rows6 = [], []
    for url, version in GEO_SOURCES:
        try:
            request = urllib.request.Request(url, headers={"User-Agent": "OutForTender/0.1"})
            raw = urllib.request.urlopen(request, timeout=300).read()
        except Exception as error:
            log(f"  could not fetch {url.rsplit('/', 1)[-1]} ({error})")
            continue
        text = gzip.decompress(raw).decode() if url.endswith(".gz") else raw.decode()
        for row in csv.reader(io.StringIO(text)):
            if len(row) < 3:
                continue
            start, end, country = row[0], row[1], row[2]
            try:
                first, last = ipaddress.ip_address(start), ipaddress.ip_address(end)
            except ValueError:
                continue
            if first.version == 4:
                rows4.append((int(first), int(last), country))
            else:
                rows6.append((f"{int(first):039d}", f"{int(last):039d}", country))

    if not rows4:
        log("  no country data available; countries will show as unknown")
        return

    db.execute("DELETE FROM geo4")
    db.execute("DELETE FROM geo6")
    db.executemany("INSERT OR REPLACE INTO geo4 VALUES (?,?,?)", rows4)
    db.executemany("INSERT OR REPLACE INTO geo6 VALUES (?,?,?)", rows6)
    db.execute("INSERT OR REPLACE INTO meta VALUES ('geo_month', ?)", (month,))
    db.commit()
    log(f"  country ranges: {len(rows4):,} IPv4, {len(rows6):,} IPv6")


class Geo:
    def __init__(self, db):
        self.db = db
        self.cache = {}

    def of(self, ip):
        if ip in self.cache:
            return self.cache[ip]
        country = None
        try:
            address = ipaddress.ip_address(ip)
            if address.version == 4:
                row = self.db.execute(
                    "SELECT country, end FROM geo4 WHERE start <= ? ORDER BY start DESC LIMIT 1",
                    (int(address),)).fetchone()
                if row and int(address) <= row[1]:
                    country = row[0]
            else:
                key = f"{int(address):039d}"
                row = self.db.execute(
                    "SELECT country, end FROM geo6 WHERE start <= ? ORDER BY start DESC LIMIT 1",
                    (key,)).fetchone()
                if row and key <= row[1]:
                    country = row[0]
        except ValueError:
            pass
        self.cache[ip] = country
        return country


SCHEMA = """
CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE IF NOT EXISTS traffic (
    day TEXT, audience TEXT, source TEXT, country TEXT, lang TEXT,
    section TEXT, hits INTEGER, PRIMARY KEY (day, audience, source, country, lang, section));
CREATE TABLE IF NOT EXISTS agents (
    day TEXT, audience TEXT, agent TEXT, hits INTEGER, PRIMARY KEY (day, audience, agent));
CREATE TABLE IF NOT EXISTS pages (
    day TEXT, audience TEXT, path TEXT, hits INTEGER, PRIMARY KEY (day, audience, path));
CREATE TABLE IF NOT EXISTS geo4 (start INTEGER PRIMARY KEY, end INTEGER, country TEXT);
CREATE TABLE IF NOT EXISTS geo6 (start TEXT PRIMARY KEY, end TEXT, country TEXT);
CREATE TABLE IF NOT EXISTS days (
    day TEXT PRIMARY KEY, lines INTEGER, people INTEGER, unverified INTEGER,
    bots INTEGER, processed_at TEXT, complete INTEGER DEFAULT 0);
"""


def open_db():
    os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
    db = sqlite3.connect(DB_PATH)
    db.executescript(SCHEMA)
    return db


def log_files():
    """Every access log still on disk, newest last. The live one has no date."""
    files = []
    for path in sorted(glob.glob(os.path.join(LOG_DIR, "access.log*"))):
        if os.path.islink(path) or "analog" in path:
            continue
        files.append(path)
    return files


def read_lines(path):
    opener = gzip.open if path.endswith(".gz") else open
    with opener(path, "rt", encoding="latin-1", errors="replace") as handle:
        for raw in handle:
            yield raw


def process(db, only_days=None, log=print):
    geo = Geo(db)
    # Pass one: group every line by the day it belongs to.
    by_day = {}
    for path in log_files():
        for raw in read_lines(path):
            match = LINE.match(raw)
            if not match:
                continue
            when = match.group("when")                 # 05/Oct/2026:04:53:23 -0700
            try:
                day = dt.datetime.strptime(when.split(":")[0], "%d/%b/%Y").date().isoformat()
            except ValueError:
                continue
            if only_days and day not in only_days:
                continue
            by_day.setdefault(day, []).append(match)

    today = dt.date.today().isoformat()
    for day, matches in sorted(by_day.items()):
        done = db.execute("SELECT complete FROM days WHERE day = ?", (day,)).fetchone()
        if done and done[0] and day != today:
            continue                                   # a finished past day never changes

        # Pass two: which addresses behaved like a browser? An address that
        # fetched a page asset was almost certainly rendering the page.
        loaded_assets = set()
        for match in matches:
            if match.group("path").startswith("/assets/"):
                loaded_assets.add(match.group("ip"))

        traffic, agents, pages = {}, {}, {}
        people = unverified = bots = 0

        for match in matches:
            ip, path, ua, ref = (match.group("ip"), match.group("path"),
                                 match.group("ua"), match.group("ref"))
            kind, agent = classify_agent(ua)
            section, lang = section_of(path)

            if section == "asset" or kind == "internal":
                continue                               # not a page view
            if any(hint in path.lower() for hint in SCANNER_HINTS):
                audience, agent = "scanner", agent or "Vulnerability scanner"
            elif kind == "browser":
                if ip in loaded_assets:
                    audience, agent = "people", "Browser"
                else:
                    audience, agent = "unverified", "Browser-like"
            elif kind == "ai":
                audience = "ai"
            elif kind == "search":
                audience = "search"
            elif kind == "seo":
                audience = "seo"
            else:
                audience = "other_bot"

            if audience == "people":
                people += 1
            elif audience == "unverified":
                unverified += 1
            else:
                bots += 1

            key = (day, audience, source_of(ref), geo.of(ip) or "??", lang, section)
            traffic[key] = traffic.get(key, 0) + 1
            akey = (day, audience, agent or "Other")
            agents[akey] = agents.get(akey, 0) + 1
            if audience in ("people", "unverified", "ai", "search"):
                pkey = (day, audience, path.split("?", 1)[0][:200])
                pages[pkey] = pages.get(pkey, 0) + 1

        # Keep the page table to the parts anyone reads.
        trimmed = {}
        for audience in {k[1] for k in pages}:
            rows = sorted(((k, v) for k, v in pages.items() if k[1] == audience),
                          key=lambda kv: -kv[1])[:300]
            trimmed.update(dict(rows))

        db.execute("DELETE FROM traffic WHERE day = ?", (day,))
        db.execute("DELETE FROM agents WHERE day = ?", (day,))
        db.execute("DELETE FROM pages WHERE day = ?", (day,))
        db.executemany("INSERT INTO traffic VALUES (?,?,?,?,?,?,?)",
                       [(*k, v) for k, v in traffic.items()])
        db.executemany("INSERT INTO agents VALUES (?,?,?,?)",
                       [(*k, v) for k, v in agents.items()])
        db.executemany("INSERT INTO pages VALUES (?,?,?,?)",
                       [(*k, v) for k, v in trimmed.items()])
        db.execute(
            "INSERT OR REPLACE INTO days VALUES (?,?,?,?,?,?,?)",
            (day, len(matches), people, unverified, bots,
             dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds"),
             1 if day != today else 0))
        db.commit()
        log(f"  {day}: {len(matches):>7,} requests -> {people:>6,} people, "
            f"{unverified:>6,} unverified, {bots:>7,} bots")


def main():
    parser = argparse.ArgumentParser(description="Summarise the access logs.")
    parser.add_argument("--all", action="store_true", help="reprocess every day on disk")
    args = parser.parse_args()

    db = open_db()
    ensure_geo(db)
    if args.all:
        db.execute("UPDATE days SET complete = 0")
        db.commit()
    process(db)
    total = db.execute("SELECT COUNT(*), SUM(people) FROM days").fetchone()
    print(f"analytics: {total[0]} day(s) summarised, {total[1] or 0:,} human page views in total")
    return 0


if __name__ == "__main__":
    sys.exit(main())

"""World Bank procurement notices - bank-funded work across ~150 countries.

Open API, no key, licensed CC BY 4.0. This is how a supplier in Nairobi or Dhaka
finds work funded by the Bank in their own country, and it is the single widest
geographic source we have.

Notices are a mix of awards and live opportunities; we keep the ones that can
still be bid on. There is no CPV code, so most records carry no category - see
the note on procurement_group below.
"""
from __future__ import annotations

import html
import re
import time

import http_client
import record

NAME = "worldbank"
LICENCE = "CC BY 4.0, The World Bank"
ENDPOINT = "https://search.worldbank.org/api/v2/procnotices"
NOTICE_URL = "https://projects.worldbank.org/en/projects-operations/procurement-detail/"
PAGE_SIZE = 100
PACE_SECONDS = 1.0
MAX_PAGES = 40

# Notices that advertise work still open to bidders. Anything with "Award" in
# the name is a result, which belongs on ContractAwarded.com, not here.
OPEN_TYPES = (
    "Invitation for Bids",
    "Request for Expression of Interest",
    "General Procurement Notice",
    "Request for Proposals",
    "Invitation to Prequalify",
)

# The Bank classifies by procurement group, not CPV. Only civil works maps to a
# CPV division cleanly; the rest are too broad to guess, so they stay
# uncategorised rather than wrong.
GROUP_TO_CPV_DIVISION = {"CW": "45"}

LANGUAGES = {"English": "en", "French": "fr", "Spanish": "es", "Portuguese": "pt",
             "Russian": "ru", "Arabic": "ar", "Chinese": "zh"}

_TAGS = re.compile(r"<[^>]+>")


def _text(fragment, limit=4000):
    """World Bank notice bodies are HTML fragments; we want readable text."""
    if not fragment:
        return None
    plain = _TAGS.sub(" ", str(fragment))
    return record.clean(html.unescape(plain), limit=limit)


def _deadline(row):
    date = row.get("submission_deadline_date")
    if not date:
        return None
    date = str(date)[:10]
    clock = str(row.get("submission_deadline_time") or "").strip()
    if re.fullmatch(r"\d{1,2}:\d{2}", clock):
        return f"{date}T{clock.zfill(5)}:00"
    return date


def _published(row):
    raw = str(row.get("noticedate") or "").strip()      # "14-Sep-2026"
    for fmt in ("%d-%b-%Y", "%Y-%m-%d"):
        try:
            return time.strftime("%Y-%m-%d", time.strptime(raw, fmt))
        except ValueError:
            continue
    return None


def to_record(row):
    identifier = row.get("id")
    title = record.clean(row.get("bid_description"), limit=300)
    if not identifier or not title:
        return None

    country, country_name = record.country_by_name(row.get("project_ctry_name"))
    if not country:
        country, country_name = record.country_by_name(row.get("contact_ctry_name"))
    division = GROUP_TO_CPV_DIVISION.get(row.get("procurement_group"))
    _, category = record.category_of(division + "000000") if division else (None, None)
    language = LANGUAGES.get(row.get("notice_lang_name"), "en")

    return record.make(
        id=f"{NAME}:{identifier}",
        source=NAME,
        source_ref=identifier,
        url=f"{NOTICE_URL}{identifier}",
        title=title,
        title_lang=language,
        titles={language: title},
        description=_text(row.get("notice_text")),
        description_lang=language,
        buyer_name=record.clean(row.get("contact_organization") or row.get("project_name")),
        country=country,
        country_name=country_name,
        cpv=None,
        cpv_division=division,
        category=category,
        value_amount=None,
        value_currency=None,
        procedure=record.clean(row.get("procurement_method_name")),
        contract_nature=record.clean(row.get("procurement_group")),
        published_at=_published(row),
        deadline_at=_deadline(row),
    )


def fetch(since_days=2, limit=None, log=print):
    session = http_client.session()

    cutoff = time.time() - since_days * 86400
    seen = kept = 0

    for page in range(MAX_PAGES):
        rows = http_client.get_json(session, ENDPOINT, log=log, params={
            "format": "json", "rows": PAGE_SIZE, "os": page * PAGE_SIZE,
            "srt": "noticedate", "order": "desc",
        }).get("procnotices") or []
        if not rows:
            break
        seen += len(rows)

        oldest_in_window = False
        for row in rows:
            published = _published(row)
            if published:
                stamp = time.mktime(time.strptime(published, "%Y-%m-%d"))
                if stamp >= cutoff:
                    oldest_in_window = True
                else:
                    continue                     # sorted by date, so this is the tail
            if row.get("notice_type") not in OPEN_TYPES:
                continue
            item = to_record(row)
            if item:
                yield item
                kept += 1
                if limit and kept >= limit:
                    return

        if not oldest_in_window:
            break                                 # walked past the window
        time.sleep(PACE_SECONDS)

    log(f"  worldbank: {seen} notices scanned, {kept} open opportunities")

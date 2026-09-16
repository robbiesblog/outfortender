"""TED - Tenders Electronic Daily, the EU's Official Journal supplement.

Open API, no key, no account. Publishes every above-threshold tender in the EU/EEA.
Notice titles come back in all 24 official EU languages, which we keep: that is
free multilingual content for a third of the world's published procurement.

API shape (verified 16 Sep 2026):
    POST https://api.ted.europa.eu/v3/notices/search
    {"query": "...", "fields": [...], "limit": 100, "page": 1}
    -> {"notices": [...], "totalNoticeCount": N, "iterationNextToken": ...}
The API rate-limits aggressively; pace requests and back off on 429.
"""
from __future__ import annotations

import time

import requests

import record

NAME = "ted"
LICENCE = "European Union, reuse permitted"
ENDPOINT = "https://api.ted.europa.eu/v3/notices/search"

# Contract notices: the "this is open, come and bid" notice types.
OPEN_CALL_TYPES = ("cn-standard", "cn-social", "cn-desg")

FIELDS = [
    "publication-number", "notice-title", "notice-type",
    "buyer-name", "buyer-country", "organisation-name-buyer",
    "publication-date", "deadline-receipt-request",
    "classification-cpv", "contract-nature", "procedure-type",
    "description-proc", "total-value", "total-value-cur",
    "estimated-value-proc", "estimated-value-cur-proc",
    "place-of-performance",
]

PAGE_SIZE = 100
PACE_SECONDS = 2.0      # between successful pages
MAX_RETRIES = 5


DASH = "\u2013"   # TED joins title parts with an en dash


def strip_prefix(title, country_name=None, category=None):
    """TED titles read "Country - CPV label - what the buyer actually wants".

    We show country and category as their own fields, so the prefix is noise that
    pushes the real subject out of search results and headlines. Remove it, but
    only when the shape is unmistakable: three or more parts, and the first part
    matching the country we already parsed.
    """
    if not title or DASH not in title:
        return title
    parts = [p.strip() for p in title.split(DASH)]
    if len(parts) < 3:
        return title
    if country_name and parts[0].casefold() != country_name.casefold():
        return title            # not the expected shape, leave it alone
    # Part two is TED's own CPV wording, which is longer than our division label,
    # so we drop it by position rather than by matching text.
    remainder = f" {DASH} ".join(parts[2:]).strip()
    return remainder or title


def _post(payload, session):
    """POST with backoff. TED answers 429 with an HTML body, not JSON."""
    delay = 5.0
    for attempt in range(MAX_RETRIES):
        response = session.post(ENDPOINT, json=payload, timeout=60)
        if response.status_code == 429:
            time.sleep(delay)
            delay *= 2
            continue
        response.raise_for_status()
        return response.json()
    raise RuntimeError(f"TED rate-limited after {MAX_RETRIES} attempts")


def _first(value):
    """TED returns most fields as single-element lists."""
    if isinstance(value, list):
        return value[0] if value else None
    return value


def _pick_language(mapping, prefer="eng"):
    """Multilingual field -> (text, language). Prefer English, fall back to whatever exists."""
    if not isinstance(mapping, dict) or not mapping:
        return (None, None)
    for lang in (prefer, "eng"):
        if mapping.get(lang):
            return (_first(mapping[lang]), lang)
    lang = sorted(mapping)[0]
    return (_first(mapping[lang]), lang)


def _value(notice):
    """Prefer the total value, fall back to the estimate. Never a value without its currency."""
    for amount_field, currency_field in (
        ("total-value", "total-value-cur"),
        ("estimated-value-proc", "estimated-value-cur-proc"),
    ):
        amount = _first(notice.get(amount_field))
        currency = _first(notice.get(currency_field))
        if amount in (None, ""):
            continue
        try:
            amount = float(amount)
        except (TypeError, ValueError):
            continue
        if amount <= 0:
            continue
        return (amount, currency or None)
    return (None, None)


def to_record(notice):
    number = _first(notice.get("publication-number"))
    if not number:
        return None

    country, country_name = record.country_of(_first(notice.get("buyer-country")))
    cpv = _first(notice.get("classification-cpv"))
    division, category = record.category_of(cpv)

    titles_raw = notice.get("notice-title") or {}
    title, title_lang = _pick_language(titles_raw)
    title = strip_prefix(record.clean(title), country_name, category)

    # Other languages carry the same prefix in their own language, so we cannot
    # match on our English names - fall back to the shape alone.
    titles = {}
    for lang, text in titles_raw.items():
        code = record.lang2(lang)
        text = record.clean(_first(text))
        if not (code and text):
            continue
        parts = [p.strip() for p in text.split(DASH)]
        titles[code] = f" {DASH} ".join(parts[2:]).strip() if len(parts) >= 3 else text

    description, description_lang = _pick_language(notice.get("description-proc") or {})

    buyer = notice.get("buyer-name") or notice.get("organisation-name-buyer") or {}
    buyer_name, _ = _pick_language(buyer)

    amount, currency = _value(notice)

    published = _first(notice.get("publication-date"))
    if published:
        published = str(published)[:10]

    return record.make(
        id=f"ted:{number}",
        source=NAME,
        source_ref=number,
        url=f"https://ted.europa.eu/en/notice/{number}/html",
        title=title,
        title_lang=record.lang2(title_lang),
        titles=titles,
        description=record.clean(description, limit=4000),
        description_lang=record.lang2(description_lang),
        buyer_name=record.clean(buyer_name),
        country=country,
        country_name=country_name,
        cpv=cpv,
        cpv_division=division,
        category=category,
        value_amount=amount,
        value_currency=currency,
        procedure=_first(notice.get("procedure-type")),
        contract_nature=_first(notice.get("contract-nature")),
        published_at=published,
        deadline_at=_first(notice.get("deadline-receipt-request")),
    )


def fetch(since_days=2, limit=None, log=print):
    """Yield normalised records for open calls published in the last `since_days` days."""
    query = (
        f"publication-date>=today(-{since_days}) "
        f"AND notice-type IN ({' '.join(OPEN_CALL_TYPES)})"
    )
    session = requests.Session()
    session.headers["User-Agent"] = "OutForTender/0.1 (+https://outfortender.com)"

    page = 1
    seen = 0
    while True:
        payload = {"query": query, "fields": FIELDS, "limit": PAGE_SIZE, "page": page}
        data = _post(payload, session)
        notices = data.get("notices") or []
        total = data.get("totalNoticeCount", 0)
        if page == 1:
            log(f"  ted: {total} notices match, fetching {PAGE_SIZE} at a time")
        if not notices:
            break

        for notice in notices:
            item = to_record(notice)
            if item:
                yield item
                seen += 1
                if limit and seen >= limit:
                    return

        if seen >= total or len(notices) < PAGE_SIZE:
            break
        page += 1
        time.sleep(PACE_SECONDS)

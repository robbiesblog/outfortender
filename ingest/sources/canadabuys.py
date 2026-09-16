"""CanadaBuys - Canadian federal and participating provincial procurement.

Open data CSV under the Open Government Licence - Canada, republished daily.
Every notice carries an English and a French version of both the title and the
description, so Canadian tenders arrive bilingual for free, as EU ones do.

The file is the full set of open notices (~6 MB), not a delta, so the reader
filters by publication and amendment date to keep hourly runs small. Pass a very
large --since-days to backfill everything.
"""
from __future__ import annotations

import csv
import html
import io
import re
import time

import requests

import record

NAME = "canadabuys"
LICENCE = "Open Government Licence - Canada"
FEED = "https://canadabuys.canada.ca/opendata/pub/openTenderNotice-ouvertAvisAppelOffres.csv"

COLUMNS = {
    "title_en": "title-titre-eng",
    "title_fr": "title-titre-fra",
    "reference": "referenceNumber-numeroReference",
    "published": "publicationDate-datePublication",
    "closing": "tenderClosingDate-appelOffresDateCloture",
    "amended": "amendmentDate-dateModification",
    "status": "tenderStatus-appelOffresStatut-eng",
    "category": "procurementCategory-categorieApprovisionnement",
    "method": "procurementMethod-methodeApprovisionnement-eng",
    "buyer": "contractingEntityName-nomEntitContractante-eng",
    "url_en": "noticeURL-URLavis-eng",
    "description_en": "tenderDescription-descriptionAppelOffres-eng",
    "description_fr": "tenderDescription-descriptionAppelOffres-fra",
}

# Canada classifies by its own procurement category and by UNSPSC, neither of
# which maps onto CPV without a crosswalk we do not have yet. Construction is
# unambiguous; everything else stays uncategorised until Phase 3 adds a proper
# UNSPSC-to-CPV mapping, because a wrong category is worse than none.
CATEGORY_TO_CPV_DIVISION = {"*CNST": "45", "CNST": "45"}


def notice_url(reference):
    """CanadaBuys' own page for a notice.

    Over half the feed's rows carry no notice URL, and we will not send a bidder
    to a raw CSV. The site's slug is the reference with punctuation other than
    hyphens deleted, lowercased - so "SSC-26-00034400:T" becomes
    "ssc-26-00034400t" and "PW-_NCS-030-11940" becomes "pw-ncs-030-11940".
    """
    slug = re.sub(r"-+", "-", re.sub(r"[^a-z0-9-]+", "", reference.lower())).strip("-")
    if not slug:
        return None
    return f"https://canadabuys.canada.ca/en/tender-opportunities/tender-notice/{slug}"


_TAGS = re.compile(r"<[^>]+>")


def _text(value, limit=4000):
    """Feed descriptions carry HTML entities and the odd tag."""
    if not value:
        return None
    plain = _TAGS.sub(" ", str(value))
    return record.clean(html.unescape(plain), limit=limit)


def _date(value):
    value = str(value or "").strip()
    return value[:10] if len(value) >= 10 else None


def to_record(row):
    reference = (row.get(COLUMNS["reference"]) or "").strip()
    title_en = record.clean(row.get(COLUMNS["title_en"]))
    title_fr = record.clean(row.get(COLUMNS["title_fr"]))
    title = title_en or title_fr
    if not reference or not title:
        return None

    titles = {code: text for code, text in (("en", title_en), ("fr", title_fr)) if text}
    division = CATEGORY_TO_CPV_DIVISION.get((row.get(COLUMNS["category"]) or "").strip().upper())
    _, category = record.category_of(division + "000000") if division else (None, None)

    closing = (row.get(COLUMNS["closing"]) or "").strip()
    url = (row.get(COLUMNS["url_en"]) or "").strip() or notice_url(reference)
    if not url:
        return None

    return record.make(
        id=f"{NAME}:{reference}",
        source=NAME,
        source_ref=reference,
        url=url,
        title=title,
        title_lang="en" if title_en else "fr",
        titles=titles,
        description=_text(row.get(COLUMNS["description_en"])
                          or row.get(COLUMNS["description_fr"])),
        description_lang="en" if row.get(COLUMNS["description_en"]) else "fr",
        buyer_name=record.clean(row.get(COLUMNS["buyer"])),
        country="CA",
        country_name="Canada",
        cpv=None,
        cpv_division=division,
        category=category,
        value_amount=None,
        value_currency=None,
        procedure=record.clean(row.get(COLUMNS["method"])),
        contract_nature=None,
        published_at=_date(row.get(COLUMNS["published"])),
        deadline_at=closing or None,
    )


def fetch(since_days=2, limit=None, log=print):
    session = requests.Session()
    session.headers["User-Agent"] = "OutForTender/0.1 (+https://outfortender.com)"

    response = session.get(FEED, timeout=180)
    response.raise_for_status()
    response.encoding = "utf-8-sig"

    cutoff = time.strftime("%Y-%m-%d", time.gmtime(time.time() - since_days * 86400))
    today = time.strftime("%Y-%m-%d", time.gmtime())
    reader = csv.DictReader(io.StringIO(response.text))

    seen = kept = 0
    for row in reader:
        seen += 1
        if (row.get(COLUMNS["status"]) or "").strip().lower() not in ("open", ""):
            continue
        closing = _date(row.get(COLUMNS["closing"]))
        if closing and closing < today:
            continue
        touched = max(_date(row.get(COLUMNS["published"])) or "",
                      _date(row.get(COLUMNS["amended"])) or "")
        if touched and touched < cutoff:
            continue              # unchanged since our window: the server already has it
        item = to_record(row)
        if item:
            yield item
            kept += 1
            if limit and kept >= limit:
                return

    log(f"  canadabuys: {seen} rows in feed, {kept} new or amended in window")

"""UK Contracts Finder - below-threshold UK public procurement.

Open API, no key, OCDS 1.1, Open Government Licence v3.0. Complements Find a
Tender: the same country, the smaller contracts. A handful of notices appear on
both, which the OCID-based id keeps separate and the site shows as two entries
from two registers - they genuinely are two notices.
"""
from __future__ import annotations

import time

import requests

import record
from . import ocds

NAME = "cf"
LICENCE = "Open Government Licence v3.0"
ENDPOINT = "https://www.contractsfinder.service.gov.uk/Published/Notices/OCDS/Search"
NOTICE_URL = "https://www.contractsfinder.service.gov.uk/notice/"
PAGE_SIZE = 100
PACE_SECONDS = 1.0
MAX_PAGES = 40


def to_record(release):
    tender = release.get("tender") or {}
    title = record.clean(tender.get("title"))
    if not title:
        return None

    ocid = release.get("ocid") or release.get("id")
    # Contracts Finder's public notice pages are keyed by the tender id.
    reference = tender.get("id") or ocid
    country, country_name = ocds.country_of(release, tender, default="GBR")
    cpv = ocds.cpv_of(tender)
    division, category = record.category_of(cpv)
    amount, currency = ocds.value_of(tender)

    return record.make(
        id=f"{NAME}:{ocid}",
        source=NAME,
        source_ref=reference,
        url=f"{NOTICE_URL}{reference}",
        title=title,
        title_lang="en",
        titles={"en": title},
        description=record.clean(tender.get("description"), limit=4000),
        description_lang="en",
        buyer_name=record.clean((release.get("buyer") or {}).get("name")),
        country=country,
        country_name=country_name,
        cpv=cpv,
        cpv_division=division,
        category=category,
        value_amount=amount,
        value_currency=currency,
        procedure=tender.get("procurementMethod"),
        contract_nature=tender.get("mainProcurementCategory"),
        published_at=(tender.get("datePublished") or release.get("date") or "")[:10] or None,
        deadline_at=ocds.deadline_of(tender),
    )


def fetch(since_days=2, limit=None, log=print):
    session = requests.Session()
    session.headers["User-Agent"] = "OutForTender/0.1 (+https://outfortender.com)"

    since = time.strftime("%Y-%m-%dT%H:%M:%S", time.gmtime(time.time() - since_days * 86400))
    params = {"publishedFrom": since, "stages": "tender", "size": PAGE_SIZE}
    url = ENDPOINT
    seen = kept = 0

    for page in range(MAX_PAGES):
        response = session.get(url, params=params if page == 0 else None, timeout=60)
        response.raise_for_status()
        data = response.json()
        releases = data.get("releases") or []
        if not releases:
            break
        seen += len(releases)

        for release in releases:
            if not ocds.is_open_tender(release):
                continue
            item = to_record(release)
            if item:
                yield item
                kept += 1
                if limit and kept >= limit:
                    return

        url = (data.get("links") or {}).get("next")
        if not url:
            break
        time.sleep(PACE_SECONDS)

    log(f"  cf: {seen} releases scanned, {kept} open tenders")

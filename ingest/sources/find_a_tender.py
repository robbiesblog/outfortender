"""UK Find a Tender Service - above-threshold UK public procurement.

Open API, no key. OCDS 1.1. Licensed under the Open Government Licence v3.0,
which requires attribution; the credit line lives in web/lib/render.php.

The endpoint returns everything that changed in a window - awards, planning
notices and tenders mixed together - so we filter to live tender stages.
Pagination follows links.next, which carries a cursor.
"""
from __future__ import annotations

import time

import requests

import record
from . import ocds

NAME = "fts"
LICENCE = "Open Government Licence v3.0"
ENDPOINT = "https://www.find-tender.service.gov.uk/api/1.0/ocdsReleasePackages"
NOTICE_URL = "https://www.find-tender.service.gov.uk/Notice/"
PAGE_SIZE = 100
PACE_SECONDS = 1.0
MAX_PAGES = 60


def to_record(release):
    tender = release.get("tender") or {}
    title = record.clean(tender.get("title"))
    if not title:
        return None

    ocid = release.get("ocid") or release.get("id")
    country, country_name = ocds.country_of(release, tender, default="GBR")
    cpv = ocds.cpv_of(tender)
    division, category = record.category_of(cpv)
    amount, currency = ocds.value_of(tender)
    buyer = (release.get("buyer") or {}).get("name")

    return record.make(
        id=f"{NAME}:{ocid}",
        source=NAME,
        source_ref=ocid,
        url=f"{NOTICE_URL}{ocid}",
        title=title,
        title_lang="en",
        titles={"en": title},
        description=record.clean(tender.get("description"), limit=4000),
        description_lang="en",
        buyer_name=record.clean(buyer),
        country=country,
        country_name=country_name,
        cpv=cpv,
        cpv_division=division,
        category=category,
        value_amount=amount,
        value_currency=currency,
        procedure=tender.get("procurementMethod"),
        contract_nature=tender.get("mainProcurementCategory"),
        published_at=(release.get("date") or "")[:10] or None,
        deadline_at=ocds.deadline_of(tender),
    )


def fetch(since_days=2, limit=None, log=print):
    session = requests.Session()
    session.headers["User-Agent"] = "OutForTender/0.1 (+https://outfortender.com)"

    since = time.strftime("%Y-%m-%dT%H:%M:%S", time.gmtime(time.time() - since_days * 86400))
    url = f"{ENDPOINT}?updatedFrom={since}&limit={PAGE_SIZE}"
    seen = kept = 0

    for page in range(MAX_PAGES):
        response = session.get(url, timeout=60)
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

    log(f"  fts: {seen} releases scanned, {kept} open tenders")

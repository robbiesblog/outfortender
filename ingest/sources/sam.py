"""SAM.gov Contract Opportunities - United States federal procurement.

Read from the public bulk extract, not the API. The API needs a key registered
to a named person; the extract is published openly on SAM.gov's Data Services
page and needs nothing at all. It is one CSV of every active notice (~225 MB,
~80,000 rows), regenerated daily, and served through a signed S3 redirect.

Because the file is regenerated once a day, this source runs from its own daily
workflow rather than the hourly one. Downloading 225 MB every hour to find the
same file would be rude to the people paying for the bandwidth.

What counts as an opportunity: solicitations, combined synopsis/solicitations,
presolicitations and sources-sought notices that still have a response deadline
in the future. Award notices belong on a results site, not here.

US federal works are in the public domain.
"""
from __future__ import annotations

import csv
import datetime as dt
import io
import sys
import tempfile

import http_client
import psc
import record

NAME = "sam"
LICENCE = "US Government work, public domain"
EXTRACT = ("https://sam.gov/api/prod/fileextractservices/v1/api/download/"
           "Contract%20Opportunities/datagov/ContractOpportunitiesFullCSV.csv?privacy=Public")
NOTICE_URL = "https://sam.gov/opp/{id}/view"

OPEN_TYPES = ("Solicitation", "Combined Synopsis/Solicitation",
              "Presolicitation", "Sources Sought")

csv.field_size_limit(sys.maxsize)       # descriptions can be very long


def _when(value):
    """SAM mixes '2026-09-24T09:00:00-06:00' and '2026-09-20 14:19:35'."""
    value = str(value or "").strip()
    if not value:
        return None
    try:
        return dt.datetime.fromisoformat(value.replace(" ", "T"))
    except ValueError:
        try:
            return dt.datetime.fromisoformat(value[:10])
        except ValueError:
            return None


def _utc(when):
    if when is None:
        return None
    return when if when.tzinfo else when.replace(tzinfo=dt.timezone.utc)


_SMALL_WORDS = {"of", "and", "the", "for", "on", "in", "at", "to"}


def _agency_case(text):
    """SAM shouts agency names: "AGRICULTURE, DEPARTMENT OF" -> "Agriculture, Department of"."""
    words = text.lower().split(" ")
    return " ".join(w if (i and w in _SMALL_WORDS) else w[:1].upper() + w[1:]
                    for i, w in enumerate(words))


def to_record(row, now):
    notice = (row.get("NoticeId") or "").strip()
    title = record.clean(row.get("Title"), limit=300)
    if not notice or not title:
        return None
    if row.get("Type") not in OPEN_TYPES or row.get("Active") != "Yes":
        return None

    deadline = _when(row.get("ResponseDeadLine"))
    if deadline is None or _utc(deadline) <= now:
        return None

    description = row.get("Description") or ""
    if description.startswith("http"):
        description = ""          # a handful of rows carry an API link, not text

    buyer = (record.clean(row.get("Office"))
             or record.clean(row.get("Sub-Tier"))
             or record.clean(row.get("Department/Ind.Agency")))
    agency = record.clean(row.get("Department/Ind.Agency"))
    if buyer and agency and buyer != agency:
        buyer = f"{buyer}, {_agency_case(agency)}"

    division = psc.to_cpv_division(row.get("ClassificationCode"))
    _, category = record.category_of(division + "000000") if division else (None, None)

    procedure = row.get("Type")
    if (row.get("SetASide") or "").strip():
        procedure = f"{procedure} - {row['SetASide'].strip()}"

    return record.make(
        id=f"{NAME}:{notice}",
        source=NAME,
        source_ref=(row.get("Sol#") or notice).strip(),
        url=NOTICE_URL.format(id=notice),
        title=title,
        title_lang="en",
        titles={"en": title},
        description=record.clean(description, limit=4000),
        description_lang="en",
        buyer_name=buyer,
        country="US",                       # a US federal buyer, wherever the work is
        country_name="United States",
        cpv=None,                           # PSC upstream; only the division is ours
        cpv_division=division,
        category=category,
        value_amount=None,                  # SAM publishes values only on awards
        value_currency=None,
        procedure=record.clean(procedure),
        contract_nature=record.clean(row.get("NaicsCode")),
        published_at=(row.get("PostedDate") or "")[:10] or None,
        deadline_at=deadline.isoformat(),
    )


def fetch(since_days=2, limit=None, log=print):
    session = http_client.session()
    now = dt.datetime.now(dt.timezone.utc)
    cutoff = (now - dt.timedelta(days=since_days)).date().isoformat()

    # Descriptions contain newlines inside quoted fields, so the file has to be
    # read by the csv module from a real file rather than split into lines.
    with tempfile.TemporaryFile() as spool:
        response = http_client.request(session, "GET", EXTRACT, timeout=600, log=log, stream=True)
        for chunk in response.iter_content(chunk_size=1 << 20):
            spool.write(chunk)
        spool.seek(0)
        # The extract is not valid UTF-8 throughout; latin-1 never fails, and
        # the few mis-decoded characters are in free text, not in the fields
        # we filter on.
        text = io.TextIOWrapper(spool, encoding="latin-1", newline="")

        seen = kept = 0
        for row in csv.DictReader(text):
            seen += 1
            if (row.get("PostedDate") or "")[:10] < cutoff:
                continue
            item = to_record(row, now)
            if item:
                yield item
                kept += 1
                if limit and kept >= limit:
                    break

    log(f"  sam: {seen:,} notices in the extract, {kept:,} open opportunities posted in window")

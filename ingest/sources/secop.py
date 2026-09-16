"""SECOP II - Colombia's public procurement platform.

Open data through datos.gov.co (Socrata), no key needed for the volumes we use.
Colombia publishes an unusually complete feed: every process, its phase, its
estimated price, and a link straight into the public SECOP portal.

We take processes in the "Presentación de oferta" phase - the window where
bidders can actually submit. Earlier phases are drafts and later ones are
decisions, neither of which a supplier can act on.

Two further filters, both learned from the data rather than the documentation.
Most rows in that phase are "contratación directa" or "régimen especial":
awarded-by-negotiation contracts whose "procedure name" is the contractor's own
name, so they read as "ROCIO MARGARITA DIAZ REYES" rather than as a piece of
work anyone can bid for. And a process with no submission deadline cannot be
bid on. Excluding both turns roughly 11,000 rows a week into about 900 real
opportunities.

Categories come from UNSPSC, which unspsc.py bridges to CPV.
"""
from __future__ import annotations

import time

import http_client
import record
import unspsc

NAME = "secop"
LICENCE = "Datos Abiertos Colombia"
ENDPOINT = "https://www.datos.gov.co/resource/p6dx-8zbt.json"
PAGE_SIZE = 500
MAX_PAGES = 40
PACE_SECONDS = 0.5

# The phase in which a supplier can still put in an offer.
OPEN_PHASE = "Presentación de oferta"

# Modalities where the "process" is a negotiated award, not a call for bids.
CLOSED_MODALITIES = ("Contratación directa", "Contratación régimen especial")


def _date(value):
    value = str(value or "").strip()
    return value[:19] if len(value) >= 10 else None


def _url(row):
    link = row.get("urlproceso")
    if isinstance(link, dict):
        link = link.get("url")
    link = str(link or "").strip()
    return link or None


def to_record(row):
    reference = (row.get("id_del_proceso") or "").strip()
    deadline = _date(row.get("fecha_de_recepcion_de"))
    url = _url(row)
    # The description is the contract's actual object; the "procedure name" is
    # sometimes just an internal reference, so prefer whichever is more telling.
    name = record.clean(row.get("nombre_del_procedimiento"), limit=300)
    about = record.clean(row.get("descripci_n_del_procedimiento"), limit=300)
    title = name if (name and len(name) >= 25) else (about or name)
    if not reference or not title or not url or not deadline:
        return None

    division = unspsc.to_cpv_division(row.get("codigo_principal_de_categoria"))
    _, category = record.category_of(division + "000000") if division else (None, None)

    amount = None
    try:
        amount = float(row.get("precio_base"))
    except (TypeError, ValueError):
        amount = None
    if amount is not None and amount <= 0:
        amount = None

    description = record.clean(row.get("descripci_n_del_procedimiento"), limit=4000)
    if description and description.casefold() == title.casefold():
        description = None          # several rows repeat the title verbatim

    return record.make(
        id=f"{NAME}:{reference}",
        source=NAME,
        source_ref=reference,
        url=url,
        title=title,
        title_lang="es",
        titles={"es": title},
        description=description,
        description_lang="es",
        buyer_name=record.clean(row.get("entidad")),
        country="CO",
        country_name="Colombia",
        cpv=None,                   # UNSPSC upstream: only the division is ours
        cpv_division=division,
        category=category,
        value_amount=amount,
        value_currency="COP" if amount else None,
        procedure=record.clean(row.get("modalidad_de_contratacion")),
        contract_nature=None,
        published_at=(_date(row.get("fecha_de_publicacion_del")) or "")[:10] or None,
        deadline_at=deadline,
    )


def fetch(since_days=2, limit=None, log=print):
    session = http_client.session()
    since = time.strftime("%Y-%m-%dT00:00:00", time.gmtime(time.time() - since_days * 86400))
    excluded = ",".join(f"'{m}'" for m in CLOSED_MODALITIES)
    where = (f"fecha_de_publicacion_del > '{since}' AND fase = '{OPEN_PHASE}' "
             f"AND fecha_de_recepcion_de IS NOT NULL "
             f"AND modalidad_de_contratacion NOT IN ({excluded})")

    kept = seen = 0
    for page in range(MAX_PAGES):
        rows = http_client.get_json(session, ENDPOINT, log=log, params={
            "$where": where,
            "$order": "fecha_de_publicacion_del DESC",
            "$limit": PAGE_SIZE,
            "$offset": page * PAGE_SIZE,
        })
        if not rows:
            break
        seen += len(rows)

        for row in rows:
            item = to_record(row)
            if item:
                yield item
                kept += 1
                if limit and kept >= limit:
                    return

        if len(rows) < PAGE_SIZE:
            break
        time.sleep(PACE_SECONDS)

    log(f"  secop: {seen} Colombian processes scanned, {kept} open for offers")

"""BOAMP - the French national bulletin of public procurement notices.

Open data through the government's Opendatasoft portal, no key. This fills the
gap TED leaves in France: contracts below the EU thresholds, published nationally
and never sent to Brussels.

Two deliberate filters:
  - notices in the JOUE family are the ones France also sends to TED, so we skip
    them and let the TED reader handle them. Otherwise every large French
    contract would appear twice, from two sources, with different references.
  - only "Avis de marché" - a live call for bids. Results, corrections and
    cancellations are other notice types.

The flat record carries no CPV code, but the full notice is embedded in the
"donnees" field. Two schemas live in there: national notices (FNS, MAPA, DSP)
use BOAMP's own shape, with the CPV under codeCPV/objetPrincipal/classPrincipale;
notices sent to the EU use eForms, with MainCommodityClassification. We read
both, because the family mix changes over time and a reader that only knew one
would quietly lose categories.
"""
from __future__ import annotations

import json
import time

import http_client
import record

NAME = "boamp"
LICENCE = "Licence Ouverte / Open Licence (Etalab)"
ENDPOINT = ("https://boamp-datadila.opendatasoft.com/api/explore/v2.1"
            "/catalog/datasets/boamp/records")
NOTICE_URL = "https://www.boamp.fr/avis/detail/"
PAGE_SIZE = 100
MAX_OFFSET = 9900          # the Explore API refuses to page beyond 10,000
PACE_SECONDS = 0.5


def _walk(node):
    """Every dict in a nested structure, in no particular order."""
    if isinstance(node, dict):
        yield node
        for value in node.values():
            yield from _walk(value)
    elif isinstance(node, list):
        for value in node:
            yield from _walk(value)


def _blob(row):
    raw = row.get("donnees")
    if not raw:
        return None
    if isinstance(raw, dict):
        return raw
    try:
        return json.loads(raw)
    except (ValueError, TypeError):
        return None


def _digits(value):
    code = "".join(c for c in str(value or "") if c.isdigit())
    return code[:8] if len(code) >= 8 else None


def cpv_of(blob):
    """The CPV code, in whichever of the two schemas this notice uses."""
    if not blob:
        return None
    for node in _walk(blob):
        # National notices: codeCPV/objetPrincipal/classPrincipale
        cpv = node.get("codeCPV")
        if isinstance(cpv, dict):
            principal = cpv.get("objetPrincipal")
            if isinstance(principal, dict):
                code = _digits(principal.get("classPrincipale"))
                if code:
                    return code
            for candidate in _walk(cpv):
                for value in candidate.values():
                    code = _digits(value) if isinstance(value, str) else None
                    if code:
                        return code
        # EU notices: eForms MainCommodityClassification
        classification = node.get("cac:MainCommodityClassification")
        if isinstance(classification, dict):
            code = classification.get("cbc:ItemClassificationCode")
            if isinstance(code, dict):
                code = code.get("#text")
            code = _digits(code)
            if code:
                return code
    return None


def description_of(blob, limit=4000):
    """The longest description in the notice - lots carry their own, shorter ones."""
    best = None
    for node in _walk(blob or {}):
        for key in ("description", "cbc:Description"):
            text = node.get(key)
            if isinstance(text, dict):
                text = text.get("#text")
            if isinstance(text, str) and (best is None or len(text) > len(best)):
                best = text
    return record.clean(best, limit=limit)


def to_record(row):
    reference = (row.get("idweb") or "").strip()
    title = record.clean(row.get("objet"), limit=300)
    if not reference or not title:
        return None

    blob = _blob(row)
    cpv = cpv_of(blob)
    division, category = record.category_of(cpv)

    deadline = row.get("datelimitereponse") or None
    published = (row.get("dateparution") or "")[:10] or None

    return record.make(
        id=f"{NAME}:{reference}",
        source=NAME,
        source_ref=reference,
        url=f"{NOTICE_URL}{reference}",
        title=title,
        title_lang="fr",
        titles={"fr": title},
        description=description_of(blob),
        description_lang="fr",
        buyer_name=record.clean(row.get("nomacheteur")),
        country="FR",
        country_name="France",
        cpv=cpv,
        cpv_division=division,
        category=category,
        value_amount=None,          # BOAMP does not publish an estimated value
        value_currency=None,
        procedure=record.clean(row.get("procedure_libelle")),
        contract_nature=record.clean((row.get("type_marche") or [None])[0]
                                     if isinstance(row.get("type_marche"), list)
                                     else row.get("type_marche")),
        published_at=published,
        deadline_at=deadline,
    )


def fetch(since_days=2, limit=None, log=print):
    session = http_client.session()
    since = time.strftime("%Y-%m-%d", time.gmtime(time.time() - since_days * 86400))
    where = (f"dateparution >= '{since}' AND famille != 'JOUE' "
             f"AND nature_libelle = 'Avis de marché'")

    kept = seen = 0
    for offset in range(0, MAX_OFFSET, PAGE_SIZE):
        data = http_client.get_json(session, ENDPOINT, log=log, params={
            "where": where,
            "order_by": "dateparution desc",
            "limit": PAGE_SIZE,
            "offset": offset,
        })
        rows = data.get("results") or []
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

    log(f"  boamp: {seen} national notices scanned, {kept} kept (JOUE excluded - TED has those)")

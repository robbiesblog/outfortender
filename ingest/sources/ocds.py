"""Helpers shared by every Open Contracting Data Standard source.

OCDS is the same shape wherever it is published, which is why one helper serves
both UK portals and will serve Ukraine, Colombia, Paraguay and the rest in
Phase 3. What varies between publishers is where they put the CPV code and how
they express value, so those get tolerant readers rather than a fixed path.
"""
from __future__ import annotations

import record

CPV_PATHS = ("classification", "additionalClassifications")


def _classifications(node):
    """Yield every classification dict hanging off a tender or item."""
    if not isinstance(node, dict):
        return
    for key in CPV_PATHS:
        value = node.get(key)
        if isinstance(value, dict):
            yield value
        elif isinstance(value, list):
            for entry in value:
                if isinstance(entry, dict):
                    yield entry


def cpv_of(tender):
    """Find a CPV code wherever this publisher chose to put it."""
    if not isinstance(tender, dict):
        return None
    candidates = list(_classifications(tender))
    for item in tender.get("items") or []:
        candidates.extend(_classifications(item))
    for entry in candidates:
        scheme = str(entry.get("scheme") or "").upper()
        code = str(entry.get("id") or "").strip()
        if code and scheme in ("CPV", "CPVS"):
            return code
    # Some publishers omit the scheme but still use CPV's 8-digit shape.
    for entry in candidates:
        code = str(entry.get("id") or "").strip()
        if len(code) == 8 and code.isdigit():
            return code
    return None


def value_of(tender):
    """(amount, currency) from an OCDS value block, ignoring zero and negatives."""
    for key in ("value", "minValue"):
        block = tender.get(key)
        if not isinstance(block, dict):
            continue
        amount = block.get("amount")
        if amount is None:
            amount = block.get("amountGross")
        try:
            amount = float(amount)
        except (TypeError, ValueError):
            continue
        if amount > 0:
            return (amount, block.get("currency"))
    return (None, None)


def deadline_of(tender):
    period = tender.get("tenderPeriod")
    if isinstance(period, dict):
        return period.get("endDate")
    return None


def country_of(release, tender, default=None):
    """Country from the delivery address, the buyer's address, or a default."""
    for item in tender.get("items") or []:
        for address in item.get("deliveryAddresses") or []:
            code, name = record.country_of(address.get("country"))
            if code:
                return (code, name)
            code, name = record.country_by_name(address.get("countryName"))
            if code:
                return (code, name)
    for party in release.get("parties") or []:
        address = party.get("address") or {}
        code, name = record.country_of(address.get("countryName") or address.get("country"))
        if code:
            return (code, name)
    return record.country_of(default) if default else (None, None)


def is_open_tender(release):
    """True when this release advertises a tender that can still be bid on."""
    tags = release.get("tag") or []
    if not any(tag in ("tender", "tenderUpdate") for tag in tags):
        return False
    tender = release.get("tender") or {}
    status = str(tender.get("status") or "").lower()
    if status in ("cancelled", "unsuccessful", "complete", "withdrawn"):
        return False
    return bool(deadline_of(tender))

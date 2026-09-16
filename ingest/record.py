"""The normalised tender record, and the reference data every source maps onto.

One record shape for every source in the world. A source reader's only job is to
produce these dicts; nothing downstream knows where a tender came from.
"""
from __future__ import annotations

import hashlib
import json
import re

FIELDS = (
    "id", "source", "source_ref", "url",
    "title", "title_lang", "titles_json",
    "description", "description_lang",
    "buyer_name", "country", "country_name",
    "cpv", "cpv_division", "category",
    "value_amount", "value_currency",
    "procedure", "contract_nature",
    "published_at", "deadline_at",
    "content_hash",
)

# ISO 3166 alpha-3 -> (alpha-2, English name). Covers the EU/EEA plus the countries
# our current and near-term sources publish for. Unknown codes pass through as-is.
COUNTRIES = {
    "AUT": ("AT", "Austria"), "BEL": ("BE", "Belgium"), "BGR": ("BG", "Bulgaria"),
    "HRV": ("HR", "Croatia"), "CYP": ("CY", "Cyprus"), "CZE": ("CZ", "Czechia"),
    "DNK": ("DK", "Denmark"), "EST": ("EE", "Estonia"), "FIN": ("FI", "Finland"),
    "FRA": ("FR", "France"), "DEU": ("DE", "Germany"), "GRC": ("GR", "Greece"),
    "HUN": ("HU", "Hungary"), "IRL": ("IE", "Ireland"), "ITA": ("IT", "Italy"),
    "LVA": ("LV", "Latvia"), "LTU": ("LT", "Lithuania"), "LUX": ("LU", "Luxembourg"),
    "MLT": ("MT", "Malta"), "NLD": ("NL", "Netherlands"), "POL": ("PL", "Poland"),
    "PRT": ("PT", "Portugal"), "ROU": ("RO", "Romania"), "SVK": ("SK", "Slovakia"),
    "SVN": ("SI", "Slovenia"), "ESP": ("ES", "Spain"), "SWE": ("SE", "Sweden"),
    "ISL": ("IS", "Iceland"), "LIE": ("LI", "Liechtenstein"), "NOR": ("NO", "Norway"),
    "CHE": ("CH", "Switzerland"), "GBR": ("GB", "United Kingdom"),
    "USA": ("US", "United States"), "CAN": ("CA", "Canada"), "BRA": ("BR", "Brazil"),
    "UKR": ("UA", "Ukraine"), "TUR": ("TR", "Turkey"), "SRB": ("RS", "Serbia"),
    "MKD": ("MK", "North Macedonia"), "ALB": ("AL", "Albania"), "MNE": ("ME", "Montenegro"),
    "BIH": ("BA", "Bosnia and Herzegovina"), "MDA": ("MD", "Moldova"), "GEO": ("GE", "Georgia"),
    "AUS": ("AU", "Australia"), "NZL": ("NZ", "New Zealand"), "IND": ("IN", "India"),
    "ZAF": ("ZA", "South Africa"), "KEN": ("KE", "Kenya"), "NGA": ("NG", "Nigeria"),
    "KHM": ("KH", "Cambodia"), "IDN": ("ID", "Indonesia"), "PHL": ("PH", "Philippines"),
    "VNM": ("VN", "Vietnam"), "BGD": ("BD", "Bangladesh"), "PAK": ("PK", "Pakistan"),
    "EGY": ("EG", "Egypt"), "MAR": ("MA", "Morocco"), "MEX": ("MX", "Mexico"),
    "COL": ("CO", "Colombia"), "CHL": ("CL", "Chile"), "ARG": ("AR", "Argentina"),
    "PER": ("PE", "Peru"), "PRY": ("PY", "Paraguay"), "JPN": ("JP", "Japan"),
    "KOR": ("KR", "South Korea"), "CHN": ("CN", "China"), "1A0": ("XK", "Kosovo"),
}

# CPV divisions: the first two digits of the EU Common Procurement Vocabulary.
# This is our master category taxonomy - other sources' codes map onto it.
# The official vocabulary is published in 24 languages, which is where the
# multilingual category names come from in Phase 2.
CPV_DIVISIONS = {
    "03": "Agriculture, farming, fishing and forestry",
    "09": "Petroleum, fuel, electricity and other energy",
    "14": "Mining, metals and minerals",
    "15": "Food, beverages and tobacco",
    "16": "Agricultural machinery",
    "18": "Clothing, footwear and luggage",
    "19": "Leather, textiles, plastics and rubber",
    "22": "Printed matter and related products",
    "24": "Chemicals",
    "30": "Office and computing machinery",
    "31": "Electrical machinery and apparatus",
    "32": "Radio, television and communications equipment",
    "33": "Medical equipment, pharmaceuticals and personal care",
    "34": "Transport equipment",
    "35": "Security, firefighting, police and defence equipment",
    "37": "Musical instruments, sport goods, games and toys",
    "38": "Laboratory, optical and precision equipment",
    "39": "Furniture, furnishings and cleaning products",
    "41": "Collected and purified water",
    "42": "Industrial machinery",
    "43": "Machinery for mining, quarrying and construction",
    "44": "Construction structures, materials and products",
    "45": "Construction work",
    "48": "Software packages and information systems",
    "50": "Repair and maintenance services",
    "51": "Installation services",
    "55": "Hotel, restaurant and retail trade services",
    "60": "Transport services",
    "63": "Supporting transport services, travel agency services",
    "64": "Postal and telecommunications services",
    "65": "Public utilities",
    "66": "Financial and insurance services",
    "70": "Real estate services",
    "71": "Architectural, construction and engineering services",
    "72": "IT services, consulting and software development",
    "73": "Research and development services",
    "75": "Administration, defence and social security services",
    "76": "Services related to the oil and gas industry",
    "77": "Agricultural, forestry and horticultural services",
    "79": "Business services: law, marketing, consulting and recruitment",
    "80": "Education and training services",
    "85": "Health and social work services",
    "90": "Sewage, refuse, cleaning and environmental services",
    "92": "Recreational, cultural and sporting services",
    "98": "Other community, social and personal services",
}

# ISO 639-2/B (what TED uses) -> ISO 639-1, for page language codes.
LANG3_TO_2 = {
    "bul": "bg", "ces": "cs", "dan": "da", "deu": "de", "ell": "el", "eng": "en",
    "est": "et", "fin": "fi", "fra": "fr", "gle": "ga", "hrv": "hr", "hun": "hu",
    "ita": "it", "lav": "lv", "lit": "lt", "mlt": "mt", "nld": "nl", "pol": "pl",
    "por": "pt", "ron": "ro", "slk": "sk", "slv": "sl", "spa": "es", "swe": "sv",
    "ukr": "uk", "rus": "ru", "ara": "ar", "zho": "zh", "nor": "no", "isl": "is",
    "tur": "tr", "srp": "sr", "mkd": "mk", "sqi": "sq", "kat": "ka", "jpn": "ja",
    "kor": "ko", "vie": "vi", "ind": "id", "tha": "th", "hin": "hi", "ben": "bn",
}

_WS = re.compile(r"\s+")


def clean(text, limit=None):
    """Collapse whitespace, strip, optionally truncate on a word boundary."""
    if not text:
        return None
    text = _WS.sub(" ", str(text)).strip()
    if not text:
        return None
    if limit and len(text) > limit:
        text = text[:limit].rsplit(" ", 1)[0] + "…"
    return text


def country_of(code):
    """Accept alpha-2 or alpha-3, return (alpha2, English name)."""
    if not code:
        return (None, None)
    code = str(code).strip().upper()
    if code in COUNTRIES:
        return COUNTRIES[code]
    if len(code) == 2:
        for a2, name in COUNTRIES.values():
            if a2 == code:
                return (a2, name)
        return (code, code)
    return (code[:2], code)


def category_of(cpv):
    """CPV code -> (division, English label)."""
    if not cpv:
        return (None, None)
    division = str(cpv).strip()[:2]
    return (division, CPV_DIVISIONS.get(division))


def lang2(code):
    if not code:
        return None
    code = str(code).strip().lower()
    return LANG3_TO_2.get(code, code[:2])


def make(**kw):
    """Build a record, filling defaults and computing the change hash.

    The hash covers the fields a reader would notice changing, so an unchanged
    notice re-fetched next hour does not count as an update.
    """
    rec = {f: kw.get(f) for f in FIELDS}
    titles = kw.get("titles") or {}
    rec["titles_json"] = json.dumps(titles, ensure_ascii=False, sort_keys=True) if titles else None
    material = "|".join(str(rec.get(f) or "") for f in (
        "title", "description", "buyer_name", "country", "cpv",
        "value_amount", "value_currency", "deadline_at", "url"))
    rec["content_hash"] = hashlib.sha1(material.encode("utf-8")).hexdigest()[:16]
    return rec


def validate(rec):
    """Return a list of problems. A record with problems is dropped, not published."""
    problems = []
    if not rec.get("id"):
        problems.append("no id")
    if not rec.get("title"):
        problems.append("no title")
    if not rec.get("url"):
        problems.append("no url")
    if not rec.get("country"):
        problems.append("no country")
    return problems

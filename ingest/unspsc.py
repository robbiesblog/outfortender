"""UNSPSC to CPV, at segment level.

Canada, Colombia and several other publishers classify with UNSPSC; our category
pages are built on the EU's CPV divisions. Without a bridge, those countries'
tenders are invisible to anyone browsing by category.

This maps the UNSPSC *segment* - the first two digits - onto a CPV division. It
is deliberately coarse: a segment is a broad family of goods or services, and a
CPV division is a broad family of goods or services, so the two line up at that
level without pretending to a precision neither classification supports. Where a
segment spans several divisions with no clear winner, it is left unmapped rather
than guessed, on the principle that no category beats a wrong one.

Segment names below are UNSPSC's own, kept so the mapping can be checked.
"""
from __future__ import annotations

SEGMENT_TO_CPV_DIVISION = {
    "10": ("03", "Live plant and animal material, accessories and supplies"),
    "11": ("14", "Mineral, textile and inedible plant and animal materials"),
    "12": ("24", "Chemicals, including bio chemicals and gas materials"),
    "13": ("19", "Resin, rosin, rubber, foam, film and elastomeric materials"),
    "14": ("22", "Paper materials and products"),
    "15": ("09", "Fuels, fuel additives, lubricants and anti-corrosives"),
    "20": ("43", "Mining and well drilling machinery and accessories"),
    "21": ("16", "Farming, fishing, forestry and wildlife machinery"),
    "22": ("43", "Building and construction machinery and accessories"),
    "23": ("42", "Industrial manufacturing and processing machinery"),
    "24": ("42", "Material handling, conditioning and storage machinery"),
    "25": ("34", "Commercial, military and private vehicles"),
    "26": ("31", "Power generation and distribution machinery"),
    "27": ("44", "Tools and general machinery"),
    "30": ("44", "Structures, building and construction components"),
    "31": ("44", "Manufacturing components and supplies"),
    "32": ("31", "Electronic components and supplies"),
    "39": ("31", "Electrical systems, lighting and components"),
    "40": ("42", "Distribution and conditioning systems and equipment"),
    "41": ("38", "Laboratory, measuring, observing and testing equipment"),
    "42": ("33", "Medical equipment, accessories and supplies"),
    "43": ("30", "Information technology broadcasting and telecommunications"),
    "44": ("30", "Office equipment, accessories and supplies"),
    "45": ("32", "Printing, photographic, audio and visual equipment"),
    "46": ("35", "Defence, law enforcement, security and safety equipment"),
    "47": ("39", "Cleaning equipment and supplies"),
    "48": ("42", "Service industry machinery, equipment and supplies"),
    "49": ("37", "Sports and recreational equipment and supplies"),
    "50": ("15", "Food, beverage and tobacco products"),
    "51": ("33", "Drugs and pharmaceutical products"),
    "52": ("39", "Domestic appliances, supplies and consumer electronics"),
    "53": ("18", "Apparel, luggage and personal care products"),
    "55": ("22", "Published products"),
    "56": ("39", "Furniture and furnishings"),
    "60": ("37", "Musical instruments, games, toys, arts and crafts"),
    "70": ("77", "Farming, fishing, forestry and wildlife contracting services"),
    "71": ("76", "Mining, oil and gas services"),
    "72": ("45", "Building and facility construction and maintenance services"),
    "73": ("50", "Industrial production and manufacturing services"),
    "76": ("90", "Industrial cleaning services"),
    "77": ("90", "Environmental services"),
    "78": ("60", "Transportation, storage and mail services"),
    "80": ("79", "Management and business professional services"),
    "81": ("71", "Engineering, research and technology based services"),
    "83": ("65", "Public utilities and public sector related services"),
    "84": ("66", "Financial and insurance services"),
    "85": ("85", "Healthcare services"),
    "86": ("80", "Education and training services"),
    "90": ("55", "Travel, food, lodging and entertainment services"),
    "91": ("98", "Personal and domestic services"),
    "92": ("75", "National defence, public order, security and safety services"),
    "93": ("75", "Politics and civic affairs services"),
    "94": ("98", "Organisations and clubs"),
    "95": ("70", "Land, buildings, structures and thoroughfares"),
    # Deliberately unmapped: 54 (timepieces and jewellery) and 82 (editorial,
    # design and fine art) straddle divisions with no honest winner.
}


def to_cpv_division(code):
    """UNSPSC code in any common spelling -> CPV division, or None.

    Accepts "80111620", "V1.80111620" (Colombia's prefixed form) and codes
    shorter than 8 digits. Returns None whenever we cannot be reasonably sure.
    """
    if not code:
        return None
    digits = "".join(c for c in str(code) if c.isdigit())
    # Colombia prefixes with a version marker, "V1."; stripping non-digits turns
    # that into a leading "1", so drop it when the rest is a full UNSPSC code.
    if len(digits) == 9 and digits.startswith("1"):
        digits = digits[1:]
    if len(digits) < 2:
        return None
    entry = SEGMENT_TO_CPV_DIVISION.get(digits[:2])
    return entry[0] if entry else None

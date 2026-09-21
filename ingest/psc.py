"""US Product Service Codes (PSC) to CPV divisions.

SAM.gov classifies every opportunity with a PSC. Services start with a letter
(the first character is the service family); products start with a digit (the
first two digits are the Federal Supply Classification group). Both map onto
CPV divisions at that level, which is as precise as either system allows
without a code-by-code table.

As with unspsc.py: families that genuinely straddle several divisions are left
unmapped, because no category beats a wrong one.
"""
from __future__ import annotations

SERVICES = {
    "A": "73",  # research and development
    "B": "79",  # special studies and analyses
    "C": "71",  # architect and engineering services
    "D": "72",  # information technology and telecommunications services
    "E": "45",  # purchase of structures and facilities
    "F": "77",  # natural resources and conservation
    "G": "85",  # social services
    "H": "71",  # quality control, testing and inspection
    "J": "50",  # maintenance, repair and rebuilding of equipment
    "K": "50",  # modification of equipment
    "L": "79",  # technical representative services
    "N": "51",  # installation of equipment
    "P": "90",  # salvage services
    "Q": "85",  # medical services
    "R": "79",  # professional, administrative and management support
    "S": "90",  # utilities and housekeeping
    "U": "80",  # education and training
    "V": "60",  # transportation, travel and relocation
    "X": "70",  # lease or rental of facilities
    "Y": "45",  # construction of structures and facilities
    "Z": "45",  # maintenance, repair or alteration of real property
    # Unmapped on purpose: M (operation of government facilities - any trade),
    # T (photographic, mapping, printing - several divisions), W (lease or
    # rental of equipment - whatever the equipment is).
}

PRODUCTS = {
    "10": "35", "13": "35", "15": "34", "16": "34", "19": "34", "20": "34",
    "23": "34", "24": "16", "25": "34", "26": "34", "28": "42", "29": "42",
    "30": "42", "31": "42", "34": "42", "35": "42", "36": "42", "37": "16",
    "38": "43", "39": "42", "40": "44", "41": "42", "42": "35", "43": "42",
    "44": "42", "45": "44", "46": "42", "47": "44", "48": "42", "49": "42",
    "51": "44", "52": "38", "53": "44", "54": "44", "55": "44", "56": "44",
    "58": "32", "59": "31", "60": "32", "61": "31", "62": "31", "63": "35",
    "65": "33", "66": "38", "67": "38", "68": "24", "70": "30", "71": "39",
    "72": "39", "73": "39", "74": "30", "75": "30", "76": "22", "77": "37",
    "78": "37", "79": "39", "80": "44", "81": "44", "83": "19", "84": "18",
    "85": "33", "87": "03", "88": "03", "89": "15", "91": "09", "93": "44",
    "94": "14", "95": "44", "96": "14",
    # Unmapped: 69 (training aids and devices), 99 (miscellaneous).
}


def to_cpv_division(code):
    """PSC code -> CPV division, or None when we cannot be reasonably sure."""
    code = str(code or "").strip().upper()
    if not code:
        return None
    if code[0].isalpha():
        return SERVICES.get(code[0])
    return PRODUCTS.get(code[:2]) if len(code) >= 2 and code[:2].isdigit() else None

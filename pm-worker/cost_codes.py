"""
Vos Glass master cost code list.
=================================
Transcribed from Cost_Codes_12_03_2025.pdf ("Vos Glass — Cost Code Summary").

This is the single source of truth the rest of the app validates against:
  - the PO builder's cost-code picker (vos-pm-suite.html)
  - the BidEstimate parser (estimate_parser.py), which flags any code it
    extracts that isn't in this list instead of silently trusting it

Keep this file and Cost_Codes_*.pdf in sync — if Vos Glass issues an updated
cost code sheet, update CODES below (and bump COST_CODES_VERSION) rather than
hand-editing the generated cost_codes.json the server reads.

2026-07-17: code 290 (Material Omissions/Errors) added directly per
instruction — it was missing from the transcribed Cost_Codes_12_03_2025.pdf
above, not the result of a newer official sheet. Noted here so the source
of this one addition stays honest if a real updated PDF arrives later.
"""

import json

COST_CODES_VERSION = "2026-07-17"

# category -> list of (code, description, when_to_use)
# when_to_use is "" where the source PDF didn't give one.
CODES = {
    "labor": [
        (100, "Fab - Shop", "When fabricating in Vos Glass shop (GR only)"),
        (102, "Fab - Cutting", "When fabricating/cutting in the Vos Glass shop (GR only)"),
        (105, "Fab - Door Hardware", "When fabricating door hardware in GR."),
        (106, "Fab - CNC", "When operating the CNC machine at Stahl for job related work"),
        (107, "Programming - CNC", "When programming the CNC machine at Stahl for job related work"),
        (110, "Fab - Field", "When fabricating in the field"),
        (115, "Fab - Other", 'Flexible code to track specific fab scope on a project, example "sunshades", or Fab at Mid Mich shop.'),
        (116, "Fab - Backpans", ""),
        (118, "Retail Cutting Table", "Only used for tracking retail jobs on cutting table"),
        (120, "Install - Exterior", "Installing on building exterior (all product types)"),
        (122, "Swing Stage", "$5 per hour premium added to regular rate for direct work on a swing stage. Not setup of the stage."),
        (125, "T&M - Extra Work", ""),
        (130, "Install - Interior", "Installing on building interior (all product types)"),
        (131, "Shower Doors", "Installing shower doors"),
        (135, "Welding", "Welding - triggers a different wage rate paid to employee."),
        (140, "Field Measure", "When field measuring (superintendent, foreman, PM, etc)"),
        (151, "Jury Duty", ""),
        (155, "Paid Time Off/Vacation", ""),
        (156, "Holiday", "Paid Vos Glass Holiday (6 per year)"),
        (157, "Off - No Pay", "Time off - no pay, but doesn't go against PTO balance"),
        (158, "Bereavement", "Bereavement time off (see employee handbook for details)"),
        (160, "Medical", "Safety related training (CPR training, OSHA training, etc.) Also PT and Med1 appts"),
        (162, "Technical Training", "Technical training (ex. Kawneer teaching on new system), WMCI or employee meeting"),
        (165, "Equipment Maintenance", "Cleaning trucks, maintenance on saws, field equipment, etc."),
        (170, "Project Management", ""),
        (171, "Drafting", ""),
        (172, "Estimating", ""),
        (175, "Field Supervision", "Superintendent time on a job"),
        (177, "Shop Facilities", "Setup lunch, shipping receiving, facilities maintenance."),
        (179, "Office", "Used for Admin dept only (AP, AR, Accounting, etc.)"),
        (185, "Load trailer/vehicle/Delivery", "Loading vehicle or trailer, and for time spent on deliveries"),
        (195, "Warranty Work", ""),
        (199, "Rework", ""),
    ],
    "material": [
        (200, "Glass", ""),
        (203, "Glass - Fire Rated/Framing", ""),
        (204, "Bullet Resistant Glass/Framing", ""),
        (205, "Glass - Doors/Walls", ""),
        (215, "Glass - Other (Mirrors, Xray, etc.)", "Misc other glass that does not have a dedicated CC (mirrors, shelves, showers, decorative, xray etc)"),
        (225, "Alum - Break Metal", ""),
        (230, "Alum - Frames / Doors", ""),
        (231, "Alum - Curved", ""),
        (235, "Alum - Windows", ""),
        (245, "Alum - Other", ""),
        (246, "Steel", ""),
        (247, "Louvers", ""),
        (248, "Metal Panels/Backpans", ""),
        (250, "Skylights/Canopies/Translucent", ""),
        (251, "Packaging", "Fabrication dept uses this for Shop Stock type of purchases in Job 690"),
        (252, "Operation Supplies", "Fabrication dept uses this for Shop Stock type of purchases in Job 690"),
        (255, "Hardware", ""),
        (260, "Sundries - Other", ""),
        (261, "Caulk", ""),
        (262, "Fasteners", ""),
        (263, "Small Tools", ""),
        (264, "Safety Equipment", ""),
        (265, "Freight", ""),
        (271, "Facilities Expense", "Fab dept uses this for Shop Stock type of purchases in Job 690"),
        (272, "Vehicle Expense", "Fab dept uses this for Shop Stock type of purchases in Job 690. Items we keep on hand for trucks."),
        (290, "Material Omissions/Errors", ""),
        (295, "Warranty Work", ""),
        (299, "Rework", ""),
    ],
    "subcontractor": [
        (300, "Caulking - Fire Safing", "Caulking and fire safing"),
        (310, "Window Cleaning", ""),
        (320, "Window Film", "Window film, decorative and security"),
        (330, "Automatic Operators", ""),
        (340, "Metal Panels", "Metal Panels (AMI or other)"),
        (350, "Other Subs", 'Used for "other" subs that do not have a cost code (ex. Concrete cutting, welding, etc)'),
        (360, "Temporary Labor", "Hired labor only from a staffing agency or partner company (AMI, D&D, Kent Co, JB Glass, etc.)"),
        (395, "Warranty Subcontractor", ""),
        (399, "Rework Subcontractor", ""),
    ],
    "other": [
        (400, "Insurance/Bonding", "Special insurance requirements and bonding when required"),
        (405, "Legal", "Legal and attorney fees (ex. Attorney review contract for project)"),
        (410, "Parking", ""),
        (412, "Allowance", "Where we hold project allowances while we wait to use"),
        (415, "Travel Per Diem/lodging", "Per diem for food and lodging expenses"),
        (416, "Travel Mileage", "Travel and mileage reimbursement per employee handbook, or CC charge for gas and tolls"),
        (420, "Shop Drawings/Engineering", "Shop drawings and engineering from outside companies"),
        (425, "Safety Inspection", "Third party safety consultant costs"),
        (428, "Dumpster", ""),
        (430, "Other", 'Misc. costs that have no other place to reside, used rarely, "junk drawer"'),
        (499, "Back charges Error", "When we are backcharged by customer (negative CO's and bills paid directly to other trades)"),
    ],
    "equipment": [
        (500, "Ariel Lift", ""),
        (510, "Crane", "Use a Master subcontract PO since this is a subcontractor operating equipment"),
        (520, "Other Equipment", 'When "other" equipment is needed that doesn\'t have dedicated cost code (ex. Swing stage)'),
        (525, "Vos Equipment Expense", "When Vos Glass owned equipment is used on a project and cost is charged to project"),
        (595, "Warranty Equip", ""),
        (599, "Rework Equip", ""),
    ],
}


def flat_list():
    """Returns a flat list of dicts: [{code, category, description, whenToUse}, ...]
    sorted by code. This is the shape served at GET /api/cost-codes."""
    out = []
    for category, rows in CODES.items():
        for code, desc, when in rows:
            out.append({
                "code": code,
                "category": category,
                "description": desc,
                "whenToUse": when,
            })
    out.sort(key=lambda r: r["code"])
    return out


def lookup():
    """Returns {code:int -> row dict} for O(1) validation lookups."""
    return {row["code"]: row for row in flat_list()}


def label(code):
    """'255 — Hardware', or '255 — (code not on master list)' if unknown."""
    try:
        code = int(code)
    except (TypeError, ValueError):
        return str(code)
    row = lookup().get(code)
    if row:
        return f"{code} — {row['description']}"
    return f"{code} — (code not on master list)"


def write_json(path):
    payload = {"version": COST_CODES_VERSION, "codes": flat_list()}
    with open(path, "w", encoding="utf-8") as f:
        json.dump(payload, f, indent=2)
    return payload


if __name__ == "__main__":
    import sys
    rows = flat_list()
    print(f"{len(rows)} cost codes loaded (version {COST_CODES_VERSION})")
    dupes = [c for c in {r['code'] for r in rows} if sum(1 for r in rows if r['code'] == c) > 1]
    if dupes:
        print("DUPLICATE CODES:", dupes)
    if len(sys.argv) > 1:
        out = write_json(sys.argv[1])
        print(f"Wrote {sys.argv[1]}")

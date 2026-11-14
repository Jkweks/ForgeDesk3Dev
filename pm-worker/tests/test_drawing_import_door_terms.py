"""D.O. and DOOR JAMB LENGTH — the two terms the parser never read (r505).

Jeremiah asked to be able to check parsed sizes against the drawing at import.
Investigating that found the parser was not misreading anything — it was
missing two terms, and both appear only on DOOR elevations:

  D.O.                the door opening itself, on both axes
  DOOR JAMB LENGTH    the height-axis frame size on a door elevation; the
                      same reading Vos calls F.S. everywhere else, which is
                      why fsH came back null on every door sheet

TRANSOM D.L.O. needed nothing: it is just another D.L.O. (Jeremiah), and the
parser already captured it as one — 34" into dloWidths and 18 3/8" into
dloHeights on 11/E8, both correct.

Everything below is checked against the REAL Alpine set in test_samples, and
every expected number was read off the rendered sheet by eye first.

What this proves:
  A. D.O. is read on both axes, on every door elevation in the set, and is
     absent on every elevation that has no door.
  B. DOOR JAMB LENGTH fills fsH on the four sheets that print it instead of
     F.S. — those returned null before, silently.
  C. The values are the sheet's own, to the sixteenth, and the chains they
     complete close exactly.
  D. Nothing that already worked changed: R.O., F.S., D.L.O. and the
     footprint rectangles the r475 visual pane draws are untouched.

Run: python3 test_drawing_import_door_terms.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                       'test_samples', 'drawing_import',
                       'Alpine_Apartments_Submittal_rev1.pdf')

def _num(x):
    # A missing term must FAIL a check, never crash the run (the r491 lesson,
    # and what keeps a reversion proof readable).
    return x if isinstance(x, (int, float)) else float('nan')

results = []
def check(name, ok):
    results.append((bool(ok), name))

if not di._HAVE_PDFPLUMBER or not os.path.isfile(FIXTURE):
    print("SKIP: pdfplumber or the Alpine fixture is unavailable")
    sys.exit(0)

res = di.parse_drawing_set(open(FIXTURE, 'rb').read(), 'Alpine.pdf')
els = {e['label']: e for e in res['elevations']}
check("the set parses to the 19 elevations it has", len(res['elevations']) == 19)

# ── A. D.O. on both axes ─────────────────────────────────────────────────

DOORS = ['6/E3', '7/E4', '8/E5', '9/E6', '10/E7', '11/E8', '12/E9',
         '13/E10', '14/E11', '15/E12', '16/E13', '17/E14', '18/E15']
NO_DOORS = ['1A/E1', '2/E1', '1B/E1', '3/E2', '4/E2', '5/E2']

check("A1: every door elevation in the set now reports a D.O. on BOTH axes — "
      "13 of them, none of which had one before",
      all(els[k].get('doW') is not None and els[k].get('doH') is not None for k in DOORS))

check("A2: and no elevation WITHOUT a door invents one — the term is read, never assumed",
      all(els[k].get('doW') is None and els[k].get('doH') is None for k in NO_DOORS))

check("A3: a pair of 6'-0\" doors reads 72\", a single 3'-0\" door reads 36\", and every "
      "door in the set is 7'-0\" tall — matching the door schedule on DS1",
      els['6/E3']['doW'] == 72.0 and els['11/E8']['doW'] == 36.0
      and all(els[k]['doH'] == 84.0 for k in DOORS))

check("A4: D.O. carries its footprint rectangle, so the visual pane can highlight where "
      "it was read from (r475) rather than just asserting a number",
      all((els[k].get('dims', {}).get('doW') or {}).get('rect') for k in DOORS)
      and all((els[k].get('dims', {}).get('doH') or {}).get('rect') for k in DOORS))

# ── B. DOOR JAMB LENGTH ──────────────────────────────────────────────────

JAMB = ['11/E8', '12/E9', '16/E13', '17/E14']

check("B1: the four sheets that print DOOR JAMB LENGTH instead of F.S. now report an "
      "fsH — every one of them returned null before, with no warning",
      all(els[k].get('fsH') is not None for k in JAMB))

check("B2: 11/E8 reads 107 5/8\" — the sheet's own number, and exactly its 108\" R.O. "
      "less the 3/8\" head joint printed beside it",
      els['11/E8']['fsH'] == 107.625
      and abs(els['11/E8']['roH'] - els['11/E8']['fsH'] - 0.375) < 1e-9)

check("B3: it lands in fsH rather than a field of its own, because it IS the frame size — "
      "every consumer of fsH gets it with no change",
      (els['11/E8'].get('dims', {}).get('fsH') or {}).get('v') == 107.625)

check("B4: an elevation that prints a real F.S. height is unaffected",
      els['1A/E1']['fsH'] == 86.75 and els['3/E2']['fsH'] == 85.75)

# ── C. the chains close ──────────────────────────────────────────────────

check("C1: 6/E3's width chain closes to the sixteenth once D.O. is counted — "
      "95 1/4\" F.S. = two 7 5/8\" sidelites + a 72\" door + four 2\" members. "
      "This is the read that looked wrong and was not: the parser had it right and the "
      "equation was missing a term.",
      abs(_num(els['6/E3']['fsW']) - (sum(els['6/E3']['dloWidths'])
                                      + _num(els['6/E3']['doW']) + 4 * 2.0)) < 1e-9)

check("C2: 11/E8's height chain closes too — 107 5/8\" jamb length = an 84\" door + an "
      "18 3/8\" transom + 5 1/4\" of members (1 1/4\" + 2\" + 2\", as printed)",
      abs(_num(els['11/E8']['fsH']) - (_num(els['11/E8']['doH'])
                                       + sum(els['11/E8']['dloHeights']) + 5.25)) < 1e-9)

check("C3: 11/E8's width chain closes on the door alone — 40\" F.S. = a 36\" door + two "
      "2\" jambs; its 34\" D.L.O. is the transom ABOVE the door, on a parallel chain, "
      "not another term in this sum",
      abs(_num(els['11/E8']['fsW']) - (_num(els['11/E8']['doW']) + 2 * 2.0)) < 1e-9
      and els['11/E8']['dloWidths'] == [34.0])

# ── D. nothing that worked changed ───────────────────────────────────────

check("D1: R.O./F.S./D.L.O. widths on a storefront elevation are exactly as before",
      els['1A/E1']['roW'] == 92.0 and els['1A/E1']['fsW'] == 91.25
      and els['1A/E1']['dloWidths'] == [42.625, 42.625])

check("D2: and its heights, including the D.L.O. heights round 383 added",
      els['1A/E1']['roH'] == 88.0 and els['1A/E1']['fsH'] == 86.75
      and els['1A/E1']['dloHeights'] == [55.125, 25.625])

check("D3: TRANSOM D.L.O. still arrives as an ordinary D.L.O., which is what it is — "
      "no new field, no special case",
      els['11/E8']['dloWidths'] == [34.0] and els['11/E8']['dloHeights'] == [18.375])

check("D4: the footprint rectangles the r475 visual pane draws are unchanged",
      (els['1A/E1']['dims']['roW'] or {}).get('rect') is not None
      and len(els['1A/E1']['dims']['dloWidths']) == 2)

check("D5: the three sheets with no D.L.O. widths still say so rather than guessing",
      all(els[k]['dloWidths'] == [] and els[k].get('warnings') for k in
          ['13/E10', '14/E11', '15/E12']))

passed = 0
for ok, name in results:
    print(("  ok  " if ok else "  FAIL") + "  " + name)
    if ok:
        passed += 1
print("\n%d/%d" % (passed, len(results)))
sys.exit(0 if passed == len(results) else 1)

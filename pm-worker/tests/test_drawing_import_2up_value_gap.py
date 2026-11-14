"""Two elevations side by side on one sheet — r543.

Jeremiah's Beacon Hill import: on E5 (9 + 10) and E31 (39 + 39A) the RIGHT
elevation's first D.L.O. width was handed to the LEFT elevation, and on E5 the
right elevation's F.S. width went missing too:

  9/E5    read 41 1/16, 41 1/8, 32 5/8, 41 1/16   (32 5/8 belongs to 10/E5)
  10/E5   read one 32 5/8 and no F.S.             -> "can't check"
  39/E31  read 33 1/2, 33 1/8, 33 1/2, 33 1/2      (33 1/8 belongs to 39A)
  39A/E31 read one 33 1/8                          -> "doesn't close"

Cause: _label_events reads a dimension's value by walking LEFT along its text
row from the label. Nothing stopped the walk at a gap, and the right
elevation's D.L.O. / F.S. rows share a baseline with stray text from the left
elevation — its 1 3/4" member dims, a bubble number, the rotated "JOINT" text.
The value was read as starting 500pt to the left, and _nearest_by_x gave it to
the left elevation. Fix: stop at a gap wider than _VALUE_GAP_MAX (20pt — the
same rule _stacked_value_above already uses reading forward).

Fixture: the real sheets, pages 18 and 44 of the Rev 1 (10-13-26) set.

Run: python3 test_drawing_import_2up_value_gap.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                       'test_samples', 'drawing_import',
                       'Beacon_Hill_2up_E5_E31.pdf')

results = []
def check(name, ok):
    results.append((bool(ok), name))

if not di._HAVE_PDFPLUMBER or not os.path.isfile(FIXTURE):
    print("SKIP: pdfplumber or the 2-up fixture is unavailable")
    sys.exit(0)

res = di.parse_drawing_set(open(FIXTURE, 'rb').read(), 'Beacon_2up.pdf')
els = {e['label']: e for e in res['elevations']}

check("P0: all four elevations found", set(els) == {'9/E5', '10/E5', '39/E31', '39A/E31'})

# ── V. the values ─────────────────────────────────────────────────────────
check("V1: 9/E5 has its own three D.L.O.s — not 10/E5's 32 5/8",
      els['9/E5']['dloWidths'] == [41.0625, 41.125, 41.0625])
check("V2: 10/E5 has BOTH of its 32 5/8 D.L.O.s",
      els['10/E5']['dloWidths'] == [32.625, 32.625])
check("V3: 10/E5's F.S. width 71 1/4 is read (was missing — 'can't check')",
      els['10/E5']['fsW'] == 71.25)
check("V4: 39/E31 has its own three 33 1/2 D.L.O.s — not 39A's 33 1/8",
      els['39/E31']['dloWidths'] == [33.5, 33.5, 33.5])
check("V5: 39A/E31 has BOTH of its 33 1/8 D.L.O.s",
      els['39A/E31']['dloWidths'] == [33.125, 33.125])
check("V6: the left elevations' F.S. widths are unchanged",
      els['9/E5']['fsW'] == 131.25 and els['39/E31']['fsW'] == 107.5)

# ── B. the frames close on their own readings, nothing dropped ─────────────
for lab, member in (('9/E5', 2.0), ('10/E5', 2.0), ('39/E31', 1.75), ('39A/E31', 1.75)):
    bw = els[lab]['balance']['w']
    check("B: %s width closes with %s\" members using every D.L.O. read (none dropped)"
          % (lab, member),
          bw and bw['ok'] and bw['member'] == member and bw['dropped'] == 0)

# ── R. the highlight boxes sit on the number, not across the sheet ─────────
for lab in els:
    for i, d in enumerate(els[lab]['dims']['dloWidths']):
        r = d.get('rect')
        check("R: %s D.L.O. %d highlight is one dimension wide (<= 60pt)" % (lab, i + 1),
              r and (r[2] - r[0]) <= 60)

# ── U. the unit: the walk stops at a gap, and only at a gap ────────────────
row = {'top': 100, 'words': [
    {'text': '1', 'x0': 100, 'x1': 104, 'top': 100},              # stray, far left
    {'text': "2'-8", 'x0': 400, 'x1': 418, 'top': 100},
    {'text': '5/8"', 'x0': 420, 'x1': 434, 'top': 100},
    {'text': 'D.L.O.', 'x0': 436, 'x1': 458, 'top': 100},
]}
ev = di._label_events([row], 'D.L.O.')
check("U1: stray text 300pt to the left is not part of the value",
      len(ev) == 1 and ev[0]['text'] == "2'-8 5/8\"" and ev[0]['x0'] == 400)
row2 = {'top': 100, 'words': [
    {'text': "2'-8", 'x0': 400, 'x1': 418, 'top': 100},
    {'text': '5/8"', 'x0': 437, 'x1': 451, 'top': 100},            # 19pt gap: still one value
    {'text': 'D.L.O.', 'x0': 453, 'x1': 475, 'top': 100},
]}
ev2 = di._label_events([row2], 'D.L.O.')
check("U2: a gap under the limit still reads as one value",
      len(ev2) == 1 and ev2[0]['text'] == "2'-8 5/8\"")

passed = sum(1 for ok, _ in results if ok)
for ok, name in results:
    print(("PASS " if ok else "FAIL ") + name)
print("\n%d/%d passed" % (passed, len(results)))
sys.exit(0 if passed == len(results) else 1)

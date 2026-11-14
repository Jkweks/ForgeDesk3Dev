"""Expansion mullions: the frame check reads the members the sheet PRINTS — r546.

Jeremiah: "Expansion mullions do come up consistently." The frame check
(r505-r513, r545) assumes every member in a chain is one size. Howell 65/E33
has six 2" members and one 2 1/2" EXPANSION MULLION and was flagged although
it closes exactly: 416 3/4 + 6 x 2 + 2 1/2 = 431 1/4.

Every member is printed on the sheet, a row of bare inch values about 26pt
under the D.L.O. row. When the uniform test FAILS, those printed members are
tried: F.S. must equal the openings plus the printed members exactly, with
one member more than openings (or as many). It can only turn a fail into a
pass on the sheet's own numbers, so a real error still fails: Alpine 5/E2's
33 13/16" D.L.O. cannot close in a 39 1/2" frame with its two printed 1 3/4"
members, and stays flagged.

Run: python3 test_drawing_import_expansion_mullion.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HOWELL = os.path.join(HERE, 'test_samples', 'drawing_import', 'Howell_SW_pairs_and_spans.pdf')
ALPINE = os.path.join(HERE, 'test_samples', 'drawing_import', 'Alpine_Apartments_Submittal_rev1.pdf')
results = []
def check(name, ok):
    results.append((bool(ok), name))

if not di._HAVE_PDFPLUMBER or not os.path.isfile(HOWELL):
    print("SKIP: pdfplumber or the Howell fixture is unavailable")
    sys.exit(0)
import pdfplumber

# ── R. reading the printed members ──────────────────────────────────────
p52 = pdfplumber.open(HOWELL).pages[2]               # set page 52, 65/E33
mev = di._member_events(p52)
row = sorted([e for e in mev if 275 < e['top'] < 285], key=lambda e: e['x0'])
check("R1: 65/E33's member row reads as printed — 2, 2, 2, 2 1/2 (the expansion "
      "mullion, typeset as '2' + '1/2\"'), 2, 2, 2",
      [e['value'] for e in row] == [2.0, 2.0, 2.0, 2.5, 2.0, 2.0, 2.0])
check("R2: a fraction glued to a feet value ('5'-9 1/2\" D.L.O.') is not read as a member",
      not any(250 < e['top'] < 256 for e in mev))
check("R3: '3/8\" JOINT' is a joint, not a member",
      not any(abs(e['value'] - 0.375) < 1e-9 and 230 < e['top'] < 240 for e in mev))

def W(t, x0, top=100, x1=None):
    return {'text': t, 'x0': x0, 'x1': x1 if x1 is not None else x0 + 5 * len(t), 'top': top,
            'bottom': top + 7}
class FakePage:
    def __init__(self, words): self._w = words
    def extract_words(self, x_tolerance=1.5): return list(self._w)
fv = [e['value'] for e in di._member_events(FakePage([
    W('2"', 10), W('2', 60, x1=64), W('1/2"', 65, x1=80), W('11/16"', 120),
    W('3/8"', 200, x1=215), W('JOINT', 217), W("5'-9", 300, x1=318), W('1/2"', 319, x1=333),
    W('38', 400), W('9"', 500)]))]
check("R4: unit — '2\"', '2'+'1/2\"' joined, '11/16\"' read; JOINT, a feet value's "
      "fraction, a bare bubble number and a 9\" (outside 1/2\"-6\") are not",
      fv == [2.0, 2.5, 0.6875])

# ── B. the check ────────────────────────────────────────────────────────
res = di.parse_drawing_set(open(HOWELL, 'rb').read(), 'Howell.pdf')
els = {e['label']: e for e in res['elevations']}
w65 = els['65/E33']['balance']['w']
c65 = (w65 or {}).get('chains', [{}])[0]
check("B1: 65/E33 now closes, on its printed members, with nothing dropped",
      w65 and w65['ok'] and c65.get('printed') is True and c65.get('dropped') == 0
      and c65.get('members') == [2.0, 2.0, 2.0, 2.5, 2.0, 2.0, 2.0])
check("B2: the record carries the printed rows so the app can re-check a typed value",
      els['65/E33']['printedMembersW'][0] == [2.0, 2.0, 2.0, 2.5, 2.0, 2.0, 2.0])
check("B3: a chain the UNIFORM test passes is untouched — 46/E22 closes the old way "
      "(no 'printed' flag)",
      els['46/E22']['balance']['w']['ok']
      and not els['46/E22']['balance']['w']['chains'][0].get('printed'))
P = di._balance_printed
check("B4: the sum must be EXACT — 1/16\" over and it fails",
      P(431.25, [], [69.5, 69.4375, 69.4375, 69.4375, 69.4375, 69.5],
        [2, 2, 2, 2.5, 2, 2, 2]) is not None
      and P(431.3125, [], [69.5, 69.4375, 69.4375, 69.4375, 69.4375, 69.5],
            [2, 2, 2, 2.5, 2, 2, 2]) is None)
check("B5: the member COUNT must fit the openings (one more, or as many) — four members "
      "round one opening do not pass even when the sum is exact",
      P(48.0, [], [44.0], [2.0, 2.0]) is not None and P(52.0, [], [44.0], [2.0, 2.0, 2.0, 2.0]) is None)
check("B6: door leaves are terms too (a pair with an expansion mullion)",
      P(80.5, [36.0, 36.0], [], [2.0, 2.5, 4.0]) is not None)

# ── X. a real error still fails ─────────────────────────────────────────
if os.path.isfile(ALPINE):
    ares = di.parse_drawing_set(open(ALPINE, 'rb').read(), 'Alpine.pdf')
    a5 = {e['label']: e for e in ares['elevations']}['5/E2']
    check("X1: Alpine 5/E2 (33 13/16\" D.L.O. in a 39 1/2\" frame, printed members "
          "1 3/4\" x 2) is STILL flagged — the printed members don't add up either",
          a5['balance']['w']['ok'] is False and a5['printedMembersW'] == [[1.75, 1.75]])
else:
    print("note: Alpine fixture missing — X1 skipped")

passed = sum(1 for ok, _ in results if ok)
for ok, name in results:
    print(("  ok    " if ok else "  FAIL  ") + name)
print("\n%d/%d" % (passed, len(results)))
sys.exit(0 if passed == len(results) else 1)

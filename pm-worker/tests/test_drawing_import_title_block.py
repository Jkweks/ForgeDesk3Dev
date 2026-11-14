"""Where each elevation's TITLE BLOCK is printed — r544.

Jeremiah: "On the preview when importing an elevation I want to be able to
read all the information below the elevations ... Right now, this is often
missing." The importer now reports `titleRect`: the bubble, the name, the
drawing scale, the THUS/OPP/TOTAL REQ'D box, the ARCH REF (or HARDWARE SET),
the system and finish lines, and any location note under them. The import
preview crops to include it.

Checked by reading the TEXT inside the reported rect on the real sheets:
everything that belongs is in, and the neighbouring elevation, the sheet's
own title block, and the NOTE at the bottom edge are not.

Fixtures: Beacon Hill pages 18 + 44 (two elevations side by side), Alpine
page 9 (three side by side; 5/E2's system line runs toward the sheet's own
REVISION / DATE block, which only the sheet border keeps out).

Run: python3 test_drawing_import_title_block.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BEACON = os.path.join(HERE, 'test_samples', 'drawing_import', 'Beacon_Hill_2up_E5_E31.pdf')
ALPINE = os.path.join(HERE, 'test_samples', 'drawing_import', 'Alpine_Apartments_Submittal_rev1.pdf')

results = []
def check(name, ok):
    results.append((bool(ok), name))

if not di._HAVE_PDFPLUMBER or not os.path.isfile(BEACON):
    print("SKIP: pdfplumber or the Beacon Hill 2-up fixture is unavailable")
    sys.exit(0)

import pdfplumber


def text_in(page, r):
    pad = 1.0
    box = (max(0, r[0] - pad), max(0, r[1] - pad),
           min(float(page.width), r[2] + pad), min(float(page.height), r[3] + pad))
    return ' '.join((page.crop(box).extract_text() or '').split())


res = di.parse_drawing_set(open(BEACON, 'rb').read(), 'Beacon_2up.pdf')
els = {e['label']: e for e in res['elevations']}
pdf = pdfplumber.open(BEACON)
txt = {L: text_in(pdf.pages[els[L]['page'] - 1], els[L]['titleRect'])
       for L in els if els[L].get('titleRect')}

check("P0: every elevation on the 2-up sheets reports a title block",
      set(txt) == {'9/E5', '10/E5', '39/E31', '39A/E31'})

# ── I. everything that belongs is in ───────────────────────────────────────
WANT = {
    '9/E5':   ['ELEVATION S6', "THUS REQ'D: 3", "TOTAL REQ'D: 3", 'ARCH REF:', 'S6/A-832',
               'DRAWING SCALE', 'TUBELITE T14000 SERIES SF (2" x 4 1/2")',
               'FINISH: "BLACK" - PAINTED', '3 @ AREA F, LEVEL 2, SOUTH ELEV.'],
    '10/E5':  ['ELEVATION S8', "THUS REQ'D: 1", 'S8/A-832', 'DRAWING SCALE',
               'FINISH: "BLACK" - PAINTED', 'AREA A, LEVEL 3, WEST ELEV.'],
    '39/E31': ['ELEVATION SF2', "THUS REQ'D: 1", 'SF2-833',
               'TUBELITE E4500 SERIES SF (1 3/4" x 4 1/2")', 'FINISH: "BLACK" - PAINTED'],
    '39A/E31': ['ELEVATION SF8', "TOTAL REQ'D: 1",
                'TUBELITE E4500 SERIES SF (1 3/4" x 4 1/2")', 'FINISH: "BLACK" - PAINTED'],
}
for L, wants in WANT.items():
    t = txt.get(L, '')
    missing = [x for x in wants if x not in t]
    check("I: %s's title block holds its name, REQ'D box, arch ref, scale, system, finish "
          "and location note%s" % (L, (' — MISSING ' + repr(missing)) if missing else ''),
          not missing)

check("I2: the bubble itself (number over sheet) is inside — the rect starts at it",
      all(els[L]['titleRect'][0] <= c['x0'] + 1
          for L in els
          for c in di._elevation_callouts(pdf.pages[els[L]['page'] - 1],
                                          pdf.pages[els[L]['page'] - 1].extract_text())
          if c['name'] == L))

# ── O. and nothing that doesn't ────────────────────────────────────────────
check("O1: the left elevation's block stops before the right elevation's (2-up sheet)",
      'ELEVATION S8' not in txt['9/E5'] and 'ELEVATION SF8' not in txt['39/E31'])
check("O2: the right elevation's block does not reach back into the left one",
      'ELEVATION S6' not in txt['10/E5'] and 'ELEVATION SF2' not in txt['39A/E31'])
check("O3: the sheet NOTE at the bottom edge is not swept in (it sits ~130pt below)",
      not any('ALL DIMENSIONS' in t or 'NOTE:' in t for t in txt.values()))
check("O4: the sheet's own title block (BEACON HILL / REVISION / SHEET) stays out",
      not any(k in t for t in txt.values() for k in ('BEACON HILL', 'REVISION', 'SHEET:')))
check("O5: no drawing dimension is swept in from above (no R.O. / D.L.O. / JOINT text)",
      not any(k in t for t in txt.values() for k in ('R.O.', 'D.L.O.', 'JOINT')))

# ── F. the sheet border fences off the sheet's own title block ─────────────
if os.path.isfile(ALPINE):
    apage = pdfplumber.open(ALPINE).pages[8]          # set page 9: 3/E2 4/E2 5/E2
    acs = di._elevation_callouts(apage, apage.extract_text())
    aw = apage.extract_words(x_tolerance=1.5)
    walls = di._sheet_walls(apage)
    c5 = next(c for c in acs if c['name'] == '5/E2')
    r5 = di._title_block_rect(aw, c5, acs, walls)
    t5 = text_in(apage, r5) if r5 else ''
    check("F1: Alpine's sheet border is found (the title-block edge at x ~1066)",
          any(1060 < x < 1070 for x in walls))
    check("F2: 5/E2's whole system line is read — 'TUBELITE E4500 SERIES SF (1 3/4\" x 4 1/2\")'",
          'TUBELITE E4500 SERIES SF (1 3/4" x 4 1/2")' in t5 and 'FINISH: "CLEAR" ANODIZED (C1)' in t5)
    check("F3: …and stops at the border: REVISION / DATE are not in it",
          r5 and r5[2] < 1066 and 'REVISION' not in t5 and 'DATE' not in t5)
    r5b = di._title_block_rect(aw, c5, acs, ())
    check("F4: (without the border it would have run into REVISION / DATE — the fence is load-bearing)",
          r5b and 'REVISION' in text_in(apage, r5b))
else:
    print("note: Alpine fixture missing — F checks skipped")

# ── U. the rule on its own ────────────────────────────────────────────────
def W(t, x0, top, x1=None, h=7):
    return {'text': t, 'x0': x0, 'x1': x1 if x1 is not None else x0 + 6 * len(t),
            'top': top, 'bottom': top + h}
c = {'x0': 100, 'top': 500, 'name': '1/E1'}
ws = [W('1', 100, 500), W('E1', 98, 515), W('ELEVATION', 120, 502), W('THUS', 220, 500),
      W('ARCH', 290, 500), W('FAR', 500, 500),                     # 210pt gap -> out
      W('TUBELITE', 200, 535), W('FINISH:', 200, 545),
      W('AREA', 200, 580),                                         # 28pt gap after FINISH -> out
      W('DIM', 120, 470)]                                          # above the bubble -> out
r = di._title_block_rect(ws, c, [c])
check("U1: across stops at a wide gap; down stops at a big row gap; nothing above the bubble",
      r == [98, 500, 290 + 24, 552])
check("U2: no bubble on its row -> None (never a guess)",
      di._title_block_rect([W('X', 400, 500)], c, [c]) is None)

passed = sum(1 for ok, _ in results if ok)
for ok, name in results:
    print(("  ok    " if ok else "  FAIL  ") + name)
print("\n%d/%d" % (passed, len(results)))
sys.exit(0 if passed == len(results) else 1)

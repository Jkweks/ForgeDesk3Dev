"""Convention E: numbered bubble + SYSTEM / ARCH REF / QUANTITIES block — r547.

Jeremiah: "This drawing does not import into the WO tab." — the MSU FCU
Gaines Marketplace set (Vos in-house, 33 pages, 11 elevations 101-111).
The import reported every page as "an unreadable text layer" and found no
elevations. Two separate causes, both real:

1. The rotated title block prints ONE unmapped glyph per page (the Z of
   GLAZING reads "GLA(cid:61)ING"); _looks_garbled condemned the whole page
   for any '(cid:'. The other ~1,800 characters read perfectly.
2. The callout convention was new: a large circled number ("105") with
   "SYSTEM: / ARCH REF: / QUANTITIES: (1) THUS" beside it — no "ELEVATION"
   word, no REQ'D, no QTY= — and dimensions labelled RO / FS / DLO / DO
   without periods, no bracketed inches, DLO labels centred UNDER their
   values, and some fractions stacked (1'-2 1/16" drawn as four pieces).

Fixture: test_samples/drawing_import/MSU_FCU_Gaines_system_block.pdf — the
real set's cover, plan, all six elevation sheets and one detail sheet.
Every expected value below was read off the rendered sheets by hand.

Run: python3 test_drawing_import_system_block.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SET = os.path.join(HERE, 'test_samples', 'drawing_import', 'MSU_FCU_Gaines_system_block.pdf')
results = []


def check(name, ok):
    if callable(ok):
        try:
            ok = ok()
        except Exception as e:          # a crash is a failure, reported by name
            name += '  [raised %s]' % type(e).__name__
            ok = False
    results.append((bool(ok), name))


if not di._HAVE_PDFPLUMBER or not os.path.isfile(SET):
    print("SKIP: pdfplumber or the Gaines fixture is unavailable")
    sys.exit(0)
import pdfplumber

pdf = pdfplumber.open(SET)
texts = [p.extract_text() or '' for p in pdf.pages]

# ── G: the garbled-text signal ────────────────────────────────────────────
check("G1 every page really does carry the one unmapped title-block glyph", lambda:
      all(t.count('(cid:') == 1 and 'GNI(cid:61)ALG' in t for t in texts))  # rotated: reads backwards
check("G2 one stray glyph in a readable page is NOT garbled", lambda:
      not any(di._looks_garbled(t) for t in texts))
check("G3 a broken-font page (cid on a large share of glyphs) still IS garbled", lambda:
      di._looks_garbled('(cid:9)SNI(cid:46)REP:STCETIHCRA' * 6)
      and di._looks_garbled('(cid:17)CNI ,SSALG SOV ' * 5))

# ── C: classification and callouts ────────────────────────────────────────
cls = [di.classify_page(p) for p in pdf.pages]
check("C1 the six elevation sheets classify as elevations", lambda:
      cls[2:8] == [True] * 6)
check("C2 cover, plan and detail sheets do not", lambda:
      not cls[0] and not cls[1] and not cls[8])
labels = [[c['name'] for c in di._elevation_callouts(pdf.pages[i], texts[i])] for i in range(2, 8)]
check("C3 callouts per sheet are the bubble numbers, nothing else", lambda:
      [sorted(l) for l in labels] == [['101', '102'], ['103', '104'], ['105', '106'],
                                      ['107', '108'], ['109', '110'], ['111']])
check("C4 the small plan-tag copies of 103/104/107/108 on the same sheets are not bubbles", lambda:
      all(len(l) == len(set(l)) for l in labels))

# ── R: the full import ───────────────────────────────────────────────────
r = di.parse_drawing_set(open(SET, 'rb').read())
E = {e['label']: e for e in r['elevations']}
check("R1 import succeeds with all eleven elevations", lambda:
      r['ok'] and sorted(E) ==
      ['101', '102', '103', '104', '105', '106', '107', '108', '109', '110', '111'])
check("R2 no 'unreadable text layer' warning anywhere", lambda:
      not any('unreadable' in w for w in r['warnings']))
check("R3 system, arch ref and quantity read off each block's own labels", lambda:
      all(e['system'] == 'KAWNEER 2500 UNITWALL' and e['thusReqd'] == 1
          and e['totalReqd'] == 1 for e in E.values())
      and all(E[k]['archRef'] == 'A401' for k in E if k != '111'))
check("R4 'ARCH REF: TBD' (111) is no reference at all", lambda:
      E['111']['archRef'] is None)

# label: (roW, fsW, dloWidths, doW, roH, fsH, dloHeights, doH)
EXPECT = {
    '101': (72.0, 70.75, [65.75, 24.0], 37.0, 105.375, 102.875, [82.375, 12.75], 84.0),
    '102': (176.0, 174.75, [40.5625] * 4, None, 232.0, 230.875, [106.875, 46.5, 67.25], None),
    '103': (275.0625, 274.4375, [62.25, 64.625, 59.0, 59.0, 14.0625], None, 139.0, 136.5, [106.875, 21.875], None),
    '104': (275.4375, 274.8125, [22.0625, 108.5, 9.5, 60.1875, 59.0625], None, 139.0, 136.5, [107.125, 24.0, 21.875], None),
    '105': (176.0, 174.75, [54.875, 55.0, 54.875], None, 232.0, 230.875, [106.875, 46.5, 67.25], None),
    '106': (176.375, 175.125, [55.0, 55.125, 55.0], None, 232.0, 230.875, [106.875, 46.5, 67.25], None),
    '107': (257.25, 256.625, [48.4375, 48.4375, 48.4375, 44.25, 51.5625], 42.0, 232.0, 230.875, [106.875, 30.0, 46.5, 67.25], 84.0),
    '108': (None, 260.25, [47.75, 49.25, 49.25, 49.25, 49.25], None, 232.0, 230.875, [106.875, 46.5, 67.25], None),
    '109': (176.0, 174.75, [54.875, 55.0, 54.875], None, 232.0, 230.875, [106.875, 46.5, 67.25], None),
    '110': (64.0, 62.75, [15.875, 57.75], 37.0, 105.375, 102.875, [82.375, 12.75], 84.0),
    '111': (72.0, 70.75, [31.625, 31.625], None, 216.0, 214.875, [90.875, 46.5, 67.25], None),
}
F = ('roW', 'fsW', 'dloWidths', 'doW', 'roH', 'fsH', 'dloHeights', 'doH')
for k, exp in EXPECT.items():
    got = tuple(E[k][f] for f in F) if k in E else None
    check("R5 %s every dimension matches the sheet" % k, lambda:
      got == exp)

# ── S: stacked fractions and the completeness rule ───────────────────────
check("S1 a stacked width fraction is rebuilt exactly (103: 1'-2 1/16\", 104: 9 1/2\")", lambda:
      E['103']['dloWidths'][-1] == 14.0625 and E['104']['dloWidths'][2] == 9.5)
check("S2 a stacked height fraction is rebuilt exactly (101: 1'-0 3/4\", 103: 1'-9 7/8\")", lambda:
      12.75 in E['101']['dloHeights'] and 21.875 in E['103']['dloHeights'])
check("S3 a piece of a value is never a value", lambda:
      di._bare_value('16') is None
      and di._bare_value("1'-2") is None and di._bare_value("14'-8\"") == 176.0)
check("S4 a stacked fraction that isn't a drafting fraction is refused", lambda:
      di._stacked_frac("1'-2", '16', '1') is None and di._stacked_frac("1'-2", '1', '15') is None
      and di._stacked_frac("1'-2", '1', '16') == 14.0625)

# ── H: honest gaps ───────────────────────────────────────────────────────
check("H1 108's overall width is printed with no RO label — left blank, flagged", lambda:
      E['108']['roW'] is None
      and any('R.O. Width not found' in w for w in E['108']['warnings']))
check("H2 104's '92 1/4 OVERALL DO' has no inch mark — door height left blank", lambda:
      E['104']['doH'] is None)
check("H3 110's '7'-0\" OVERALL DO' is read", lambda:
      E['110']['doH'] == 84.0)

# ── B: the frame check closes on every elevation ─────────────────────────
check("B1 every elevation closes on both axes with nothing dropped", lambda:
      all(e['balance']['w'] and e['balance']['w']['ok'] and e['balance']['h']
          and e['balance']['h']['ok']
          and all(c['dropped'] == 0 for c in e['balance']['w']['chains'])
          for e in E.values()))
check("B2 mixed-member frames close on left end + TYP + right end", lambda:
      E['103']['balance']['w']['chains'][0].get('members') == [2.5, 2.5, 2.5, 2.5, 2.5, 3.0]
      and E['104']['balance']['w']['chains'][0].get('members') == [3.0, 2.5, 2.5, 2.5, 2.5, 2.5]
      and E['107']['balance']['w']['chains'][0].get('members') == [2.5, 2.5, 2.5, 2.5, 2.5, 3.0]
      and E['108']['balance']['w']['chains'][0].get('members') == [3.0, 2.5, 2.5, 2.5, 2.5, 2.5])
check("B3 '2' + '1/2\"' set 3.5pt apart reads as 2 1/2\", not 1/2\"", lambda:
      E['105']['printedMembersW'] and all(v == 2.5 for v in E['105']['printedMembersW'][0]))

# ── X: bare labels never leak into other conventions ─────────────────────
p6 = pdf.pages[4]
check("X1 without the convention-E switch, a bare 'RO' is not a dimension", lambda:
      di._width_events(p6)['RO'] == [] and di._height_events(p6)['RO'] == [])
check("X2 with it, the same sheet's R.O.s are read", lambda:
      sorted(e['value'] for e in di._width_events(p6, bare=True)['RO']) == [176.0, 176.375])

pdf.close()
fails = [n for ok, n in results if not ok]
for ok, n in results:
    print(('  ok    ' if ok else '  FAIL  ') + n)
print('\n%d/%d' % (len(results) - len(fails), len(results)))
sys.exit(1 if fails else 0)

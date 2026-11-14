"""Door pairs and side-by-side ownership — r545 (Howell SW Elementary).

Jeremiah, on 13/E6: "I believe the math on this shows this closing. Confirm
and fix if needed." It does close (87 1/4 - 45 5/8 - 35 5/8 = 6 = 3 x 2");
r543's fix already reads it right. The same set then flagged twelve more
width chains. Ten were real misses and are fixed here; one is a real limit
of the check and stays flagged:

  P. A PAIR OF DOORS prints one D.O. per leaf (67/E35: 3'-0" D.O. twice).
     The check took only the FIRST D.O. per chain, leaving 36" unaccounted:
     119 1/4 - 36 - 36 - 18 5/8 - 18 5/8 = 10 = 5 x 2". Now every D.O. in a
     WIDTH chain is a term. Height chains keep one, since leaves never stack.
  S. 46/E22 prints its first D.L.O. 32pt LEFT of its own bubble, and the
     column rule gave it to 45/E22. Each sheet prints its scale, so each
     frame's extent on the sheet is known (R.O. x scale, centred on the R.O.
     text). A width dimension inside exactly one frame belongs to that one.
  X. 65/E33 has a 2 1/2" EXPANSION MULLION among 2" members. It closes
     exactly; r545 left it flagged because the check assumed one member
     size. r546 reads the printed members, and X1 is re-pinned to that.

Fixture: real Howell pages 19, 41, 52, 54, 63.
Run: python3 test_drawing_import_door_pairs.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                       'test_samples', 'drawing_import', 'Howell_SW_pairs_and_spans.pdf')
results = []
def check(name, ok):
    results.append((bool(ok), name))

if not di._HAVE_PDFPLUMBER or not os.path.isfile(FIXTURE):
    print("SKIP: pdfplumber or the Howell fixture is unavailable")
    sys.exit(0)

res = di.parse_drawing_set(open(FIXTURE, 'rb').read(), 'Howell.pdf')
els = {e['label']: e for e in res['elevations']}
W = lambda L: (els[L]['balance'] or {}).get('w') or {}

# ── C. the one Jeremiah asked about ──────────────────────────────────────
check("C1: 13/E6 reads R.O. 88, F.S. 87 1/4 and BOTH D.L.O.s 45 5/8 + 35 5/8 (r543)",
      els['13/E6']['roW'] == 88.0 and els['13/E6']['fsW'] == 87.25
      and els['13/E6']['dloWidths'] == [45.625, 35.625])
check("C2: …and closes on 2\" members with nothing dropped",
      W('13/E6').get('ok') and W('13/E6').get('member') == 2.0 and W('13/E6').get('dropped') == 0)

# ── P. pairs of doors ────────────────────────────────────────────────────
for L, fs in (('67/E35', 119.25), ('68/E35', 147.5), ('87/E44', 78.0)):
    e = els[L]
    check("P: %s — both 36\" leaves read (doW + doWExtra) and the width closes on 2\" members"
          % L, e['doW'] == 36.0 and e['doWExtra'] == [36.0]
          and W(L).get('ok') and W(L).get('member') == 2.0)
check("P4: a pair printed as ONE 6'-0\" D.O. (86/E44) has no extra leaf and still closes",
      els['86/E44']['doW'] == 72.0 and els['86/E44']['doWExtra'] == [] and W('86/E44').get('ok'))
check("P5: the extra leaf's footprint is kept so the sheet can highlight it",
      len(els['67/E35']['dims']['doWExtra']) == 1 and els['67/E35']['dims']['doWExtra'][0]['rect'])
B = di._balance_axis
check("P6: the arithmetic — 67/E35 closes with both leaves, not with one",
      B(119.25, [36.0, 36.0], [18.625, 72.625, 18.625])['ok']
      and B(119.25, [36.0, 36.0], [18.625, 72.625, 18.625])['member'] == 2.0
      and not B(119.25, 36.0, [18.625, 72.625, 18.625])['ok'])
check("P7: a single D.O. (a scalar) behaves exactly as before",
      B(40.0, 36.0, []) == {'ok': True, 'member': 2.0, 'used': 1, 'dropped': 0})
h = di._balance_chains([{'x0': 10, 'value': 100.0}],
                       [{'x0': 20, 'value': 50.0}, {'x0': 30, 'value': 46.0}], [], 'x0')
check("P8: on the HEIGHT axis only the first D.O. counts — leaves never stack "
      "(100 - 50 = 2 x 25 would fail; 100 - 50 - 46 = 4 closes; first-only must not close)",
      h is not None and h['ok'] is False)

# ── S. ownership by the frame's extent ───────────────────────────────────
check("S1: 46/E22 gets its own first 45 5/8 (drawn 32pt left of its bubble)",
      els['46/E22']['dloWidths'] == [45.625, 68.0, 68.0, 68.0, 45.625])
check("S2: …and closes with every D.L.O. used: 2 x 45 5/8 + 3 x 68 + 6 x 2 = 307 1/4",
      W('46/E22').get('ok') and W('46/E22').get('dropped') == 0 and W('46/E22').get('used') == 5)
check("S3: 45/E22 no longer holds the borrowed value — and closes with nothing dropped",
      els['45/E22']['dloWidths'] == [33.625, 71.625] and W('45/E22').get('dropped') == 0)
import pdfplumber
p41 = pdfplumber.open(FIXTURE).pages[1]
check("S4: the sheet scale is read from the title block for THIS paper size — 11x17 at "
      "1/4\" = 1'-0\" is 1.5 pt per inch (24x36 at 1/2\" would say 3.0)",
      di._sheet_scale(p41, p41.extract_text()) == 1.5)
check("S5: no readable scale -> no spans (the column rule decides, as before)",
      di._sheet_scale(p41, 'no scale here') is None and di._width_spans([{}], [], None) == {})
ca, cb = {'x0': 100, 'top': 500}, {'x0': 100, 'top': 800}
ev = {'x0': 150, 'top': 200, 'rect': [150, 200, 170, 210]}
spans = {id(ca): (0, 400), id(cb): (0, 400)}
check("S6: two frames that overlap (a stacked column) -> ambiguous -> the column rule decides",
      di._width_owner([ca, cb], ev, spans) is di._nearest_by_x([ca, cb], 150, 200))

# ── X. a real limit, still reported ──────────────────────────────────────
check("X1 (re-pinned r546): 65/E33 — a 2 1/2\" expansion mullion among 2\" members — "
      "was left flagged in r545 (the check assumed one member size). r546 reads the "
      "members the sheet prints, and it closes on them: 416 3/4 + 6 x 2 + 2 1/2 = 431 1/4. "
      "Detail in test_drawing_import_expansion_mullion.py",
      W('65/E33').get('ok') is True and W('65/E33')['chains'][0].get('printed') is True
      and abs(sum(els['65/E33']['dloWidths']) + 6 * 2 + 2.5 - els['65/E33']['fsW']) < 1e-9)

passed = sum(1 for ok, _ in results if ok)
for ok, name in results:
    print(("  ok    " if ok else "  FAIL  ") + name)
print("\n%d/%d" % (passed, len(results)))
sys.exit(0 if passed == len(results) else 1)

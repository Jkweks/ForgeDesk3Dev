"""Round 560 — dimensions typed on the sheet as Bluebeam markups.

Fixture: test_samples/drawing_import/Howell_NW_typed_markups.pdf — seven real
pages (11, 20, 22, 25, 30, 44, 46) of Howell_NW_Shop_Drawings.pdf, annotations
intact. TimA typed field-revised dimensions over the CAD ones as FreeText
"typewriter" boxes; some sheets had the CAD value deleted (page 11), some
keep it hidden under the white box (page 22).

What this proves:
  T1  1/E1 (p11): its widths exist ONLY as typed markups — they are read
      (R.O. 64 1/4", F.S. 63 1/2", D.L.O. 59 1/2") and the frame closes.
  T2  …and they are credited: author TimA, date 20260903, axis w.
  T3  15/E8 (p22): a typed 42 1/8" D.L.O. over a hidden printed 57" replaces
      it, and the record says what it covered.
  T4  Printed beats typed for an overall: 25/E13's typed 31 1/2" F.S. (a
      transom frame) does not displace the printed 70 3/4" …
  T5  … is NOT credited as a source (it did not land) …
  T6  … and is reported, never silently dropped (no-smoothing).
  T7  55/E25: a typed F.S. touching the D.L.O. line is read as the F.S. it
      sits level with (r563 — r560 misread it as a D.L.O. conflict).
  T10 19/E10 (p25): typed bay D.L.O.s set UNDER their label are read (r563).
  T11 25/E13: the same layout, three bays.
  T12 15/E8: a hidden printed value stays out on a doubled-text sheet.
  T13 The conflict path (typed beside a visible printed value).
  T8  A page with no markups reads exactly as it always has (57/E27).
  T9  12/E6 (p20) members as printed, 2 bays × 2 bands, both chains close.

Non-vacuity (proved by reversion on a scratch copy): dropping the
_with_markups wrap in iter_drawing_pages fails T1/T2/T3; dropping the
_landed filter fails T5; dropping the typed-vs-printed warning fails T6;
reverting "printed beats typed" fails T4. r563: dropping the label-above
rule fails T10/T11; first-match instead of level-with label fails T7;
dropping the hidden-under-box sweep fails T3/T12.

Run:  python3 test_drawing_import_typed_markups.py   (exit code is the verdict)
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FX = os.path.join(HERE, 'test_samples', 'drawing_import', 'Howell_NW_typed_markups.pdf')
if not di._HAVE_PDFPLUMBER or not os.path.isfile(FX):
    print("SKIP: pdfplumber or the Howell NW fixture is unavailable")
    sys.exit(0)

res = di.parse_drawing_set(open(FX, 'rb').read(), 'Howell_NW_typed_markups.pdf')
E = res['elevations']
by = {}
for e in E:
    by.setdefault(e['label'], []).append(e)
one = lambda k: by.get(k, [None])[0]
results = []
check = lambda name, ok: results.append((bool(ok), name))

e1 = one('1/E1')
check('T1: 1/E1 reads its typed widths (64 1/4 / 63 1/2 / [59 1/2]) and closes',
      e1 and e1['roW'] == 64.25 and e1['fsW'] == 63.5 and e1['dloWidths'] == [59.5]
      and e1['balance']['w']['ok'])
check('T2: …credited to TimA, 20260903, as widths',
      e1 and sorted((m['label'], m['axis'], m['author'], m['date']) for m in e1['markups'])
      == sorted([('R.O.', 'w', 'TimA', '20260903'), ('F.S.', 'w', 'TimA', '20260903'),
                 ('D.L.O.', 'w', 'TimA', '20260903')]))

e8 = one('15/E8')
check('T3: 15/E8 — the typed 42 1/8" D.L.O. replaces the hidden printed 57", and says so',
      e8 and 42.125 in e8['dloWidths'] and 57.0 not in e8['dloWidths']
      and any(m['label'] == 'D.L.O.' and m['value'] == 42.125 and m['covers'] == 57.0
              for m in e8['markups']))

e13 = one('25/E13')
check('T4: 25/E13 keeps the printed 70 3/4" F.S. height over the typed 31 1/2"',
      e13 and e13['fsH'] == 70.75)
check('T5: …the typed 31 1/2" is not credited as a source of this record',
      e13 and not any(m['axis'] == 'h' and m['label'] == 'F.S.' for m in e13['markups']))
check('T6: …and is reported for the PM to check',
      e13 and any('typed height F.S. of 31 1/2" is on the sheet, but the printed 70 3/4" was used'
                  in w for w in e13['warnings']))

e25 = by.get('55/E25', [None])[0]
check('T7: 55/E25 (p44) — the typed 111 3/4" is read as the F.S. it sits level with, not as the '
      'D.L.O. line it touches; with the typed R.O. and both typed bays the frame closes, and no '
      'false conflict is reported (r563)',
      e25 and e25['roW'] == 112.5 and e25['fsW'] == 111.75
      and e25['dloWidths'] == [52.875, 60.625, 52.875] and e25['balance']['w']['ok']
      and not any('A typed markup says' in w for w in e25['warnings']))

e19 = one('19/E10')
check('T10: 19/E10 (p25) — the two typed 40 1/16" bays UNDER their "D.L.O." labels are read with '
      'the printed 25 5/8"; R.O. 120 1/2" and the typed F.S. 119 3/4" (running under its label) '
      'too; the width chain closes (r563)',
      e19 and e19['dloWidths'] == [25.625, 40.0625, 40.0625] and e19['roW'] == 120.5
      and e19['fsW'] == 119.75 and e19['balance']['w']['ok']
      and sum(1 for m in e19['markups'] if m['label'] == 'D.L.O.' and m['value'] == 40.0625) == 2)
check('T11: 25/E13 (p30) — the same layout: three bays, 25 5/8" + two typed 39 15/16"',
      e13 and e13['dloWidths'] == [25.625, 39.9375, 39.9375] and e13['balance']['w']['ok'])
check('T12: 15/E8 — the hidden 57" stays out even though p22 prints its labels twice',
      e8 and 57.0 not in e8['dloWidths'])

# The conflict path: no sheet in this set still has a typed value beside a
# VISIBLE printed one (p44's was a misread), so it is exercised directly on
# _merge_markups with a reading and a markup shaped as the reader makes them.
class _P(object):
    markup_conflicts = []
_pg = _P()
_lab = [300.0, 140.0, 318.0, 146.0]
_pg.markups = [{'axis': 'w', 'label': 'D.L.O.', 'lrect': _lab, 'value': 40.0, 'text': '40"',
                'x0': 260.0, 'x1': 296.0, 'top': 139.0, 'bottom': 147.0, 'hides': []}]
_out = di._merge_markups(_pg, {'DLO': [{'x0': 270.0, 'top': 140.0, 'value': 39.5, 'stacked': False,
                                        'lrect': list(_lab)}]}, 'w')
check('T13: a typed value beside a visible printed one is a conflict — printed kept, reported',
      [e['value'] for e in _out['DLO']] == [39.5] and len(_pg.markup_conflicts) == 1
      and _pg.markup_conflicts[0]['printed'] == 39.5)

e27 = one('57/E27')
check('T8: 57/E27 (no markups) — unchanged: 7 bays, printed members with the 2 1/2" expansion '
      'mullions, F.S. 599 1/4", no markups',
      e27 and len(e27['dloWidths']) == 7 and e27['fsW'] == 599.25
      and e27['printedMembersW'] == [[2.0, 2.0, 2.5, 2.0, 2.0, 2.5, 2.0, 2.0]]
      and e27['markups'] == [])

e6 = one('12/E6')
check('T9: 12/E6 — 2 bays × 2 bands, members printed 2", both chains close',
      e6 and e6['dloWidths'] == [35.625, 45.375] and e6['dloHeights'] == [27.125, 37.625]
      and e6['printedMembersW'] == [[2.0, 2.0, 2.0]]
      and e6['balance']['w']['ok'] and e6['balance']['h']['ok'])

fail = 0
for ok, name in results:
    print(('PASS ' if ok else 'FAIL ') + name)
    fail += (not ok)
print('\n%d/%d passed' % (len(results) - fail, len(results)))
sys.exit(1 if fail else 0)

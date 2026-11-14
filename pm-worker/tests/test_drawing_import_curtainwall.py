"""Multi-sheet curtain-wall elevations — r510.

A large curtain wall is drawn across several sheets, and the adjoining sheets
OVERLAP: each draws a little past its boundary so the neighbouring lites read
in context. Counting sheet by sheet double-counts. The boundary is a MATCH
LINE, drawn as a thick bar with a numbered label.

Jeremiah's rules, confirmed against the drawing:
  * a sheet owns the last lite drawn in FULL before its match line
  * a lite may be split by the line and need not fall on a mullion
  * match lines always come in numbered pairs

One test covers all three: a sheet owns every lite drawn WHOLLY BETWEEN its
match lines. A lite cut by a line is not wholly inside on the sheet that cuts
it, and is wholly inside on the sheet that draws it complete — counted once,
no special case.

Elevation-vs-plan is decided by GLASS TAGS. An elevation tags every lite
because someone has to order that glass; a plan locates layout points because
someone has to set the frame out. Two signals that looked obvious first each
held for one elevation and failed on the other: 'L.P. TO L.P.' dimensions
(C3's elevations have 14/9/7, C2's have none) and the 'FLATTENED VIEW' note
(misses E41C). Only the tags hold for both.

Fixture: the real C2/C3 runs from Beacon Hill, pages 54-66 of the set.

Run: python3 test_drawing_import_curtainwall.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                       'test_samples', 'drawing_import',
                       'Beacon_Hill_curtainwall_C2_C3.pdf')

results = []
def check(name, ok):
    results.append((bool(ok), name))

if not di._HAVE_PDFPLUMBER or not os.path.isfile(FIXTURE):
    print("SKIP: pdfplumber or the curtain-wall fixture is unavailable")
    sys.exit(0)

import pdfplumber
pdf = pdfplumber.open(FIXTURE)
PAGES = list(pdf.pages)          # fixture page i == set page 54 + i

# ── M. finding the match lines ───────────────────────────────────────────

check("M1: the label is MIRRORED in the text layer — it reads '1ENILHCTAM', which is why "
      "every plain search for 'MATCH LINE' came back empty on sheets that plainly have "
      "them. The detector reads it anyway.",
      len(di._matchlines(PAGES[0])) == 1
      and di._matchlines(PAGES[0])[0]['num'] == 1)

check("M2: they come in NUMBERED PAIRS, so a run chains without guessing page order — "
      "C3 is E39(1) -> E39A(1,2) -> E39B(2)",
      [[m['num'] for m in di._matchlines(PAGES[i])] for i in (0, 1, 2)]
      == [[1], [1, 2], [2]])

check("M3: and C2 chains over FOUR sheets, 1-2-3 — which is what the glass-tag rule "
      "predicted before the answer was known",
      [[m['num'] for m in di._matchlines(PAGES[i])] for i in (6, 7, 8, 9)]
      == [[1], [1, 2], [2, 3], [3]])

check("M4: a middle sheet's two lines sit at opposite edges, an end sheet's single line "
      "at the end it joins on",
      (lambda a, b, c: len(a) == 1 and len(b) == 2 and len(c) == 1
       and a[0]['x'] > 900 and b[0]['x'] < 200 and b[1]['x'] > 900
       and c[0]['x'] < 200)(di._matchlines(PAGES[0]), di._matchlines(PAGES[1]),
                            di._matchlines(PAGES[2])))

check("M5: the label is drawn twice a few points apart, either side of the bar — those "
      "collapse to the ONE boundary they mark rather than reading as two",
      all(len(di._matchlines(p)) <= 2 for p in PAGES))

# ── O. ownership ─────────────────────────────────────────────────────────

def owned(i):
    p = PAGES[i]
    ev = [e for e in di._width_events(p)['DLO'] if e['value'] is not None]
    return [e['value'] for e in di._owned_between(ev, di._matchlines(p), float(p.width))]

check("O1: E39 owns 53 3/8\" — the last lite drawn in full before its match line, exactly "
      "as Jeremiah named it — and NOT the 53 1/2\" drawn past the line for context",
      53.375 in owned(0) and 53.5 not in owned(0))

check("O2: E39A does not re-count that 53 3/8\": it is drawn left of E39A's own match "
      "line 1, so it belongs to the sheet before. Its first owned lite is the 53 1/2\".",
      53.375 not in owned(1) and owned(1)[0] == 53.5)

check("O3: the overlap is real and the rule is what removes it — 53 3/8\" and 53 1/2\" are "
      "both drawn on BOTH sheets",
      (lambda a, b: 53.375 in a and 53.5 in a and 53.375 in b and 53.5 in b)(
          [e['value'] for e in di._width_events(PAGES[0])['DLO'] if e['value'] is not None],
          [e['value'] for e in di._width_events(PAGES[1])['DLO'] if e['value'] is not None]))

check("O4: an end sheet with one match line owns everything on its own side of it",
      len(owned(2)) == 12 and len(owned(0)) == 15 and len(owned(1)) == 11)

check("O5: a sheet with no match lines owns everything on it — the rule costs nothing on "
      "an ordinary single-sheet elevation",
      (lambda p: len(di._owned_between(
          [e for e in di._width_events(p)['DLO'] if e['value'] is not None], [], float(p.width)))
       == len([e for e in di._width_events(p)['DLO'] if e['value'] is not None]))(PAGES[3]))

# ── A. where a dimension SITS ────────────────────────────────────────────

check("A1: a dimension is anchored by its BRACKETED value, not by its captured footprint. "
      "On E39 one 53 3/8\" reading comes back with a 270pt-wide rect spanning the "
      "FLATTENED VIEW title and its own arrow — sorting on that rect's left edge put the "
      "lite hundreds of points from where it is drawn and reordered it against its "
      "neighbours. (r543 re-pin: that wide footprint is now cut at its source — the "
      "value walk stops at a 20pt gap — so every reading on E39 is one dimension wide AND "
      "still anchors on its own bracket. A1b keeps the anchor rule tested on its own.)",
      (lambda ev: ev and all(e.get('rect') and (e['rect'][2] - e['rect'][0]) <= 120 for e in ev)
       and all(e.get('bracket_rect') is None
               or abs(di._dim_anchor_x(e) - (e['bracket_rect'][0] + e['bracket_rect'][2]) / 2.0) < 1e-6
               for e in ev)
       and any(e['value'] == 53.375 for e in ev))(
          [e for e in di._width_events(PAGES[0])['DLO'] if e['value'] is not None]))

check("A1b: and the anchor is what OWNERSHIP is measured with. A dimension whose unioned "
      "footprint starts left of a match line but whose value is drawn right of it belongs "
      "to the next sheet; anchoring on the footprint would hand it to this one.",
      (lambda ev, ml: [e['value'] for e in di._owned_between(ev, ml, 1224.0)] == [10.0])(
          [{'value': 10.0, 'x0': 100.0, 'top': 50.0,
            'bracket_rect': [110.0, 50.0, 140.0, 60.0], 'rect': [100.0, 50.0, 400.0, 60.0]},
           {'value': 20.0, 'x0': 200.0, 'top': 50.0,
            'bracket_rect': [980.0, 50.0, 1010.0, 60.0], 'rect': [200.0, 50.0, 1010.0, 60.0]}],
          [{'num': 1, 'x': 900.0}]))

check("A2: the bracketed value's own box is kept apart from the unioned label footprint, "
      "so the anchor has something honest to use",
      any(e.get('bracket_rect') for e in di._width_events(PAGES[0])['DLO'])
      and all((e.get('bracket_rect') is None
               or (e['bracket_rect'][2] - e['bracket_rect'][0]) < 120)
              for e in di._width_events(PAGES[0])['DLO']))

# ── C. elevation or plan ─────────────────────────────────────────────────

res = di.parse_drawing_set(open(FIXTURE, 'rb').read(), 'Beacon.pdf')
els = {e['label']: e for e in res['elevations']}

check("C1: the thirteen sheets come out as TWO elevations, not thirteen records and not "
      "six colliding ones called C3",
      len(res['elevations']) == 2 and set(els) == {'C2', 'C3'})

check("C2: C3 is three elevation sheets with three plan sheets alongside",
      els['C3']['sheets'] == [1, 2, 3] and els['C3']['planSheets'] == [4, 5, 6])

check("C3: C2 is FOUR elevation sheets and three plan sheets — the asymmetry the glass-tag "
      "rule predicted and a symmetry assumption would have got wrong",
      els['C2']['sheets'] == [7, 8, 9, 10] and els['C2']['planSheets'] == [11, 12, 13])

check("C4: the plan sheets produce no records of their own — they are set-out drawings, "
      "not things to build",
      not any(e.get('label') in ('49/E40', '50/E42') for e in res['elevations']))

check("C5: a plan sheet whose title block will not extract still groups by its LABEL "
      "rather than being orphaned or guessed at from page order (page 66)",
      len(els['C2']['planSheets']) == 3)

check("C6: each merge says in the warnings which pages went where, so a dropped sheet is "
      "visible rather than silent",
      any('C3' in w and 'plan sheet' in w for w in res['warnings'])
      and any('C2' in w and 'plan sheet' in w for w in res['warnings']))

# ── Q. quantity and width ────────────────────────────────────────────────

check("Q1: quantity is the DEDUPLICATED lite count across the run (Jeremiah) — C3's "
      "15 + 11 + 12, with the two lites at the joins counted once each",
      els['C3']['totalReqd'] == 38 and len(els['C3']['dloWidths']) == 38)

check("Q2: and C2's four sheets likewise",
      els['C2']['totalReqd'] == 36 and len(els['C2']['dloWidths']) == 36)

check("Q3: R.O. width is the complete total across the run — the framing width Vos will "
      "build, even though it does not match the laid-out curve (Jeremiah)",
      abs(els['C3']['roW'] - sum(els['C3']['dloWidths'])) < 1e-9
      and els['C3']['roW'] > 1700)

check("Q4: the merged record is marked multi-sheet, so nothing downstream mistakes it for "
      "an ordinary elevation",
      els['C3'].get('multiSheet') is True and els['C2'].get('multiSheet') is True)

check("Q5: its balance is left UNJUDGED rather than passed or failed — the frame closes "
      "per sheet and never across the join, so an axis check on the merged record would "
      "be arithmetic on a thing that does not exist",
      els['C3']['balance'] == {'w': None, 'h': None}
      and els['C2']['balance'] == {'w': None, 'h': None})

pdf.close()

# ── R. mirror is a manual takeoff (r511) ─────────────────────────────────
# Beacon Hill's YOGA and FITNESS are MIRROR WALLS, not framed elevations: no
# F.S., no D.L.O., an overall R.O., bare module widths, row heights labelled
# R.O., and a J-channel note. Jeremiah's call is that mirror stays a MANUAL
# takeoff — so the job is not to size anything, it is to make sure a PM is
# reminded to. He puts mirror at 5-10% of jobs, so this recurs.

MIRROR_SCHEDULE = [{'tag': 'IG-1', 'makeup': ['1/4" CLEAR LOW-E ANNEALED']},
                   {'tag': 'GL-M', 'makeup': ['1/4" CLEAR MIRROR']}]

check("R1: mirror is recognised from the schedule's MAKE-UP, not from a hardcoded tag — "
      "sheet 1 defines GL-M as 1/4\" CLEAR MIRROR, and reading it there keeps working on a "
      "job that names the tag something else",
      di._mirror_tags(MIRROR_SCHEDULE) == {'GL-M'})

check("R2: a schedule with no mirror in it yields no tags, so nothing is flagged on an "
      "ordinary job",
      di._mirror_tags([{'tag': 'IG-1', 'makeup': ['1/4\" CLEAR TEMPERED']}]) == set()
      and di._mirror_tags([]) == set()
      and di._mirror_tags(None) == set())

check("R3: an elevation glazed with a mirror tag is flagged, with the panel count, and one "
      "glazed with anything else is left alone",
      (lambda recs: (recs[0].get('manualTakeoff') or {}).get('reason') == 'mirror'
       and recs[0]['manualTakeoff']['count'] == 3
       and recs[1].get('manualTakeoff') is None)(
          (lambda rs: (di._flag_mirror(rs, MIRROR_SCHEDULE), rs)[1])(
              [{'label': 'YOGA', 'glassTags': [{'tag': 'GL-M'}, {'tag': 'GL-M'}, {'tag': 'GL-M'}]},
               {'label': '1/E1', 'glassTags': [{'tag': 'IG-1'}]}])))

check("R4: the flag says MANUAL TAKEOFF and does not touch a single reading — the app has "
      "not sized these, and pretending otherwise is what would put a wrong mirror size on "
      "a glass order",
      (lambda rs: (di._flag_mirror(rs, MIRROR_SCHEDULE),
                   rs[0]['roW'] == 384.0 and rs[0]['dloWidths'] == [])[1])(
          [{'label': 'YOGA', 'roW': 384.0, 'dloWidths': [],
            'glassTags': [{'tag': 'GL-M'}]}]))

check("R5: it writes a warning naming the tag and the count, so it is visible at import "
      "rather than only on the record",
      (lambda rs: any('mirror' in n.lower() and 'MANUAL' in n
                      for n in di._flag_mirror(rs, MIRROR_SCHEDULE)))(
          [{'label': 'YOGA', 'glassTags': [{'tag': 'GL-M'}]}]))

MIRROR_FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                              'test_samples', 'drawing_import',
                              'Beacon_Hill_mirror_YOGA.pdf')

if os.path.isfile(MIRROR_FIXTURE):
    mres = di.parse_drawing_set(open(MIRROR_FIXTURE, 'rb').read(), 'mirror.pdf')
    mels = {e['label']: e for e in mres['elevations']}

    check("R6: end to end on the REAL sheets — YOGA's 16 mirror panels and FITNESS's 8 are "
          "both flagged, from sheet 1's schedule, with nothing hardcoded",
          (mels['51/E43'].get('manualTakeoff') or {}).get('count') == 16
          and (mels['52/E44'].get('manualTakeoff') or {}).get('count') == 8)

    check("R7: and the warning reaches the IMPORT, not just the record — it is what a PM "
          "reads at the moment the sheets are in front of them",
          len([w for w in mres['warnings']
               if 'mirror' in w.lower() and 'MANUAL' in w]) == 2)

    check("R8: their readings are untouched — an overall R.O. of 384\" is what the sheet "
          "prints, and no lite list was invented for a wall the app did not size",
          mels['51/E43']['roW'] == 384.0 and mels['51/E43']['dloWidths'] == []
          and mels['51/E43']['balance'] == {'w': None, 'h': None})

passed = 0
for ok, name in results:
    print(("  ok  " if ok else "  FAIL") + "  " + name)
    if ok:
        passed += 1
print("\n%d/%d" % (passed, len(results)))
sys.exit(0 if passed == len(results) else 1)

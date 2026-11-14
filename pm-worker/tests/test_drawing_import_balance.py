"""Does the frame close? — the chain-aware balance check (r506).

An elevation's frame is an equation: frame size = the openings in it plus the
members between them. If it cannot close, something was read off the wrong
dimension line — the failure worth catching, because a wrong D.L.O. flows
straight into a glass size.

The NAIVE form (F.S. minus every term) does not work, and r505 found out why
on real sheets: a TRANSOM sits on a PARALLEL chain, not in series. On 11/E8
the 34" transom is ABOVE the 36" door, so summing them overshoots the 40"
frame. Chain membership is the whole problem.

So this asks the arithmetic instead of guessing chains from geometry: the
LARGEST subset of terms that closes the frame with a plausible uniform member.
Two structural facts keep it honest — a D.O. is mandatory (a door is always in
its own frame), and members number |S|+1 or |S| (a door runs to the sill with
no member beneath it).

What this proves:
  R. The rule itself, on the cases that define it.
  A. Against the REAL Alpine set: exactly two flags, and both are genuine.
  U. Unverifiable is not a pass.
  N. It reports and never edits — r505's lesson was that the parser is
     usually right and the equation was wrong.

Run: python3 test_drawing_import_balance.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                       'test_samples', 'drawing_import',
                       'Alpine_Apartments_Submittal_rev1.pdf')

results = []
def check(name, ok):
    results.append((bool(ok), name))

B = di._balance_axis

# ── R. the rule ──────────────────────────────────────────────────────────

check("R1: a plain storefront closes — 91 1/4\" frame, two 42 5/8\" lites, three 2\" "
      "members (1A/E1's real numbers)",
      (B(91.25, None, [42.625, 42.625]) or {}).get('ok') is True
      and B(91.25, None, [42.625, 42.625])['member'] == 2.0)

check("R2: a door elevation closes only once the D.O. is counted — 6/E3's two 7 5/8\" "
      "sidelites and a 72\" door in a 95 1/4\" frame, four 2\" members. Without D.O. this "
      "is the read that looked wrong and was not.",
      (B(95.25, 72.0, [7.625, 7.625]) or {}).get('ok') is True
      and B(95.25, 72.0, [7.625, 7.625])['dropped'] == 0)

check("R3: a TRANSOM is dropped, not summed — 11/E8's 40\" frame holds a 36\" door with "
      "two 2\" jambs; its 34\" transom D.L.O. sits on a parallel chain above the door, and "
      "including it would overshoot by 30\"",
      (lambda r: r['ok'] and r['dropped'] == 1 and r['member'] == 2.0)(B(40.0, 36.0, [34.0])))

check("R4: a door running to the sill needs no member beneath it — 85 3/4\" frame, 84\" "
      "door, one 1 3/4\" head member (13/E10)",
      (lambda r: r['ok'] and r['member'] == 1.75)(B(85.75, 84.0, [])))

check("R5: the D.O. is MANDATORY — it can never be the term dropped, because a door is "
      "always in the frame it sits in. A frame that only closes by ignoring its door "
      "does not close.",
      (B(40.0, 99.0, [38.0]) or {}).get('ok') is False)

check("R6: an implied member has to be a real one — a sixteenth, between 1\" and 4 1/2\". "
      "A face wider than that is not a member, it is a panel.",
      (B(100.0, None, [80.0]) or {}).get('ok') is False          # 10" members
      and (B(100.0, None, [97.03]) or {}).get('ok') is False     # not a sixteenth
      and (B(100.0, None, [96.0]) or {}).get('ok') is True)      # 2" members

check("R7: the ceiling is not doing delicate work — 5/E2's 5 11/16\" implied member fails "
      "at 4 1/2\" and would also fail at 3 1/2\", while every correct elevation in the set "
      "closes at 1 1/2\"-2\"",
      (B(39.5, None, [33.8125]) or {}).get('ok') is False)

# ── C. chains ────────────────────────────────────────────────────────────

_G = di._chain_groups

check("C1: dimension lines nested within one chain stay together — 10/E7's left chain "
      "steps 80 -> 102 -> 125, about 22pt apart",
      len(_G([{'x0': 80.1}, {'x0': 102.4}, {'x0': 124.9}], 'x0')) == 1)

check("C2: a wide gap splits chains — 10/E7's door chain sits at 430-498, three hundred "
      "points from the sidelite chain",
      len(_G([{'x0': 80.1}, {'x0': 102.4}, {'x0': 124.9},
              {'x0': 430.1}, {'x0': 452.6}, {'x0': 475.1}, {'x0': 497.6}], 'x0')) == 2)

check("C3: the 60pt threshold has wide margin either side — within-chain steps are ~22pt "
      "and the between-chain gap is ~305pt, so it is not delicate",
      len(_G([{'x0': 0}, {'x0': 22}, {'x0': 44}], 'x0')) == 1
      and len(_G([{'x0': 0}, {'x0': 305}], 'x0')) == 2)

check("C4b: EVERY chain has to close, not just one — an elevation with a good frame and "
      "a broken one is a broken elevation, and passing it because something on the sheet "
      "added up would be worse than not checking at all",
      (lambda r: r is not None and r['ok'] is False and len(r['chains']) == 2)
      (di._balance_chains(
          [{'x0': 10, 'value': 91.25}, {'x0': 400, 'value': 40.0}],
          [],
          [{'x0': 12, 'value': 42.625}, {'x0': 14, 'value': 42.625},   # closes: 2" members
           {'x0': 402, 'value': 9.0}],                                  # 40 - 9 = 31 over 2
          'x0')))

check("C4: a chain with no frame size is skipped, not failed — there is nothing to close",
      (lambda r: r is not None and r['ok'])
      (di._balance_chains([{'x0': 10, 'value': 91.25}],
                          [],
                          [{'x0': 12, 'value': 42.625}, {'x0': 14, 'value': 42.625},
                           {'x0': 400, 'value': 33.0}],   # a chain with only a D.L.O.
                          'x0')))

# ── U. unverifiable is not a pass ────────────────────────────────────────

check("U1: no frame size means the axis CANNOT be judged, and says so — silence would "
      "read as fine, which is what the four DOOR JAMB LENGTH sheets did before r505",
      B(None, 84.0, [18.375]) is None)

check("U2 (repinned r513): a frame size with NOTHING in it cannot be judged either \u2014 the "
      "same rule as 'no frame size is not a pass', applied to the other side: unjudgeable "
      "is not a FAIL. This check was originally written as 'is None OR ok is False', which "
      "permitted both answers and so pinned neither.",
      B(40.0, None, []) is None)

TWIN = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                    'test_samples', 'drawing_import', 'Beacon_Hill_twin_E25.pdf')
if di._HAVE_PDFPLUMBER and os.path.isfile(TWIN):
    tres = di.parse_drawing_set(open(TWIN, 'rb').read(), 'twin.pdf')
    tels = {e['label']: e for e in tres['elevations']}

    check("U3 (r513): the real case. 33/E25 shares its sheet with 34/E25 and both are drawn "
          "with identical height chains. The callout filter let the neighbour's F.S. "
          "through while keeping only this elevation's D.L.O.s, so a chain arrived holding "
          "a frame size and no openings \u2014 and was reported as a frame that does not close, "
          "on an elevation where Jeremiah confirmed both axes check out.",
          (tels['33/E25']['balance']['h'] or {}).get('ok') is True)

    check("U4: and its one real chain still closes on its own numbers \u2014 142 3/4\" frame, "
          "a 21 5/8\" and a 115 1/8\" lite, three 2\" members",
          len((tels['33/E25']['balance']['h'] or {}).get('chains', [])) == 1
          and tels['33/E25']['balance']['h']['chains'][0]['member'] == 2.0)

    check("U5: the width was never in doubt and is untouched",
          (tels['33/E25']['balance']['w'] or {}).get('ok') is True)

# ── A. the real set ──────────────────────────────────────────────────────

if di._HAVE_PDFPLUMBER and os.path.isfile(FIXTURE):
    res = di.parse_drawing_set(open(FIXTURE, 'rb').read(), 'Alpine.pdf')
    els = {e['label']: e for e in res['elevations']}
    flags = [(lab, ax) for lab, e in els.items() for ax in ('w', 'h')
             if (e.get('balance') or {}).get(ax) is not None
             and not e['balance'][ax]['ok']]
    unver = [(lab, ax) for lab, e in els.items() for ax in ('w', 'h')
             if (e.get('balance') or {}).get(ax) is None]

    check("A1: every elevation carries a per-axis verdict",
          all((e.get('balance') or {}).get('w') is not None
              or (e.get('balance') or {}).get('h') is not None
              for e in res['elevations']))

    check("A2 (r507): the whole 19-elevation set produces exactly ONE flag — every other "
          "axis closes, including all 13 door elevations that no rule could judge before "
          "r505 and the two-frame elevation that r506 wrongly flagged",
          len(flags) == 1)

    check("A3: and it is the genuine one: 5/E2's width, which Jeremiah independently "
          "confirmed is wrong",
          sorted(flags) == [('5/E2', 'w')])

    check("A6 (r507): 10/E7 carries TWO independent height chains and both close — the "
          "sidelites in an 83\" frame starting 16\" off the floor (2\" members) and the "
          "doors in a 99\" frame running to it (1 3/4\"). They top out together: "
          "83 + 16 = 99. Comparing one chain's frame size against the other's door is "
          "what made r506 flag a correct elevation.",
          (lambda b: b is not None and b['ok'] and len(b['chains']) == 2
           and sorted(c['member'] for c in b['chains']) == [1.75, 2.0])
          (els['10/E7']['balance']['h']))

    check("A7 (r507): a single-chain elevation still reports one chain, so the grouping "
          "costs nothing where there is nothing to group",
          len(els['1A/E1']['balance']['w']['chains']) == 1
          and len(els['1A/E1']['balance']['h']['chains']) == 1)

    check("A4: nothing is left unverifiable now that DOOR JAMB LENGTH reads (r505) — "
          "before it, four sheets had no frame-size height at all",
          unver == [])

    check("A5: a door elevation reports which terms it had to drop, so the verify screen "
          "can say WHY rather than only that it closed",
          els['11/E8']['balance']['w']['dropped'] == 1
          and els['11/E8']['balance']['h']['dropped'] == 0)

    # ── N. reports, never edits ──────────────────────────────────────────
    check("N1: the flagged elevation's own readings are untouched — the check reports, "
          "it does not 'correct'. r505's lesson was that the parser is usually right and "
          "the equation was wrong; a check that edited would repeat that with more "
          "confidence.",
          els['5/E2']['fsW'] == 39.5 and els['5/E2']['dloWidths'] == [33.8125]
          and els['10/E7']['doH'] == 84.0)

    check("N2: and a balancing elevation is reported as such without its values moving",
          els['1A/E1']['balance']['w']['ok'] is True
          and els['1A/E1']['dloWidths'] == [42.625, 42.625])
else:
    print("  note: Alpine fixture unavailable — set checks skipped")

passed = 0
for ok, name in results:
    print(("  ok  " if ok else "  FAIL") + "  " + name)
    if ok:
        passed += 1
print("\n%d/%d" % (passed, len(results)))
sys.exit(0 if passed == len(results) else 1)

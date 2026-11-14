"""
test_drawing_import_bubble_callouts.py — round 474 (2026-09-10).

Proves the split-bubble elevation-callout convention (convention D) against
the REAL Alpine Apartments submittal set (084313-2 rev 1, Vos in-house
drawn, current title style) — the set whose import failure was reported
directly: sheet E1 carries three elevations (1A/E1 ×4, 2/E1 ×1, 1B/E1 ×6)
but only one, mislabeled, imported; sheets E2–E15 imported nothing at all
("no ELEVATION bubble found").

What round 474 fixed, each pinned below and each proven non-vacuous by a
scratch-copy reversion during the round:
  1. _bubble_callouts: the elevation identity on this style is a bubble —
     number over sheet ("1A" over "E1") — anchored on the THUS REQ'D block,
     verified against the page's own SHEET: field; label "<num>/<sheet>".
     Conventions A/B were grabbing prose ("EAST ELEVATION 1A") and arch
     titles ("ELEVATION S2") instead.
  2. _qty_labeled_events: per-callout labeled THUS/OPP/TOTAL trios — the
     flat _QTY_RE handed the FIRST elevation's numbers to every callout on
     a multi-up sheet (E1 would read 4/4/4 instead of 4/1/6).
  3. _nearest_by_x left-anchor mode: convention-D bubbles sit at their
     block's bottom-LEFT, so column-interval assignment (with a vertical
     tie-break for stacked same-column elevations like 1A/1B) replaces
     plain nearest-x FOR THESE CALLOUTS ONLY — conventions A/B/rounds
     340/344 sets keep the exact old behavior.
  4. _ARCHREF_TOKEN_RE admits dotted arch sheet numbers ("S2/A9.01").

Fixture: test_samples/drawing_import/Alpine_Apartments_Submittal_rev1.pdf
(the real submitted set — drawing pages start after the submittal cover /
signoff pages, proving non-drawing front matter is skipped too). Skips
cleanly when pdfplumber or the fixture is absent, same as
test_drawing_import.py.

Run: python3 test_drawing_import_bubble_callouts.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                        'test_samples', 'drawing_import',
                        'Alpine_Apartments_Submittal_rev1.pdf')


def _skip_no_pdfplumber(test):
    return unittest.skipUnless(di._HAVE_PDFPLUMBER, 'pdfplumber not installed')(test)


def _skip_no_fixture(test):
    return unittest.skipUnless(os.path.isfile(FIXTURE), 'Alpine fixture missing')(test)


class TestArchrefTokenRegex(unittest.TestCase):
    def test_dotted_sheet_number_matches(self):
        self.assertTrue(di._ARCHREF_TOKEN_RE.match('S2/A9.01'))
        self.assertTrue(di._ARCHREF_TOKEN_RE.match('S10/A9.01'))

    def test_legacy_undotted_still_matches(self):
        self.assertTrue(di._ARCHREF_TOKEN_RE.match('W1/A-601'))
        self.assertTrue(di._ARCHREF_TOKEN_RE.match('1A1/A6.10'))

    def test_inch_fractions_never_match(self):
        # '3/8"' — the trailing inch mark must keep dimension fractions out.
        self.assertFalse(di._ARCHREF_TOKEN_RE.match('3/8"'))
        self.assertFalse(di._ARCHREF_TOKEN_RE.match('1/4" JOINT'))


class TestNearestByXLeftAnchor(unittest.TestCase):
    """Pure-geometry checks mirroring the real Alpine E1/E2 layouts."""

    def _e2(self):
        # Three side-by-side blocks, bubbles at block bottom-LEFT (real
        # x positions from the Alpine E2 sheet).
        return [{'x0': 67.9, 'top': 498.8, 'anchor': 'left', 'name': '3/E2'},
                {'x0': 419.4, 'top': 498.8, 'anchor': 'left', 'name': '4/E2'},
                {'x0': 770.5, 'top': 498.8, 'anchor': 'left', 'name': '5/E2'}]

    def test_wide_block_right_edge_stays_with_own_bubble(self):
        # Block 1's third D.L.O. at x275.8 is NEARER bubble 4 (dx 143.6)
        # than bubble 3 (dx 207.9) — interval assignment must still hand
        # it to 3.
        c = di._nearest_by_x(self._e2(), 275.8, 248.3)
        self.assertEqual(c['name'], '3/E2')

    def test_event_slightly_left_of_own_bubble(self):
        # A height chain 13pt left of its bubble (real: x54.4 vs 67.9).
        c = di._nearest_by_x(self._e2(), 54.4, 376.8)
        self.assertEqual(c['name'], '3/E2')

    def test_middle_and_right_columns(self):
        self.assertEqual(di._nearest_by_x(self._e2(), 576.9, 203.3)['name'], '4/E2')
        self.assertEqual(di._nearest_by_x(self._e2(), 923.5, 203.3)['name'], '5/E2')

    def test_stacked_same_column_split_vertically(self):
        # Alpine E1: 1A top-left and 1B bottom-left share a column; each
        # elevation's dims sit ABOVE its own bubble.
        cs = [{'x0': 138.2, 'top': 326.5, 'anchor': 'left', 'name': '1A/E1'},
              {'x0': 657.7, 'top': 501.5, 'anchor': 'left', 'name': '2/E1'},
              {'x0': 138.1, 'top': 718.1, 'anchor': 'left', 'name': '1B/E1'}]
        self.assertEqual(di._nearest_by_x(cs, 299.7, 28.1)['name'], '1A/E1')
        self.assertEqual(di._nearest_by_x(cs, 299.7, 419.6)['name'], '1B/E1')
        self.assertEqual(di._nearest_by_x(cs, 812.8, 210.5)['name'], '2/E1')

    def test_non_anchored_callouts_keep_plain_nearest_x(self):
        # Conventions A/B (rounds 340/344) — no anchor field — must keep
        # the exact old behavior even when a top is supplied.
        cs = [{'x0': 100.0, 'top': 300.0, 'name': 'W1'},
              {'x0': 500.0, 'top': 300.0, 'name': 'W2'}]
        self.assertEqual(di._nearest_by_x(cs, 280.0, 10.0)['name'], 'W1')
        self.assertEqual(di._nearest_by_x(cs, 320.0, 700.0)['name'], 'W2')


@_skip_no_pdfplumber
@_skip_no_fixture
class TestAlpineRealSet(unittest.TestCase):
    """End-to-end against the real submitted set — the exact reported
    failure. Values below were spot-checked by hand against the sheets'
    own printed brackets before pinning (e.g. 3/E2: 3×36 1/2 D.L.O. +
    4×1 3/4 E4500 members = 116 1/2 F.S., exactly as printed)."""

    @classmethod
    def setUpClass(cls):
        with open(FIXTURE, 'rb') as f:
            cls.res = di.parse_drawing_set(f.read(), 'Alpine_Apartments_Submittal_rev1.pdf')

    def _by_label(self):
        return {e['label']: e for e in self.res['elevations']}

    def test_ok_and_no_skipped_elevation_warnings(self):
        self.assertTrue(self.res['ok'])
        # The reported failure surfaced as 14 "no ELEVATION bubble found"
        # warnings (E2–E15) — none may remain.
        self.assertEqual(self.res['warnings'], [])

    def test_all_nineteen_elevations_found(self):
        labels = [e['label'] for e in self.res['elevations']]
        self.assertEqual(len(labels), 19)
        expected = ['1A/E1', '2/E1', '1B/E1', '3/E2', '4/E2', '5/E2', '6/E3',
                    '7/E4', '8/E5', '9/E6', '10/E7', '11/E8', '12/E9', '13/E10',
                    '14/E11', '15/E12', '16/E13', '17/E14', '18/E15']
        self.assertEqual(sorted(labels), sorted(expected))

    def test_e1_three_elevations_with_own_quantities(self):
        by = self._by_label()
        self.assertEqual((by['1A/E1']['thusReqd'], by['1A/E1']['oppReqd'], by['1A/E1']['totalReqd']), (4, 0, 4))
        self.assertEqual((by['2/E1']['thusReqd'], by['2/E1']['totalReqd']), (1, 1))
        self.assertEqual((by['1B/E1']['thusReqd'], by['1B/E1']['totalReqd']), (6, 0 + 6))

    def test_e1_stacked_pair_dimensions_not_pooled(self):
        by = self._by_label()
        a, b, two = by['1A/E1'], by['1B/E1'], by['2/E1']
        self.assertEqual((a['roW'], a['fsW']), (92.0, 91.25))
        self.assertEqual(a['dloWidths'], [42.625, 42.625])
        self.assertEqual((b['roW'], b['fsW']), (92.0, 91.25))
        self.assertEqual(b['dloWidths'], [42.625, 42.625])
        self.assertEqual((two['roW'], two['fsW']), (114.0, 113.25))
        self.assertEqual(two['dloWidths'], [35.0625, 35.125, 35.0625])
        self.assertEqual((a['roH'], a['fsH']), (88.0, 86.75))
        self.assertEqual((two['roH'], two['fsH']), (83.0, 81.75))

    def test_e2_side_by_side_dimensions_by_column(self):
        by = self._by_label()
        self.assertEqual(by['3/E2']['roW'], 117.0)
        self.assertEqual(by['3/E2']['dloWidths'], [36.5, 36.5, 36.5])
        self.assertEqual(by['4/E2']['roW'], 109.0)
        self.assertEqual(by['4/E2']['dloWidths'], [33.8125, 33.875, 33.8125])
        self.assertEqual(by['5/E2']['roW'], 40.0)
        self.assertEqual(by['5/E2']['dloWidths'], [33.8125])

    def test_arch_refs_resolved_dotted(self):
        by = self._by_label()
        self.assertEqual(by['1A/E1']['archRef'], 'S2/A9.01')
        self.assertEqual(by['2/E1']['archRef'], 'S4/A9.01')
        self.assertEqual(by['1B/E1']['archRef'], 'S2/A9.01')
        self.assertEqual(by['5/E2']['archRef'], 'S10/A9.01')

    def test_system_and_finish(self):
        by = self._by_label()
        self.assertIn('T14000', by['1A/E1']['system'])
        self.assertIn('E4500', by['3/E2']['system'])
        self.assertEqual(by['1A/E1']['finish'], 'CLEAR')
        self.assertEqual(by['1A/E1']['finishCode'], 'C1')

    def test_glazing_schedule_and_per_lite_tags(self):
        tags = set(t['tag'] for t in self.res['glazingSchedule'])
        self.assertEqual(tags, {'IG-1', 'IG-1T', 'GL-1'})
        by = self._by_label()
        self.assertEqual(sorted(t['tag'] for t in by['2/E1']['glassTags']),
                         ['IG-1', 'IG-1', 'IG-1', 'IG-1T', 'IG-1T', 'IG-1T'])

    def test_honest_warning_on_doorless_dlo_sheets(self):
        # E10/E11/E12 (single doors) genuinely print no D.L.O. width —
        # a manual-entry warning, never a guessed value.
        by = self._by_label()
        for lbl in ('13/E10', '14/E11', '15/E12'):
            self.assertEqual(by[lbl]['dloWidths'], [])
            self.assertTrue(any('D.L.O.' in w for w in by[lbl]['warnings']))

    # ── Round 475: dimension footprints for the visual sizing view ────────

    def test_dims_footprints_on_2E1(self):
        # Every dimension the sheet prints carries its page-space rect so
        # the client can draw a status highlight exactly over it. Values in
        # dims mirror the flat fields byte-for-byte.
        e = self._by_label()['2/E1']
        self.assertEqual(e['pageSize'], [1224.0, 792.0])
        d = e['dims']
        for k, flat in (('roW', 'roW'), ('fsW', 'fsW'), ('roH', 'roH'), ('fsH', 'fsH')):
            self.assertIsNotNone(d[k], k)
            self.assertEqual(d[k]['v'], e[flat])
            r = d[k]['rect']
            self.assertEqual(len(r), 4)
            self.assertLess(r[0], r[2])
            self.assertLess(r[1], r[3])
        self.assertEqual([x['v'] for x in d['dloWidths']], e['dloWidths'])
        self.assertEqual([x['v'] for x in d['dloHeights']], e['dloHeights'])
        # D.L.O. anchors read left-to-right — rect centers strictly increase.
        cxs = [(x['rect'][0] + x['rect'][2]) / 2 for x in d['dloWidths']]
        self.assertEqual(cxs, sorted(cxs))
        self.assertEqual(len(cxs), 3)
        # The whole footprint sits on the page.
        for x in d['dloWidths'] + d['dloHeights']:
            self.assertTrue(0 <= x['rect'][0] and x['rect'][2] <= 1224)
            self.assertTrue(0 <= x['rect'][1] and x['rect'][3] <= 792)

    def test_dims_vertical_anchor_centers_on_the_lite(self):
        # The vision D.L.O. height chain's rect center is the visual pane's
        # vertical anchor for the glass boxes — verified against the real
        # lite center measured off the rendered sheet (~381.9pt) during the
        # round-475 build. Pinned loosely (±5pt) so a re-extraction can't
        # silently drift the boxes off the lites.
        d = self._by_label()['2/E1']['dims']
        vision = max(d['dloHeights'], key=lambda x: x['v'])
        cy = (vision['rect'][1] + vision['rect'][3]) / 2
        self.assertAlmostEqual(cy, 381.9, delta=5)


if __name__ == '__main__':
    unittest.main(verbosity=1)

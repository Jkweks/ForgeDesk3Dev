"""Height chains go to the elevation whose frame they sit beside (ForgeDesk addition).

Howell_SW_pairs_and_spans.pdf, sheet E22: two elevations side by side. 46/E22's height chain (R.O. 120", F.S. 118 3/4",
D.L.O.s) is printed 100-170pt LEFT of its own bubble, so the column rule (_nearest_by_x, 20pt grace for left-anchored
bubbles) gave it to 45/E22. Result: 46/E22 had no heights, and 45/E22 carried 46's R.O., F.S. and two D.L.O.s.

The chains of one elevation sit together and beside its frame, so _height_chain_owner groups the sheet's vertical
labels into chain groups and gives each group to the nearest frame span. It only answers when at least two frames can
be placed and one is clearly nearer; otherwise None and the column rule decides, as before.

Run: python3 test_drawing_import_height_chain_owner.py
"""
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

SAMPLE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                      'test_samples', 'drawing_import', 'Howell_SW_pairs_and_spans.pdf')


class _Page:
    def __init__(self, xs):
        # one rotated (upright=False) character per label, at x, so each becomes its own vertical line
        self.chars = [{'text': '1', 'x0': x, 'x1': x + 6.0, 'top': 300.0, 'bottom': 306.0, 'upright': False} for x in xs]


# bubbles as on E22 (left-anchored), frame spans as _width_spans would give them
C45, C46 = {'x0': 61.8, 'top': 474.0, 'anchor': 'left'}, {'x0': 598.4, 'top': 474.0, 'anchor': 'left'}
SPANS = {id(C45): (163.0, 355.0), id(C46): (544.0, 1018.0)}


class HeightChainOwnerTests(unittest.TestCase):
    def test_a_chain_far_left_of_its_bubble_goes_to_its_own_frame(self):
        # 45's left chain (52-145), 45's right chain (351), 46's left chain (430-523)
        page = _Page([51.7, 74.0, 96.5, 119.0, 145.1, 351.2, 430.1, 452.4, 497.4, 523.5])
        owner = di._height_chain_owner(page, [C45, C46], SPANS)
        for x in (430.1, 452.4, 497.4):
            self.assertIs(owner({'x0': x}), C46, x)
        for x in (51.7, 119.0, 351.2):
            self.assertIs(owner({'x0': x}), C45, x)
        # the old rule is what went wrong: 46's R.O. at 430 is 168pt left of its bubble
        self.assertIs(di._nearest_by_x([C45, C46], 430.1, 322.0), C45)

    def test_falls_back_to_the_column_rule_when_unclear(self):
        page = _Page([51.7, 430.1])
        self.assertIsNone(di._height_chain_owner(page, [C45, C46], {id(C45): (163.0, 355.0)})({'x0': 430.1}))   # only one frame placed
        self.assertIsNone(di._height_chain_owner(page, [C45], SPANS)({'x0': 430.1}))                            # one elevation
        # a chain exactly between two frames (same distance to both) is not guessed
        mid = _Page([449.0])
        self.assertIsNone(di._height_chain_owner(mid, [C45, C46], {id(C45): (163.0, 400.0), id(C46): (500.0, 1018.0)})({'x0': 449.0}))
        self.assertIsNone(di._height_chain_owner(page, [C45, C46], SPANS)({'x0': 9999.0}))                      # not a known label


@unittest.skipUnless(di._HAVE_PDFPLUMBER and os.path.isfile(SAMPLE), 'sample PDF fixture missing')
class HowellE22Tests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with open(SAMPLE, 'rb') as f:
            r = di.parse_drawing_set(f.read(), 'Howell_SW_pairs_and_spans.pdf')
        cls.by = {e['label']: e for e in r['elevations']}

    def test_46_reads_its_own_heights(self):
        e = self.by['46/E22']
        self.assertEqual((e['roH'], e['fsH']), (120.0, 118.75))
        self.assertEqual(sorted(e['dloHeights']), [45.625, 67.125])
        self.assertIsNotNone(e['balance']['h'])
        self.assertTrue(e['balance']['h']['ok'])

    def test_45_no_longer_carries_46s_heights(self):
        e = self.by['45/E22']
        self.assertEqual(sorted(e['dloHeights']), [14.0, 32.125, 33.0])
        self.assertNotIn(67.125, e['dloHeights'])
        self.assertNotIn(45.625, e['dloHeights'])

    def test_68_owns_its_left_chain_not_67(self):
        # E35: 67 and 68 each have a chain on both sides; 68's left chain (x ~ 590-645) was being given to 67
        self.assertEqual(self.by['68/E35']['doH'], 86.0)
        self.assertEqual(len(self.by['67/E35']['dloHeights']), 3)
        self.assertEqual(len(self.by['68/E35']['dloHeights']), 3)


if __name__ == '__main__':
    unittest.main()

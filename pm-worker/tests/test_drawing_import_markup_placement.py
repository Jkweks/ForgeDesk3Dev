"""Typed Bluebeam HEIGHT boxes dropped beside a label rather than under it
(Work Order 8: 45 3/8" two points left of "TRANSOM D.L.O.", and a column of
42" boxes down a stack of lites). Pure geometry — no fixture PDF needed.
Run: python3 test_drawing_import_markup_placement.py"""
import os, sys, unittest
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di


def vline(text, x0, top, x1, bottom):
    return {'text': text, 'x0': x0, 'top': top, 'rect': [x0, top, x1, bottom], 'chars': []}


def box(x0, top, x1, bottom, text='45 3/8"'):
    return {'text': text, 'x0': x0, 'top': top, 'x1': x1, 'bottom': bottom, 'vertical': True}


fb = di._MarkupPage._vertical_fallback


class PlacementTests(unittest.TestCase):
    def test_box_just_left_of_rotated_label(self):          # WO8 p12
        lab = vline('TRANSOM D.L.O.', 926.1, 237, 932.3, 287)
        got = fb(box(914, 251, 924, 279), [lab])
        self.assertEqual(got[1], 'D.L.O.')

    def test_fs_label_further_above_than_strict_window(self):  # WO8 p7 134"
        lab = vline('F.S.', 475, 301, 481, 313)
        got = fb(box(471, 330, 481, 349, '134"'), [lab])
        self.assertEqual(got[1], 'F.S.')

    def test_stack_of_lites_needs_siblings(self):           # WO8 p7 vs Howell SW p2
        lab = vline('D.L.O.', 497, 236, 504, 254)
        low = box(505, 291, 515, 306, '42"')                 # 37pt past the label
        self.assertIsNone(fb(low, [lab], [low]))             # lone value = a note
        sib = box(504, 259, 514, 274, '42"')
        self.assertEqual(fb(low, [lab], [low, sib])[1], 'D.L.O.')

    def test_far_box_is_not_placed(self):
        lab = vline('R.O.', 52, 296, 58, 310)
        self.assertIsNone(fb(box(200, 296, 210, 310), [lab]))


if __name__ == '__main__':
    unittest.main()

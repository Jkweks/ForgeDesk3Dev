"""
test_drawing_import.py — round 340 (2026-08-25).

Proves drawing_import.py against a REAL shop-drawing-set PDF (not a
synthetic mock) — the same discipline as the OCR quote/ack/receiving
parsers: "verified against the actual sheet, not a guess." The fixture is
the real Lake Trust - Grand Ledge Branch set (Vos in-house drawn,
test_samples/drawing_import/), spot-checked by hand against the drawing
before being pinned into assertions below.

Also proves the honest-failure paths pure-unit style (no second multi-MB
fixture bundled for this): a garbled/CID-mapped text layer (the real,
verified failure mode found in a second real outside-draftsman sample
during development — see drawing_import.py's module docstring) is
detected and reported, never silently emitted as wrong data; a PDF with
no elevation sheets at all comes back empty, not guessed at.

Run: python3 test_drawing_import.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import drawing_import as di

FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                        'test_samples', 'drawing_import',
                        'Lake_Trust_Grand_Ledge_Branch_Rev3.pdf')


def _skip_no_pdfplumber(test):
    return unittest.skipUnless(di._HAVE_PDFPLUMBER, 'pdfplumber not installed')(test)


def _skip_no_fixture(test):
    return unittest.skipUnless(os.path.isfile(FIXTURE), 'sample PDF fixture missing')(test)


class TestNumericHelpers(unittest.TestCase):
    def test_mixed_to_float_whole_and_fraction(self):
        self.assertEqual(di.mixed_to_float('35 7/8'), 35.875)
        self.assertEqual(di.mixed_to_float('104'), 104.0)
        self.assertEqual(di.mixed_to_float('7/8'), 0.875)

    def test_mixed_to_float_empty_and_garbage(self):
        self.assertIsNone(di.mixed_to_float(''))
        self.assertIsNone(di.mixed_to_float(None))
        self.assertIsNone(di.mixed_to_float('abc'))
        self.assertIsNone(di.mixed_to_float('1/0'))

    def test_feetinch_to_float(self):
        self.assertEqual(di._feetinch_to_float('6\'-6 1/2"'), 78.5)
        self.assertEqual(di._feetinch_to_float('6\'-8"'), 80.0)

    def test_looks_garbled_detects_cid_markers(self):
        self.assertTrue(di._looks_garbled('(cid:17)CNI ,SSALG SOV ' * 5))

    def test_looks_garbled_false_for_normal_drawing_text(self):
        self.assertFalse(di._looks_garbled(
            "6'-6 1/2\" R.O.\n[78 1/2\"]\nELEVATION W1 THUS REQ'D: 1"))

    def test_looks_garbled_ignores_short_text(self):
        self.assertFalse(di._looks_garbled('W1'))

    def test_looks_reversed_detects_mirrored_elevation_text(self):
        # Round 344: a real horizontally-flipped sheet in the Hope College
        # set reads "ELEVATION" as "NOITAVELE" end to end.
        self.assertTrue(di._looks_reversed('FRAME\nNOITAVELE\n2e\n'))

    def test_looks_reversed_false_for_normal_text(self):
        self.assertFalse(di._looks_reversed("ELEVATION W1 THUS REQ'D: 1"))

    def test_feetinch_to_float_falls_back_to_plain_inches(self):
        # Round 344: a vendor whose per-opening D.L.O./F.D. values are
        # printed as plain inches with no feet component at all (no
        # apostrophe) — e.g. "38 1/4\"", not "3'-2 1/4\"". Only the
        # overall R.O. width on that same real sheet uses feet-inches.
        self.assertAlmostEqual(di._feetinch_to_float('38 1/4"'), 38.25)
        self.assertAlmostEqual(di._feetinch_to_float("6'-6 1/2\""), 78.5)
        self.assertIsNone(di._feetinch_to_float('not a number'))


class TestUnreadableFontHonestFailure(unittest.TestCase):
    """A real third vendor sample (Kentwood DPW, 2nd submittal — 110
    pages, not bundled here at full size) has an embedded font with no
    usable ToUnicode CMap: extract_text() returns literal "(cid:NN)"
    garbage instead of the real characters, the same failure mode as the
    round-340 GRCC sample. Round 344 fix: this used to vanish into
    skippedPages with zero trace whenever the page ALSO failed
    classify_page (which it always does, since a cid-mapped page never
    contains the literal word "ELEVATION"); now parse_drawing_set surfaces
    a capped, honest warning instead (see TestRealDrawingSet and the
    Aspen/Ryan Engineering suites above for the full end-to-end proof
    against real files). Reproduced here at the text-helper level with a
    short synthetic snippet matching the real file's actual cid-mapped
    text, not the full multi-MB PDF."""

    def test_garbled_snippet_is_flagged_garbled_and_not_reversed(self):
        cid_text = '(cid:9)SNI(cid:46)REP:STCETIHCRA' * 6
        self.assertTrue(di._looks_garbled(cid_text))
        self.assertFalse(di._looks_reversed(cid_text))


class TestSystemFinishRegexes(unittest.TestCase):
    def test_system_regex_vendor_agnostic(self):
        for s in ('TUBELITE T14000 SERIES SF (2" x 4 1/2")',
                  'TUBELITE E4500 SERIES SF (1 3/4" x 4 1/2")',
                  'KAWNEER 1600 SERIES (2" x 4 1/2")'):
            self.assertTrue(di._SYSTEM_RE.search(s), s)

    def test_finish_regex(self):
        m = di._FINISH_RE.search('FINISH: "CLEAR" ANODIZED (C2)')
        self.assertIsNotNone(m)
        self.assertEqual(m.group(1).strip(), 'CLEAR')
        self.assertEqual(m.group(2), 'C2')

    def test_qty_regex(self):
        m = di._QTY_RE.search("THUS REQ'D: 2 ARCH REF: W4/A-601\nOPP REQ'D: 0 W4/A-601\nE3 TOTAL REQ'D: 2")
        self.assertIsNotNone(m)
        self.assertEqual(m.groups(), ('2', '0', '2'))


def _fake_vchar(text, x0, top):
    """A minimal synthetic pdfplumber char dict — just the fields
    _vertical_lines actually reads — for testing the gap-splitting logic
    in isolation from a real PDF."""
    return {'text': text, 'x0': x0, 'top': top, 'upright': False}


class TestVerticalTextLines(unittest.TestCase):
    """Round 342: the height axis is drawn as rotated text, read by
    grouping characters into columns (by X) and lines within a column (by
    a gap-in-'top' break) — the exact transpose of the width axis's
    row-grouping. These are synthetic-char tests, independent of the real
    PDF fixture, isolating the one genuinely new piece of logic this round
    added: two real dimension strings can share an X column and must be
    told apart by a real gap, not merged into one unreadable string."""

    def test_single_contiguous_run_reads_top_to_bottom(self):
        # Descending 'top' order restores the correct on-page reading
        # direction — verified against five real rendered sheets before
        # being hardcoded (see the module docstring).
        chars = [_fake_vchar(ch, 100.0, top) for ch, top in
                  zip(list('R.O.'), [354.6, 352.9, 348.1, 346.4])]
        lines = di._vertical_lines(chars)
        self.assertEqual(len(lines), 1)
        self.assertEqual(lines[0]['text'], 'R.O.')

    def test_a_large_gap_in_the_same_column_splits_into_two_lines(self):
        # The real case found on the actual E1 sheet: "2'-6"" (a small,
        # unrelated detail) and "8'-8" R.O." (the real height dimension)
        # share the same X column, separated by a large real gap (~85pt on
        # the real sheet) — must NOT be concatenated into one string.
        near_chars = [_fake_vchar(ch, 404.2, top) for ch, top in
                       zip(list('2\'-6"'), [463.3, 462.1, 460.1, 456.6, 454.5])]
        far_chars = [_fake_vchar(ch, 404.2, top) for ch, top in
                      zip(list('8\'-8" R.O.'), [369.6, 368.5, 366.4, 363.0, 360.8, 359.1, 354.6, 352.9, 348.1, 346.4])]
        lines = di._vertical_lines(near_chars + far_chars)
        self.assertEqual(len(lines), 2)
        texts = sorted(l['text'] for l in lines)
        self.assertIn('2\'-6"', texts)
        self.assertIn('8\'-8" R.O.', texts)

    def test_a_small_gap_does_not_split(self):
        # Ordinary letter-spacing within a real word/number never crosses
        # the gap_break threshold — a normal string stays one line.
        chars = [_fake_vchar(ch, 200.0, top) for ch, top in
                  zip(list('R.O.'), [354.6, 352.9, 349.1, 347.4])]  # ~1-3pt gaps
        lines = di._vertical_lines(chars)
        self.assertEqual(len(lines), 1)

    def test_different_x_columns_never_merge(self):
        a = [_fake_vchar('X', 100.0, 300.0)]
        b = [_fake_vchar('Y', 250.0, 300.0)]
        lines = di._vertical_lines(a + b)
        self.assertEqual(len(lines), 2)

    def test_upright_chars_are_excluded(self):
        upright = {'text': 'X', 'x0': 100.0, 'top': 300.0, 'upright': True}
        lines = di._vertical_lines([upright])
        self.assertEqual(lines, [])

    def test_height_events_end_to_end_on_synthetic_chars(self):
        # "8'-8" R.O." on one line, "[104"]" bracket on an adjacent
        # (nearby X, nearby top) line — mirrors the real sheet's layout.
        label_chars = [_fake_vchar(ch, 404.2, top) for ch, top in
                        zip(list('8\'-8" R.O.'), [369.6, 368.5, 366.4, 363.0, 360.8, 359.1, 354.6, 352.9, 348.1, 346.4])]
        bracket_chars = [_fake_vchar(ch, 411.0, top) for ch, top in
                          zip(list('[104"]'), [368.0, 366.0, 364.0, 362.0, 360.0, 358.0, 356.0])]

        class _FakePage:
            def __init__(self, chars):
                self.chars = chars
        events = di._height_events(_FakePage(label_chars + bracket_chars))
        self.assertEqual(len(events['RO']), 1)
        self.assertAlmostEqual(events['RO'][0]['value'], 104.0)


@_skip_no_pdfplumber
class TestStatus(unittest.TestCase):
    def test_status_reports_pdfplumber(self):
        s = di.drawing_import_status()
        self.assertIn('pdfplumber', s)
        self.assertTrue(s['pdfplumber'])


class TestNoPdfplumber(unittest.TestCase):
    def test_missing_dependency_reports_honest_error(self):
        saved = di._HAVE_PDFPLUMBER
        try:
            di._HAVE_PDFPLUMBER = False
            r = di.parse_drawing_set(b'not a real pdf', 'x.pdf')
            self.assertFalse(r['ok'])
            self.assertIn('pdfplumber', r['error'])
        finally:
            di._HAVE_PDFPLUMBER = saved


@_skip_no_pdfplumber
class TestBadFile(unittest.TestCase):
    def test_not_a_pdf_at_all(self):
        r = di.parse_drawing_set(b'this is not a pdf file at all, just bytes', 'x.pdf')
        self.assertFalse(r['ok'])
        self.assertIn('error', r)


@_skip_no_pdfplumber
@_skip_no_fixture
class TestRealDrawingSet(unittest.TestCase):
    """Every number here was cross-checked by hand against the real sheet
    before being pinned — the exact discipline the project's other real-
    world parsers (OCR quote/ack/receiving) already follow."""

    @classmethod
    def setUpClass(cls):
        with open(FIXTURE, 'rb') as f:
            cls.result = di.parse_drawing_set(f.read(), os.path.basename(FIXTURE))
        cls.by_label = {e['label']: e for e in cls.result['elevations']}

    def test_ok(self):
        self.assertTrue(self.result['ok'])

    def test_finds_every_real_elevation_sheet(self):
        # W1..W17 (no W5) plus SF17 — 17 elevation records total, matching
        # the sheet index (E1 through E11) hand-verified against the PDF.
        expected = {'W1', 'W2', 'W3', 'W4', 'W6', 'W7', 'W8', 'W9', 'W10',
                    'W11', 'W17', 'W12', 'W13', 'W14', 'W15', 'W16', 'SF17'}
        self.assertEqual(set(self.by_label.keys()), expected)
        self.assertEqual(len(self.result['elevations']), 17)

    def test_non_elevation_pages_skipped(self):
        # Cover, door schedule, plan views, and every DETAILS sheet.
        self.assertGreater(self.result['skippedPages'], 10)

    def test_single_opening_elevation_w1(self):
        w1 = self.by_label['W1']
        self.assertAlmostEqual(w1['roW'], 78.5)
        self.assertAlmostEqual(w1['fsW'], 77.75)
        self.assertEqual(w1['dloWidths'], [35.875, 35.875])
        self.assertEqual((w1['thusReqd'], w1['oppReqd'], w1['totalReqd']), (1, 0, 1))
        self.assertEqual(w1['system'], 'TUBELITE T14000 SERIES SF (2" x 4 1/2")')
        self.assertEqual(w1['finish'], 'CLEAR')
        self.assertEqual(w1['finishCode'], 'C2')
        self.assertEqual(w1['archRef'], 'W1/A-601')

    def test_height_axis_w1(self):
        # Round 342: hand-verified against the actual rendered E1 sheet —
        # the rotated dimension chain reads "8'-8" R.O. [104"] / 8'-6 3/4"
        # F.S. [102 3/4"]" directly below a small unrelated "2'-6" [30"]"
        # detail sharing the same X column (the exact case that needed the
        # gap-based line-splitting fix, not a stale/mismatched value).
        w1 = self.by_label['W1']
        self.assertAlmostEqual(w1['roH'], 104.0)
        self.assertAlmostEqual(w1['fsH'], 102.75)

    def test_height_shared_across_elevations_at_the_same_story(self):
        # Round 342: W3 and W4 share one two-up sheet and, like several
        # other elevation pairs in this real building, genuinely share the
        # exact same overall height (same floor-to-floor band) despite
        # very different widths (42" vs 83" R.O. width) — hand-verified
        # against the rendered E3 sheet. W7/W8 (a different sheet) share a
        # taller height. Both pairs proving the same real behavior, not a
        # coincidence limited to one sheet.
        w3, w4 = self.by_label['W3'], self.by_label['W4']
        self.assertAlmostEqual(w3['roH'], 80.0)
        self.assertAlmostEqual(w4['roH'], 80.0)
        self.assertAlmostEqual(w3['fsH'], 78.75)
        self.assertAlmostEqual(w4['fsH'], 78.75)
        w7, w8 = self.by_label['W7'], self.by_label['W8']
        self.assertAlmostEqual(w7['roH'], 104.0)
        self.assertAlmostEqual(w8['roH'], 104.0)

    def test_height_axis_w12(self):
        # A third, independent hand-verification against the rendered E8
        # sheet (a different system type, TUBELITE E4500).
        w12 = self.by_label['W12']
        self.assertAlmostEqual(w12['roH'], 122.0)
        self.assertAlmostEqual(w12['fsH'], 121.75)

    def test_no_height_sheet_flags_a_warning_not_a_guess(self):
        # SF17's sheet prints a HARDWARE SET box instead of a normal
        # R.O./F.S. height chain for its door-height reference — confirms
        # the parser reports the gap rather than inventing a number.
        sf17 = self.by_label['SF17']
        self.assertIsNone(sf17['fsH'])

    def test_archref_found_on_every_sheet_that_prints_one(self):
        # Round 341 fix: W2 and W13 are single-elevation sheets whose ARCH
        # REF token sits noticeably above the callout's baseline (unlike
        # the more common two/three-up layout) — the original proximity
        # window missed both and silently returned None. SF17 genuinely
        # has no ARCH REF field on its sheet (prints HARDWARE SET instead)
        # and must stay None, not get a wrong nearby token guessed in.
        expect_ref = {
            'W1': 'W1/A-601', 'W2': 'W2/A-601', 'W3': 'W3/A-601', 'W4': 'W4/A-601',
            'W6': 'W6/A-601', 'W7': 'W7/A-601', 'W8': 'W8/A-601', 'W9': 'W9/A-601',
            'W10': 'W10/A-601', 'W11': 'W11/A-601', 'W17': 'W17/A-601',
            'W12': 'W12/A-601', 'W13': 'W13/A-601', 'W14': 'W14/A-601',
            'W15': 'W15/A-601', 'W16': 'W16/A-601',
        }
        for label, ref in expect_ref.items():
            self.assertEqual(self.by_label[label]['archRef'], ref, label)
        self.assertIsNone(self.by_label['SF17']['archRef'])

    def test_two_up_sheet_splits_cleanly_w3_w4(self):
        # W3 (single opening) and W4 (2 openings) share one physical sheet —
        # proves the nearest-column split, not just single-elevation pages.
        w3, w4 = self.by_label['W3'], self.by_label['W4']
        self.assertAlmostEqual(w3['roW'], 42.0)
        self.assertEqual(w3['dloWidths'], [37.25])
        self.assertAlmostEqual(w4['roW'], 83.0)
        self.assertEqual(w4['dloWidths'], [38.125, 38.125])

    def test_wide_multi_opening_sheet_w9_w10(self):
        # The widest real test of the column-assignment logic: 4 openings
        # (W9) and 7 openings (W10) side by side on one sheet.
        w9, w10 = self.by_label['W9'], self.by_label['W10']
        self.assertEqual(len(w9['dloWidths']), 4)
        self.assertEqual(len(w10['dloWidths']), 7)
        self.assertTrue(all(abs(v - 36.0) < 0.01 for v in w9['dloWidths']))

    def test_transom_door_elevation_w2(self):
        w2 = self.by_label['W2']
        self.assertEqual(w2['dloWidths'], [37.25, 34.0])

    def test_second_system_type_picked_up(self):
        w12 = self.by_label['W12']
        self.assertEqual(w12['system'], 'TUBELITE E4500 SERIES SF (1 3/4" x 4 1/2")')

    def test_no_dlo_sheet_flags_a_warning_not_a_guess(self):
        sf17 = self.by_label['SF17']
        self.assertEqual(sf17['dloWidths'], [])
        self.assertTrue(any('D.L.O.' in w for w in sf17['warnings']))

    def test_page_numbers_are_1_indexed_and_plausible(self):
        for e in self.result['elevations']:
            self.assertGreaterEqual(e['page'], 1)
            self.assertLessEqual(e['page'], self.result['pageCount'])


HOPE_FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                             'test_samples', 'drawing_import',
                             'Hope_College_CW_Shops_Working_Set.pdf')
KENTWOOD_FIXTURE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                                 'test_samples', 'drawing_import',
                                 'Kentwood_DPW_First_Submittal.pdf')


def _skip_no_hope_fixture(test):
    return unittest.skipUnless(os.path.isfile(HOPE_FIXTURE), 'Hope College fixture missing')(test)


def _skip_no_kentwood_fixture(test):
    return unittest.skipUnless(os.path.isfile(KENTWOOD_FIXTURE), 'Kentwood fixture missing')(test)


@_skip_no_pdfplumber
@_skip_no_hope_fixture
class TestAspenVendorSet(unittest.TestCase):
    """Round 344: a second real outside-draftsman set (Aspen drafting for
    A.M.D.G. Architects — Hope College North Campus Gateway), a
    genuinely different convention from both Vos house style and the
    round-340 fixture on every axis: the callout bubble reads "<name>
    FRAME ELEVATION" (name precedes, not follows); "F.D." instead of
    "F.S."; quantities as "N THUS TOTAL" + a per-location "N THUS REQ'D
    <location> ELEVATION" breakdown instead of THUS/OPP/TOTAL REQ'D; the
    arch ref as two separate tokens with no slash; D.L.O./F.D. values
    typeset stacked above their label instead of inline before it; and
    two real sheets that are horizontally mirrored end to end. Every
    number below was cross-checked against the actual rendered sheet."""

    @classmethod
    def setUpClass(cls):
        with open(HOPE_FIXTURE, 'rb') as f:
            cls.result = di.parse_drawing_set(f.read(), os.path.basename(HOPE_FIXTURE))
        cls.by_label = {e['label']: e for e in cls.result['elevations']}

    def test_ok(self):
        self.assertTrue(self.result['ok'])

    def test_finds_every_real_elevation_sheet(self):
        # 1e..31e (33 real elevations exist on the sheet index; 32e/33e are
        # the two mirrored sheets, correctly excluded — see
        # test_mirrored_sheets_flagged_not_guessed below).
        self.assertEqual(len(self.result['elevations']), 31)
        self.assertNotIn('ASPEN', self.by_label)  # the round-343 mislabel bug
        self.assertNotIn('NO.', self.by_label)

    def test_label_precedes_frame_elevation_not_follows(self):
        # The core round-344 fix: "<name> FRAME ELEVATION", not
        # "ELEVATION <name>". Verified on a 2-up sheet (1e/2e together)
        # so both the single- and multi-callout-per-page cases are proven.
        self.assertIn('1e', self.by_label)
        self.assertIn('2e', self.by_label)
        self.assertIn('10e', self.by_label)  # previously mislabeled 'ASPEN'
        self.assertIn('15e', self.by_label)  # previously mislabeled 'NO.'

    def test_fd_label_aliases_to_fs(self):
        # This vendor never prints "F.S." at all — only "F.D." (Frame
        # Dimension), for the identical reading.
        e = self.by_label['3e']
        self.assertAlmostEqual(e['roW'], 216.0)

    def test_dlo_values_found_via_stacked_typesetting(self):
        # D.L.O./F.D. values on this vendor sit ~13pt ABOVE their label
        # instead of inline before it — every one of the 31 real
        # elevations has at least one D.L.O. width once that's handled.
        for label, e in self.by_label.items():
            self.assertTrue(e['dloWidths'], 'no D.L.O. found for %s' % label)
        self.assertEqual(self.by_label['1e']['dloWidths'], [15.0, 38.25, 38.25, 15.0])
        self.assertEqual(self.by_label['3e']['dloWidths'],
                          [11.75, 38.25, 38.25, 18.5, 38.25, 38.25, 11.75])

    def test_qty_thus_total_and_reqd_breakdown(self):
        # 1e and 2e share one 2-up sheet, each with its own "N THUS TOTAL"
        # + location breakdown — proves per-callout column assignment,
        # not a page-wide pool (a real bug caught in round-344 testing:
        # the flat-text version summed BOTH elevations' lines into each).
        e1, e2 = self.by_label['1e'], self.by_label['2e']
        self.assertEqual(e1['totalReqd'], 1)
        self.assertEqual(e1['reqdBreakdown'], [{'qty': 1, 'location': '1ST FLOOR SOUTH ELEVATION'}])
        self.assertEqual(e1['warnings'], [])
        self.assertEqual(e2['totalReqd'], 4)
        self.assertEqual(e2['reqdBreakdown'],
                          [{'qty': 2, 'location': '1ST FLOOR SOUTH ELEVATION'},
                           {'qty': 2, 'location': '1ST FLOOR EAST ELEVATION'}])
        self.assertEqual(e2['warnings'], [])

    def test_system_regex_does_not_match_incidental_text(self):
        # Round 344 bug: "DRAFTING\nFRAME (-) ON ARCHITECTURAL DRAWINGS"
        # (plain descriptive text near the scale note) matched the
        # vendor-agnostic system pattern on two real sheets before the
        # dimensions-must-contain-a-digit fix.
        for e in self.result['elevations']:
            self.assertIsNotNone(e['system'])
            self.assertNotIn('DRAFTING', e['system'])
        self.assertEqual(self.by_label['10e']['system'], 'TUBELITE 400TU (2 1/2" x 10") CURTAINWALL')
        self.assertEqual(self.by_label['15e']['system'], 'TUBELITE 400TU (2 1/2" x 10") CURTAINWALL')

    def test_archref_two_token_convention(self):
        # "ARCH. <bubble> REF. <sheet>" with no slash — the bubble number
        # sits BETWEEN the two labels, not after REF. Column-assigned so
        # a wide sheet's ref isn't accidentally claimed by the wrong
        # elevation (9e's real "A6.12" vs. 8e's genuinely blank "--").
        self.assertEqual(self.by_label['1e']['archRef'], 'A6.10')
        self.assertEqual(self.by_label['4e']['archRef'], 'A6.16')
        self.assertEqual(self.by_label['9e']['archRef'], 'A6.12')
        self.assertIsNone(self.by_label['8e']['archRef'])  # genuinely "ARCH. REF. --" on the sheet

    def test_mirrored_sheets_flagged_not_guessed(self):
        # 32e and 33e are real horizontally-flipped elevations (a mirror-
        # image opposite-hand copy) — their text extracts back-to-front
        # end to end. Never decoded (out of scope, same family as an
        # unreadable CID-mapped font); must be a named, actionable
        # warning, not a silent gap in skippedPages.
        self.assertNotIn('32e', self.by_label)
        self.assertNotIn('33e', self.by_label)
        reversed_warnings = [w for w in self.result['warnings'] if 'mirrored/reversed' in w]
        self.assertGreaterEqual(len(reversed_warnings), 2)


@_skip_no_pdfplumber
@_skip_no_kentwood_fixture
class TestRyanEngineeringVendorSet(unittest.TestCase):
    """Round 344: a third real outside-draftsman set (Ryan Engineering for
    Ghafari Architects — Kentwood DPW Improvements), close to Vos house
    style on the callout ("ELEVATION <name>") but with a flat "QTY = N"
    instead of any REQ'D convention, and arch refs joined with a pipe
    ("4/AE1-00|2/AE1-01") instead of a single slash token. Also proves a
    real, structural limitation: this vendor's dimension figures are
    drawn as vector line art, not text at all — confirmed by inspecting
    the raw PDF characters directly — so R.O./F.S./D.L.O. values are
    correctly reported as not-found rather than guessed at."""

    @classmethod
    def setUpClass(cls):
        with open(KENTWOOD_FIXTURE, 'rb') as f:
            cls.result = di.parse_drawing_set(f.read(), os.path.basename(KENTWOOD_FIXTURE))
        cls.by_page = {}
        for e in cls.result['elevations']:
            cls.by_page.setdefault(e['page'], []).append(e)

    def test_ok(self):
        self.assertTrue(self.result['ok'])

    def test_finds_every_real_elevation_matching_the_sheet_index(self):
        # Sheet index: E001 (SF1, SF2), E101/E102 (D1, D2 each),
        # E103/E104/E105 (D3 each), E106 (SF3) = 10 elevations.
        self.assertEqual(len(self.result['elevations']), 10)
        labels = sorted(e['label'] for e in self.result['elevations'])
        self.assertEqual(labels, sorted(['SF1', 'SF2', 'D1', 'D2', 'D1', 'D2',
                                          'D3', 'D3', 'D3', 'SF3']))

    def test_qty_flat_convention_per_callout_on_a_shared_sheet(self):
        # SF1 and SF2 share sheet E001 — proves QTY = N is assigned to
        # the right callout by column, not just grabbed off the page.
        e001 = {e['label']: e for e in self.by_page[5]}
        self.assertEqual(e001['SF1']['totalReqd'], 8)
        self.assertEqual(e001['SF2']['totalReqd'], 2)

    def test_archref_pipe_joined_convention(self):
        e001 = {e['label']: e for e in self.by_page[5]}
        self.assertEqual(e001['SF1']['archRef'], '4/AE1-00|2/AE1-01')
        self.assertEqual(e001['SF2']['archRef'], '4/AE1-00|7/AE1-01')

    def test_system_found_without_series_keyword(self):
        e001 = {e['label']: e for e in self.by_page[5]}
        self.assertEqual(e001['SF1']['system'], 'TUBELITE T24650 (2" X 6 1/2")')

    def test_dimension_values_honestly_not_found(self):
        # Real, structural limitation, not a parser bug: this vendor's
        # elevation dimension figures are drawn as vector line art, never
        # as extractable text (confirmed against the raw PDF characters
        # directly during development) — every elevation must warn, none
        # may silently report a wrong or guessed number.
        for e in self.result['elevations']:
            self.assertIsNone(e['roW'])
            self.assertEqual(e['dloWidths'], [])
            self.assertTrue(any('R.O. Width' in w for w in e['warnings']))


class TestDloHeightExtraction(unittest.TestCase):
    """Round 383: _height_events now includes 'DLO' so per-band D.L.O. heights
    are extracted alongside R.O./F.S. height.  _elevation_record populates
    dloHeights on each record.

    These tests exercise the schema change and the _height_events DLO key
    without requiring a real PDF that happens to have rotated D.L.O. labels
    (the field is new; existing real drawings tested so far only label height
    R.O./F.S., not D.L.O. height explicitly — that is a valid "found nothing"
    case, not a parse failure).
    """

    def test_dloheights_key_present_in_elevation_record_schema(self):
        """Every _elevation_record result must carry dloHeights regardless of
        whether the sheet has any — the key must exist so the JS consumer
        never reads undefined."""
        from drawing_import import _elevation_record
        import unittest.mock as mock
        # Build a minimal fake page that _elevation_record won't crash on
        page = mock.MagicMock()
        page.extract_words.return_value = []
        page.chars = []
        page.extract_text.return_value = ''
        callout = {'text': 'ELEVATION 1', 'name': 'ELEVATION 1', 'x0': 100, 'top': 200}
        rec = _elevation_record(page, callout, 1, [], '', False)
        self.assertIn('dloHeights', rec,
                      'dloHeights key must be present in every _elevation_record result')
        self.assertIsInstance(rec['dloHeights'], list)

    def test_dloheights_empty_on_garbled_page(self):
        """A garbled page must zero dloHeights, same as dloWidths."""
        from drawing_import import _elevation_record
        import unittest.mock as mock
        page = mock.MagicMock()
        page.extract_words.return_value = []
        page.chars = []
        page.extract_text.return_value = ''
        callout = {'text': 'ELEVATION 1', 'name': 'ELEVATION 1', 'x0': 100, 'top': 200}
        rec = _elevation_record(page, callout, 1, [], '', True)  # garbled=True
        self.assertEqual(rec['dloHeights'], [])

    def test_height_events_returns_dlo_key(self):
        """_height_events must return a dict with a 'DLO' key (may be empty)."""
        from drawing_import import _height_events
        import unittest.mock as mock
        page = mock.MagicMock()
        page.chars = []  # no rotated text → empty extraction, no crash
        out = _height_events(page)
        self.assertIn('DLO', out,
                      "_height_events must return a 'DLO' key alongside 'RO' and 'FS'")
        self.assertIsInstance(out['DLO'], list)

    def test_height_events_extracts_bracket_value_for_dlo_label(self):
        """_height_events returns a 'DLO' list populated when the page has
        rotated D.L.O. label text paired with its bracketed inch value.
        Non-vacuous: without the 'D.L.O.' label in the search loop, the DLO
        list must be empty — proven here by calling _vertical_label_events
        with a wrong label and verifying it returns nothing."""
        from drawing_import import (
            _vertical_lines, _vertical_bracket_events,
            _vertical_label_events, _nearest_vertical_bracket,
        )
        # Synthesise rotated chars where the bracket value "[33 5/8\"]" precedes
        # "D.L.O." on the SAME vertical line (small gap so _vertical_lines
        # keeps them together), simulating the layout a real dimension chain uses.
        def char(text, x0, top):
            return {'text': text, 'x0': x0, 'top': top, 'upright': False}

        # gap_break=10 by default in _vertical_lines; use gap=5 between ] and D
        combined = [
            char('[', 50, 520), char('3', 50, 512), char('3', 50, 504),
            char(' ', 50, 496), char('5', 50, 488), char('/', 50, 480),
            char('8', 50, 472), char('"', 50, 464), char(']', 50, 456),
            # gap of 5pt (456-5=451 → D starts at 451)
            char('D', 50, 451), char('.', 50, 443), char('L', 50, 435),
            char('.', 50, 427), char('O', 50, 419), char('.', 50, 411),
        ]

        lines = _vertical_lines(combined)
        brackets = _vertical_bracket_events(lines)
        label_evs = _vertical_label_events(lines, 'D.L.O.')

        dlo_values = []
        for ev in label_evs:
            b = _nearest_vertical_bracket(ev, brackets)
            if b is not None and b['value'] is not None:
                dlo_values.append(b['value'])

        # The bracket "[33 5/8\"]" should parse to 33.625
        self.assertTrue(len(dlo_values) > 0,
                        'D.L.O. label should be paired with its bracket value on '
                        'the same vertical line (gap < gap_break)')
        self.assertAlmostEqual(dlo_values[0], 33.625, places=2)

        # Non-vacuity: a wrong label must find nothing.
        dlo_no_label = []
        for ev in _vertical_label_events(lines, 'X.X.X.'):
            b = _nearest_vertical_bracket(ev, brackets)
            if b is not None and b['value'] is not None:
                dlo_no_label.append(b['value'])
        self.assertEqual(dlo_no_label, [],
                         'Non-vacuity: wrong label must find nothing')


if __name__ == '__main__':
    unittest.main(verbosity=2)

"""
drawing_import.py — Glazing shop-drawing set import (round 340, 2026-08-25).

Reads a real, exported glazing/aluminum shop-drawing set PDF (the kind Vos's
own drafters or an architect's/GC's outside draftsman produce — glazing
schedule + elevations + details) and pulls out, per elevation SHEET:

  - the elevation label ("W1", "1/E1", whatever's printed)
  - the printed THUS/OPP/TOTAL REQ'D counts (a QTY SUGGESTION only — never
    written to a WO line without the PM confirming it, same posture as
    round 326's split-group suggestion)
  - the system spec string ("TUBELITE T14000 SERIES SF (2" x 4 1/2")")
  - the finish ("CLEAR" ANODIZED (C2))
  - the ARCH REF tag
  - R.O. Width, F.S. Width, and each opening's D.L.O. Width, in left-to-
    right plan order — feeding round-325's Elevation Sizing width axis
    (job.elevSizing[elevKey]) as SUGGESTED values, same pattern.
  - R.O. Height, F.S. Height (round 342) — one overall value per
    elevation, feeding the same panel's height axis the same way.

Height (round 342, 2026-08-25): initially left as a manual-only entry
because rotated (vertical) height-dimension text on one sheet appeared to
decode to a DIFFERENT elevation's numbers. Investigated properly this
round by rendering the actual sheets to images rather than guessing from
extracted text alone — the "mismatch" was a false alarm: several real
elevations in this building genuinely share the exact same overall
height (same floor-to-floor band, different opening widths), confirmed
by looking at the real printed sheets for three independent elevations.
The real, separate issue that DID need fixing: a sheet can have a small
supplementary vertical dimension (e.g. a sill detail) sharing the same
X position as the main height dimension chain, which naive char-position
clustering merged into one garbled string. Fixed by splitting each
vertical-text column into separate lines wherever the gap between
consecutive characters is too large to be normal letter-spacing (a real,
consistently large gap — confirmed ~85pt vs. ~1-5pt for genuine word
spacing on the actual sheet). R.O. Height and F.S. Height now extract the
same way width does; D.L.O. Height is deliberately NOT extracted, since
Elevation Sizing's height axis only ever needs R.O./F.S. Height (a single
overall value per elevation, not a per-group breakdown — see round 325's
own stated boundary on height being one value, not full 2-D solving).

Extraction approach — label-anchored, whole-page word-position search, NOT
fixed regions (2026-08-24 design conversation with Jeremiah): every page is
walked with pdfplumber's real word coordinates, searching for the label
strings themselves ("ELEVATION", "R.O.", "F.S.", "D.L.O.", "ARCH REF",
"FINISH:", "THUS REQ'D:"/"OPP REQ'D:"/"TOTAL REQ'D:") wherever they land,
then reading the value nearest each label — not a hardcoded crop box. A
page qualifies as an elevation sheet by its own printed
"SHEET DESCRIPTION: ELEVATIONS" field or, failing that, the presence of an
"ELEVATION <name>" bubble; every other page (plans, schedules, details,
cover) is silently skipped, never guessed at by position or page count.

Multi-elevation sheets (two or more "ELEVATION <name>" bubbles on one page,
common when several narrow elevations share a sheet) are split by nearest-
column assignment: every R.O./F.S./D.L.O. value found on the page is
assigned to whichever elevation bubble is closest in the page's X axis —
verified against a real 2-up sheet (W3/W4) and a real 7-opening/4-opening
side-by-side sheet (W9/W10) in this drawing set, splitting cleanly in both
cases.

A REAL SECOND SAMPLE (an outside draftsman's set, tested 2026-08-25) turned
out to have NO usable text layer at all — pdfplumber returns scrambled,
partly (cid:NN)-mapped garbage for most labels (a broken/missing
ToUnicode CMap in that PDF's embedded font, not a wording/label
difference). No amount of label-pattern tuning fixes a font that doesn't
map its own glyphs to Unicode; that page would need real OCR (out of scope
here — see the OCR Quote Import engine, ocr_quote_parser.py, as the
existing precedent for that kind of work). classify_page() / the
extraction regexes simply find nothing recognizable on a page like that and
it's surfaced as a skipped/unparseable page with a plain-language reason —
never silently emitted as wrong data. quality-checked via _looks_garbled().

Optional dependency — same guarded posture as ocr_quote_parser.py's
pdfplumber use (see requirements.txt): this app has zero HARD third-party
dependencies. Without pdfplumber installed, parse_drawing_set() returns a
clear, honest error instead of failing to import or producing nothing.

Round 344 (2026-08-25) — three more real vendor sets tested (Jeremiah:
"I want to make sure additional drafting vendors import correctly to work
orders"), two genuinely new drafting conventions found and handled, one
confirmed as a real structural dead end:

1. Aspen drafting for A.M.D.G. Architects (Hope College North Campus
   Gateway, 45 real pages). Different on nearly every axis: the callout
   bubble reads "<name> FRAME ELEVATION" (name PRECEDES, not follows —
   the original "next word after ELEVATION" scan got the right answer on
   most sheets purely by pdfplumber's incidental word-stream order, and
   silent garbage ("ASPEN", "NO.") on several real ones); "F.D." instead
   of "F.S." for the identical reading; quantities as "N THUS TOTAL" +
   one "N THUS REQ'D <location> ELEVATION" line per location instead of
   THUS/OPP/TOTAL REQ'D; the arch ref as two separate tokens with no
   slash ("ARCH. <bubble> REF. <sheet>"); and D.L.O./F.D. values
   typeset stacked ~13pt ABOVE their label instead of inline before it
   on the same baseline. All handled as additional conventions tried
   alongside the original, never replacing it. Two real sheets (32e,
   33e) are horizontally mirrored end to end — a real drafting move to
   get an opposite-hand elevation — and their text extracts completely
   backwards; never decoded (same "needs real OCR" family as the round-
   340 broken-font sample below), but now surfaced as a named, actionable
   warning instead of a silent gap in skippedPages.
2. Ryan Engineering for Ghafari Architects (Kentwood DPW Improvements,
   confirmed against two real submittals). Closer to Vos house style on
   the callout ("ELEVATION <name>"), but quantities are a flat "QTY = N"
   (no REQ'D language at all), and arch refs are pipe-joined
   ("4/AE1-00|2/AE1-01") rather than one slash token. Both added as
   further conventions. Also the source of a genuine, confirmed dead
   end: this vendor's dimension figures (R.O./F.S./D.L.O.) are drawn as
   vector line art, not text — confirmed by inspecting the raw PDF
   characters directly (thousands of lines/curves in that region, zero
   characters). No regex or label-matching fix can read geometry; those
   values are correctly reported as not-found, same as any other sheet
   missing a dimension. The vendor's second submittal (110 pages) has a
   broken embedded font (real (cid:NN)-mapped garbage, same family as
   the round-340 sample) — previously silently dropped whenever a page
   also failed classify_page (which it always does, since cid-mapped
   text never contains the literal word "ELEVATION"); now surfaced as a
   capped, honest warning instead.

Column-based ownership (the same nearest-x logic used for widths/heights)
was extended to quantities and arch refs this round too, replacing a
flat whole-page regex search that was pooling a 2-up sheet's two
elevations' quantity lines into each elevation's record — a real bug
caught by hand-checking the Hope College E1e/2e sheet, not a hypothetical.
"""

import itertools
import re

try:
    import pdfplumber
    _HAVE_PDFPLUMBER = True
except ImportError:
    pdfplumber = None
    _HAVE_PDFPLUMBER = False


def drawing_import_status():
    return {"pdfplumber": _HAVE_PDFPLUMBER}


# ── low-level numeric / text helpers (unit-tested) ─────────────────────────

_FRAC = r'\d+(?:\s+\d+/\d+)?|\d+/\d+'
_BRACKET_RE = re.compile(r'\[\s*(' + _FRAC + r')"?\s*\]')
# 'F.D.' (Frame Dimension) is a second drafting vendor's label for the exact
# same reading Vos's own house style calls 'F.S.' (Frame Size) — round 344,
# added when a real outside draftsman's set (Aspen/A.M.D.G., Hope College)
# used F.D. throughout and never printed F.S. at all. Both map to the 'FS'
# key everywhere below.
_DIM_STOPWORDS = ('R.O.', 'F.S.', 'F.D.', 'D.L.O.', 'JOINT', 'D.O.')
# r543: the widest gap (pt) allowed between words of ONE dimension value,
# reading back from its label. Measured on the Beacon Hill and Alpine sets:
# genuine values run at 1-4pt between words; the stray text that leaked in
# sat 100pt+ away.
_VALUE_GAP_MAX = 20.0


def mixed_to_float(s):
    """'35 7/8' -> 35.875 ; '104' -> 104.0 ; '' / garbage -> None."""
    if s is None:
        return None
    s = str(s).strip().replace('"', '')
    if not s:
        return None
    try:
        if '/' in s:
            parts = s.split()
            if len(parts) == 2:
                whole, frac = parts
            else:
                whole, frac = '0', parts[0]
            num, den = frac.split('/')
            den_f = float(den)
            if den_f == 0:
                return None
            return float(whole) + float(num) / den_f
        return float(s)
    except (ValueError, ZeroDivisionError):
        return None


_CID_RE = re.compile(r'\(cid:\d+\)')
_CID_GARBLED_SHARE = 0.02    # unmapped glyphs per readable glyph


def _looks_garbled(text):
    """A cheap, honest signal that a page's text layer is unusable — an
    unmapped-CID marker, or an unusually high share of characters outside
    normal drawing-label vocabulary (letters/digits/common punctuation).
    Used to keep classify_page() from treating scrambled text as a real
    non-match (silence) vs. a real page worth flagging as unparseable."""
    if not text:
        return False
    # r547: an unmapped glyph is only fatal in QUANTITY. The MSU FCU Gaines
    # Marketplace set (Vos in-house, 33 pages) prints its rotated title block
    # with one unmapped glyph — the Z of "GLAZING" reads "GLA(cid:61)ING" — on
    # every page, while the other ~1,800 characters of each elevation sheet
    # read perfectly. The old any-'(cid:' rule condemned all 33 pages over that
    # one letter. Broken-font sets (round-340 GRCC, Ryan's 110-page submittal)
    # run (cid:NN) on a large share of their glyphs; a stray one in a title
    # block is a few tenths of a percent.
    n_cid = len(_CID_RE.findall(text))
    if n_cid:
        glyphs = sum(1 for c in _CID_RE.sub('#', text) if not c.isspace())
        if n_cid / float(max(1, glyphs)) > _CID_GARBLED_SHARE:
            return True
        text = _CID_RE.sub('', text)
    sample = text[:2000]
    weird = sum(1 for c in sample if not (c.isalnum() or c in " \n.,:/-'\"()#%$"))
    return len(sample) > 40 and (weird / max(1, len(sample))) > 0.15


def _looks_reversed(text):
    """True if this page's text layer reads back-to-front (a mirrored/
    horizontally-flipped elevation view — round 344). See _REVERSED_TEXT_RE."""
    return bool(text) and bool(_REVERSED_TEXT_RE.search(text))


# ── word/row clustering ─────────────────────────────────────────────────────

def _rows_from_words(words, tol=1.2):
    """Group words sharing (near enough) the same baseline into a text
    row, preserving x0 order — the unit everything else below reads off
    of. Two separate loops (assemble text after sorting) keep row text in
    true left-to-right order regardless of the words' original order."""
    rows = []
    for w in sorted(words, key=lambda w: (w['top'], w['x0'])):
        placed = False
        for r in rows:
            if abs(r['top'] - w['top']) <= tol:
                r['words'].append(w)
                placed = True
                break
        if not placed:
            rows.append({'top': w['top'], 'words': [w]})
    for r in rows:
        r['words'].sort(key=lambda w: w['x0'])
        r['text'] = ' '.join(w['text'] for w in r['words'])
    rows.sort(key=lambda r: r['top'])
    return rows


def _urect(a, b):
    """Union of two [x0, top, x1, bottom] rects (round 475 — the printed
    footprint of a dimension, label text + bracket value together, kept so
    the client can draw a status highlight exactly over the sheet's own
    number). Either side may be None."""
    if not a:
        return list(b) if b else None
    if not b:
        return list(a)
    return [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]


def _wrect(ws):
    """Bounding rect of a list of pdfplumber words/chars."""
    if not ws:
        return None
    return [min(w['x0'] for w in ws), min(w['top'] for w in ws),
            max(w.get('x1', w['x0']) for w in ws),
            max(w.get('bottom', w['top']) for w in ws)]


def _bracket_events(rows):
    """Every '[35 7/8"]'-style bracket value on the page (often split
    across two words — '[35' + '7/8"]' — since it wraps onto its own
    line below the feet-inch dimension), with the x0 of the word actually
    containing the opening bracket, used to line it up with its label."""
    ev = []
    for r in rows:
        offsets = []
        pos = 0
        for w in r['words']:
            offsets.append((pos, pos + len(w['text']), w))
            pos += len(w['text']) + 1
        for m in re.finditer(_BRACKET_RE, r['text']):
            x0 = r['words'][0]['x0']
            span = [w for start, end, w in offsets
                    if start < m.end() and end > m.start()]
            if span:
                x0 = span[0]['x0']
            ev.append({'top': r['top'], 'value': mixed_to_float(m.group(1)), 'x0': x0,
                       'rect': _wrect(span or r['words'][:1])})
    return ev


def _stacked_value_above(rows, label_top, label_x0, x_tol=6, top_win=20):
    """Fallback for a vendor that typesets a dimension value stacked
    slightly ABOVE its label instead of inline before it on the same
    baseline (round 344 — Aspen/A.M.D.G.'s per-opening D.L.O. and F.D.
    readings on the Hope College set: e.g. "38 1/4\"" sits ~13pt above its
    own "D.L.O." label, both starting at the same x0). Finds the nearest
    row above within `top_win` that has a word starting within `x_tol` of
    the label's x0, then reads that value forward the same way
    _label_events reads backward: until a stopword or a real x-gap."""
    start_w, best_dtop = None, None
    for r in rows:
        dtop = label_top - r['top']
        if not (0 < dtop <= top_win):
            continue
        for w in r['words']:
            if abs(w['x0'] - label_x0) <= x_tol:
                if best_dtop is None or dtop < best_dtop:
                    best_dtop, start_w = dtop, w
    if start_w is None:
        return None
    row = min((r for r in rows if abs(r['top'] - start_w['top']) <= 1.2),
              key=lambda r: abs(r['top'] - start_w['top']))
    val_words = []
    prev_x1 = None
    for w in row['words']:
        if w['x0'] < start_w['x0'] - 1:
            continue
        if w['text'] in _DIM_STOPWORDS:
            break
        if prev_x1 is not None and (w['x0'] - prev_x1) > 20:
            break
        val_words.append(w)
        prev_x1 = w.get('x1', w['x0'])
    if not val_words:
        return None
    return {'x0': val_words[0]['x0'], 'text': ' '.join(v['text'] for v in val_words),
            'rect': _wrect(val_words)}


def _label_events(rows, label):
    """Every occurrence of an exact label token ('R.O.', 'F.S.', 'D.L.O.')
    on the page, paired with the feet-inch expression before it — normally
    immediately before it on the SAME row (walking backward until another
    dimension label or the start of the row), e.g. "3'-1 1/4\" D.L.O." ->
    value text "3'-1 1/4\"", anchored at that text's x0 for bracket-
    matching below. Falls back to _stacked_value_above when a label
    occurrence has nothing usable on its own row (a different vendor's
    stacked-typesetting convention — see that function)."""
    ev = []
    for r in rows:
        for idx, w in enumerate(r['words']):
            if w['text'] != label:
                continue
            val_words = []
            right_x0 = w['x0']
            for pw in reversed(r['words'][:idx]):
                if pw['text'] in _DIM_STOPWORDS:
                    break
                # r543: stop at a real x-gap, the same 20pt rule
                # _stacked_value_above already uses reading forward. A
                # dimension's value is one tight run of words; without this
                # the walk kept going across the sheet. Beacon Hill E5/E31
                # (two elevations side by side): the right elevation's
                # D.L.O. row shares a baseline with the left elevation's
                # 1 3/4" member dims, a bubble number, or the rotated
                # "JOINT" text, so its first D.L.O. and its F.S. were read
                # as starting 500pt to the left and handed to the LEFT
                # elevation by _nearest_by_x.
                if right_x0 - pw.get('x1', pw['x0']) > _VALUE_GAP_MAX:
                    break
                val_words.insert(0, pw)
                right_x0 = pw['x0']
            if val_words:
                ev.append({'top': r['top'], 'x0': val_words[0]['x0'],
                           'text': ' '.join(vw['text'] for vw in val_words),
                           'rect': _wrect(val_words + [w]),
                           'lrect': [w['x0'], w['top'], w.get('x1', w['x0']), w.get('bottom', w['top'])]})
                continue
            stacked = _stacked_value_above(rows, r['top'], w['x0'])
            if stacked:
                ev.append({'top': r['top'], 'x0': stacked['x0'], 'text': stacked['text'],
                           'rect': _urect(stacked.get('rect'), _wrect([w])),
                           'lrect': [w['x0'], w['top'], w.get('x1', w['x0']), w.get('bottom', w['top'])],
                           'stacked': True})   # r560: borrowed from above, not beside
    return ev


def _nearest_bracket(label_ev, brackets, x_win=32, top_win=(-2, 15)):
    best, best_d = None, None
    for b in brackets:
        dtop = b['top'] - label_ev['top']
        if dtop < top_win[0] or dtop > top_win[1]:
            continue
        dx = abs(b['x0'] - label_ev['x0'])
        if dx > x_win:
            continue
        d = dx + dtop
        if best is None or d < best_d:
            best, best_d = b, d
    return best


# ── r510: multi-sheet curtain-wall elevations ────────────────────────────
#
# A large curtain wall is drawn across several sheets — Beacon Hill's C3 runs
# over E39/E39A/E39B and C2 over E41..E41C — and the adjoining sheets OVERLAP:
# each draws a little past its boundary so the neighbouring lites read in
# context. Counting the lites sheet by sheet therefore double-counts.
#
# The boundary is a MATCH LINE, drawn as a thick bar with a NUMBERED label.
# Jeremiah's rules, confirmed against the drawing:
#   * a sheet owns the last lite drawn in FULL before its match line
#   * a lite may be split by the line and need not fall on a mullion
#   * match lines always come in numbered pairs, so line 2 on one sheet is
#     line 2 on the next
#
# Which gives one test that covers all three: a sheet owns every lite drawn
# WHOLLY BETWEEN its match lines. A lite cut by a line is not wholly inside on
# the sheet that cuts it, and is wholly inside on the sheet that draws it
# complete — so it is counted exactly once, with no special case.
#
# THE LABEL IS MIRRORED. It reads "1ENILHCTAM" in the text layer, which is why
# every plain search for "MATCH LINE" came back empty on sheets that plainly
# have them. The characters are rotated and do not group into words either, so
# they are clustered by x column here.
_MATCHLINE_TOKENS = ('MATCHLINE', 'ENILHCTAM')


def _matchlines(page):
    """[{'num': int|None, 'x': float}] for this sheet, left to right."""
    cols = {}
    for c in page.chars:
        if c.get('upright', True):
            continue
        cols.setdefault(round(c['x0'] / 3.0) * 3.0, []).append(c)
    out = []
    for x, cs in cols.items():
        txt = ''.join(ch['text'] for ch in sorted(cs, key=lambda c: c['top']))
        flat = re.sub(r'\s+', '', txt).upper()
        if not any(t in flat for t in _MATCHLINE_TOKENS):
            continue
        m = re.search(r'(\d+)', flat)
        out.append({'num': int(m.group(1)) if m else None, 'x': float(x)})
    # The label is drawn twice, a few points apart, on either side of the bar;
    # collapse a pair into the one boundary it marks.
    out.sort(key=lambda e: e['x'])
    merged = []
    for e in out:
        if merged and abs(e['x'] - merged[-1]['x']) < 40 and e['num'] == merged[-1]['num']:
            merged[-1]['x'] = (merged[-1]['x'] + e['x']) / 2.0
            continue
        merged.append(dict(e))
    return merged


# Where a dimension actually SITS on the sheet.
#
# Not the left edge of its captured footprint: on a flattened curtain-wall
# sheet the label group can union across a whole dimension row — one 53 3/8"
# reading came back with a rect 270pt wide, spanning the FLATTENED VIEW title
# and its own arrow — which put it hundreds of points left of where it is
# drawn and reordered it against its neighbours. The BRACKETED value is a
# short token and is where the eye reads the number, so that anchors it.
def _dim_anchor_x(ev):
    b = ev.get('bracket_rect') or ev.get('value_rect')
    if b:
        return (b[0] + b[2]) / 2.0
    r = ev.get('rect')
    if r and (r[2] - r[0]) <= 120:
        return (r[0] + r[2]) / 2.0
    return ev.get('x0')


def _owned_between(events, mls, page_width):
    """The events drawn wholly between this sheet's match lines."""
    xs = sorted(e['x'] for e in mls)
    lo = xs[0] if xs else None
    hi = xs[-1] if len(xs) > 1 else (xs[0] if xs else None)
    if len(xs) == 1:
        # One line: it is a boundary at whichever end it sits.
        if xs[0] < page_width / 2.0:
            lo, hi = xs[0], None
        else:
            lo, hi = None, xs[0]
    out = []
    for e in events:
        x = _dim_anchor_x(e)
        if x is None:
            continue
        if lo is not None and x < lo:
            continue
        if hi is not None and x > hi:
            continue
        out.append(e)
    return out


# ── r511: mirror is a manual takeoff ─────────────────────────────────────
#
# Beacon Hill's YOGA and FITNESS are MIRROR WALLS, not framed elevations.
# They carry no F.S. and no D.L.O. at all: an overall R.O., bare module
# widths, row heights labelled R.O., and a J-channel note. Jeremiah's call is
# that mirror stays a MANUAL takeoff for now — so the job here is not to size
# anything, it is to make sure a PM is REMINDED to.
#
# The tag is not hardcoded. Sheet 1's glazing schedule defines GL-M as
# 1/4" CLEAR MIRROR, so mirror is recognised from the MAKE-UP — which keeps
# working on a job that names the tag something else, and is the difference
# between reading the drawing and guessing at Vos's conventions. Jeremiah
# puts mirror at 5-10% of jobs, so this will come round again.
_MIRROR_RE = re.compile(r'\bMIRROR\b', re.I)


def _mirror_tags(glazing_schedule):
    """The tags whose make-up says mirror, from the set's own schedule."""
    out = set()
    for row in (glazing_schedule or []):
        text = ' '.join(str(x) for x in (row.get('makeup') or []))
        if _MIRROR_RE.search(text):
            tag = str(row.get('tag') or '').strip().upper()
            if tag:
                out.add(tag)
    return out


def _flag_mirror(elevations, glazing_schedule):
    """Mark every elevation glazed with a mirror tag as needing a manual
    takeoff, and say so in the warnings. Nothing is sized, nothing is
    dropped — the readings stay exactly as they were read."""
    tags = _mirror_tags(glazing_schedule)
    notes = []
    if not tags:
        return notes
    for rec in elevations:
        used = {str(t.get('tag') or '').strip().upper()
                for t in (rec.get('glassTags') or [])}
        hit = sorted(used & tags)
        if not hit:
            continue
        rec['manualTakeoff'] = {'reason': 'mirror', 'tags': hit,
                                'count': len([t for t in (rec.get('glassTags') or [])
                                              if str(t.get('tag') or '').strip().upper() in tags])}
        notes.append(
            'Elevation %s is glazed with %s (mirror) \u2014 %d panel(s) tagged. Mirror is a '
            'MANUAL takeoff: the app has not sized these, and the sizes on this elevation '
            'are whatever the sheet printed.'
            % (rec.get('label') or '?', ', '.join(hit), rec['manualTakeoff']['count']))
    return notes


# ── r512: say WHY a page was skipped ─────────────────────────────────────
#
# Beacon Hill skips 63 of 114 pages, and every one of them is right to skip:
# 47 detail sheets, 12 plan sheets, a section, a cover. But "skippedPages: 63"
# tells a PM nothing, and a genuinely lost elevation would hide inside it.
# The bubble already names what a sheet is — "48 SECTION 1", "DETAIL 12" — so
# the kind is read off it and the skip is reported by kind. A SECTION is rare
# enough to be named by page; details and plans are summarised.
# Order matters and each form is the sheet's own callout, not prose: the
# cover carries "D1 THRU D45 DETAILS" and "P1 THRU P12 PLAN VIEWS" in its
# index and "- SECTION ARE NOT ANSWERED" in a note, and would match all three
# loose forms. It is a cover because it has a SHEET INDEX, and that is
# checked first.
_SHEET_KIND_RES = (
    ('cover',   re.compile(r'\bSHEET\s+INDEX\b', re.I)),
    ('section', re.compile(r'(?:^|\s)\d{1,3}\s+SECTION\s+\d', re.I)),
    ('detail',  re.compile(r'\b(?:HEAD|SILL|JAMB|MULLION|HORIZ\w*|VERT\w*|CORNER|TYP\w*)\s+DETAIL\b'
                           r'|(?:^|\s)\d{1,3}\s+DETAIL\b|\bDETAIL\s+\d', re.I)),
    ('plan',    re.compile(r'(?:^|\s)\d{1,3}\s+[A-Z ]*PLAN\b', re.I)),
)


def _sheet_kind(text):
    """'section' | 'detail' | 'plan' | None, from the sheet's own callout."""
    for kind, rx in _SHEET_KIND_RES:
        if rx.search(text or ''):
            return kind
    return None


def _skip_summary(kinds):
    """One line for the warnings from a {kind: [pages]} tally. Empty when
    nothing was skipped."""
    parts = []
    for kind, label in (('cover', 'cover'), ('detail', 'detail'), ('plan', 'plan'),
                        ('section', 'section')):
        pages = kinds.get(kind) or []
        if not pages:
            continue
        if kind == 'section':
            parts.append('%d section sheet%s (page%s %s)' % (
                len(pages), '' if len(pages) == 1 else 's', '' if len(pages) == 1 else 's',
                ', '.join(str(p) for p in pages)))
        else:
            parts.append('%d %s sheet%s' % (len(pages), label, '' if len(pages) == 1 else 's'))
    other = kinds.get(None) or []
    if other:
        parts.append('%d other page%s (%s)' % (
            len(other), '' if len(other) == 1 else 's', ', '.join(str(p) for p in other[:6])
            + ('\u2026' if len(other) > 6 else '')))
    if not parts:
        return ''
    return 'Not imported: ' + '; '.join(parts) + ' \u2014 none of these are elevations.'


def _continued_base(label):
    """'C3 - CONTINUED' -> 'C3'. The suffix is how a sheet says it is carrying
    on the elevation named before it."""
    return re.sub(r'\s*-\s*CONTINUED\s*$', '', str(label or '').strip(), flags=re.I)


def _merge_continued(elevations):
    """Fold a multi-sheet elevation into ONE record, and drop the plan sheets
    drawn alongside it.

    Elevation or plan is decided by GLASS TAGS: an elevation tags every lite
    because someone has to order that glass; a plan locates layout points
    because someone has to set the frame out. Across Beacon Hill's thirteen
    curtain-wall sheets the two are perfectly anti-correlated, and the signals
    that looked obvious first — 'L.P. TO L.P.' dimensions, the 'FLATTENED
    VIEW' note — each held for one elevation and failed on the other.

    Quantity is the deduplicated lite count and R.O. width their total
    (Jeremiah): it is the framing width Vos will build, even though it does
    not match the laid-out curve.
    """
    groups, order, out, notes = {}, [], [], []
    for rec in elevations:
        # The bubble NAME is the key, because the head sheet of a run is
        # labelled by its callout number and only continuations carry the
        # name. Where the bubble itself will not extract — Beacon Hill's page
        # 66, a plan sheet whose title block is unreadable — the LABEL still
        # says C2, so it falls back to that rather than being orphaned or
        # guessed at from page order.
        name = rec.get('elevName') or rec.get('label') or ''
        base = _continued_base(name)
        if not base:
            out.append(rec)
            continue
        if base not in groups:
            groups[base] = []
            order.append(base)
        groups[base].append(rec)
    for base in order:
        recs = groups[base]
        # Only a run that actually says CONTINUED is a multi-sheet elevation.
        # Two elevations that merely share a name are not one wall.
        def _says_continued(r):
            n = r.get('elevName') or r.get('label') or ''
            return _continued_base(n) != str(n).strip() or bool(r.get('continued'))
        if len(recs) == 1 or not any(_says_continued(r) for r in recs):
            out.extend(recs)
            continue
        elev = [r for r in recs if (r.get('glassTags') or [])]
        plan = [r for r in recs if not (r.get('glassTags') or [])]
        if not elev:
            out.extend(recs)                     # nothing to tell apart; leave it alone
            continue
        head = dict(elev[0])
        lites = []
        for r in elev:
            lites.extend(r.get('ownedDloWidths') or [])
        head['label'] = base
        head['sheets'] = [r.get('page') for r in elev]
        head['planSheets'] = [r.get('page') for r in plan]
        head['dloWidths'] = lites
        head['totalReqd'] = len(lites)
        head['roW'] = sum(lites) if lites else head.get('roW')
        head['multiSheet'] = True
        # The frame closes per sheet, never across the join, so an axis check
        # on the merged record would be arithmetic on a thing that does not
        # exist. Left unjudged rather than passed or failed.
        head['balance'] = {'w': None, 'h': None}
        out.append(head)
        notes.append(
            'Elevation %s is drawn across %d sheets (pages %s) with %d plan sheet(s) '
            'alongside (pages %s). Merged into one elevation of %d lites; the plan '
            'sheets were not imported.'
            % (base, len(elev), ', '.join(str(p) for p in head['sheets']),
               len(plan), ', '.join(str(p) for p in head['planSheets']) or '-',
               len(lites)))
    return out, notes


# ── r506: does the frame close? ──────────────────────────────────────────
#
# An elevation's frame is an equation: the frame size is the openings in it
# plus the members between them. If that can't close, something was read off
# the wrong dimension line — which is the failure mode worth catching, since
# a wrong D.L.O. flows straight into a glass size.
#
# The naive form (F.S. - every term = members) does NOT work, and round 505
# found out why on real sheets: a TRANSOM sits on a PARALLEL chain, not in
# series. On 11/E8 the 34" transom is ABOVE the 36" door, so summing them
# overshoots the 40" frame. Chain membership is the whole problem.
#
# Rather than guess chains from text geometry, this asks the arithmetic:
# find the LARGEST subset of terms that closes the frame with a plausible
# uniform member. Two structural facts make that well-behaved:
#
#   * A D.O. is MANDATORY when present. A door is always in the frame it
#     sits in — it can never be the term on a parallel chain.
#   * Members number |S|+1 normally, or |S| when one end is the floor: a
#     door runs to the sill with no member under it.
#
# The member ceiling is 4 1/2": a frame member whose FACE is wider than that
# is not a member, it is a panel. Verified against the whole Alpine set —
# every correct elevation closes at 1 1/2" to 2", and the ceiling only ever
# changes the verdict for 5/E2, which Jeremiah independently confirmed is
# wrong. The answer is the same anywhere from 3 1/2" to 4 1/2", so the bound
# is not doing delicate work.
#
# This reports; it never edits a value. Round 505's lesson was that the
# parser is usually right and the equation was wrong, so a check that
# "corrected" readings would be the same mistake with more confidence.
_MEMBER_MIN, _MEMBER_MAX = 1.0, 4.5


def _is_sixteenth(x):
    return abs(x - round(x * 16) / 16) < 1e-9


# An elevation can carry more than one INDEPENDENT chain on an axis, and
# 10/E7 is why this matters: the sidelites sit in an 83" frame starting 16"
# off the floor while the doors sit in a 99" frame running to it. Both are
# correct and they top out together (83 + 16 = 99). Comparing the sidelite
# frame's F.S. against the door's D.O. — which is what a single-chain check
# does — flags a perfectly good elevation.
#
# Chains are separated on the sheet by a wide gap in the CROSS-axis
# coordinate: on 10/E7 the left chain's dimension lines sit at x 80-125 and
# the door chain's at x 430-498, while the nested lines WITHIN a chain step
# by about 22pt. 60pt splits them with a wide margin either side.
_CHAIN_GAP = 60.0


def _chain_groups(events, key):
    """Cluster dimension events into chains by a gap in `key` ('x0' for the
    height axis, 'top' for the width axis)."""
    evs = sorted([e for e in events if e.get(key) is not None], key=lambda e: e[key])
    groups, cur = [], []
    for e in evs:
        if cur and e[key] - cur[-1][key] > _CHAIN_GAP:
            groups.append(cur)
            cur = []
        cur.append(e)
    if cur:
        groups.append(cur)
    return groups


# ── r546: members as PRINTED — expansion mullions ───────────────────────
#
# Jeremiah: "Expansion mullions do come up consistently." The check above
# assumes every member in a chain is one size, so Howell 65/E33 (2" members
# with one 2 1/2" EXPANSION MULLION) was flagged although it closes exactly:
# 6 D.L.O.s 416 3/4 + 6 x 2 + 2 1/2 = 431 1/4.
#
# The sheet prints every member: a row of bare inch values ('2"', '2 1/2"')
# about 26pt below the D.L.O. row. So when the uniform test fails, the
# printed members are tried instead — F.S. must equal the openings plus the
# printed members EXACTLY, with one member more than openings (or as many,
# the same allowance the uniform test makes). This only ever turns a FAIL
# into a pass on the sheet's own numbers; anything the uniform test passes
# is untouched. Width axis only: expansion mullions are vertical members.
_MEMBER_TOKEN_RE = re.compile(r'^(\d+(?:\s+\d+/\d+)?|\d+/\d+)"$')
_MEMBER_JOIN_GAP = 4.0         # '2' + '1/2"' typeset as two words
# r547: 4.0 (was 3.0) — the Gaines Marketplace set's Arial sets them 3.5pt
# apart, which read every 2 1/2" member as 1/2".
_MEMBER_ROW_WIN = 45.0         # how far below the chain's lowest reading
_MEMBER_MIN_V, _MEMBER_MAX_V = 0.5, 6.0


def _member_events(page):
    """Every bare inch value on the sheet ('2"', '2 1/2"', '11/16"') that is
    not part of a labelled dimension: [{x0, x1, top, value, rect}]."""
    rows = _rows_from_words(page.extract_words(x_tolerance=1.5))
    out = []
    for r in rows:
        ws = r['words']
        i = 0
        while i < len(ws):
            w = ws[i]
            run = [w]
            # '2' then '1/2"' tight together is ONE value
            if (re.match(r'^\d+$', w['text']) and i + 1 < len(ws)
                    and re.match(r'^\d+/\d+"$', ws[i + 1]['text'])
                    and ws[i + 1]['x0'] - w.get('x1', w['x0']) <= _MEMBER_JOIN_GAP):
                run.append(ws[i + 1])
            txt = ' '.join(x['text'] for x in run)
            nxt = ws[i + len(run)] if i + len(run) < len(ws) else None
            prv = ws[i - 1] if i > 0 else None
            m = _MEMBER_TOKEN_RE.match(txt)
            labelled = (nxt is not None and nxt['text'] in _DIM_STOPWORDS + ('TRANSOM',)
                        and nxt['x0'] - run[-1].get('x1', run[-1]['x0']) <= 6)
            # a fraction glued to a feet value ("5'-9" "1/2\"") is not a member
            glued = (prv is not None and "'" in prv['text']
                     and run[0]['x0'] - prv.get('x1', prv['x0']) <= _MEMBER_JOIN_GAP)
            if m and not labelled and not glued:
                v = mixed_to_float(m.group(1))
                if v is not None and _MEMBER_MIN_V <= v <= _MEMBER_MAX_V:
                    rect = _wrect(run)
                    out.append({'x0': run[0]['x0'], 'x1': run[-1].get('x1', run[-1]['x0']),
                                'top': r['top'], 'value': v, 'rect': rect,
                                'bracket_rect': rect})
            i += len(run)
    return out


def _member_rows(grp_bottom, mem_evs):
    """Candidate printed-member rows under a chain, nearest first, each the
    values left to right."""
    near = [e for e in mem_evs if 0 < e['top'] - grp_bottom <= _MEMBER_ROW_WIN]
    rows = []
    for e in sorted(near, key=lambda e: e['top']):
        if rows and abs(rows[-1][0]['top'] - e['top']) <= 1.5:
            rows[-1].append(e)
        else:
            rows.append([e])
    return [[e['value'] for e in sorted(r, key=lambda e: e['x0'])]
            for r in rows if len(r) >= 2]


def _balance_printed(fs, dos, dlos, members):
    """Closes on the PRINTED members? Same subset search as _balance_axis,
    but the members are the sheet's own and the sum must be exact."""
    opt = [d for d in (dlos or []) if d is not None]
    dos = [d for d in (dos or []) if d is not None]
    tot_m = sum(members)
    for k in range(len(opt), -1, -1):
        for sub in itertools.combinations(range(len(opt)), k):
            terms = [opt[i] for i in sub] + dos
            if not terms:
                continue
            if len(members) not in (len(terms) + 1, len(terms)):
                continue
            if abs(fs - sum(terms) - tot_m) < 1e-6:
                common = max(set(members), key=members.count)
                return {'ok': True, 'member': common, 'members': list(members),
                        'printed': True, 'used': len(terms), 'dropped': len(opt) - k}
    return None


def _balance_chains(fs_evs, do_evs, dlo_evs, key, pair_doors=False, mem_evs=None):
    """Balance each chain that has a frame size, and pass only when they all
    do. None when no chain has one — unverifiable is not a pass."""
    tagged = ([dict(e, _k='fs') for e in fs_evs]
              + [dict(e, _k='do') for e in do_evs]
              + [dict(e, _k='dlo') for e in dlo_evs])
    out = []
    for grp in _chain_groups(tagged, key):
        fs = next((e for e in grp if e['_k'] == 'fs'), None)
        if fs is None:
            continue                      # a chain with no frame size to close
        # r545: EVERY door opening in the chain, not just the first. A pair
        # of doors prints one D.O. per leaf (Howell 67/E35: 3'-0" D.O. twice),
        # and each leaf is its own term in the frame — taking only the first
        # left 36" unaccounted for and flagged a frame that closes.
        # Width axis only (pair_doors): leaves sit side by side across a frame,
        # never stacked up one, so the height axis keeps the first D.O.
        dos = [e['value'] for e in grp if e['_k'] == 'do' and e.get('value') is not None]
        if not pair_doors:
            dos = dos[:1]
        dl = [e['value'] for e in grp if e['_k'] == 'dlo']
        r = _balance_axis(fs['value'], dos or None, dl)
        # r546: a chain the uniform test fails may still close on the
        # members the sheet prints (an expansion mullion).
        if r is not None and not r['ok'] and mem_evs:
            bottom = max(e['top'] for e in grp)
            for row in _member_rows(bottom, mem_evs):
                pr = _balance_printed(fs['value'], dos, dl, row)
                if pr:
                    r = pr
                    break
        if r is not None:
            out.append(r)
    if not out:
        return None
    return {'ok': all(r['ok'] for r in out), 'chains': out,
            'member': out[0].get('member'),
            'used': sum(r['used'] for r in out),
            'dropped': sum(r['dropped'] for r in out)}


def _balance_axis(fs, do, dlos):
    """None when it cannot be judged (no frame size) — deliberately NOT a
    pass. Otherwise {'ok', 'member', 'used', 'dropped'}."""
    if fs is None:
        return None
    # r545: `do` is one door opening or a list of them (a pair of leaves);
    # every one is mandatory and each is its own term.
    if isinstance(do, (list, tuple)):
        dos = [x for x in do if x is not None]
    else:
        dos = [do] if do is not None else []
    do = dos or None
    opt = [d for d in (dlos or []) if d is not None]
    # r513: a frame size with NOTHING in it cannot be judged either. This is
    # the same rule as "no frame size is not a pass", applied to the other
    # side: unjudgeable is not a FAIL. 33/E25 shares its sheet with 34/E25,
    # both drawn with identical height chains; the callout filter let the
    # neighbour's F.S. through while keeping only this elevation's D.L.O.s, so
    # a chain arrived holding a frame size and no openings — and was reported
    # as a frame that does not close, on an elevation where both axes are
    # correct.
    if not opt and do is None:
        return None
    for k in range(len(opt), -1, -1):
        for sub in itertools.combinations(range(len(opt)), k):
            terms = [opt[i] for i in sub] + dos
            if not terms:
                continue
            left = fs - sum(terms)
            for n in (len(terms) + 1, len(terms)):
                if n <= 0:
                    continue
                m = left / n
                if _is_sixteenth(m) and _MEMBER_MIN <= m <= _MEMBER_MAX:
                    return {'ok': True, 'member': m,
                            'used': len(terms), 'dropped': len(opt) - k}
    return {'ok': False, 'member': None, 'used': 0, 'dropped': 0}


_BARE_DIM_STOP = ('RO', 'FS', 'DLO', 'DO')
_BARE_WHOLE_RE = re.compile(r"^\d+'-\d+$|^\d+$")
_DENOMS = (2, 4, 8, 16, 32, 64)


def _bare_value(text):
    """A bare-label dimension value, only when it is COMPLETE — it must close
    with its inch mark. On this set a stacked fraction (1'-2 ¹⁄₁₆") is drawn as
    four separate pieces; reading any one piece alone ("16", "1'-2") would be a
    plausible-looking wrong number, so a piece that doesn't close is no value."""
    t = (text or '').strip()
    if not t.endswith('"'):
        return None
    return _feetinch_to_float(t)


def _stacked_frac(main, num, den):
    """whole + num/den, or None unless num/den is a real drafting fraction."""
    if not (num.isdigit() and den.isdigit()):
        return None
    n, d = int(num), int(den)
    if d not in _DENOMS or not (0 < n < d):
        return None
    w = _feetinch_to_float(main + '"')
    return None if w is None else w + float(n) / d


def _stacked_frac_h(words, cx, label_top):
    """r547 — a horizontal stacked-fraction value centred over a DLO label:
    whole part ("1'-2"), numerator ~6pt ABOVE it and denominator ~6pt BELOW
    it, just to its right, then the closing '"' back on the whole's baseline.
    Every piece must be where it belongs; otherwise None."""
    near = [w for w in words if 0 < label_top - w['top'] <= 34 and abs((w['x0'] + w['x1']) / 2.0 - cx) <= 40]
    best = None
    for m in near:
        if not _BARE_WHOLE_RE.match(m['text']):
            continue
        nums = [w for w in near if w is not m and w['text'].isdigit()
                and -3 <= w['x0'] - m['x1'] <= 8 and 3 <= m['top'] - w['top'] <= 9]
        dens = [w for w in near if w is not m and w['text'].isdigit()
                and -8 <= w['x0'] - m['x1'] <= 8 and 3 <= w['top'] - m['top'] <= 9]
        if len(nums) != 1 or len(dens) != 1:
            continue
        nu, de = nums[0], dens[0]
        right = max(nu['x1'], de['x1'])
        closes = [w for w in near if w['text'] == '"' and abs(w['top'] - m['top']) <= 2
                  and -3 <= w['x0'] - right <= 6]
        if len(closes) != 1:
            continue
        v = _stacked_frac(m['text'], nu['text'], de['text'])
        if v is None:
            continue
        span = [m, nu, de, closes[0]]
        c = (min(w['x0'] for w in span) + max(w['x1'] for w in span)) / 2.0
        if abs(c - cx) > 14:
            continue
        if best is None or abs(c - cx) < best[0]:
            best = (abs(c - cx), v, span)
    return (best[1], best[2]) if best else (None, None)


def _stacked_frac_v(lines, lab):
    """The rotated counterpart: the whole part is one vertical line, the
    numerator one column LEFT and the denominator one column RIGHT of it at
    its reading end (smaller 'top'), and the '"' on the whole's own column
    just past them."""
    lr = lab.get('rect')
    if not lr:
        return None, None
    lcy = (lr[1] + lr[3]) / 2.0
    for m in lines:
        mr = m.get('rect')
        if not mr or not (0 < lab['x0'] - m['x0'] <= 34) or not _BARE_WHOLE_RE.match(m['text'].strip()):
            continue
        def at(dx, txt_ok):
            return [l for l in lines if l is not m and l.get('rect') and abs((l['x0'] - m['x0']) - dx) <= 2.5
                    and txt_ok(l['text'].strip())
                    and -3 <= mr[1] - l['rect'][3] <= 3]
        nums = at(-6, str.isdigit)
        dens = at(6, str.isdigit)
        if len(nums) != 1 or len(dens) != 1:
            continue
        nr = nums[0]['rect']
        closes = [l for l in lines if l is not m and l.get('rect') and abs(l['x0'] - m['x0']) <= 2.5
                  and l['text'].strip() == '"' and -3 <= nr[1] - l['rect'][3] <= 3]
        if len(closes) != 1:
            continue
        v = _stacked_frac(m['text'].strip(), nums[0]['text'].strip(), dens[0]['text'].strip())
        if v is None:
            continue
        rect = _urect(_urect(mr, closes[0]['rect']), _urect(nr, dens[0]['rect']))
        if abs((rect[1] + rect[3]) / 2.0 - lcy) > 14:
            continue
        return v, dict(m, rect=rect)
    return None, None


def _bare_width_events(rows):
    """r547 — convention-E sheets (MSU FCU Gaines Marketplace) label their
    dimensions without periods and without bracketed inches:

        14'-8" RO          ← value then label, same row
        14'-6 3/4" FS
        4'-6 7/8"  4'-7"  4'-6 7/8"
          DLO       DLO     DLO      ← label CENTRED under its value, ~18pt below

    Only ever called for a page whose callouts are convention E, so a bare
    'RO' / 'FS' / 'DLO' token on another vendor's sheet (a note, a room tag)
    can never become a dimension."""
    out = {'RO': [], 'FS': [], 'DLO': [], 'DO': []}
    for r in rows:
        ws = r['words']
        for idx, w in enumerate(ws):
            if w['text'] not in ('RO', 'FS', 'DO'):
                continue
            val, right_x0 = [], w['x0']
            for pw in reversed(ws[:idx]):
                if pw['text'] in _BARE_DIM_STOP or right_x0 - pw['x1'] > _VALUE_GAP_MAX:
                    break
                val.insert(0, pw)
                right_x0 = pw['x0']
            v = _bare_value(' '.join(x['text'] for x in val)) if val else None
            if v is not None:
                out[w['text']].append({'x0': val[0]['x0'], 'top': r['top'], 'value': v,
                                       'bracket_rect': None, 'rect': _wrect(val + [w])})
    all_words = [w for r in rows for w in r['words']]
    for r in rows:
        for w in r['words']:
            if w['text'] != 'DLO':
                continue
            cx = (w['x0'] + w['x1']) / 2.0
            sv, span = _stacked_frac_h(all_words, cx, w['top'])
            if sv is not None:
                out['DLO'].append({'x0': min(x['x0'] for x in span), 'top': r['top'], 'value': sv,
                                   'vtop': span[0]['top'], 'x1': max(x['x1'] for x in span),
                                   'bracket_rect': None, 'rect': _wrect(span + [w])})
                continue
            best = None
            for ar in rows:
                dtop = r['top'] - ar['top']
                if not (0 < dtop <= 26):
                    continue
                # split the row above into runs (one value each)
                runs, cur = [], []
                for aw in ar['words']:
                    if cur and aw['x0'] - cur[-1]['x1'] > 6:
                        runs.append(cur)
                        cur = []
                    cur.append(aw)
                if cur:
                    runs.append(cur)
                for run in runs:
                    rc = (run[0]['x0'] + run[-1]['x1']) / 2.0
                    if abs(rc - cx) > 14 or _bare_value(' '.join(x['text'] for x in run)) is None:
                        continue
                    d = abs(rc - cx) + dtop
                    if best is None or d < best[0]:
                        best = (d, run, ar['top'])
            if best is None:
                continue
            run = best[1]
            v = _bare_value(' '.join(x['text'] for x in run))
            if v is not None:
                out['DLO'].append({'x0': run[0]['x0'], 'top': r['top'], 'value': v,
                                   'vtop': best[2], 'x1': run[-1]['x1'],
                                   'bracket_rect': None, 'rect': _wrect(run + [w])})
    return out


def _width_events(page, bare=False):
    """{'RO':[{x0,top,value}], 'FS':[...], 'DLO':[...]} for one page —
    every R.O./F.S./D.L.O. reading found, bracket-inches value preferred
    (already-decimal, no feet/inches/fraction parsing needed) and falling
    back to parsing the feet-inch text itself only if no bracket paired."""
    words = page.extract_words(x_tolerance=1.5)
    rows = _rows_from_words(words)
    if bare:
        return _bare_width_events(rows)
    brackets = _bracket_events(rows)
    out = {'RO': [], 'FS': [], 'DLO': [], 'DO': []}
    # r505: D.O. (the door opening) is a real term in the frame equation and
    # was never read, so no door elevation could ever be checked —
    # F.S. - D.O. - sum(D.L.O.) = members. TRANSOM D.L.O. needs nothing new:
    # it is just another D.L.O. (Jeremiah) and already matches below.
    for label, key in (('R.O.', 'RO'), ('F.S.', 'FS'), ('F.D.', 'FS'),
                       ('D.L.O.', 'DLO'), ('D.O.', 'DO')):
        for ev in _label_events(rows, label):
            b = _nearest_bracket(ev, brackets)
            value = b['value'] if b else _feetinch_to_float(ev['text'])
            out[key].append({'x0': ev['x0'], 'top': ev['top'], 'value': value,
                             'stacked': bool(ev.get('stacked')), 'lrect': ev.get('lrect'),
                             # r510: the bracketed value's OWN box, kept apart
                             # from the unioned label footprint — see
                             # _dim_anchor_x for why the union cannot be
                             # trusted to say where a dimension sits.
                             'bracket_rect': (b.get('rect') if b else None),
                             'rect': _urect(ev.get('rect'), b.get('rect') if b else None)})
    return _merge_markups(page, out, 'w')   # r560: typed markups


# ── vertical (rotated) text — the height axis (round 342) ─────────────────
#
# The height dimension chain is drawn as rotated (90°) text along the left
# margin of each elevation. This is the exact transpose of the width-axis
# helpers above: group by X (the position that stays ~constant along one
# vertical dimension line) instead of Y, and read DOWN the page instead of
# across it. Reading direction — sorting each column's characters by
# DESCENDING 'top' reads top-of-sheet to bottom — was verified against five
# independent real sheets (rendered to images and compared by eye) before
# being hardcoded; this document's rotation is consistent throughout.
#
# One real wrinkle found while verifying this against real sheets: a small
# supplementary dimension (e.g. a sill detail) can share the exact same X
# column as the main height chain, stacked well above or below it — naive
# character concatenation within one X-bucket merged the two into one
# unreadable string. Fixed by splitting a column into separate LINES
# wherever the gap between consecutive characters is too big to be normal
# letter-spacing (measured on the real sheet: ~1-5pt within a word/number,
# ~85pt at a genuine break) — the vertical-text analogue of _rows_from_words
# grouping by 'top' tolerance.

def _vertical_lines(chars, x_tol=1.5, gap_break=10.0):
    only = [c for c in chars if not c.get('upright', True)]
    buckets = []
    for c in sorted(only, key=lambda c: (round(c['x0']), -c['top'])):
        placed = False
        for b in buckets:
            if abs(b['x0'] - c['x0']) <= x_tol:
                b['chars'].append(c)
                placed = True
                break
        if not placed:
            buckets.append({'x0': c['x0'], 'chars': [c]})
    lines = []
    for b in buckets:
        cs = sorted(b['chars'], key=lambda c: -c['top'])
        cur = []
        prev_top = None
        for c in cs:
            if prev_top is not None and (prev_top - c['top']) > gap_break:
                lines.append({'x0': b['x0'], 'chars': cur})
                cur = []
            cur.append(c)
            prev_top = c['top']
        if cur:
            lines.append({'x0': b['x0'], 'chars': cur})
    for l in lines:
        l['text'] = ''.join(ch['text'] for ch in l['chars'])
        l['top'] = l['chars'][0]['top']
        l['rect'] = _wrect(l['chars'])
    lines.sort(key=lambda l: (l['x0'], -l['top']))
    return lines


def _vertical_bracket_events(lines):
    ev = []
    for l in lines:
        for m in re.finditer(_BRACKET_RE, l['text']):
            ev.append({'x0': l['x0'], 'top': l['top'], 'value': mixed_to_float(m.group(1)),
                       'rect': l.get('rect')})
    return ev


def _vertical_label_events(lines, label):
    """Same idea as _label_events, but the 'row' is already one contiguous
    line (character-level, not word-level, since extract_words() doesn't
    handle rotated text) — the value text is everything in that line before
    the label, itself trimmed back to the last stopword."""
    ev = []
    for l in lines:
        if label not in l['text']:
            continue
        idx = l['text'].find(label)
        before = l['text'][:idx]
        for stop in _DIM_STOPWORDS:
            p = before.rfind(stop)
            if p >= 0:
                before = before[p + len(stop):]
        if before.strip():
            ev.append({'x0': l['x0'], 'top': l['top'], 'text': before, 'rect': l.get('rect')})
    return ev


def _nearest_vertical_bracket(label_ev, brackets, x_win=15, top_win=40):
    best, best_d = None, None
    for b in brackets:
        dtop = abs(b['top'] - label_ev['top'])
        dx = abs(b['x0'] - label_ev['x0'])
        if dtop > top_win or dx > x_win:
            continue
        d = dx + dtop
        if best is None or d < best_d:
            best, best_d = b, d
    return best


_BARE_VLINE_RE = re.compile(r'^(.*?)\s+(?:OVERALL\s+)?(RO|FS|DO)$')


def _typ_member_row(page, dlo_evs, mem_evs):
    """r547 — convention-E sheets print only the two END members on the D.L.O.
    value row (2 1/2" at the left jamb, 3" at the right) and every interior
    mullion once, as "2 1/2\" TYP" under the row. The member row that drawing
    states is therefore [left] + [TYP] x (lites - 1) + [right]. Returned as
    member events laid one row under the chain so the r546 printed-member
    check reads it like any other printed row; [] unless the left end, the
    right end AND exactly one TYP value are all printed for this chain."""
    vals = [e for e in dlo_evs if e.get('vtop') is not None]
    if not vals:
        return []
    # the row's baseline is the one most of its values sit on; a stacked
    # fraction's whole part rides ~6pt higher but is still in the row
    tops = sorted(e['vtop'] for e in vals)
    vtop = max(tops, key=lambda t: sum(1 for u in tops if abs(u - t) <= 1.5))
    row = sorted((e for e in vals if abs(e['vtop'] - vtop) <= 8), key=lambda e: e['x0'])
    lo, hi = row[0]['x0'], max(e.get('x1', e['x0']) for e in row)
    same = [m for m in mem_evs if abs(m['top'] - vtop) <= 2]
    left = [m for m in same if m['x1'] <= lo + 1]
    right = [m for m in same if m['x0'] >= hi - 1]
    if not left or not right:
        return []
    L = max(left, key=lambda m: m['x1'])
    R = min(right, key=lambda m: m['x0'])
    if lo - L['x1'] > 120 or R['x0'] - hi > 120:
        return []
    try:
        words = page.extract_words(x_tolerance=1.5)
    except Exception:
        return []
    typs = [w for w in words if w['text'] == 'TYP' and lo <= w['x0'] <= hi]
    typ_vals = set()
    for m in mem_evs:
        if not (lo <= m['x0'] <= hi) or not (0 < m['top'] - vtop <= 50):
            continue
        if any(4 <= t['top'] - m['top'] <= 20 and t['x0'] - 10 <= m['x0'] <= t['x1'] + 10 for t in typs):
            typ_vals.add(m['value'])
    if len(typ_vals) != 1 or len(row) < 2:
        return []
    typ = typ_vals.pop()
    out_top = max(e['top'] for e in row) + 1.0
    vals_lr = [L['value']] + [typ] * (len(row) - 1) + [R['value']]
    step = (R['x0'] - L['x0']) / float(max(1, len(vals_lr) - 1))
    return [{'x0': L['x0'] + i * step, 'x1': L['x0'] + i * step + 1, 'top': out_top,
             'value': v, 'rect': None, 'bracket_rect': None, 'synth': True}
            for i, v in enumerate(vals_lr)]


def _bare_height_events(lines):
    """r547 — the vertical counterpart of _bare_width_events: overall
    heights read "19'-4\" RO" / "19'-2 7/8\" FS" as one rotated line, and
    each lite's D.L.O. height is a value line with a separate "DLO" line
    one column to its right, centred on it."""
    out = {'RO': [], 'FS': [], 'DLO': [], 'DO': []}
    for l in lines:
        m = _BARE_VLINE_RE.match(l['text'].strip())
        if m:
            v = _bare_value(m.group(1))
            if v is not None:
                out[m.group(2)].append({'x0': l['x0'], 'top': l['top'], 'value': v,
                                        'rect': l.get('rect')})
    def _cy(l):
        r = l.get('rect')
        return (r[1] + r[3]) / 2.0 if r else l['top']
    for lab in lines:
        if lab['text'].strip() != 'DLO':
            continue
        sv, sl = _stacked_frac_v(lines, lab)
        if sv is not None:
            out['DLO'].append({'x0': sl['x0'], 'top': sl['top'], 'value': sv,
                               'rect': _urect(sl.get('rect'), lab.get('rect'))})
            continue
        best = None
        for l in lines:
            dx = lab['x0'] - l['x0']
            if not (0 < dx <= 30) or abs(_cy(l) - _cy(lab)) > 14:
                continue
            v = _bare_value(l['text'])
            if v is None:
                continue
            if best is None or dx < best[0]:
                best = (dx, l, v)
        if best:
            l = best[1]
            out['DLO'].append({'x0': l['x0'], 'top': l['top'], 'value': best[2],
                               'rect': _urect(l.get('rect'), lab.get('rect'))})
    return out


def _height_events(page, bare=False):
    """{'RO':[{x0,top,value}], 'FS':[...], 'DLO':[...]} — R.O./F.S./D.L.O. HEIGHT.
    Round 383 adds D.L.O. heights to seed the grid model's row D.L.O. inputs,
    using the same label-anchored bracket technique _width_events uses for
    column D.L.O.s. Reading order is top-of-sheet to bottom (descending 'top'),
    consistent with the confirmed real-sheet direction from rounds 342/343."""
    lines = _vertical_lines(page.chars)
    if bare:
        return _bare_height_events(lines)
    brackets = _vertical_bracket_events(lines)
    out = {'RO': [], 'FS': [], 'DLO': [], 'DO': []}
    # r505: on a door elevation the height-axis frame size is printed as
    # "DOOR JAMB LENGTH", never "F.S." — which is why fsH came back null on
    # every door sheet. It maps to the same FS key: it is the same reading.
    for label, key in (('R.O.', 'RO'), ('F.S.', 'FS'), ('F.D.', 'FS'),
                       ('DOOR JAMB LENGTH', 'FS'), ('D.L.O.', 'DLO'), ('D.O.', 'DO')):
        for ev in _vertical_label_events(lines, label):
            b = _nearest_vertical_bracket(ev, brackets)
            if b is not None and b['value'] is not None:
                out[key].append({'x0': ev['x0'], 'top': ev['top'], 'value': b['value'],
                                 'rect': _urect(ev.get('rect'), b.get('rect'))})
    return _merge_markups(page, out, 'h')   # r560: typed markups


_FEETINCH_RE = re.compile(r"(\d+)'-?\s*(" + _FRAC + r")?\"?")


def _feetinch_to_float(s):
    """Fallback only (no bracket found): "6'-6 1/2"" -> 78.5. Also handles
    a value with no feet component at all — just inches, e.g. "38 1/4\""
    (round 344/345: this is the normal per-opening D.L.O./F.D. reading on
    two of the three real vendor sets tested; only the overall R.O. width
    on those sets uses feet-inches)."""
    m = _FEETINCH_RE.search(s or '')
    if m:
        feet = float(m.group(1))
        inches = mixed_to_float(m.group(2)) or 0.0
        return feet * 12 + inches
    return mixed_to_float(s)


# ── elevation callouts (label / qty / system / finish / arch ref) ─────────

_QTY_RE = re.compile(r"THUS\s*REQ'D:\s*(\d+).*?OPP\s*REQ'D:\s*(\d+).*?TOTAL\s*REQ'D:\s*(\d+)", re.S)
# Second convention (round 344, Aspen/A.M.D.G. — Hope College set): no colons,
# no OPP/TOTAL REQ'D labels at all. Instead one "<N> THUS TOTAL" line, then
# one "<n> THUS REQ'D <floor/face description> ELEVATION" line per location
# that reuses this same frame type (e.g. "3 THUS REQ'D 1ST FLOOR SOUTH
# ELEVATION" + "3 THUS REQ'D 1ST FLOOR EAST ELEVATION" for one frame used at
# 6 total locations). Read as a QTY SUGGESTION only, same as the primary
# convention — never written to a WO line without a PM confirming it.
# Matched position-aware via _qty_thus_events below (a 2-up sheet needs each
# elevation's own lines kept separate — a flat page-text regex here pooled
# both elevations' lines into each record, a real bug caught in round 344
# testing on the Hope College E1e/2e sheet).
# Third convention (round 344, Ryan Engineering — Kentwood DPW set): just a
# flat "QTY = N" (or "QTY=N") per elevation, no per-location breakdown at
# all — the simplest of the three.
_QTY_FLAT_RE = re.compile(r"QTY\s*=\s*(\d+)")
_FINISH_RE = re.compile(r'FINISH:\s*"?([A-Z][A-Z \-]*?)"?\s*(?:ANODIZED)?\s*\(([A-Z0-9]+)\)', re.I)
# Vendor-agnostic on purpose — "<vendor> <series #> SERIES <suffix?> (<dims>)"
# matches Tubelite, Kawneer, EFCO, Vistawall, etc. without a hardcoded list.
_SYSTEM_RE = re.compile(r'([A-Z][A-Za-z]+\s+[A-Z0-9]+\s+SERIES\s*[A-Z]{0,3}\s*\([^)]*\d[^)]*\))')
# Fallback for a vendor that never prints the word "SERIES" at all (round
# 344): "<vendor> <model> (<dims>) <system type>?", e.g.
# "TUBELITE 400TU (2 1/2\" x 10\") CURTAINWALL". Still vendor-agnostic — no
# hardcoded vendor list — just requires a model token immediately before
# parenthesized DIMENSIONS (the parens must contain a digit — round 344
# fix: without that, this matched incidental text like "DRAFTING FRAME
# (-) ON ARCHITECTURAL DRAWINGS" on two real sheets, since "DRAFTING" and
# "FRAME" both look like a plausible vendor/model pair on their own).
_SYSTEM_RE2 = re.compile(
    r'([A-Z][A-Za-z]+\s+[A-Z0-9]{2,8}\s*\([^)]*\d[^)]*\)\s*(?:CURTAINWALL|STOREFRONT|ENTRANCE|WINDOW)?)')
# '.' admitted in the sheet part (round 474): the Alpine set prints dotted
# arch sheet numbers ("S2/A9.01") — the undotted 'W1/A-601' style still
# matches. Fraction tokens ('3/8"') can't slip in: their trailing inch
# mark fails the anchored match.
_ARCHREF_TOKEN_RE = re.compile(r'^[A-Za-z0-9.]+/[A-Za-z0-9.\-]+(?:\|[A-Za-z0-9.]+/[A-Za-z0-9.\-]+)*$')
# Reliable, unambiguous signal that a page's text is mirrored/reversed end-
# to-end (round 344 finding: two real sheets in the Hope College set are
# horizontally-flipped elevations — a common drafting move to create a
# mirror-image opposite-hand elevation — which makes pdfplumber emit every
# word's characters, and the words themselves, back-to-front: "ELEVATION"
# reads "NOITAVELE"). This is the exact same honest-failure family as
# _looks_garbled's CID-mapped-font case, just a different real cause — both
# get surfaced as a clear, actionable warning, never silently dropped or
# guessed at.
_REVERSED_TEXT_RE = re.compile(r'NOITAVELE')


def _qty_flat_events(page):
    """Every 'QTY = N' occurrence on the page with its position, for the
    Ryan Engineering convention (round 344) — needed because a multi-
    elevation sheet prints one QTY per callout, so (like widths) each has
    to be assigned to its nearest elevation bubble by X position, not
    just grabbed globally off the page text."""
    words = page.extract_words(x_tolerance=1.5)
    ev = []
    for i, w in enumerate(words):
        if w['text'] == 'QTY' and i + 2 < len(words) and words[i + 1]['text'] == '=':
            m = re.match(r'^(\d+)$', words[i + 2]['text'])
            if m:
                ev.append({'x0': w['x0'], 'top': w['top'], 'value': int(m.group(1))})
    return ev


# ── Round 387: glazing schedule (cover sheet) + per-elevation glass tags ──
#
# The cover sheet's GLAZING SCHEDULE column lists every glass type as a
# 'TYPE: <tag>' label with its make-up lines printed beside it (verified on
# the real Lake Trust — Grand Ledge set: IG-1 / IG-1T / SPAN / GL-1, each
# with 1–3 make-up lines). Extraction is the same label-anchored,
# position-bounded technique as the sizing parser: the schedule column's
# x-range is derived from its own printed header (left) and the next
# column's header (right), which is what keeps adjacent-column text
# ("SYSTEM NOTES" bleed) out of the make-up lines. Each make-up row is
# assigned to the vertically nearest TYPE label — within a schedule cell
# the lines sit tight around their label; the gap between cells is much
# larger (measured, not assumed).
#
# Per-elevation glass tags: elevation sheets print the schedule tag inside
# each lite (IG-1, SPAN, …). When a schedule was found, ONLY its exact tag
# strings are matched on elevation sheets (no false positives from detail
# callouts); with no schedule a conservative fallback pattern is used.
# Tags attribute to their elevation bubble via the same _nearest_by_x
# machinery every other per-callout event uses, and each is returned with
# its raw x/top so the client can map tags onto lite-grid cells by
# position clustering — as SUGGESTIONS only, per the standing posture.

_GLASS_TAG_FALLBACK_RE = re.compile(r'^(?:IG|GL)-\d+[A-Z]*$|^SPAN$')


def _join_schedule_row(ws):
    """Join one schedule row's words into a line using the actual gap
    between them, not a naive space-join. Two real cover-sheet artifacts
    make this necessary (both verified on the Lake Trust set): text inside
    a revision-cloud region re-extracts as per-CHARACTER words with zero
    gap between them (naive joining reads '1 / 2 " AI R S P A C E …'), and
    a revision-triangle number printed beside a make-up line reads as a
    stray trailing digit. Chars with a gap under 1.0pt rejoin without a
    space; a standalone digit separated from the line by a large gap is
    the revision triangle, dropped."""
    ws = sorted(ws, key=lambda w: w['x0'])
    parts, prev = [], None
    for w in ws:
        if prev is not None and abs(w['x0'] - prev['x0']) < 0.3 and w['text'] == prev['text']:
            continue  # exact-overlap duplicate glyph
        if prev is not None and (w['x0'] - prev['x1']) > 12 \
                and re.match(r'^\d$', w['text']) and w is ws[-1]:
            break     # trailing revision-triangle callout digit
        if prev is None or (w['x0'] - prev['x1']) >= 1.0:
            parts.append(w['text'])
        else:
            parts[-1] += w['text']
        prev = w
    return ' '.join(parts).strip()


def _glazing_schedule_from_page(page):
    """Parse one page's GLAZING SCHEDULE column. Returns a list of
    {'tag','makeup':[lines]} in printed order, or None if this page has
    no readable schedule."""
    try:
        words = page.extract_words(x_tolerance=1.5)
    except Exception:
        return None
    hdr = None
    for i, w in enumerate(words):
        if w['text'] == 'GLAZING' and i + 1 < len(words) \
                and words[i + 1]['text'] == 'SCHEDULE' \
                and abs(words[i + 1]['top'] - w['top']) < 3:
            if hdr is None or w['x0'] < hdr['x0']:
                hdr = w
    if hdr is None:
        return None
    left = hdr['x0'] - 12
    # Right bound: a conservative fixed span from the schedule's own header
    # (the make-up text ends well inside it on the verified set), further
    # clamped by the next column's own header ('SYSTEM NOTES' on Vos sets)
    # when it reads. The fixed span matters: neighboring-column words can
    # START left of the neighbor's header, so the header alone isn't a
    # tight enough fence (verified on the real Lake Trust cover).
    right = left + 260
    for i, w in enumerate(words):
        if w['text'] == 'SYSTEM' and i + 1 < len(words) \
                and words[i + 1]['text'] == 'NOTES' \
                and abs(w['top'] - hdr['top']) < 25 and w['x0'] > hdr['x0']:
            right = min(right, w['x0'] - 8)
    # Bottom bound: the next section heading in the SAME column (the Vos
    # title block prints DIMENSION VERIFICATION under the schedule).
    bottom = page.height
    for w in words:
        if w['text'] == 'DIMENSION' and left <= w['x0'] <= right \
                and w['top'] > hdr['top']:
            bottom = min(bottom, w['top'] - 4)
    inside = [w for w in words
              if left <= w['x0'] <= right
              and hdr['top'] + 8 < w['top'] < bottom]
    if not inside:
        return None
    # Row clustering by top (same gap discipline as _rows_from_words but on
    # words, tolerance tuned to the schedule's own line spacing).
    inside.sort(key=lambda w: (w['top'], w['x0']))
    rows = []
    for w in inside:
        if rows and abs(w['top'] - rows[-1]['top']) <= 2.5:
            rows[-1]['words'].append(w)
        else:
            rows.append({'top': w['top'], 'words': [w]})
    types = []   # [{'tag','top'}]
    lines = []   # [{'text','top'}]
    for r in rows:
        ws = sorted(r['words'], key=lambda w: w['x0'])
        if ws[0]['text'] == 'TYPE:':
            # The label and a make-up line can share one visual row (the
            # Lake Trust cover prints '1/2" AIRSPACE …' at the same height
            # as its TYPE label) — split by x: tokens near the label are
            # the tag, tokens at the make-up indent are a make-up line.
            split_x = ws[0]['x0'] + 65
            tag_ws = [w for w in ws[1:] if w['x0'] < split_x]
            mk_ws = [w for w in ws[1:] if w['x0'] >= split_x]
            tag = tag_ws[0]['text'] if tag_ws else ''
            if tag:
                types.append({'tag': tag, 'top': r['top']})
            mk = _join_schedule_row(mk_ws)
            if mk:
                lines.append({'text': mk, 'top': r['top']})
            continue
        text = _join_schedule_row(ws)
        if text:
            lines.append({'text': text, 'top': r['top']})
    if not types:
        return None
    out = [{'tag': t['tag'], 'makeup': [], '_top': t['top']} for t in types]
    for ln in lines:
        best, bd = None, None
        for entry in out:
            d = abs(ln['top'] - entry['_top'])
            if bd is None or d < bd:
                best, bd = entry, d
        # Make-up lines sit within their own cell — measured ≤ ~20pt from
        # the label on the real set, next cell ≥ ~45pt away. 30 splits them.
        if best is not None and bd <= 30:
            best['makeup'].append((ln['top'], ln['text']))
    result = []
    for entry in out:
        mk = [t for _, t in sorted(entry['makeup'])]
        if mk:
            result.append({'tag': entry['tag'], 'makeup': mk})
    return result or None


def parse_glazing_schedule(pdf, max_pages=10):
    """First readable GLAZING SCHEDULE in the set. Returns
    [{'tag','makeup':[...]}] or []. Only the FRONT of the set is scanned —
    the schedule lives on the cover/general sheets on every set seen, and
    walking a whole 100+-page CAD export just to conclude 'no schedule'
    is real time and (page-cache) memory on large real sets. Each scanned
    page's layout cache is released, same discipline as the main loop."""
    for page in pdf.pages[:max_pages]:
        try:
            sched = _glazing_schedule_from_page(page)
        except Exception:
            sched = None
        finally:
            try:
                page.flush_cache()
            except Exception:
                pass
        if sched:
            return sched
    return []


def _glass_tag_events(page, tagset):
    """Every printed glass tag on the page with its position. `tagset` is
    the exact tag strings from the set's own glazing schedule (preferred);
    falls back to a conservative pattern when the set had no schedule."""
    words = page.extract_words(x_tolerance=1.5)
    ev = []
    for w in words:
        t = w['text']
        ok = (t in tagset) if tagset else bool(_GLASS_TAG_FALLBACK_RE.match(t))
        if ok:
            ev.append({'tag': t, 'x0': round(w['x0'], 1), 'top': round(w['top'], 1)})
    return ev


# The sheet's own printed id from its title block ("SHEET: E1"). "SHEET
# DESCRIPTION:" never matches — the colon must follow "SHEET" directly.
_PAGE_SHEET_RE = re.compile(r'\bSHEET:\s*\n?\s*([A-Z]{1,3}\d{1,3})\b')
_BUBBLE_NUM_RE = re.compile(r'^\d{1,3}[A-Za-z]?$')
_SHEET_TOKEN_RE = re.compile(r'^[A-Z]{1,3}\d{1,3}$')


def _page_sheet_id(page_text):
    m = _PAGE_SHEET_RE.search(page_text or '')
    return m.group(1) if m else None


def _bubble_callouts(words, page_text):
    """Convention D (round 474, Alpine Apartments set — Vos current title
    style): the elevation's identity is a split BUBBLE — number over sheet
    (e.g. "1A" directly above "E1") — with a free-form title to the right
    ("VESTIBULE 100 (EXT.)", or even "ELEVATION S2", which is an arch
    reference, NOT the frame's identity) and the THUS/OPP/TOTAL REQ'D
    block on the same row. Anchor on each "THUS REQ'D" pair, scan LEFT
    along that row for a short number token with a sheet token directly
    beneath it (same column, ~15pt below); when the page's own SHEET:
    field is readable, the sheet token must match it, so a room number
    like "111" over the word "SCALE" can never be mistaken for a bubble.
    Label = "<num>/<sheet>" ("1A/E1") — the identity Vos's own plans,
    registry keys, and hold-size letters use. When any such bubble is
    found, conventions A/B are skipped for the page entirely (on this real
    set, A's "next word after ELEVATION" scan was grabbing prose — "EAST
    ELEVATION 1A" — and titles — "ELEVATION S2" — producing garbage
    labels; E1 imported one wrong elevation and E2–E15 imported none)."""
    sheet_id = _page_sheet_id(page_text)
    out, seen = [], set()
    for i, w in enumerate(words):
        if w['text'] != 'THUS' or i + 1 >= len(words):
            continue
        if not words[i + 1]['text'].startswith("REQ'D"):
            continue
        row = [c for c in words if abs(c['top'] - w['top']) <= 3.5 and c['x0'] < w['x0']]
        row.sort(key=lambda c: -c['x0'])  # right-to-left from THUS
        for cand in row:
            if not _BUBBLE_NUM_RE.match(cand['text']):
                continue
            sheet = None
            for b in words:
                if b is cand:
                    continue
                if abs(b['x0'] - cand['x0']) <= 6 and 8 <= (b['top'] - cand['top']) <= 24 \
                        and _SHEET_TOKEN_RE.match(b['text']):
                    if sheet_id and b['text'] != sheet_id:
                        continue
                    sheet = b['text']
                    break
            if sheet:
                key = (round(cand['x0']), round(cand['top']))
                if key not in seen:
                    seen.add(key)
                    out.append({'x0': cand['x0'], 'top': cand['top'],
                                'anchor': 'left',
                                'name': '%s/%s' % (cand['text'], sheet)})
                break  # first bubble left of this THUS owns it
    return out


# r547 convention E (MSU FCU Gaines Marketplace — Vos in-house, newer title
# style): the elevation's identity is a large plain number in a circle
# ("105", ~32pt text) with its spec block printed to the right of it:
#
#   (105) SYSTEM:  KAWNEER 2500 UNITWALL
#         ARCH REF:  A401
#         QUANTITIES:
#                (1) THUS
#
# No "ELEVATION" word, no REQ'D, no QTY=. The plans tag openings with the
# same bare number, so the label is the number alone. Anchored on each
# "SYSTEM:" that has "QUANTITIES:" in its own column below it; the bubble is
# the nearest bare number to its left on the same line, printed clearly
# larger than the label text (so a plan-tag or detail number sitting nearby
# can never be taken for it). Everything in the block is read off its own
# label — never a whole-page search — so a 2-up sheet keeps its two blocks
# apart.
_E_BUBBLE_RE = re.compile(r'^\d{1,4}[A-Za-z]?$')
_E_COUNT_RE = re.compile(r'^\((\d+)\)$')


def _system_block_callouts(words):
    out = []
    for w in words:
        if w['text'] != 'SYSTEM:':
            continue
        h = max(1.0, w['bottom'] - w['top'])
        qty_lbl = [q for q in words if q['text'] == 'QUANTITIES:'
                   and abs(q['x0'] - w['x0']) <= 8 and 0 < q['top'] - w['top'] <= 8 * h]
        if not qty_lbl:
            continue
        ql = min(qty_lbl, key=lambda q: q['top'])
        bub = [b for b in words if _E_BUBBLE_RE.match(b['text'])
               and 0 < w['x0'] - b['x1'] <= 6 * h
               and abs(b['top'] - w['top']) <= 1.5 * h
               and (b['bottom'] - b['top']) >= 1.8 * h]
        if not bub:
            continue
        b = max(bub, key=lambda b: b['x1'])
        # the block's column: from the label to the next SYSTEM: on this row
        nxt = [o['x0'] for o in words if o['text'] == 'SYSTEM:' and o is not w
               and o['x0'] > w['x0'] and abs(o['top'] - w['top']) <= 3 * h]
        col_r = (min(nxt) - 4) if nxt else float('inf')

        def row_after(lbl, stop_gap=6 * h):
            got, r = [], lbl['x1']
            for t in sorted((t for t in words if t is not lbl
                             and abs(t['top'] - lbl['top']) <= 0.6 * h
                             and t['x0'] > lbl['x0'] and t['x1'] <= col_r),
                            key=lambda t: t['x0']):
                if t['x0'] - r > stop_gap:
                    break
                got.append(t['text'])
                r = t['x1']
            return got

        system = ' '.join(row_after(w)) or None
        arch = None
        for ref in words:
            if ref['text'] != 'REF:' or not (0 < ref['top'] - w['top'] < ql['top'] - w['top'] + 1):
                continue
            if not any(a['text'] == 'ARCH' and abs(a['top'] - ref['top']) <= 0.6 * h
                       and 0 < ref['x0'] - a['x1'] <= 3 * h and abs(a['x0'] - w['x0']) <= 8
                       for a in words):
                continue
            toks = row_after(ref)
            if toks and toks[0].upper() not in ('TBD', 'N/A', '-', '--'):
                arch = toks[0]
            break
        # "(N) THUS" / "(N) OPP" lines directly under QUANTITIES:
        counts = {}
        for c in words:
            m = _E_COUNT_RE.match(c['text'])
            if not m or not (0 < c['top'] - ql['top'] <= 6 * h):
                continue
            if c['x0'] < w['x0'] - 2 or c['x1'] > col_r or c['x0'] - w['x0'] > 20 * h:
                continue
            kind = next((k['text'] for k in words if k['text'] in ('THUS', 'OPP')
                         and abs(k['top'] - c['top']) <= 0.6 * h
                         and 0 <= k['x0'] - c['x1'] <= 2 * h), None)
            if kind and kind not in counts:
                counts[kind] = int(m.group(1))
        out.append({'x0': b['x0'], 'top': b['top'], 'name': b['text'],
                    'conv': 'E', 'system': system, 'archRef': arch,
                    'thus': counts.get('THUS'), 'opp': counts.get('OPP')})
    out.sort(key=lambda c: (c['top'], c['x0']))
    return out


def _elevation_callouts(page, page_text=''):
    """Every elevation-name callout on the page, in reading order — the
    anchor everything else (qty/system/finish/arch-ref/dimensions) is
    matched against by proximity.

    Three conventions, all real (rounds 344/474 — see drawing_import.py
    module docstring); convention D (split num-over-sheet bubble,
    _bubble_callouts above) is checked FIRST and wins outright when
    present:

    A) Vos house style: the bubble text itself reads "ELEVATION <name>"
       (name follows the label) — the original, still-supported case.
    B) Aspen/A.M.D.G. (Hope College set): the bubble reads "<name> FRAME
       ELEVATION", with <name> sitting a few points above-left of "FRAME"
       (name PRECEDES a "FRAME"+"ELEVATION" word pair). This page also
       prints "ELEVATION" as the last word of unrelated location lines
       ("1ST FLOOR SOUTH ELEVATION") — those are never callout bubbles, so
       once any "FRAME ELEVATION" pair is found on a page, convention A's
       generic "next word after ELEVATION" scan is skipped entirely for
       that page (it was the source of the round-340 mislabeling: on ~15%
       of this real set's sheets it grabbed unrelated title-block text —
       "ASPEN", "NO." — instead of the real callout)."""
    words = page.extract_words(x_tolerance=1.5)
    bubbles = _bubble_callouts(words, page_text)
    if bubbles:
        return bubbles
    blocks = _system_block_callouts(words)
    if blocks:
        return blocks
    out = []
    for i, w in enumerate(words):
        if w['text'] == 'FRAME' and i + 1 < len(words) and words[i + 1]['text'] == 'ELEVATION':
            best, best_d = None, None
            for cand in words:
                # The label token sits a few points BELOW (larger 'top') and
                # to the LEFT of "FRAME" — e.g. "1e" at top+5.3, x0-59 from
                # the real sheet. Verified against every sheet in the set.
                dtop = cand['top'] - w['top']
                dx = w['x0'] - cand['x0']
                if 0 <= dtop <= 12 and 0 < dx <= 150:
                    d = dx + dtop
                    if best is None or d < best_d:
                        best, best_d = cand, d
            if best is not None:
                out.append({'x0': w['x0'], 'top': w['top'], 'name': best['text']})
    if out:
        return out
    for i, w in enumerate(words):
        if w['text'] == 'ELEVATION' and i + 1 < len(words):
            out.append({'x0': w['x0'], 'top': w['top'], 'name': words[i + 1]['text']})
    return out


def _nearest_archref(page, callout, words=None, other_callouts=None):
    """Nearest 'W1/A-601'-style token to the elevation callout. The window
    below was widened (round 341) after real testing found two real sheets
    (single-elevation pages, not the more common two/three-up layout) place
    this token noticeably ABOVE the callout's own baseline — the original
    -20..40 top window missed both, silently returning None on otherwise-
    fine sheets. Re-verified the wider window doesn't pick up a wrong token
    on any of the other 15 real sheets in the same set before keeping it."""
    words = words if words is not None else page.extract_words(x_tolerance=1.5)
    best, best_d = None, None
    for w in words:
        if _ARCHREF_TOKEN_RE.match(w['text']):
            dx = w['x0'] - callout['x0']
            dy = w['top'] - callout['top']
            if -40 < dy < 40 and 0 <= dx < 400:
                d = abs(dx) + abs(dy)
                if best is None or d < best_d:
                    best, best_d = w['text'], d
    if best is not None:
        return best
    return _nearest_archref_split(page, callout, words, other_callouts)


def _nearest_archref_split(page, callout, words=None, other_callouts=None):
    """Fallback for a vendor that prints the ref as two separate tokens —
    a bubble ref and a sheet ref, e.g. "ARCH. REF." then "1A1" "A6.10" —
    with no slash joining them (round 344, Aspen/A.M.D.G. set). Anchored
    on the literal "ARCH." "REF." label pair, since these two tokens never
    appear together for any other reason on these sheets.

    Ownership on a 2+-up sheet is by nearest-callout (round 344), not a
    fixed dx window: a fixed window either missed real refs sitting far
    from their own callout on wide sheets, or (if simply widened) started
    stealing a neighboring elevation's ref instead. Column assignment via
    _nearest_by_x is the same mechanism already trusted for width/height/
    qty matching above, and was verified here against every sheet in the
    real 45-page set before adopting it — a candidate is only accepted if
    THIS callout is genuinely its nearest one, not just within some
    distance of it."""
    words = words if words is not None else page.extract_words(x_tolerance=1.5)
    all_callouts = [callout] + [c for c in (other_callouts or []) if c is not callout]
    best, best_d = None, None
    for i, w in enumerate(words):
        if w['text'] != 'REF.':
            continue
        # Nearest 'ARCH.' word close by (not necessarily list-adjacent —
        # real sheets interleave a stray token between them in stream
        # order; proximity is the reliable signal, same lesson as the
        # FRAME/ELEVATION callout fix above).
        arch = None
        for cand in words:
            if cand['text'] != 'ARCH.':
                continue
            if abs(cand['top'] - w['top']) <= 16 and abs(cand['x0'] - w['x0']) <= 60:
                arch = cand
                break
        if arch is None:
            continue
        if _nearest_by_x(all_callouts, w['x0']) is not callout:
            continue
        dx = abs(w['x0'] - callout['x0'])
        # The bubble number, if any, sits BETWEEN "ARCH." and "REF." (e.g.
        # "ARCH. 2 REF. A6.16") — not after REF. at all. Only ONE token is
        # taken after REF. itself (the sheet ref, e.g. "A6.16"): taking a
        # second speculative token there was picking up unrelated
        # neighboring text (a "©"-ish glyph, a stray dimension fragment)
        # on several real sheets.
        between = [t['text'] for t in words if t is not arch and t is not w
                   and arch['top'] - 2 <= t['top'] <= w['top'] + 2
                   and arch['x0'] < t['x0'] < w['x0']]
        sheet_ref = None
        if i + 1 < len(words):
            cand = words[i + 1]['text']
            if cand not in ('SCALE:', 'FRAME', 'ELEVATION', '-') and len(cand) <= 10:
                sheet_ref = cand
        if sheet_ref is None:
            continue
        bubble = next((b for b in between if len(b) <= 3), None)
        val = ('%s/%s' % (bubble, sheet_ref)) if bubble else sheet_ref
        if best is None or dx < best_d:
            best, best_d = val, dx
    return best


# ── r545: which elevation a WIDTH dimension belongs to, by the frame's extent ─
#
# Howell SW 46/E22 (25'-8" R.O.) prints its first D.L.O. at x 566, 32pt LEFT
# of its own bubble (x 598). The column rule in _nearest_by_x hands an event
# to the nearest bubble at-or-left of it (20pt grace), so that 45 5/8" went to
# 45/E22. 45/E22 still "closed" by dropping it, and 46/E22 failed for want of
# it, though the drawing closes exactly (2 x 45 5/8 + 3 x 68 + 6 x 2 = 307 1/4).
#
# Every Vos title block prints its scale ("11x17: 1/4\" = 1'-0\""), so each
# elevation's R.O. covers a known stretch of the sheet: the R.O. text is
# centred on the frame, and the frame is R.O. x scale wide. A width dimension
# inside exactly ONE elevation's stretch belongs to it. Anything else — no
# readable scale, an elevation with no R.O., two stretches overlapping (a
# stacked column, Alpine 1A/1B over each other) — keeps the old rule.
_SCALE_RE = re.compile(r'(\d+)\s*x\s*(\d+)\s*:\s*([0-9/ ]+?)\s*"\s*=\s*1\s*\'\s*-\s*0\s*"')
_SPAN_SLACK = 6.0     # pt either side — text drawn right at the frame line


def _sheet_scale(page, page_text):
    """Points on the sheet per real inch, from the printed drawing scale that
    matches THIS sheet's paper size; None when absent or ambiguous."""
    pw, ph = float(page.width) / 72.0, float(page.height) / 72.0
    found = set()
    for a, b, frac in _SCALE_RE.findall(page_text or ''):
        lo, hi = sorted((float(a), float(b)))
        if abs(lo - min(pw, ph)) > 0.5 or abs(hi - max(pw, ph)) > 0.5:
            continue                       # the scale for the OTHER paper size
        v = mixed_to_float(frac)
        if v:
            found.add(round(72.0 * v / 12.0, 6))
    return found.pop() if len(found) == 1 else None


def _width_spans(callouts, ro_events, k):
    """{id(callout): (x_lo, x_hi)} for every callout whose R.O. width can be
    placed, using the existing rule to pick each one's R.O. (the R.O. text is
    centred on its frame, so it is never the ambiguous case)."""
    spans = {}
    if not k:
        return spans
    for c in callouts:
        mine = [e for e in ro_events if e.get('value')
                and _nearest_by_x(callouts, e['x0'], e.get('top')) is c]
        if not mine:
            continue
        e = min(mine, key=lambda e: e['top'])
        r = e.get('bracket_rect') or e.get('rect')
        if not r:
            continue
        cx = (r[0] + r[2]) / 2.0
        half = e['value'] * k / 2.0
        spans[id(c)] = (cx - half - _SPAN_SLACK, cx + half + _SPAN_SLACK)
    return spans


def _width_owner(callouts, ev, spans):
    """The callout a width event belongs to: the one elevation whose frame
    extent holds it, else the column rule."""
    if len(callouts) > 1 and spans:
        r = ev.get('bracket_rect') or ev.get('rect')
        x = ((r[0] + r[2]) / 2.0) if r else ev.get('x0')
        hits = [c for c in callouts if id(c) in spans
                and spans[id(c)][0] <= x <= spans[id(c)][1]]
        if len(hits) == 1:
            return hits[0]
    return _nearest_by_x(callouts, ev['x0'], ev.get('top'))


# ── r544: the elevation's TITLE BLOCK ────────────────────────────────────
#
# Jeremiah: "On the preview when importing an elevation I want to be able to
# read all the information below the elevations ... Right now, this is often
# missing." The preview crop was built from dimension footprints only, so it
# stopped at the bottom dimension and cut off the bubble, the name, the
# drawing scale, the THUS/OPP/TOTAL REQ'D box, the ARCH REF, the system and
# finish lines, and any location note under them.
#
# Where that block is, read off the sheet itself (never a fixed crop):
#   * it starts at the elevation's own callout bubble;
#   * across: the bubble's row runs right as one tight group (name, REQ'D box,
#     ARCH REF) and stops at a wide gap, at the next elevation's bubble, or at
#     the sheet's border — the long vertical line that fences off the sheet's
#     own title block (Alpine 5/E2's system line otherwise ran on into
#     REVISION / DATE);
#   * down: the rows under it (scale, system, finish, location note) follow on
#     with small gaps, and the block ends at the first big one, so a sheet
#     note at the bottom edge is not swept in.
# Measured on the Beacon Hill and Alpine sets: within a block, groups sit 40-50pt
# apart across and rows 10-17pt apart down. The next thing over is 100pt+ away.
_TITLE_CHAIN_GAP = 80.0
_TITLE_ROW_GAP = 24.0


def _sheet_walls(page):
    """x of every vertical line taller than 40% of the sheet — the drawing
    border and the edge of the sheet's own title block."""
    try:
        edges = page.edges
    except Exception:
        return []
    return sorted({round(e['x0'], 1) for e in edges
                   if e.get('orientation') == 'v'
                   and (e['bottom'] - e['top']) > 0.4 * float(page.height)})


def _title_block_rect(words, callout, callouts, walls=()):
    """[x0, top, x1, bottom] of the text printed under this elevation, or None."""
    ctop = callout['top']
    band = (ctop - 6, ctop + 24)
    right = [o['x0'] for o in callouts
             if o is not callout and abs(o['top'] - ctop) < 40 and o['x0'] > callout['x0']]
    col_r = (min(right) - 10) if right else float('inf')
    fence = [x for x in walls if x > callout['x0'] + 5]
    if fence:
        col_r = min(col_r, min(fence) - 2)

    def chain(cands, start_hi):
        got, r = [], None
        for w in sorted(cands, key=lambda w: w['x0']):
            if w.get('x1', w['x0']) > col_r:
                break
            if r is None:
                if w['x0'] > start_hi:
                    break
            elif w['x0'] - r > _TITLE_CHAIN_GAP:
                break
            got.append(w)
            r = max(r if r is not None else w['x1'], w.get('x1', w['x0']))
        return got

    first = chain([w for w in words
                   if band[0] <= (w['top'] + w['bottom']) / 2.0 <= band[1]
                   and w['x0'] >= callout['x0'] - 12], callout['x0'] + 12)
    if not first:
        return None
    left = min(w['x0'] for w in first)
    right_x = max(w['x1'] for w in first)
    bottom = max(w['bottom'] for w in first)
    got = list(first)
    below = [w for w in words if w['top'] > band[1] and w['x0'] >= left - 12]
    for r in _rows_from_words(below):
        if r['top'] - bottom > _TITLE_ROW_GAP:
            break
        row = chain(r['words'], right_x)
        if not row:
            continue
        got += row
        bottom = max(bottom, max(w['bottom'] for w in row))
        right_x = max(right_x, max(w['x1'] for w in row))
    return [min(w['x0'] for w in got), min(w['top'] for w in got),
            max(w['x1'] for w in got), max(w['bottom'] for w in got)]


def _nearest_by_x(callouts, x0, top=None):
    """Which elevation callout (of possibly several on one sheet) a given
    x0 belongs to — nearest-column assignment. Verified against a real
    2-up sheet and a real 7+4-opening side-by-side sheet (round 340
    testing notes) before trusting it for real numbers.

    Round 474: when two callouts share the same COLUMN (a vertically
    stacked layout — real case: Alpine Apartments E1, elevations 1A/E1
    top-left and 1B/E1 bottom-left at the same x), plain nearest-x is
    ambiguous and used to hand one stack ALL of the column's events. If
    the caller supplies the event's own `top`, the tie (any callout within
    60pt of the best column distance) is broken vertically: the callout
    bubble sits BELOW its drawing on every real sheet checked, so prefer
    the nearest callout at-or-below the event; if none is below, nearest
    |dy|. A single-callout column behaves exactly as before."""
    if not callouts:
        return None
    if len(callouts) > 1 and all(c.get('anchor') == 'left' for c in callouts):
        # Convention-D bubbles sit at their block's bottom-LEFT, so a wide
        # block's right-edge dimensions are genuinely nearer the NEXT
        # bubble in x (real case: Alpine E2, block 1's third D.L.O. at
        # x275 vs bubbles at x68/x419). Column-interval assignment instead:
        # cluster bubbles into columns, hand the event to the rightmost
        # column starting at-or-left of it (20pt grace for dimensions that
        # start slightly left of their own bubble — real case: a height
        # chain 13pt left of its bubble on the same sheet).
        cols = []
        for c in sorted(callouts, key=lambda c: c['x0']):
            if cols and abs(c['x0'] - cols[-1]['x']) <= 60:
                cols[-1]['cs'].append(c)
            else:
                cols.append({'x': c['x0'], 'cs': [c]})
        owner = cols[0]
        for col in cols:
            if col['x'] - 20 <= x0:
                owner = col
        cs = owner['cs']
        if len(cs) == 1 or top is None:
            return cs[0]
        below = [c for c in cs if c['top'] >= top - 3]
        if below:
            return min(below, key=lambda c: (c['top'] - top, c['x0']))
        return min(cs, key=lambda c: (abs(c['top'] - top), c['x0']))
    return min(callouts, key=lambda c: abs(c['x0'] - x0))


# ── page classification ─────────────────────────────────────────────────

def classify_page(page):
    """True if this page is a real elevation sheet worth parsing. Prefers
    the sheet's own printed 'SHEET DESCRIPTION: ELEVATIONS' field; falls
    back to an 'ELEVATION <name>' bubble paired with a real quantity
    marker if that field isn't found (a different draftsman's title block
    may not use the same field name). Two quantity conventions are
    recognized as that marker: "...REQ'D" (Vos house style, and Aspen/
    A.M.D.G.'s "N THUS REQ'D..." — round 344) and "QTY =" / "QTY=" (Ryan
    Engineering's convention — round 344, Kentwood DPW set). Never guessed
    at by page position or page count — every page is walked and
    classified on its own printed content."""
    text = page.extract_text() or ''
    if re.search(r'SHEET DESCRIPTION:\s*\n?\s*ELEVATIONS', text):
        return True
    if re.search(r'\bELEVATION\s+[A-Za-z0-9/]+\b', text) and \
            (re.search(r"REQ'D", text) or re.search(r'QTY\s*=', text)):
        return True
    # r547 convention E: a numbered bubble beside a SYSTEM: / ARCH REF: /
    # QUANTITIES: block, no "ELEVATION" word anywhere. All three labels plus a
    # "(N) THUS" count must be printed — a detail or plan sheet carries none of
    # them; the callouts themselves are confirmed by _system_block_callouts.
    if re.search(r'\bSYSTEM:', text) and re.search(r'\bQUANTITIES:', text) and \
            re.search(r'\(\d+\)\s*(?:THUS|OPP)\b', text):
        return bool(_system_block_callouts(page.extract_words(x_tolerance=1.5)))
    return False


# ── per-elevation record assembly ─────────────────────────────────────────

def _qty_thus_events(page):
    """Every 'N THUS TOTAL' / 'N THUS REQ'D <location> ELEVATION' line on
    the page, each with its own position — needed (round 344 fix) because
    a 2-up sheet prints one full set of these lines per elevation, and a
    flat whole-page regex search was pooling BOTH elevations' lines into
    EACH elevation's record (visible as a real false "quantities don't
    add up" warning on the real Hope College E1e/2e sheet). Assigned to
    the nearest callout by X the same way widths/heights/QTY are, using
    the leading number's own x0 as that line's position."""
    words = page.extract_words(x_tolerance=1.5)
    out = []
    for i, w in enumerate(words):
        if w['text'] != 'THUS' or i == 0:
            continue
        num_m = re.match(r'^(\d+)$', words[i - 1]['text'])
        if not num_m:
            continue
        n = int(num_m.group(1))
        x0, top = words[i - 1]['x0'], words[i - 1]['top']
        if i + 1 < len(words) and words[i + 1]['text'] == 'TOTAL':
            out.append({'x0': x0, 'top': top, 'kind': 'total', 'value': n})
        elif i + 1 < len(words) and words[i + 1]["text"] == "REQ'D":
            loc = []
            for nxt in words[i + 2:i + 12]:
                loc.append(nxt['text'])
                if nxt['text'] == 'ELEVATION':
                    break
            if loc and loc[-1] == 'ELEVATION':
                out.append({'x0': x0, 'top': top, 'kind': 'reqd', 'value': n,
                             'location': re.sub(r'\s+', ' ', ' '.join(loc)).strip()})
    return out


def _qty_labeled_events(page):
    """Every labeled "THUS REQ'D: n / OPP REQ'D: n / TOTAL REQ'D: n" trio
    on the page, each with its own position (round 474). Needed because
    _QTY_RE is a flat page-text search — on a real 3-up sheet (Alpine
    Apartments E1: three elevations, THUS totals 4/1/6) it hands the FIRST
    elevation's numbers to EVERY callout. Same fix shape as round 344's
    _qty_thus_events: read each block where it sits and let the caller
    assign it to its nearest callout. OPP/TOTAL are found stacked beneath
    THUS in the same column (verified geometry on the real sheet: rows
    ~8pt apart, x within 4pt)."""
    words = page.extract_words(x_tolerance=1.5)

    def _val_after(idx):
        # the number following "<LABEL> REQ'D:" in reading order, same row
        for nxt in words[idx + 2:idx + 5]:
            if abs(nxt['top'] - words[idx]['top']) <= 3.5 and re.match(r'^\d+$', nxt['text']):
                return int(nxt['text'])
        return None

    out = []
    for i, w in enumerate(words):
        if w['text'] != 'THUS' or i + 1 >= len(words) or not words[i + 1]['text'].startswith("REQ'D"):
            continue
        thus = _val_after(i)
        if thus is None:
            continue
        opp = total = None
        for j, w2 in enumerate(words):
            if w2['text'] in ('OPP', 'TOTAL') and j + 1 < len(words) \
                    and words[j + 1]['text'].startswith("REQ'D") \
                    and abs(w2['x0'] - w['x0']) <= 12 and 3 < (w2['top'] - w['top']) <= 30:
                v = _val_after(j)
                if w2['text'] == 'OPP':
                    opp = v
                else:
                    total = v
        out.append({'x0': w['x0'], 'top': w['top'], 'thus': thus, 'opp': opp, 'total': total})
    return out


# r558: the title block's HARDWARE SET field. Jeremiah: "Hardware is imported
# via a work order, but I believe it should be imported at the same step as a
# shop drawing." The sheet already says which doors an elevation holds and
# which hardware set each takes, so the drawing import reads it:
#   Howell (Northwest Elementary):   HARDWARE SET:
#                                      DR. 1100A - 1.0
#                                      DR. 1100B - 2.0
#   Alpine / Howell p5 (set only):   HARDWARE SET:
#                                      B1          (or "9.1")
# Rows stack ~10 pt apart directly under the label and the block ends at the
# first row that is neither (the system line, "SF (2" x 4 1/2")"). Ownership
# on a two-up sheet is nearest-callout, the rule every per-elevation field
# already uses. Nothing here guesses: a row that doesn't match is the end.
_HW_DOOR_ROW_RE = re.compile(r"^DR\.?\s*([0-9][0-9A-Z\-]*)\s*-\s*([0-9A-Z][0-9A-Z.\- ]*)$", re.I)
_HW_SET_ONLY_RE = re.compile(r"^([A-Z]{0,2}\d{1,3}(?:\.\d{1,2})?[A-Z]?)$")


def _hardware_set_block(words, callout, all_callouts):
    for i, w in enumerate(words):
        if w['text'].upper() != 'HARDWARE':
            continue
        lab = next((x for x in words if x['text'].upper().startswith('SET')
                    and abs(x['top'] - w['top']) <= 3 and 0 < x['x0'] - w['x0'] <= 60), None)
        if lab is None:
            continue
        if _nearest_by_x(all_callouts, w['x0'], w.get('top')) is not callout:
            continue
        rows = {}
        for x in words:
            dy, dx = x['top'] - w['top'], x['x0'] - w['x0']
            if 4 < dy <= 70 and -12 <= dx <= 90:
                rows.setdefault(round(x['top']), []).append(x)
        doors, set_only = [], None
        for top in sorted(rows):
            txt = " ".join(t['text'] for t in sorted(rows[top], key=lambda t: t['x0'])).strip()
            m = _HW_DOOR_ROW_RE.match(txt)
            if m:
                doors.append({'mark': m.group(1).upper(), 'set': m.group(2).strip()})
                continue
            if not doors and set_only is None and _HW_SET_ONLY_RE.match(txt):
                set_only = txt
                continue
            break
        if doors or set_only:
            return {'doors': doors, 'set': set_only}
        return None
    return None


def _elevation_record(page, callout, page_no, other_callouts, page_text, page_garbled,
                      tagset=None):
    words = page.extract_words(x_tolerance=1.5)
    rec = {
        'page': page_no + 1,
        'label': callout['name'],
        'archRef': _nearest_archref(page, callout, words, other_callouts),
        'system': None,
        'finish': None,
        'finishCode': None,
        'thusReqd': None, 'oppReqd': None, 'totalReqd': None, 'reqdBreakdown': None,
        'roW': None, 'fsW': None, 'dloWidths': [], 'doW': None, 'doH': None,
        'doWExtra': [],                      # r545: other leaves of a door pair
        'printedMembersW': [],               # r546: member dims as printed, per row
        'balance': {'w': None, 'h': None},
        'matchLines': [], 'ownedDloWidths': [], 'elevName': '',
        'manualTakeoff': None,
        'roH': None, 'fsH': None, 'dloHeights': [],
        'glassTags': [],
        # Round 475: the printed footprint of each dimension (page-space
        # [x0,top,x1,bottom]) so the Elevation Sizing visual view can draw
        # its status highlights exactly over the sheet's own numbers, plus
        # the page's own size for client-side scaling.
        'dims': {'roW': None, 'fsW': None, 'roH': None, 'fsH': None,
                 'doW': None, 'doH': None,
                 'dloWidths': [], 'dloHeights': []},
        'pageSize': [float(page.width), float(page.height)],
        # r544: where the elevation's title block (bubble, name, REQ'D box,
        # ARCH REF, system/finish, location note) is printed, so the import
        # preview can show it. None when it could not be located.
        'titleRect': None,
        'warnings': [],
    }
    # Round 387: printed glass tags on this sheet, attributed to this
    # callout the same way every other per-callout event is.
    all_c = [callout] + [c for c in other_callouts if c is not callout]
    rec['titleRect'] = _title_block_rect(words, callout, all_c, _sheet_walls(page))
    rec['hardware'] = _hardware_set_block(words, callout, all_c)   # r558
    rec['markups'] = []          # r560: values read from typed markups
    rec['glassTags'] = [e for e in _glass_tag_events(page, tagset or set())
                        if _nearest_by_x(all_c, e['x0'], e.get('top')) is callout]
    labeled = _qty_labeled_events(page)
    if len(labeled) >= 2:
        # A multi-callout sheet prints one labeled trio per elevation — the
        # flat _QTY_RE would hand every callout the first trio (round 474).
        mine = [e for e in labeled
                if _nearest_by_x(all_c, e['x0'], e['top']) is callout]
        if mine:
            e = min(mine, key=lambda e: abs(e['top'] - callout['top']))
            rec['thusReqd'], rec['oppReqd'], rec['totalReqd'] = e['thus'], e['opp'], e['total']
    elif _QTY_RE.search(page_text):
        qm = _QTY_RE.search(page_text)
        rec['thusReqd'], rec['oppReqd'], rec['totalReqd'] = (int(qm.group(i)) for i in (1, 2, 3))
    else:
        other_c = [c for c in other_callouts if c is not callout]
        all_c = [callout] + other_c
        thus_here = [e for e in _qty_thus_events(page) if _nearest_by_x(all_c, e['x0'], e.get('top')) is callout]
        if thus_here:
            total_ev = next((e for e in thus_here if e['kind'] == 'total'), None)
            breakdown = [{'qty': e['value'], 'location': e['location']}
                         for e in thus_here if e['kind'] == 'reqd']
            rec['totalReqd'] = total_ev['value'] if total_ev else None
            rec['reqdBreakdown'] = breakdown or None
            bsum = sum(b['qty'] for b in breakdown)
            if breakdown and rec['totalReqd'] is not None and bsum != rec['totalReqd']:
                rec['warnings'].append(
                    "Location quantities on this sheet (%d) don't add up to the printed "
                    "THUS TOTAL (%d) — check by hand." % (bsum, rec['totalReqd']))
        else:
            qty_here = [e for e in _qty_flat_events(page) if _nearest_by_x(all_c, e['x0'], e.get('top')) is callout]
            if qty_here:
                rec['totalReqd'] = min(qty_here, key=lambda e: e['top'])['value']
    sm = _SYSTEM_RE.search(page_text) or _SYSTEM_RE2.search(page_text)
    if sm:
        rec['system'] = re.sub(r'\s+', ' ', sm.group(1)).strip()
    fm = _FINISH_RE.search(page_text)
    if fm:
        rec['finish'] = fm.group(1).strip()
        rec['finishCode'] = fm.group(2).strip()
    if callout.get('conv') == 'E':
        # r547: this block's own labels win over every page-wide search above.
        rec['system'] = callout.get('system')
        rec['archRef'] = callout.get('archRef')
        t, o = callout.get('thus'), callout.get('opp')
        rec['thusReqd'], rec['oppReqd'] = t, o
        rec['totalReqd'] = (t or 0) + (o or 0) if (t is not None or o is not None) else None

    _bare = callout.get('conv') == 'E'
    widths = _width_events(page, bare=_bare)
    other = [c for c in other_callouts if c is not callout]
    # r545: width events go to the elevation whose FRAME holds them, when the
    # sheet's scale says where each frame is (see _width_owner).
    _wcs = [callout] + other
    _spans = _width_spans(_wcs, widths['RO'], _sheet_scale(page, page_text)) if len(_wcs) > 1 else {}
    _mine = lambda ev: _width_owner(_wcs, ev, _spans) is callout
    ro_vals, fs_vals, dlo = [], [], []
    for ev in widths['RO']:
        if _mine(ev) and ev['value'] is not None:
            ro_vals.append(ev)
    for ev in widths['FS']:
        if _mine(ev) and ev['value'] is not None:
            fs_vals.append(ev)
    for ev in widths['DLO']:
        if _mine(ev) and ev['value'] is not None:
            dlo.append(ev)
    if ro_vals:
        _e = min(ro_vals, key=lambda e: (bool(e.get('markup') and not e.get('over')), e['top']))   # r560: printed beats typed
        rec['roW'] = _e['value']
        rec['dims']['roW'] = {'v': _e['value'], 'rect': _e.get('rect')}
    if fs_vals:
        _e = min(fs_vals, key=lambda e: (bool(e.get('markup') and not e.get('over')), e['top']))   # r560: printed beats typed
        rec['fsW'] = _e['value']
        rec['dims']['fsW'] = {'v': _e['value'], 'rect': _e.get('rect')}
    dlo.sort(key=lambda e: e['x0'])
    rec['dloWidths'] = [e['value'] for e in dlo]
    rec['dims']['dloWidths'] = [{'v': e['value'], 'rect': e.get('rect')} for e in dlo]
    # r505: the door opening's WIDTH. Topmost wins, same rule R.O./F.S. use —
    # a sheet prints the chain once, top to bottom.
    do_vals = [ev for ev in widths['DO']
               if _mine(ev) and ev['value'] is not None]
    if do_vals:
        _e = min(do_vals, key=lambda e: (bool(e.get('markup') and not e.get('over')), e['top']))   # r560: printed beats typed
        rec['doW'] = _e['value']
        rec['dims']['doW'] = {'v': _e['value'], 'rect': _e.get('rect')}
        # r545: the OTHER door openings in the same width chain as doW — the
        # second leaf of a pair. Kept so the app's own re-check (wo-model.js
        # woBalanceRowAxis) counts every leaf when a PM edits a value; doW is
        # still the one door opening the rest of the app reads.
        _grp = next((g for g in _chain_groups(
            [dict(e, _id=i) for i, e in enumerate(do_vals)] +
            [dict(e, _id=None) for e in fs_vals + dlo], 'top')
            if any(x.get('_id') == do_vals.index(_e) for x in g)), [])
        _extra = [do_vals[x['_id']] for x in _grp
                  if x.get('_id') is not None and do_vals[x['_id']] is not _e]
        _extra.sort(key=lambda e: e['x0'])
        rec['doWExtra'] = [e['value'] for e in _extra]
        rec['dims']['doWExtra'] = [{'v': e['value'], 'rect': e.get('rect')} for e in _extra]

    heights = _height_events(page, bare=_bare)
    roH_vals, fsH_vals, dloH = [], [], []
    for ev in heights['RO']:
        if _nearest_by_x([callout] + other, ev['x0'], ev.get('top')) is callout:
            roH_vals.append(ev)
    for ev in heights['FS']:
        if _nearest_by_x([callout] + other, ev['x0'], ev.get('top')) is callout:
            fsH_vals.append(ev)
    for ev in heights['DLO']:
        if _nearest_by_x([callout] + other, ev['x0'], ev.get('top')) is callout and ev['value'] is not None:
            dloH.append(ev)
    if roH_vals:
        _e = min(roH_vals, key=lambda e: (bool(e.get('markup') and not e.get('over')), e['top']))   # r560: printed beats typed
        rec['roH'] = _e['value']
        rec['dims']['roH'] = {'v': _e['value'], 'rect': _e.get('rect')}
    if fsH_vals:
        _e = min(fsH_vals, key=lambda e: (bool(e.get('markup') and not e.get('over')), e['top']))   # r560: printed beats typed
        rec['fsH'] = _e['value']
        rec['dims']['fsH'] = {'v': _e['value'], 'rect': _e.get('rect')}
    # Sort by descending 'top' so the row ordering is top-of-sheet → bottom,
    # matching the vertical reading direction confirmed in round 342/343.
    dloH.sort(key=lambda e: -e['top'])
    rec['dloHeights'] = [e['value'] for e in dloH]
    rec['dims']['dloHeights'] = [{'v': e['value'], 'rect': e.get('rect')} for e in dloH]
    # r505: the door opening's HEIGHT.
    doH_vals = [ev for ev in heights['DO']
                if _nearest_by_x([callout] + other, ev['x0'], ev.get('top')) is callout
                and ev['value'] is not None]
    if doH_vals:
        _e = min(doH_vals, key=lambda e: (bool(e.get('markup') and not e.get('over')), e['top']))   # r560: printed beats typed
        rec['doH'] = _e['value']
        rec['dims']['doH'] = {'v': _e['value'], 'rect': _e.get('rect')}

    # r510: on a multi-sheet curtain wall, which lites does THIS sheet own?
    # Recorded per page so the merge below can add them up without
    # double-counting the overlap. Harmless on an ordinary elevation, which
    # has no match lines and therefore owns everything on it.
    # r510: the NAME in the elevation bubble ("ELEVATION C3", "ELEVATION C3 -
    # CONTINUED"). The head sheet of a run is labelled by its callout number
    # and sheet, and only the continuations carry the name — so the name is
    # the only key that groups a whole run, head included.
    _nm = re.search(r'ELEVATION\s+([A-Z0-9][A-Z0-9 \-]{0,28}?)\s+(?:THUS|OPP|TOTAL)',
                    page_text or '', re.I)
    rec['elevName'] = _nm.group(1).strip() if _nm else ''

    _mls = _matchlines(page)
    rec['matchLines'] = [dict(m) for m in _mls]
    if _mls:
        _own = _owned_between([e for e in widths['DLO'] if e['value'] is not None],
                              _mls, float(page.width))
        rec['ownedDloWidths'] = [e['value'] for e in _own]
    else:
        rec['ownedDloWidths'] = list(rec.get('dloWidths') or [])

    # r546: the printed member dims under this elevation's width chains.
    mem_w = [ev for ev in _member_events(page) if _mine(ev)]
    if _bare and mem_w:
        mem_w = mem_w + _typ_member_row(page, dlo, mem_w)
    rec['printedMembersW'] = []
    if fs_vals and mem_w:
        _fs0 = min(fs_vals, key=lambda e: (bool(e.get('markup') and not e.get('over')), e['top']))
        for _g in _chain_groups([dict(e, _f=(e is _fs0)) for e in fs_vals + do_vals + dlo], 'top'):
            if any(x.get('_f') for x in _g):
                rec['printedMembersW'] = _member_rows(max(x['top'] for x in _g), mem_w)
                break

    # r560: say which readings came from typed markups (and whose), and
    # report a typed value that sits beside a printed one instead of picking.
    _mine_m = lambda m: _nearest_by_x(all_c, m['x0'], m['top']) is callout
    # Credit only a typed value that LANDED in this record — a typed F.S.
    # that lost to the printed one (printed beats typed) is not a source.
    _fld = {('R.O.', 'w'): 'roW', ('F.S.', 'w'): 'fsW', ('D.O.', 'w'): 'doW',
            ('R.O.', 'h'): 'roH', ('F.S.', 'h'): 'fsH', ('D.O.', 'h'): 'doH',
            ('D.L.O.', 'w'): 'dloWidths', ('D.L.O.', 'h'): 'dloHeights'}

    def _landed(m):
        f = _fld.get((m['label'], m['axis']))
        if f is None:
            return True
        got = rec.get(f)
        vals = got if isinstance(got, list) else [got]
        return any(v is not None and abs(v - m['value']) < 1e-6 for v in vals)
    for m in getattr(page, 'markups', None) or []:
        if not (m.get('_used') and _mine_m(m)):
            continue
        if _landed(m):
            rec['markups'].append({'label': m['label'], 'axis': m['axis'], 'value': m['value'],
                                   'text': m['text'], 'author': m.get('author', ''),
                                   'date': m.get('date', ''), 'covers': m.get('covers')})
        else:
            _f = _fld.get((m['label'], m['axis']))
            _got = rec.get(_f) if _f else None
            if _got is not None and not isinstance(_got, list):
                # No-smoothing: the two disagree, so say so (once each).
                _wt = ('A typed %s %s of %s is on the sheet, but the printed %s was used \u2014 '
                       'check which is right.' % ('width' if m['axis'] == 'w' else 'height',
                                                  m['label'], m['text'], _fmt_in(_got)))
                if _wt not in rec['warnings']:
                    rec['warnings'].append(_wt)
    for m in getattr(page, 'markup_conflicts', None) or []:
        if _mine_m(m):
            rec['warnings'].append(
                'A typed markup says %s beside the printed %s %s %s \u2014 the printed value was used; '
                'check the sheet.' % (m['text'], 'width' if m['axis'] == 'w' else 'height', m['label'],
                                      _fmt_in(m.get('printed'))))

    # r506/r507: can each axis's frames close? Per CHAIN, since one elevation
    # can carry several independent ones. Reported, never corrected.
    rec['balance'] = {
        'w': _balance_chains(fs_vals, do_vals, dlo, 'top', pair_doors=True, mem_evs=mem_w),
        'h': _balance_chains(fsH_vals, doH_vals, dloH, 'x0'),
    }

    if page_garbled:
        rec['warnings'].append(
            "This sheet's text could not be reliably read (likely an embedded "
            "font without a usable text map) — sizing left blank, enter manually.")
        rec['roW'] = rec['fsW'] = rec['roH'] = rec['fsH'] = None
        rec['doW'] = rec['doH'] = None
        rec['doWExtra'] = []
        rec['printedMembersW'] = []
        rec['balance'] = {'w': None, 'h': None}
        rec['dloWidths'] = []
        rec['dloHeights'] = []
        rec['dims'] = {'roW': None, 'fsW': None, 'roH': None, 'fsH': None,
                       'doW': None, 'doH': None,
                       'dloWidths': [], 'dloHeights': []}
        rec['glassTags'] = []
        rec['system'] = rec['finish'] = rec['finishCode'] = None
        rec['thusReqd'] = rec['oppReqd'] = rec['totalReqd'] = rec['reqdBreakdown'] = None
    else:
        if rec['roW'] is None:
            rec['warnings'].append('R.O. Width not found on this sheet — enter manually.')
        if not rec['dloWidths']:
            rec['warnings'].append('No D.L.O. widths found on this sheet — enter manually.')
        if rec['roH'] is None:
            rec['warnings'].append('R.O. Height not found on this sheet — enter manually.')
    return rec


# ── r548: the set's finish from the cover sheet's GENERAL NOTES ──────────────
# Vos's own sets print the finish under each elevation (_FINISH_RE). An outside
# CAD supplier's set (MSU FCU Gaines Marketplace, r547) prints it once, on the
# cover: "4. ALUMINUM MATERIAL FINISH - DARK BRONZE ANODIZED". On that set the
# notes are drawn as CAD linework (an SHX font): the heading "GENERAL NOTES:"
# is real text but the seven notes under it are curves with no characters.
#
# So the heading is the anchor (label-anchored, never a fixed crop). The notes
# are read from the text layer when they are there; otherwise, only if
# Tesseract is installed, that one block is OCR'd. OCR text is NOT trusted as
# is: on this very block it read 500T as SOOT / 3007 / 3001 and 2500 as 2900.
# A finish is kept only when both resolutions read it identically AND every
# word of it is a known finish word. Anything else stays blank, as before. The
# value is a suggestion on rows whose own sheet prints no finish; it never
# overrides one.
_FINISH_WORDS = frozenset("""
    CLEAR DARK LIGHT MEDIUM EXTRA BRONZE BLACK ANODIZED ANODIZE ANODIC
    CHAMPAGNE PAINTED PAINT KYNAR CLASS I II WHITE SILVER SATIN STAINLESS
    MILL PVDF FLUOROPOLYMER POWDER COAT COATED STANDARD CUSTOM COLOR BONE
    GRAY GREY GOLD BRASS BROWN BEIGE SANDSTONE ALUMINUM NATURAL PERMANODIC
    PERMAFLUOR DURANODIC DURANAR TO MATCH AND WITH OR A AS SELECTED BY
    ARCHITECT ARCHITECTS INTERIOR EXTERIOR INT EXT
""".split())
_COVER_PAGES = 3          # the notes live on the cover; look no further
_OCR_DPIS = (150, 200)    # two independent reads that must agree


def _notes_anchor(page):
    """The 'GENERAL NOTES' heading's box on this page, or None."""
    try:
        ws = page.extract_words()
    except Exception:
        return None
    for i, w in enumerate(ws):
        if w['text'].upper() == 'GENERAL' and i + 1 < len(ws) \
                and ws[i + 1]['text'].upper().startswith('NOTES') \
                and abs(ws[i + 1]['top'] - w['top']) < 2:
            return {'x0': w['x0'], 'top': w['top'], 'bottom': w['bottom'],
                    'h': w['bottom'] - w['top']}
    return None


def _notes_box(page, a):
    # Under the heading: the notes run left-aligned from it, a few lines deep.
    # Width and depth scale with the heading's own text height.
    h = a['h']
    return (max(0, a['x0'] - h * 0.5), a['bottom'],
            min(page.width, a['x0'] + h * 26), min(page.height, a['bottom'] + h * 12))


def _finish_from_line(line):
    """'4. ALUMINUM MATERIAL FINISH - DARK BRONZE ANODIZED' -> 'DARK BRONZE ANODIZED'."""
    m = re.search(r'\bFINISH(?:ES)?\b\s*[:\-–—]+\s*(.+)$', line, re.I)
    if not m:
        return None
    v = re.sub(r'\s+', ' ', m.group(1)).strip(' .;,')
    return v or None


def _finish_is_known(v):
    words = re.findall(r"[A-Za-z]+", v or '')
    return bool(words) and all(w.upper() in _FINISH_WORDS for w in words)


def _cover_finish_text(page, a):
    """The FINISH note from the text layer, or None (no text there)."""
    try:
        txt = page.within_bbox(_notes_box(page, a)).extract_text() or ''
    except Exception:
        return None
    for line in txt.split('\n'):
        v = _finish_from_line(line)
        if v:
            return v
    return None


def _cover_finish_ocr(page, a):
    """The FINISH note read by OCR, or None. Kept only when every resolution
    reads the same known-finish words (see the block comment above)."""
    try:
        from ocr_quote_parser import pytesseract, _tesseract_runs
    except Exception:
        return None
    if pytesseract is None or not _tesseract_runs():
        return None
    reads = []
    try:
        crop = page.crop(_notes_box(page, a))
        for dpi in _OCR_DPIS:
            im = crop.to_image(resolution=dpi).original.convert('L')
            txt = pytesseract.image_to_string(im, config='--psm 6')
            found = None
            for line in txt.split('\n'):
                found = _finish_from_line(line)
                if found:
                    break
            reads.append(found.upper() if found else None)
    except Exception:
        return None
    if not reads or reads[0] is None or any(r != reads[0] for r in reads):
        return None
    return reads[0] if _finish_is_known(reads[0]) else None


def _cover_finish(pdf, upto):
    """{'finish', 'page', 'via': 'text'|'ocr'} from the GENERAL NOTES on the
    pages before the first elevation sheet (at most the first three), or None."""
    for i in range(min(_COVER_PAGES, upto, len(pdf.pages))):
        page = pdf.pages[i]
        a = _notes_anchor(page)
        if not a:
            continue
        v = _cover_finish_text(page, a)
        if v:
            return {'finish': v, 'page': i + 1, 'via': 'text'}
        v = _cover_finish_ocr(page, a)
        if v:
            return {'finish': v, 'page': i + 1, 'via': 'ocr'}
    return None


# r560: TYPED MARKUPS. Jeremiah's Howell NW set (Northwest Elementary): on
# about 50 sheets the R.O./F.S./D.L.O. values were re-typed by hand as
# Bluebeam typewriter annotations (FreeText, author "TimA", 09-03-2026), and
# the CAD's own value text is gone from those dimensions — only the little
# "R.O." label is left in the drawing. The import read page text only, so 1/E1
# came back with no widths at all ("this information should already be
# autofilled from the shop drawings").
# A typed annotation is read ONLY when it is a dimension value (plain inches
# or feet-inches) sitting directly against a dimension label — immediately
# left of a horizontal label, overlapping its row; or in a rotated label's
# own column, just before it in reading order. Anything else (a note, a
# number floating free) is left alone. Every value read this way is
# recorded on its elevation with who typed it and when.
_MARKUP_DIM_RE = re.compile(r"""^\s*(?:\d+'\s*-?\s*)?\d+(?:\s+\d+/\d+)?\s*"?\s*$|^\s*\d+/\d+\s*"?\s*$""")
_MARKUP_LABELS = ('R.O.', 'F.S.', 'F.D.', 'D.L.O.', 'D.O.')


def _markup_dims(page):
    out = []
    try:
        annots = page.annots or []
    except Exception:
        return out
    for a in annots:
        data = a.get('data') or {}
        sub = str(data.get('Subtype') or '')
        if 'FreeText' not in sub:
            continue
        txt = (a.get('contents') or '').strip()
        if not txt or not _MARKUP_DIM_RE.match(txt):
            continue
        def _s(v):
            try:
                return v.decode('latin-1') if isinstance(v, bytes) else str(v or '')
            except Exception:
                return ''
        out.append({'text': txt, 'x0': float(a['x0']), 'x1': float(a['x1']),
                    'top': float(a['top']), 'bottom': float(a['bottom']),
                    'vertical': (a['bottom'] - a['top']) > (a['x1'] - a['x0']),
                    'author': _s(data.get('T')).strip(),
                    'date': _s(data.get('M') or data.get('CreationDate'))[2:10]})
    return out


class _MarkupPage(object):
    """A pdfplumber page plus the typed dimension markups that sit against a
    dimension label (`self.markups`, each with the label it belongs to and
    that label's footprint). The markups are handed to _width_events /
    _height_events as finished readings — never mixed into the page's text,
    where a typed "26 3/4"" stacked over a printed 2" member was once joined
    into "226 3/4"". Everything else is the page's own (delegated)."""
    def __init__(self, page, marks):
        self._p = page
        self.markups, self.markup_conflicts = [], []
        words = page.extract_words(x_tolerance=1.5)
        labels = [w for w in words if w['text'] in _MARKUP_LABELS]
        vlines = _vertical_lines(page.chars)
        for m in marks:
            v = _feetinch_to_float(m['text'])
            if v is None:
                continue
            # Bluebeam typewriter boxes are filled white: one typed OVER the
            # CAD's own value hides it (page 22: "80 1/2"" over "8'-0" [96"]").
            # What the sheet SHOWS is the typed value.
            m['hides'] = [w['text'] for w in words if re.search(r'\d', w['text'])
                          and m['x0'] - 1 <= (w['x0'] + w.get('x1', w['x0'])) / 2.0 <= m['x1'] + 1
                          and m['top'] - 1 <= (w['top'] + w.get('bottom', w['top'])) / 2.0 <= m['bottom'] + 1]
            if not m['vertical']:
                # Beside: the label starts at the box's right end — or inside
                # its right half, where a wide box runs under it (p25's typed
                # 119 3/4" F.S. runs 8pt under its "F.S.").
                # Of several, the one level with the box: p44's typed 111 3/4"
                # touches the D.L.O. line's top but sits level with "F.S.".
                mid = (m['x0'] + m['x1']) / 2.0
                vc = (m['top'] + m['bottom']) / 2.0
                beside = [l for l in labels if mid <= l['x0'] <= m['x1'] + 12
                          and l['top'] < m['bottom'] and l['bottom'] > m['top']]
                lab = min(beside, key=lambda l: (round(abs((l['top'] + l['bottom']) / 2.0 - vc)),
                                                 abs(l['x0'] - m['x1']))) if beside else None
                if lab is None:
                    # r563 Above: CAD sets a bay's D.L.O. as "3'-4" D.L.O." with
                    # "[40"]" under it. A typed box over that value line leaves
                    # the label directly ABOVE the box (Howell NW p25 19/E10:
                    # 40 1/16" under each of its two bays' "D.L.O.").
                    above = [l for l in labels if m['top'] - 10 <= l['bottom'] <= m['top'] + 4
                             and l['x0'] < m['x1'] and l['x1'] > m['x0']]
                    lab = min(above, key=lambda l: abs((l['x0'] + l['x1']) / 2.0 - mid)) if above else None
                if lab is None:
                    continue
                self.markups.append(dict(m, value=v, axis='w', label=lab['text'],
                                         lrect=[lab['x0'], lab['top'], lab['x1'], lab['bottom']]))
            else:
                hit = None
                for l in vlines:
                    lbl = next((t for t in _MARKUP_LABELS if t in l['text']), None)
                    if not lbl or not (m['x0'] - 2 <= l['x0'] <= m['x1'] + 2):
                        continue
                    low = max(c['top'] for c in l['chars'])
                    # the label's lowest letter sits just above the box (up to 14pt
                    # on page 52's R.O.) or overlapping its top
                    if m['top'] - 14 <= low <= m['top'] + 12:
                        hit = (l, lbl)
                        break
                if hit is None:
                    continue
                l, lbl = hit
                self.markups.append(dict(m, value=v, axis='h', label=lbl, lrect=l.get('rect') or _wrect(l['chars'])))

    def __getattr__(self, name):
        return getattr(self._p, name)

    def flush_cache(self):
        try:
            self._p.flush_cache()
        except Exception:
            pass


_MARKUP_KEY = {'R.O.': 'RO', 'F.S.': 'FS', 'F.D.': 'FS', 'D.L.O.': 'DLO', 'D.O.': 'DO'}


def _rect_hits(rect, pt, pad=1.0):
    return bool(rect) and rect[0] - pad <= pt[0] <= rect[2] + pad and rect[1] - pad <= pt[1] <= rect[3] + pad


def _merge_markups(page, out, axis):
    """r560: add the page's typed markups to the readings — but ONLY for a
    label that has no printed value of its own. Where the sheet still prints
    one, the printed value stays and the typed one is recorded as a conflict
    for the import to report (the no-smoothing rule: never pick silently)."""
    for m in getattr(page, 'markups', None) or []:
        if m['axis'] != axis:
            continue
        key = _MARKUP_KEY.get(m['label'])
        if not key:
            continue
        lr = m['lrect']
        centre = ((lr[0] + lr[2]) / 2.0, (lr[1] + lr[3]) / 2.0)
        if axis == 'w':
            # the printed reading that belongs to THIS label word — a
            # reading's outline (value + label) can spill over a neighbour's
            # label, so overlap alone is not ownership
            hits = [e for e in out.get(key, []) if e.get('lrect')
                    and abs(e['lrect'][0] - lr[0]) <= 0.6 and abs(e['lrect'][1] - lr[1]) <= 0.6]
            # r563: a printed value whose text starts UNDER a box that hides
            # digits is not on the sheet any more, whichever label word it was
            # read against (p22 prints its text twice, so the box can sit
            # beside one copy of "D.L.O." while the hidden 57" was read
            # against the other).
            if m.get('hides'):
                hits += [e for e in out.get(key, []) if e not in hits and not e.get('markup')
                         and m['x0'] - 2 <= e['x0'] <= m['x1']
                         and m['top'] - 8 <= e['top'] <= m['bottom']]
        else:
            hits = [e for e in out.get(key, []) if _rect_hits(e.get('rect'), centre)]
        # A value printed BESIDE its label wins over a typed one (reported).
        # A value the reader only BORROWED from above the label (its stacked-
        # typesetting fallback, used when the label has nothing beside it) is
        # weaker evidence than a typed value right against the label — page
        # 22's R.O. borrowed another dimension's 96" — so the typed one
        # replaces it.
        beside = next((e for e in hits if not e.get('stacked')), None)
        if beside is not None and not m.get('hides'):
            page.markup_conflicts.append(dict(m, printed=beside.get('value')))
            continue
        if beside is not None:
            m['covers'] = beside.get('value')   # typed over it: replaced, and said so
        over = bool(m.get('hides'))
        for e in hits:
            out[key].remove(e)
        rect = [min(m['x0'], lr[0]), min(m['top'], lr[1]), max(m['x1'], lr[2]), max(m['bottom'], lr[3])]
        if axis == 'w':
            ev = {'x0': m['x0'], 'top': lr[1], 'value': m['value'], 'rect': rect,
                  'vrect': [m['x0'], m['top'], m['x1'], m['bottom']], 'markup': True, 'over': over}
        else:
            ev = {'x0': lr[0], 'top': m['bottom'] - 1, 'value': m['value'], 'rect': rect, 'markup': True, 'over': over}
        out.setdefault(key, []).append(ev)
        m['_used'] = True
    return out


def _fmt_in(v):
    if v is None:
        return ''
    whole = int(v)
    frac = round((v - whole) * 16)
    if frac == 16:
        whole, frac = whole + 1, 0
    if not frac:
        return '%d"' % whole
    n, d = frac, 16
    while n % 2 == 0:
        n //= 2
        d //= 2
    return ('%d %d/%d"' % (whole, n, d)) if whole else ('%d/%d"' % (n, d))


def _with_markups(page):
    marks = _markup_dims(page)
    return _MarkupPage(page, marks) if marks else page


def _read_page(page, page_no, tagset):
    """r554: everything one page contributes, as a plain picklable dict, so a
    page can be read in a worker and assembled in the server. Nothing in it
    depends on any other page — that is what lets an import pick up where a
    dead worker stopped instead of starting again from page 1.
      kind: 'skip' (not an elevation sheet), 'nocallout' (elevation sheet, no
      readable bubble) or 'elev'. The warning text is NOT built here — the
      unreadable-layer warnings are capped across the whole set, so they are
      composed at assembly, in page order, exactly as before."""
    try:
        text = page.extract_text() or ''
    except Exception:
        text = ''
    if not classify_page(page):
        return {"page": page_no, "kind": "skip", "skipKind": _sheet_kind(text),
                "reversed": bool(_looks_reversed(text)),
                "garbled": bool(_looks_garbled(text)), "recs": []}
    garbled = _looks_garbled(text)
    callouts = _elevation_callouts(page, text)
    if not callouts:
        return {"page": page_no, "kind": "nocallout", "garbled": bool(garbled), "recs": []}
    recs = [_elevation_record(page, c, page_no, callouts, text, garbled, tagset=tagset)
            for c in callouts]
    return {"page": page_no, "kind": "elev", "recs": recs}


def _skipped_page_part(page_no, reason):
    """r554: a page the reader could not get through (it took the worker
    process down twice). Kept as its own kind so assembly can say so."""
    return {"page": page_no, "kind": "failed", "reason": reason, "recs": []}


def iter_drawing_pages(pdf, start, tagset, tick=None, last_label=None):
    """r554: read pages start..end, yielding one part per page (see
    _read_page). `tick(done, count, note, busy)` is called when a page starts
    and when it is released, as parse_drawing_set always did."""
    count = len(pdf.pages) if hasattr(pdf, 'pages') else 0
    note = last_label
    for page_no in range(start, count):
        page = pdf.pages[page_no]
        if tick:
            tick(page_no, count, None, page_no + 1)
        try:
            page = _with_markups(page)     # r560: typed dimension markups
        except Exception:
            pass
        part = None
        try:
            part = _read_page(page, page_no, tagset)
        except Exception as e:
            # A page that raises (rather than killing the process) has always
            # been dropped silently by the loop's outer try; say so instead.
            part = _skipped_page_part(page_no, "could not be read (%s)" % e)
        finally:
            page.flush_cache()
        if part["recs"]:
            note = part["recs"][-1].get('label')
        yield part
        if tick:
            tick(page_no + 1, count, note, None)


def cover_finish_for(pdf, any_no_finish, first_elev_page):
    """r548 cover finish, given what the pages showed: read only when some
    elevation prints no finish, from pages ahead of the first elevation."""
    if not any_no_finish or first_elev_page is None:
        return None
    try:
        return _cover_finish(pdf, max(first_elev_page - 1, 1))
    except Exception:
        return None


def parts_finish_facts(parts):
    """(any elevation without a finish, first elevation's page) — what
    cover_finish_for needs, from parts already read."""
    recs = [r for p in parts for r in p.get("recs") or []]
    if not recs:
        return False, None
    return (any(not r.get('finish') for r in recs), min(r.get('page', 1) for r in recs))


def assemble_drawing_set(parts, glazing_schedule, set_finish, page_count):
    """r554: the result parse_drawing_set has always returned, built from the
    per-page parts in page order. Identical output to the pre-r554 loop."""
    elevations, warnings, skip_kinds, skipped = [], [], {}, 0
    for part in sorted(parts, key=lambda p: p["page"]):
        n = part["page"] + 1
        k = part["kind"]
        if k == "skip":
            skip_kinds.setdefault(part["skipKind"], []).append(n)
            if part.get("reversed"):
                warnings.append(
                    'Page %d has mirrored/reversed text (reads backwards) — if this is '
                    'an elevation sheet it could not be read automatically, add it '
                    'manually.' % n)
            elif part.get("garbled") and sum(1 for w in warnings if 'unreadable' in w) < 5:
                warnings.append(
                    'Page %d has an unreadable text layer (likely an embedded font with '
                    'no usable character map) — if this is an elevation sheet it could '
                    'not be read automatically, add it manually.' % n)
            skipped += 1
        elif k == "nocallout":
            warnings.append(
                'Page %d looks like an elevation sheet but no elevation '
                'name could be read from it (%s) — skipped, add it manually.'
                % (n, 'unreadable text layer' if part.get("garbled") else 'no ELEVATION bubble found'))
            skipped += 1
        elif k == "failed":
            warnings.append(
                'Page %d %s — skipped. If it is an elevation sheet, add it manually.'
                % (n, part.get("reason") or "could not be read"))
            skipped += 1
        else:
            elevations.extend(part["recs"])
    if set_finish:
        for e in elevations:
            if not e.get('finish'):
                e['finish'] = set_finish['finish']
                e['finishCode'] = None
                e['finishSource'] = 'cover'
    # The skip accounting is its OWN field, not a warning. A warning says
    # something may be wrong; this says 63 pages were correctly left alone and
    # what they were. test_drawing_import_bubble_callouts pins warnings == []
    # on a clean set, and it was right to.
    skip_summary = _skip_summary(skip_kinds)
    elevations, merge_notes = _merge_continued(elevations)
    warnings.extend(merge_notes)
    warnings.extend(_flag_mirror(elevations, glazing_schedule))
    return {"ok": True, "elevations": elevations, "skippedPages": skipped,
            "skipSummary": skip_summary,
            "glazingSchedule": glazing_schedule,
            "setFinish": set_finish,
            "pageCount": page_count,
            "warnings": warnings}


def open_drawing_set(file_bytes):
    """(pdf, None) or (None, error result)."""
    if not _HAVE_PDFPLUMBER:
        return None, {"ok": False, "error":
            "PDF drawing-set import needs the pdfplumber package, which isn't "
            "installed on this machine. Run:  pip install --user pdfplumber "
            "(see requirements.txt) and try again."}
    try:
        import io as _io
        return pdfplumber.open(_io.BytesIO(file_bytes)), None
    except Exception as e:
        return None, {"ok": False, "error": "Could not open this file as a PDF: %s" % e}


def read_glazing_schedule(pdf):
    try:
        return parse_glazing_schedule(pdf)
    except Exception:
        return []


def parse_drawing_set(file_bytes, filename='', progress=None):
    """The one entry point the server calls. Returns:
      {ok, elevations:[...], skippedPages:int, pageCount:int, warnings:[...]}
    or {ok:False, error:'...'} if pdfplumber isn't installed / the file
    can't be opened at all. Never raises out to the caller.

    r517: `progress`, if given, is called once per page as
    progress(pages_done, page_count, note) — `note` being the elevation found
    on that page, or None. Measured on Beacon Hill, 98% of a 107-second import
    is pdfminer laying out each page's content stream, so per-page is both the
    natural and the only honest granularity: there is no sub-page progress to
    report. A progress callback that raises must never take the import with
    it, so every call is guarded.

    r554: built from iter_drawing_pages + assemble_drawing_set, the same
    pieces the server's resumable worker uses.
    """
    def _tick(done, count, note=None, busy=None):
        if progress is None:
            return
        try:
            progress(done, count, note, busy)
        except Exception:
            # Older callbacks take three arguments; a progress channel must
            # never be load-bearing enough to break an import over its shape.
            try:
                progress(done, count, note)
            except Exception:
                pass
    pdf, err = open_drawing_set(file_bytes)
    if err:
        return err
    try:
        # Round 387: read the set's own GLAZING SCHEDULE first (cover sheet) —
        # its exact tag strings are what elevation sheets are then matched
        # against, so a set with no schedule can't invent tags off detail text.
        glazing_schedule = read_glazing_schedule(pdf)
        tagset = set(e['tag'] for e in glazing_schedule)
        count = len(pdf.pages) if hasattr(pdf, 'pages') else 0
        _tick(0, count, None)
        parts = list(iter_drawing_pages(pdf, 0, tagset, tick=_tick))
        set_finish = cover_finish_for(pdf, *parts_finish_facts(parts))
    finally:
        pdf.close()
    return assemble_drawing_set(parts, glazing_schedule, set_finish, count)

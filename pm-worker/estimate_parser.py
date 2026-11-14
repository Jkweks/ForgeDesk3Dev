"""
BidEstimate parser
===================
Parses the CONTRACT tab (and, with the same logic, ACTUAL / CO #N / PCO #N
tabs, which share the identical template) out of a Vos Glass BidEstimate
.xls file into a normalized, cost-code-validated structure.

Why anchor-based, not fixed cell coordinates
---------------------------------------------
Three real sample files were inspected while building this (Frattallone,
CONRAC, BeaconHillPh4). All three share the same rough template — a
Material block on the left, and a stacked Large Sub / Other / Labor block
on the right, with a summary block below — but:

  1. Column counts differ. Frattallone & BeaconHillPh4 put the cost code in
     its own column, right after Supplier. CONRAC has no separate cost-code
     column at all — the code is embedded in the description text as a
     leading "(NNN)" prefix, e.g. "(340) A.M.I. Metal Panels".
  2. Absolute row/column positions shift between files (different number
     of header rows, different note columns, etc).

So instead of "cost code is always column B", this parser:
  - finds the column boundary between the Material block and the
    Sub/Other/Labor block by searching for the literal text "LARGE SUB"
  - finds each column's role (supplier / code / description / qty / price
    / total) by searching for its header label, not by counting columns
  - falls back to a regex on the description text when there's no
    dedicated cost-code column
  - validates every extracted code against the master cost code list and
    sorts anything it isn't confident about into review_needed instead of
    silently trusting it

Public API
----------
    parse_workbook(path, sheet_name="CONTRACT", cost_code_lookup=None) -> dict

Run directly for a human-readable dump:
    python estimate_parser.py path/to/BidEstimate_X.xls [SHEET_NAME]
"""

import math
import re
import sys

try:
    import xlrd
except ImportError:
    xlrd = None

# r550 (Domino C.2): estimators also save BidEstimates as .xlsx, which xlrd
# 2.x refuses outright ("Excel xlsx file; not supported"). The server already
# FINDS BidEstimate_*.xlsx, so such a job failed at import. openpyxl is the
# .xlsx reader the server already uses for the production chart.
try:
    import openpyxl
except ImportError:
    openpyxl = None

import cost_codes as _cc

SECTION_MARKERS = [
    ("large_sub", re.compile(r"LARGE\s+SUB", re.I)),
    ("other",     re.compile(r"^\s*\d\.\s*OTHER\b", re.I)),
    ("labor",     re.compile(r"^\s*\d\.\s*LABOR\b", re.I)),
]

TOTAL_ROW_RE = re.compile(r"^\s*TOTAL\b", re.I)
MATERIAL_TOTAL_RE = re.compile(r"MATERIAL\s+TOTAL", re.I)
SECTION_TOTAL_RE = {
    "large_sub": re.compile(r"TOTAL\s+LARGE\s+SUB", re.I),
    "other":     re.compile(r"TOTAL\s+OTHER", re.I),
    "labor":     re.compile(r"TOTAL\s+LABOR", re.I),
}
CODE_PREFIX_RE = re.compile(r"^\s*\((\d{2,3})(?:\s*/\s*(\d{2,3}))?\)\s*(.*)$")
# Fallback for a missing opening paren, e.g. "425)Safety / PM time" (a real
# typo found in one source file) — still requires the closing paren so it
# doesn't fire on every line that happens to start with a number.
CODE_PREFIX_LENIENT_RE = re.compile(r"^\s*(\d{2,3})\)\s*(.*)$")
CONTRACT_AMOUNT_RE = re.compile(r"CONTRACT\s+AMOUNT", re.I)
PER_SF_RE = re.compile(r"PER\s+SQUARE\s+FOOT", re.I)
SUMMARY_LABELS = {
    "material":          re.compile(r"^\s*MATERIAL\s*$", re.I),
    "tax":               re.compile(r"^\s*TAX\s*%?\s*$", re.I),
    "large_sub":         re.compile(r"^\s*LARGE\s+SUB\s*$", re.I),
    "other":             re.compile(r"^\s*OTHER\s*$", re.I),
    "labor":             re.compile(r"^\s*LABOR\s*$", re.I),
    "material_overhead": re.compile(r"^\s*MATERIAL\s+OVERHEAD\s*$", re.I),
}

# r549: bumped whenever a parser fix changes what an already-imported sheet
# would parse to. Stamped on every result; the server flags a cached import
# with an older (or missing) revision as parserStale so the dashboard can
# ask for a re-import — re-parsing stays an explicit PM action.
PARSER_REVISION = 550

ROUNDING_TOLERANCE = 1.00

# r550: what a codeless percentage row has to be called to count as an
# overhead adjustment (Subcontractor Overhead, Other Overhead, a markup or
# contingency line) rather than a real line missing its cost code.
ADJUSTMENT_DESC_RE = re.compile(r"overhead|mark\s*-?\s*up|contingenc", re.I)  # dollars — float/locale rounding noise in source sheets

# A description with no letters in it is not a description (2026-07-30):
# the markup-scenario table renders its rate column as bare numbers (0.15,
# 0.2, ...) that land in what the line loop reads as the description cell.
_HAS_LETTERS_RE = re.compile(r"[A-Za-z]")


def _norm(v):
    if v is None:
        return ""
    if isinstance(v, float):
        if math.isnan(v) or math.isinf(v):
            return ""                # formula-error cells (xlrd returns NaN for
                                     # some Excel error types); treat as blank
        if v == int(v):
            v = int(v)
    return str(v).strip()


def _num(v):
    if isinstance(v, (int, float)) and not isinstance(v, bool):
        if isinstance(v, float) and (math.isnan(v) or math.isinf(v)):
            return None              # formula-error cells; treat as no value
        return float(v)
    return None


def _is_blank(v):
    return v is None or (isinstance(v, str) and v.strip() == "")


def _is_xlsx(path):
    return str(path).lower().endswith((".xlsx", ".xlsm"))


class _XlsxSheet:
    """An openpyxl worksheet presented with xlrd's sheet surface (nrows,
    ncols, cell_value) and xlrd's value conventions, so _Grid and every
    parsing rule run unchanged: an empty cell is '', every number is a float
    (bools as 1.0/0.0), a date is the Excel serial number. Values are the
    workbook's cached results (data_only) — what Excel last displayed."""

    def __init__(self, ws, epoch):
        import datetime as _dt
        rows = []
        for row in ws.iter_rows(values_only=True):
            out = []
            for v in row:
                if v is None:
                    out.append("")
                elif isinstance(v, bool):
                    out.append(1.0 if v else 0.0)
                elif isinstance(v, (int, float)):
                    out.append(float(v))
                elif isinstance(v, (_dt.datetime, _dt.date)):
                    if not isinstance(v, _dt.datetime):
                        v = _dt.datetime(v.year, v.month, v.day)
                    delta = v - epoch
                    out.append(delta.days + delta.seconds / 86400.0)
                elif isinstance(v, _dt.time):
                    out.append((v.hour * 3600 + v.minute * 60 + v.second) / 86400.0)
                else:
                    out.append(v if isinstance(v, str) else str(v))
            rows.append(out)
        while rows and all(c == "" for c in rows[-1]):
            rows.pop()
        self.nrows = len(rows)
        self.ncols = max((len(r) for r in rows), default=0)
        self._rows = [r + [""] * (self.ncols - len(r)) for r in rows]

    def cell_value(self, r, c):
        return self._rows[r][c]


class _XlsxBook:
    def __init__(self, path):
        import datetime as _dt
        self._wb = openpyxl.load_workbook(path, data_only=True, read_only=True)
        # 1900 date system with Excel's phantom Feb 29: serial = days since 1899-12-30
        self._epoch = (_dt.datetime(1904, 1, 1) if getattr(self._wb, "epoch", None)
                       and self._wb.epoch.year == 1904 else _dt.datetime(1899, 12, 30))

    def sheet_names(self):
        return list(self._wb.sheetnames)

    def sheet_by_name(self, name):
        return _XlsxSheet(self._wb[name], self._epoch)


def open_book(path):
    """The workbook behind a BidEstimate file, .xls (xlrd) or .xlsx/.xlsm
    (openpyxl), with the same sheet_names()/sheet_by_name() surface."""
    if _is_xlsx(path):
        if openpyxl is None:
            raise RuntimeError("openpyxl is required to read .xlsx BidEstimates (pip install openpyxl).")
        return _XlsxBook(path)
    if xlrd is None:
        raise RuntimeError("xlrd is required to read .xls files (pip install xlrd).")
    return xlrd.open_workbook(path)


def list_sheet_names(path):
    return list(open_book(path).sheet_names())


class _Grid:
    """Thin wrapper so the rest of the module doesn't touch xlrd directly —
    keeps the door open for an .xlsx (openpyxl) backend later without
    rewriting the parsing logic."""

    def __init__(self, sheet):
        self.nrows = sheet.nrows
        self.ncols = sheet.ncols
        self._rows = [
            [sheet.cell_value(r, c) for c in range(sheet.ncols)]
            for r in range(sheet.nrows)
        ]

    def cell(self, r, c):
        if 0 <= r < self.nrows and 0 <= c < self.ncols:
            return self._rows[r][c]
        return None

    def row(self, r):
        return self._rows[r] if 0 <= r < self.nrows else []

    def find(self, pattern, row_range=None, col_range=None):
        """Yield (row, col) for every cell whose text matches `pattern`
        (a compiled regex or exact string, case-insensitive either way)."""
        rows = row_range or range(self.nrows)
        for r in rows:
            cols = col_range or range(self.ncols)
            for c in cols:
                text = _norm(self.cell(r, c))
                if not text:
                    continue
                if isinstance(pattern, re.Pattern):
                    if pattern.search(text):
                        yield (r, c)
                else:
                    if text.lower() == str(pattern).lower():
                        yield (r, c)

    def find_first(self, pattern, **kw):
        for hit in self.find(pattern, **kw):
            return hit
        return None

    def value_right_of(self, r, c, max_scan=1):
        """First non-blank cell to the right of (r, c) on the same row,
        within max_scan columns — used for label: value pairs like
        'PROJECT:' -> 'Frattallone Wealth Partners'. Defaults to just the
        immediate next cell: scanning further risks skipping past a
        genuinely blank value (e.g. GC/CM left empty) into the *next*
        label entirely, which is worse than returning nothing."""
        for cc in range(c + 1, min(c + 1 + max_scan, self.ncols)):
            v = self.cell(r, cc)
            if not _is_blank(v):
                return v
        return None


def _detect_layout(grid):
    """Locate the Material/Sub-block boundary column and the canonical
    qty/price/total columns for both blocks. Raises ValueError with a
    clear message if the sheet doesn't match the expected template at
    all (caller should treat that as 'needs manual review', not crash)."""

    large_sub_hit = grid.find_first(SECTION_MARKERS[0][1])
    if large_sub_hit is None:
        raise ValueError("Could not locate a 'LARGE SUB' section header — "
                          "this sheet may not use the standard Vos template.")
    boundary_col = large_sub_hit[1]

    # --- material block (columns 0..boundary_col-1) ---
    # SUPPLIER is the PREFERRED anchor but not a required one (2026-07-30).
    # The GRCC LRC workbook's CO#2 tab has no supplier column at all — column
    # A was deleted on that tab, shifting the whole material block one to the
    # left (DESCRIPTION c1 / QUANTITY c2 / PRICE c3 / TOTAL c4, boundary c5,
    # against CONTRACT's SUPPLIER c0 / DESCRIPTION c2 / ... / boundary c6).
    # Requiring SUPPLIER made that tab raise outright and import as nothing,
    # which is the worst of the three possible outcomes: a tab that parses
    # imperfectly still gives a PM something to correct, and (since round 70)
    # rows the parser can't code land in review where they can be fixed or
    # ignored. So: anchor on whichever of the block's labels are present.
    supplier_hit = grid.find_first("SUPPLIER", col_range=range(0, boundary_col))
    if supplier_hit is not None:
        mat_header_row, supplier_col = supplier_hit
    else:
        supplier_col = None
        mat_header_row = None
        # The header row is the one carrying the block's own column labels.
        # Matched on TOTAL *and* QUANTITY together: either alone appears on
        # plenty of other rows (a "TOTAL ..." summary line, a stray label),
        # while the pair only co-occurs on the real header.
        for hit_row, _ in grid.find(re.compile(r"^TOTAL$", re.I),
                                     col_range=range(0, boundary_col)):
            if _find_in_row_any(grid, re.compile(r"^QUANTITY$", re.I), hit_row, 0) is not None:
                mat_header_row = hit_row
                break
        if mat_header_row is None:
            raise ValueError("Could not locate the material block's column headers "
                              "(no 'SUPPLIER', and no row carrying both 'QUANTITY' "
                              "and 'TOTAL') — this sheet may not use the standard "
                              "Vos template.")

    def _find_in_row(label_re, row):
        for c in range(boundary_col):
            if label_re.search(_norm(grid.cell(row, c))):
                return c
        return None

    mat_qty_col   = _find_in_row(re.compile(r"^QUANTITY$", re.I), mat_header_row)
    mat_price_col = _find_in_row(re.compile(r"^PRICE$", re.I), mat_header_row)
    mat_total_col = _find_in_row(re.compile(r"^TOTAL$", re.I), mat_header_row)
    if None in (mat_qty_col, mat_price_col, mat_total_col):
        raise ValueError("Could not locate QUANTITY/PRICE/TOTAL headers in the material block.")

    between = list(range(supplier_col + 1, mat_qty_col)) if supplier_col is not None else []
    if supplier_col is None:
        # No supplier column: anchor the description on its own header and
        # take the code column as the one immediately left of it, which is
        # the same relative shape the supplier-bearing layout has (supplier,
        # code, description) minus its first column. Falls back to the
        # column left of QUANTITY if this tab has no DESCRIPTION label
        # either, since that position is what the block's geometry implies.
        desc_hit = _find_in_row(re.compile(r"^DESCRIPTION$", re.I), mat_header_row)
        mat_desc_col = desc_hit if desc_hit is not None else max(mat_qty_col - 1, 0)
        mat_code_col = mat_desc_col - 1 if mat_desc_col - 1 >= 0 else None
    elif len(between) == 2:
        mat_code_col, mat_desc_col = between[0], between[1]
    elif len(between) == 1:
        mat_code_col, mat_desc_col = None, between[0]   # merged "(NNN) description" style
    else:
        # Unexpected column count — still try to parse, treating the last
        # of the extra columns as description and flagging the rest.
        mat_code_col, mat_desc_col = (None, between[-1]) if between else (None, supplier_col + 1)

    # --- right block (columns boundary_col..end) ---
    # The "2. LARGE SUB" marker row reliably carries QTY./PRICE./TOTAL in
    # the same row — derive the column layout from it once and reuse for
    # the Other/Labor sections stacked underneath, since it's one fixed
    # column block reused vertically.
    marker_row = large_sub_hit[0]
    right_qty_col   = _find_in_row_any(grid, re.compile(r"^QTY\.?$", re.I), marker_row, boundary_col)
    right_price_col = _find_in_row_any(grid, re.compile(r"^PRICE$", re.I), marker_row, boundary_col)
    right_total_col = _find_in_row_any(grid, re.compile(r"^TOTAL$", re.I), marker_row, boundary_col)
    if None in (right_qty_col, right_price_col, right_total_col):
        raise ValueError("Could not locate QTY./PRICE/TOTAL headers in the Large Sub block.")

    who_col = boundary_col
    between_r = list(range(who_col + 1, right_qty_col))
    if len(between_r) == 2:
        right_code_col, right_desc_col = between_r[0], between_r[1]
    elif len(between_r) == 1:
        right_code_col, right_desc_col = None, between_r[0]
    else:
        right_code_col, right_desc_col = (None, between_r[-1]) if between_r else (None, who_col + 1)

    return {
        "boundary_col": boundary_col,
        "material": {
            "header_row": mat_header_row,
            "supplier_col": supplier_col,
            "code_col": mat_code_col,
            "desc_col": mat_desc_col,
            "qty_col": mat_qty_col,
            "price_col": mat_price_col,
            "total_col": mat_total_col,
        },
        "right": {
            "who_col": who_col,
            "code_col": right_code_col,
            "desc_col": right_desc_col,
            "qty_col": right_qty_col,
            "price_col": right_price_col,
            "total_col": right_total_col,
        },
    }


def _find_in_row_any(grid, label_re, row, start_col):
    for c in range(start_col, grid.ncols):
        if label_re.search(_norm(grid.cell(row, c))):
            return c
    return None


def _extract_code_and_desc(code_cell, desc_cell):
    """Given the raw cell values for the (optional) dedicated code column
    and the description column, return (code:int|None, description:str,
    extra_code:int|None, confidence:str).
    confidence is one of: 'dedicated_column', 'parsed_from_text', 'missing'."""
    if code_cell is not None and not _is_blank(code_cell):
        n = _num(code_cell)
        if n is not None:
            return int(round(abs(n))), _norm(desc_cell), None, "dedicated_column"
        # Code column held text instead of a number — this happens when Excel
        # auto-converts a number to a string (text-formatted column, leading
        # apostrophe, or a CSV-import artifact). Try parsing the text as a
        # plain integer before falling through to the description-prefix regex.
        if isinstance(code_cell, str):
            stripped = code_cell.strip()
            if stripped.isdigit():
                return int(stripped), _norm(desc_cell), None, "dedicated_column"

    desc_text = _norm(desc_cell)
    m = CODE_PREFIX_RE.match(desc_text)
    if m:
        primary = int(m.group(1))
        extra = int(m.group(2)) if m.group(2) else None
        remainder = m.group(3).strip()
        return primary, remainder, extra, "parsed_from_text"

    m = CODE_PREFIX_LENIENT_RE.match(desc_text)
    if m:
        return int(m.group(1)), m.group(2).strip(), None, "parsed_from_text_lenient"

    return None, desc_text, None, "missing"


def _section_for_row(row_idx, section_rows):
    for name, (start, end) in section_rows.items():
        if start <= row_idx <= end:
            return name
    return None


def resolve_sheet_name(names, requested):
    """The ACTUAL workbook sheet name for a `requested` one, or None.

    Excel preserves whatever whitespace someone typed into a tab, and real
    workbooks carry it: this project's own GRCC LRC file has tabs named
    'CO#1 PR #4 ' and 'Break Metal at CW Sill ' -- trailing space included.
    Meanwhile every endpoint that accepts a sheet name .strip()s it as
    ordinary input hygiene, so the stripped name never matched and the tab
    was unimportable, reported as a parse failure ("It is a negative number
    and should be able to be imported properly" -- the negative was a red
    herring; the space was the bug).

    Matched most-exact-first so a workbook that really does contain two
    tabs differing only by whitespace or case still resolves the one asked
    for: exact, then whitespace-insensitive, then also case-insensitive.
    ONE resolution point, used by parse_workbook and by server.py's raw
    viewer, so the two can't disagree about which tab a name means.
    """
    if requested is None:
        return None
    names = list(names)
    if requested in names:
        return requested
    want = requested.strip()
    for n in names:
        if n.strip() == want:
            return n
    want_l = want.lower()
    for n in names:
        if n.strip().lower() == want_l:
            return n
    return None


def rebuild_by_cost_code(lines, cc_lookup):
    """The byCostCode rollup for `lines`. Extracted 2026-07-30 so the
    server can recompute it after applying a PM's ignores and manually
    added lines and get a byte-identical shape to a fresh parse -- the
    alternative was a second copy of this aggregation living in server.py,
    which is exactly the duplicated-region hazard this codebase has been
    bitten by twice."""
    by_code = {}
    for l in lines:
        by_code.setdefault(l["costCode"], {"total": 0.0, "lineCount": 0, "qty": 0.0})
        if l["total"]:
            by_code[l["costCode"]]["total"] += l["total"]
        if l.get("qty"):
            by_code[l["costCode"]]["qty"] += l["qty"]
        by_code[l["costCode"]]["lineCount"] += 1
    for code, agg in by_code.items():
        agg["total"] = round(agg["total"], 2)
        agg["qty"] = round(agg["qty"], 2)
        agg["description"] = cc_lookup.get(code, {}).get("description", "(not on master list)")
    return by_code


def parse_workbook(path, sheet_name="CONTRACT", cost_code_lookup=None):
    cc_lookup = cost_code_lookup if cost_code_lookup is not None else _cc.lookup()

    wb = open_book(path)
    # Resolved, not matched exactly: a tab's real name can carry trailing
    # whitespace the caller's .strip()ed name has lost. The RESOLVED name is
    # what gets reported back in result["sheet"], so everything downstream
    # (cache keys, the imported-tab checkmarks) agrees on one spelling.
    actual_sheet = resolve_sheet_name(wb.sheet_names(), sheet_name)
    if actual_sheet is None:
        raise ValueError(f"Sheet {sheet_name!r} not found. Available sheets: {wb.sheet_names()}")
    sheet_name = actual_sheet
    sheet = wb.sheet_by_name(sheet_name)
    grid = _Grid(sheet)

    warnings = []
    layout = _detect_layout(grid)
    mat = layout["material"]
    right = layout["right"]

    # ---- project meta -----------------------------------------------------
    meta = {}
    hit = grid.find_first(re.compile(r"^PROJECT:?$", re.I))
    if hit:
        meta["projectName"] = _norm(grid.value_right_of(*hit))
    hit = grid.find_first(re.compile(r"^GC\s*/\s*CM\s*:?$", re.I))
    if hit:
        meta["gc"] = _norm(grid.value_right_of(*hit))
    hit = grid.find_first(re.compile(r"^ARCH'?T:?$", re.I))
    if hit:
        meta["architect"] = _norm(grid.value_right_of(*hit))
    hit = grid.find_first(re.compile(r"^DUE:?$", re.I))
    if hit:
        meta["due"] = _norm(grid.value_right_of(*hit))

    # ---- material lines -----------------------------------------------------
    lines = []
    review_needed = []
    seen_codes = set()
    skipped_placeholders = 0  # 2026-07-17: $0/blank-total template rows never counted as bid lines
    skipped_summary = 0       # 2026-07-30: summary-block rollups, sub-total
                              # rows and side-math rows (gross profit, $/sqft,
                              # markup scenarios) — numbers that are other
                              # numbers added together, never line items

    BLANK_RUN_CUTOFF = 6  # consecutive empty rows -> treat as real end of data,
                          # not a gap between entries. Stops the parser from
                          # reading stray leftover values in far-below template
                          # rows that were never part of this bid's real data.
    blank_run = 0

    # r549 (2026-10-20, Wege Pharmacy): the template's Sundries / Incidentals
    # line (code 260, a % of material) sits directly above MATERIAL TOTAL,
    # BELOW a long run of unused template rows. The blank-run cutoff broke
    # out before reaching it, silently dropping a real priced line ($359.89
    # on Wege). The cutoff exists for sheets with NO explicit end marker;
    # when MATERIAL TOTAL is present, it is the end, and every row above it
    # is read.
    mat_total_row = None
    _mat_cols = [c for c in (mat["desc_col"], mat["code_col"], mat["supplier_col"]) if c is not None]
    for _r in range(mat["header_row"] + 1, grid.nrows):
        if any(MATERIAL_TOTAL_RE.search(_norm(grid.cell(_r, _c))) for _c in _mat_cols):
            mat_total_row = _r
            break
    mat_total_stated = (_num(grid.cell(mat_total_row, mat["total_col"]))
                        if mat_total_row is not None else None)

    for r in range(mat["header_row"] + 1, grid.nrows):
        if mat_total_row is not None and r >= mat_total_row:
            break
        code_cell = grid.cell(r, mat["code_col"]) if mat["code_col"] is not None else None
        desc_cell = grid.cell(r, mat["desc_col"])
        total_cell = grid.cell(r, mat["total_col"])
        qty_cell = grid.cell(r, mat["qty_col"])
        supplier = _norm(grid.cell(r, mat["supplier_col"])) if mat["supplier_col"] is not None else ""
        desc_text_raw = _norm(desc_cell)
        total_n = _num(total_cell)
        qty_n = _num(qty_cell)

        is_blank_row = (_is_blank(desc_cell) and not supplier
                        and (total_n is None or total_n == 0)
                        and (qty_n is None or qty_n == 0))
        if is_blank_row:
            blank_run += 1
            if blank_run >= BLANK_RUN_CUTOFF and mat_total_row is None:
                break
            continue
        blank_run = 0

        if MATERIAL_TOTAL_RE.search(desc_text_raw):
            break  # explicit end-of-material marker -- nothing after this
                   # row is a real bid line (one sample has an internal
                   # glass-takeoff worksheet directly below it, reusing
                   # these same columns for an unrelated purpose)
        if TOTAL_ROW_RE.match(desc_text_raw):
            continue  # a stray "TOTAL" style row inside the material block, not a line

        # 2026-07-17: the standard BidEstimate template pre-prints
        # description/cost-code labels on rows that don't always get used —
        # a row can have real-looking text and a valid code but a total
        # that was simply never filled in. That's a template placeholder,
        # not a bid line, so it shouldn't become a PO-able line item (or
        # even a review-needed entry — there's nothing to review, it's
        # legitimately blank). Checked on the DOLLAR AMOUNT specifically
        # (not qty), since a $0/blank total is what makes a row
        # non-actionable regardless of whether qty happens to be set.
        if total_n is None or total_n == 0:
            skipped_placeholders += 1
            continue

        code, desc, extra_code, confidence = _extract_code_and_desc(code_cell, desc_cell)
        qty = qty_n
        price = _num(grid.cell(r, mat["price_col"]))

        line = {
            "section": "material",
            "row": r + 1,  # 1-indexed, matches what a PM sees if they open the sheet
            "supplier": supplier,
            "costCode": code,
            "description": desc,
            "qty": qty,
            "unitPrice": price,
            "total": total_n,
            "confidence": confidence,
        }
        if extra_code is not None:
            line["additionalCostCode"] = extra_code

        if code is None:
            if total_n:  # a real-looking line with no code at all — worth a human look
                line["reviewReason"] = "no_cost_code_found"
                review_needed.append(line)
            continue  # don't add codeless lines to the usable line list
        if code not in cc_lookup:
            line["reviewReason"] = "code_not_on_master_list"
            review_needed.append(line)
        elif confidence == "parsed_from_text_lenient":
            line["reviewReason"] = "recovered_from_malformed_code_prefix"
            review_needed.append(line)
        lines.append(line)
        seen_codes.add(code)

    # ---- right block: large sub / other / labor -----------------------------
    # Labor has no subcontractor name, so the template drops the "who"
    # column entirely and shifts code+description one column left compared
    # to Large Sub/Other — confirmed against all three sample files. Build
    # per-section column maps instead of assuming one fixed layout.
    labor_desc_col = right["code_col"] if right["code_col"] is not None else right["desc_col"]
    SECTION_COLS = {
        "large_sub": {"who_col": right["who_col"], "code_col": right["code_col"], "desc_col": right["desc_col"]},
        "other":     {"who_col": right["who_col"], "code_col": right["code_col"], "desc_col": right["desc_col"]},
        "labor":     {"who_col": None, "code_col": right["who_col"], "desc_col": labor_desc_col},
    }

    section_starts = {}
    for name, pattern in SECTION_MARKERS:
        hit = grid.find_first(pattern, col_range=range(right["who_col"], right["who_col"] + 2))
        if hit:
            section_starts[name] = hit[0]

    ordered = sorted(section_starts.items(), key=lambda kv: kv[1])
    section_rows = {}
    for i, (name, start_row) in enumerate(ordered):
        end_row = ordered[i + 1][1] - 1 if i + 1 < len(ordered) else grid.nrows - 1
        section_rows[name] = (start_row + 1, end_row)

    section_totals_stated = {}
    adjustments = []

    for name, (start, end) in section_rows.items():
        cols = SECTION_COLS[name]
        blank_run = 0
        # r550 (Forslund CO #1): the blank-run cutoff stopped the OTHER
        # section after six unused template rows, dropping two real lines
        # below them (D&D +$11,745, Port-A-John -$9,000). Same fix as r549's
        # material end marker: when the section's own TOTAL row exists, it
        # is the end, and every row above it is read.
        _stated_re = SECTION_TOTAL_RE.get(name)
        has_total_row = bool(_stated_re) and any(
            _stated_re.search(_norm(grid.cell(_r, _c)))
            for _r in range(start, end + 1)
            for _c in {right["desc_col"], cols["desc_col"], cols["code_col"]} if _c is not None)
        for r in range(start, end + 1):
            who = _norm(grid.cell(r, cols["who_col"])) if cols["who_col"] is not None else ""
            code_cell = grid.cell(r, cols["code_col"]) if cols["code_col"] is not None else None
            desc_cell = grid.cell(r, cols["desc_col"])
            total_cell = grid.cell(r, right["total_col"])
            qty_cell = grid.cell(r, right["qty_col"])
            desc_text_raw = _norm(desc_cell)
            # "TOTAL <SECTION>" / stray "TOTAL ..." rows are USUALLY written
            # in the canonical (unshifted) desc column — but not always
            # (2026-07-30, GRCC LRC): the labor sub-totals ("TOTAL NON
            # PREVAILING WAGE", "TOTAL PREVAILING WAGE LABOR") sit in the
            # column the shifted labor layout reads as its CODE column, so
            # checking the canonical column alone let both through as
            # $101,500 / $457,200 review lines. Every TOTAL-shaped check now
            # looks at the section's own code and desc columns too.
            canonical_desc_text = _norm(grid.cell(r, right["desc_col"]))
            code_text = _norm(code_cell) if code_cell is not None else ""
            total_shaped_texts = (canonical_desc_text, code_text, desc_text_raw)
            who_text = who
            total_n = _num(total_cell)
            qty_n = _num(qty_cell)

            stated_total_re = SECTION_TOTAL_RE.get(name)
            if stated_total_re and any(stated_total_re.search(t) for t in total_shaped_texts):
                section_totals_stated[name] = total_n
                break  # nothing after a section's own TOTAL row belongs to it
            if any(TOTAL_ROW_RE.match(t) for t in total_shaped_texts + (who_text,)):
                continue

            # The sheet's own summary block (2026-07-30): MATERIAL / TAX % /
            # LARGE SUB / OTHER / LABOR / MATERIAL OVERHEAD / CONTRACT
            # AMOUNT ( SELL PRICE ) rollup rows physically sit inside the
            # labor section's row range, and every one of their totals is
            # other numbers added together. The parser ALREADY reads this
            # block, correctly, into `summary` further down — the bug was
            # knowing what these rows are in one place and forgetting in
            # the other. A codeless row whose own-column description is
            # exactly one of the summary labels is that block: counted,
            # skipped, never reviewable — there is no cost code to fix on a
            # rollup.
            # r550 (Forslund combined_9.3 row 21): the template's "Other"
            # placeholder rows read exactly like the summary block's OTHER
            # label — but one priced 1 x $31,000 is a real line. A row whose
            # qty x price is its total is never the summary block (those rows
            # carry a total and at most a rate).
            _rq, _rp = qty_n, _num(grid.cell(r, right["price_col"]))
            _row_is_priced = (_rq is not None and _rp is not None and total_n is not None
                              and total_n != 0 and abs(_rq * _rp - total_n) <= ROUNDING_TOLERANCE)
            if not code_text and not who_text and desc_text_raw and not _row_is_priced:
                _is_summary_label = (
                    any(lr.search(desc_text_raw) for lr in SUMMARY_LABELS.values())
                    or CONTRACT_AMOUNT_RE.search(desc_text_raw)
                    or PER_SF_RE.search(desc_text_raw))
                if _is_summary_label:
                    skipped_summary += 1
                    continue

            is_blank_row = (_is_blank(desc_cell) and not who
                            and (total_n is None or total_n == 0)
                            and (qty_n is None or qty_n == 0))
            if is_blank_row:
                blank_run += 1
                if blank_run >= BLANK_RUN_CUTOFF and not has_total_row:
                    break
                continue
            blank_run = 0

            # 2026-07-17: same template-placeholder skip as the material
            # loop above — a large-sub/other/labor row can have a real
            # who/code/description pre-printed but a total that was never
            # filled in. Checked AFTER the rate/markup detection below
            # would otherwise misfire on it, so this sits here rather than
            # immediately after is_blank_row: a markup row's own total is
            # always nonzero when real (an unset rate row is already
            # caught by is_blank_row above, since it has no who/desc
            # either), so this ordering doesn't cost anything.
            if total_n is None or total_n == 0:
                skipped_placeholders += 1
                continue

            code, desc, extra_code, confidence = _extract_code_and_desc(code_cell, desc_cell)
            qty = qty_n
            price = _num(grid.cell(r, right["price_col"]))

            if code is None:
                # A row with NO code, NO who, and no real description in
                # the section's own columns is not a line item at all
                # (2026-07-30): it is the sheet's side math — Total Gross
                # Profit (a fraction), Gross Profit per Labor Hour, the
                # $/sqft figure, and the four markup-scenario rows (whose
                # "description" is the bare rate: 0.15, 0.20, ...). All of
                # these were surfacing as review lines reading "row 52 · ·
                # $0.39", with nothing to review — no code to fix, no
                # description to recognize. Letters are the test: a real
                # description describes something.
                # r550 (Beacon Hill Base Bid (2), row 35): a priced line can
                # also have no description — 120 hrs x $70 = $8,400 in the
                # labor section, counted in the sheet's TOTAL LABOR. When qty
                # x price really is the total, it's a line, not side math:
                # it goes to review instead of vanishing.
                _q = qty_n
                _p = _num(grid.cell(r, right["price_col"]))
                _priced_line = (_q is not None and _p is not None and total_n is not None
                                and abs(_q * _p - total_n) <= ROUNDING_TOLERANCE)
                if not who and not _HAS_LETTERS_RE.search(desc_text_raw) and not _priced_line:
                    skipped_summary += 1
                    continue
                # Overhead/markup calculation rows (e.g. "Subcontractor
                # Overhead" at a % rate) legitimately have no cost code.
                # Heuristic: a real missing-code LINE has a qty that looks
                # like a quantity (>=1 or absent); a markup row's "qty" is
                # a rate, typically a fraction under 1.
                # r550 (Forslund): a fraction qty alone isn't enough — a real
                # codeless purchase priced at 0.5 x $50,000 ("Engineered
                # Scaffolding") was filed as an overhead adjustment. The
                # template's percentage rows are the overhead/markup lines,
                # so the description has to say so too; anything else goes to
                # review where a PM can give it a code.
                looks_like_rate = (qty is not None and 0 < qty < 1
                                   and bool(ADJUSTMENT_DESC_RE.search(desc_text_raw or desc or "")))
                if looks_like_rate and total_n:
                    adjustments.append({
                        "section": name, "row": r + 1, "description": desc,
                        "rate": qty, "base": price, "amount": total_n,
                    })
                elif total_n:
                    line = {
                        "section": name, "row": r + 1, "who": who,
                        "costCode": None, "description": desc,
                        "qty": qty, "unitPrice": price, "total": total_n,
                        "confidence": "missing", "reviewReason": "no_cost_code_found",
                    }
                    review_needed.append(line)
                continue

            line = {
                "section": name,
                "row": r + 1,
                "who": who,
                "costCode": code,
                "description": desc,
                "qty": qty,
                "unitPrice": price,
                "total": total_n,
                "confidence": confidence,
            }
            if extra_code is not None:
                line["additionalCostCode"] = extra_code
            if code not in cc_lookup:
                line["reviewReason"] = "code_not_on_master_list"
                review_needed.append(line)
            elif confidence == "parsed_from_text_lenient":
                line["reviewReason"] = "recovered_from_malformed_code_prefix"
                review_needed.append(line)
            lines.append(line)
            seen_codes.add(code)

    # ---- cross-check section sums against the sheet's own stated totals ----
    # r549: material joins the check (its stated total is the MATERIAL TOTAL
    # row) — the missing material check is how the dropped Sundries line
    # went unnoticed.
    if mat_total_stated is not None:
        section_totals_stated = dict(section_totals_stated, material=mat_total_stated)
    for name, stated in section_totals_stated.items():
        if stated is None:
            continue
        if math.isnan(stated) or math.isinf(stated):
            # The stated-total cell was a formula-error cell (xlrd returned
            # NaN/Inf). abs(computed - nan) evaluates to nan, which is NOT
            # > ROUNDING_TOLERANCE (nan comparisons return False), so the
            # mismatch would be silently suppressed. Instead, warn explicitly.
            warnings.append({
                "type": "section_total_mismatch",
                "section": name,
                "computedFromLines": None,
                "statedInSheet": None,
                "note": "stated total cell appears to contain a formula error (NaN/Inf)",
            })
            continue
        computed = sum(l["total"] for l in lines if l["section"] == name and l["total"])
        computed += sum(a["amount"] for a in adjustments if a["section"] == name and a["amount"])
        if abs(computed - stated) > ROUNDING_TOLERANCE:
            warnings.append({
                "type": "section_total_mismatch",
                "section": name,
                "computedFromLines": round(computed, 2),
                "statedInSheet": round(stated, 2),
            })

    # ---- summary block (contract amount, GC/architect already captured) ----
    summary = {}
    contract_hit = grid.find_first(CONTRACT_AMOUNT_RE)
    if contract_hit:
        summary["contractAmount"] = _num(grid.cell(contract_hit[0], right["total_col"]))
    per_sf_hit = grid.find_first(PER_SF_RE)
    if per_sf_hit:
        summary["dollarsPerSquareFootGlass"] = _num(grid.cell(per_sf_hit[0], right["total_col"]))
    # r549 (2026-10-20, Wege Pharmacy): the summary block is the contiguous
    # rollup directly above CONTRACT AMOUNT. First-match-anywhere read the
    # Other section's pre-printed "Other" placeholder rows as the OTHER
    # summary (Wege: $0 instead of $22,997.73). Take the match NEAREST
    # above the contract row; with no contract row, the last match on the
    # sheet (the summary block is always below the sections).
    for key, label_re in SUMMARY_LABELS.items():
        hits = list(grid.find(label_re, col_range=range(right["who_col"], right["who_col"] + 3)))
        if contract_hit:
            hits = [h for h in hits if h[0] <= contract_hit[0]]
        if hits:
            hit = max(hits, key=lambda h: h[0])
            summary[key] = _num(grid.cell(hit[0], right["total_col"]))

    # ---- cost-code rollup (drives the PO builder's "lines for this code") --
    by_code = rebuild_by_cost_code(lines, cc_lookup)

    # Note: estimated field labor hours are NOT computed here (2026-07-27,
    # same day as first added — reworked to be configurable). Which cost
    # codes count as "field labor hours" is now an admin-editable setting
    # (shared-data's fieldLaborHourCodes, see server.py), not a fixed set
    # baked into the parser — so the actual hour total is computed
    # dynamically client-side from byCostCode[code].qty against whatever
    # that setting currently is (see schedule.js's
    # estimatedFieldLaborHoursFromEstData()), not cached here at parse
    # time where it would go stale the moment the setting changes.

    return {
        "sourceFile": path,
        "parserRevision": PARSER_REVISION,
        "sheet": sheet_name,
        "meta": meta,
        "summary": summary,
        "lines": lines,
        "adjustments": adjustments,
        "byCostCode": by_code,
        "reviewNeeded": review_needed,
        "warnings": warnings,
        "lineCount": len(lines),
        "reviewCount": len(review_needed),
        "skippedPlaceholderCount": skipped_placeholders,
        "skippedSummaryCount": skipped_summary,
    }


def _print_report(result):
    print(f"Source: {result['sourceFile']}  [{result['sheet']}]")
    print(f"Project: {result['meta'].get('projectName', '?')}")
    print(f"GC/CM: {result['meta'].get('gc', '?')}   Architect: {result['meta'].get('architect', '?')}")
    print(f"Contract amount: {result['summary'].get('contractAmount')}")
    print(f"$/SF glass: {result['summary'].get('dollarsPerSquareFootGlass')}")
    print(f"Lines parsed: {result['lineCount']}   Needing review: {result['reviewCount']}   Skipped placeholders ($0/blank): {result['skippedPlaceholderCount']}")
    if result["warnings"]:
        print("\nWARNINGS:")
        for w in result["warnings"]:
            print(" -", w)
    print("\nBy cost code:")
    for code in sorted(result["byCostCode"], key=lambda c: (c is None, c)):
        agg = result["byCostCode"][code]
        label = f"{code} — {agg['description']}" if code is not None else "(no code)"
        print(f"  {label:<55} {agg['lineCount']:>3} line(s)  ${agg['total']:,.2f}")
    if result["reviewNeeded"]:
        print(f"\nNEEDS REVIEW ({len(result['reviewNeeded'])}):")
        for l in result["reviewNeeded"]:
            print(f"  row {l['row']:>4}  [{l['section']:<9}]  reason={l.get('reviewReason')}"
                  f"  code={l.get('costCode')}  desc={l.get('description')!r}  total={l.get('total')}")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python estimate_parser.py <path.xls> [SHEET_NAME]")
        sys.exit(1)
    path = sys.argv[1]
    sheet_name = sys.argv[2] if len(sys.argv) > 2 else "CONTRACT"
    result = parse_workbook(path, sheet_name=sheet_name)
    _print_report(result)

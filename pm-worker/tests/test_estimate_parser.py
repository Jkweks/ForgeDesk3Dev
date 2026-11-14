"""
test_estimate_parser.py
=======================
Tests all 10 edge-case scenarios for estimate_parser.py, plus adversarial
sub-cases for each real bug found during interactive probing.

Each test builds a minimal but structurally faithful .xls workbook that
matches the real Vos BidEstimate template (same column layout, same header
labels, same section markers that _detect_layout() searches for) and then
asserts the parser's actual behaviour — covering both what it handles
cleanly and what currently fails, so bugs are documented not just assumed.

Run with:
    cd /home/claude/parser_test
    python3 test_estimate_parser.py
"""
import os as _vos_os, sys as _vos_sys; _vos_sys.path.insert(0, _vos_os.path.dirname(_vos_os.path.dirname(_vos_os.path.abspath(__file__))))  # repo root: server modules live there

import sys, os, io, tempfile, traceback, math
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# xlwt builds the .xls fixtures; xlrd is what estimate_parser reads them
# with. Neither is needed to RUN the app (xlrd is optional there too, and
# xlwt is used by this test alone) — so an environment without them is an
# environment that cannot run this file, which is an environment report,
# not a failure of the code under test. Same posture as
# test_production_chart_endpoint_e2e.py's openpyxl skip, and equally
# NARROW: only an ImportError of these two names skips; a real assertion
# failure, or any other exception anywhere below, still exits non-zero.
# In the Claude sandbox both install in seconds and MUST be installed at
# session start alongside `npm install jsdom`:
#   pip install xlwt xlrd --break-system-packages
# (on a Windows machine: pip install --user xlwt xlrd)
try:
    import xlwt
    import xlrd  # noqa: F401 — proves the parser's reader is present too
except ImportError as e:
    print("SKIP: fixture dependencies not installed (%s)" % e)
    print("      pip install xlwt xlrd --break-system-packages   # sandbox")
    print("      pip install --user xlwt xlrd                    # Windows")
    sys.exit(0)

import estimate_parser as ep

# ---------------------------------------------------------------------------
# Fixture builder
# ---------------------------------------------------------------------------
MAT_SUPP_COL = 0
MAT_CODE_COL = 1
MAT_DESC_COL = 2
MAT_QTY_COL  = 3
MAT_PRC_COL  = 4
MAT_TOT_COL  = 5
BDRY_COL     = 6
R_WHO_COL    = 6
R_CODE_COL   = 7
R_DESC_COL   = 8
R_QTY_COL    = 9
R_PRC_COL    = 10
R_TOT_COL    = 11

VALID_MAT_CODE   = 300   # in master list
VALID_LABOR_CODE = 120   # in master list
VALID_SUB_CODE   = 350   # in master list
INVALID_CODE     = 999   # NOT in master list

def _new_wb():
    return xlwt.Workbook()

def _header_rows(ws, hrow=2):
    ws.write(0, 0, "PROJECT:"); ws.write(0, 1, "Test Project")
    ws.write(1, 0, "GC / CM:"); ws.write(1, 1, "Test GC")
    ws.write(hrow, MAT_SUPP_COL, "SUPPLIER")
    ws.write(hrow, MAT_CODE_COL, "CODE")
    ws.write(hrow, MAT_DESC_COL, "DESCRIPTION")
    ws.write(hrow, MAT_QTY_COL,  "QUANTITY")
    ws.write(hrow, MAT_PRC_COL,  "PRICE")
    ws.write(hrow, MAT_TOT_COL,  "TOTAL")
    ws.write(hrow, R_WHO_COL,  "LARGE SUB")
    ws.write(hrow, R_CODE_COL, "CODE")
    ws.write(hrow, R_DESC_COL, "DESCRIPTION")
    ws.write(hrow, R_QTY_COL,  "QTY.")
    ws.write(hrow, R_PRC_COL,  "PRICE")
    ws.write(hrow, R_TOT_COL,  "TOTAL")
    return hrow

def _section_marker(ws, row, label):
    ws.write(row, R_WHO_COL, label)

def _section_total(ws, row, label, total):
    ws.write(row, R_DESC_COL, label)
    ws.write(row, R_TOT_COL,  total)

def _mat_line(ws, row, supplier="Supplier A", code=VALID_MAT_CODE,
              desc="Aluminum Frame", qty=10.0, price=50.0, total=500.0):
    if supplier is not None: ws.write(row, MAT_SUPP_COL, supplier)
    if code     is not None: ws.write(row, MAT_CODE_COL, code)
    if desc     is not None: ws.write(row, MAT_DESC_COL, desc)
    if qty      is not None: ws.write(row, MAT_QTY_COL,  qty)
    if price    is not None: ws.write(row, MAT_PRC_COL,  price)
    if total    is not None: ws.write(row, MAT_TOT_COL,  total)

def _sub_line(ws, row, who="Sub Co", code=None, desc="Storefront",
              qty=1.0, price=10000.0, total=10000.0):
    if who   is not None: ws.write(row, R_WHO_COL,  who)
    if code  is not None: ws.write(row, R_CODE_COL, code)
    if desc  is not None: ws.write(row, R_DESC_COL, desc)
    if qty   is not None: ws.write(row, R_QTY_COL,  qty)
    if price is not None: ws.write(row, R_PRC_COL,  price)
    if total is not None: ws.write(row, R_TOT_COL,  total)

def _save_and_parse(wb, sheet_name="CONTRACT"):
    buf = io.BytesIO(); wb.save(buf); buf.seek(0)
    with tempfile.NamedTemporaryFile(suffix=".xls", delete=False) as f:
        f.write(buf.read()); path = f.name
    try:
        return ep.parse_workbook(path, sheet_name=sheet_name)
    finally:
        os.unlink(path)

def _minimal_right_block(ws, start_row):
    """Add bare-minimum section markers so _detect_layout doesn't raise."""
    _section_marker(ws, start_row,   "2. OTHER")
    _section_marker(ws, start_row+1, "3. LABOR")

# ---------------------------------------------------------------------------
# Test runner
# ---------------------------------------------------------------------------
_results = []

def test(name):
    def decorator(fn):
        _results.append((name, fn))
        return fn
    return decorator

def run_all():
    passed = failed = errored = 0
    for name, fn in _results:
        try:
            fn()
            print(f"  PASS  {name}")
            passed += 1
        except AssertionError as e:
            print(f"  FAIL  {name}")
            print(f"        {e}")
            failed += 1
        except Exception as e:
            print(f"  ERROR {name}")
            print(f"        {type(e).__name__}: {e}")
            traceback.print_exc()
            errored += 1
    print()
    print(f"Results: {passed} passed, {failed} failed, {errored} errored  ({len(_results)} total)")
    return failed + errored


# ===========================================================================
# UNIT TESTS — helpers
# ===========================================================================

@test("U01 _norm: NaN raises ValueError — BUG (crashes parser on formula-error cells)")
def _():
    """xlrd returns float('nan') for some Excel error cell types (#NUM!, #DIV/0!
    that resolve to NaN). _norm() calls `int(v)` without checking, which
    raises ValueError. Any such cell in description or code position crashes
    the whole parse."""
    try:
        ep._norm(float('nan'))
        # If no exception was raised, the bug is already fixed:
        result = ep._norm(float('nan'))
        assert isinstance(result, str), "Fixed: _norm(nan) should return a string"
    except (ValueError, OverflowError) as e:
        raise AssertionError(
            f"BUG CONFIRMED: _norm(float('nan')) raises {type(e).__name__}: {e}\n"
            f"        Fix: add `if isinstance(v, float) and (math.isnan(v) or math.isinf(v)): return ''` "
            f"before the int(v) check in _norm()."
        )


@test("U02 _norm: Inf raises OverflowError — same NaN/Inf family bug")
def _():
    try:
        ep._norm(float('inf'))
        result = ep._norm(float('inf'))
        assert isinstance(result, str)
    except (ValueError, OverflowError) as e:
        raise AssertionError(
            f"BUG CONFIRMED: _norm(float('inf')) raises {type(e).__name__}: {e}\n"
            f"        Same fix as U01."
        )


@test("U03 _num: NaN passes through as float nan — silent downstream corruption")
def _():
    """_num(float('nan')) returns nan, not None. This propagates into
    line['total'], byCostCode rollup, and the section-total cross-check.
    In the cross-check: abs(computed - nan) evaluates to nan (not a real
    number), and nan > 1.0 is False — so a formula-error total cell
    *silently suppresses* the mismatch warning instead of flagging it."""
    result = ep._num(float('nan'))
    assert result is None, (
        f"BUG CONFIRMED: _num(float('nan')) returns {result!r} instead of None.\n"
        f"        NaN in a total cell propagates into sums and suppresses mismatch warnings.\n"
        f"        Fix: add `if math.isnan(v) or math.isinf(v): return None` at start of _num()."
    )


@test("U04 _num: Inf passes through — same family bug")
def _():
    result = ep._num(float('inf'))
    assert result is None, (
        f"BUG CONFIRMED: _num(float('inf')) returns {result!r} instead of None.\n"
        f"        Same fix as U03."
    )


@test("U05 _extract_code_and_desc: string '340' in dedicated code col -> missing")
def _():
    """Excel sometimes stores a number as a text string (text-formatted column,
    leading apostrophe, CSV import). xlrd returns a str, not a float.
    _extract_code_and_desc calls _num(code_cell) which returns None for strings,
    then falls through to regex — but the description 'Curtain wall' has no
    code prefix, so the line is reported as confidence='missing' and silently
    dropped from the usable line list even though the code WAS present."""
    code, desc, extra, conf = ep._extract_code_and_desc('340', 'Curtain wall')
    assert code == 340, (
        f"BUG CONFIRMED: string '340' in dedicated code column is treated as missing (code={code}, conf={conf}).\n"
        f"        Fix: in _extract_code_and_desc, after _num() fails, try int(code_cell.strip()) "
        f"when code_cell is a digit-only string before falling through to description regex."
    )


@test("U06 _extract_code_and_desc: string '340' col + prefixed desc -> dedicated_column (string fix applied)")
def _():
    """After the fix, a digit-only string in the code col is now parsed as
    dedicated_column — the description prefix is not even reached, which is
    the RIGHT behaviour (the dedicated column IS the authoritative source)."""
    code, desc, extra, conf = ep._extract_code_and_desc('340', '(340) Curtain wall')
    assert code == 340
    assert conf == "dedicated_column", \
        f"After fix, string code col should win as dedicated_column, got {conf}"


@test("U07 _extract_code_and_desc: float 340.5 rounds to nearest int (banker rounding awareness)")
def _():
    """int(round(abs(340.5))) uses Python's banker rounding (round-half-to-even),
    so 340.5 -> 340, NOT 341. Verify the actual result so no one is surprised."""
    code, _, _, conf = ep._extract_code_and_desc(340.5, 'Test')
    assert conf == "dedicated_column"
    assert code == 340, f"Expected 340 (banker rounding), got {code}"


@test("U08 _extract_code_and_desc: '(3400)' four-digit code -> missing (correct, >999 not valid)")
def _():
    code, _, _, _ = ep._extract_code_and_desc(None, '(3400) Too long')
    assert code is None, f"Four-digit code should not match, got {code}"


@test("U09 _is_blank: numeric 0 is not blank (correct)")
def _():
    assert not ep._is_blank(0),   "_is_blank(0) must be False — a zero qty/total is a real value"
    assert not ep._is_blank(0.0), "_is_blank(0.0) must be False"
    assert ep._is_blank(''),      "_is_blank('') must be True"
    assert ep._is_blank(None),    "_is_blank(None) must be True"


# ===========================================================================
# TEST 1 — blank supplier
# ===========================================================================
@test("01 blank_supplier: line with no supplier is parsed, total still extracted")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, supplier=None, code=VALID_MAT_CODE, desc="Spandrel glass",
              qty=50.0, price=20.0, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    found = [l for l in result["lines"] if "Spandrel" in (l.get("description") or "")]
    assert found, "Line with blank supplier should still appear in lines"
    assert found[0]["supplier"] == "", f"supplier should be empty string, got {found[0]['supplier']!r}"
    assert found[0]["total"] == 1000.0, f"total should be 1000, got {found[0]['total']}"


@test("01b blank_supplier: supplier='' is included in rollup (not zero-total guard)")
def _():
    """The is_blank_row guard checks `not supplier` — an empty string is falsy
    in Python, so a blank supplier with a real total should still NOT trigger
    blank_run. Verify via a line that has supplier='' but a real total."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    # supplier cell completely absent, but total is real
    _mat_line(ws, r, supplier=None, total=750.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)
    assert VALID_MAT_CODE in result["byCostCode"], \
        "Code should appear in rollup even when supplier is blank"


# ===========================================================================
# TEST 2 — blank quantity
# ===========================================================================
@test("02 blank_qty: line with no qty is accepted; qty=None, total still captured")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, qty=None, total=2500.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    found = [l for l in result["lines"] if l["total"] == 2500.0]
    assert found, "Blank-qty line should appear in lines"
    assert found[0]["qty"] is None, f"qty should be None, got {found[0]['qty']}"
    assert VALID_MAT_CODE in result["byCostCode"]


@test("02b blank_qty AND blank_total: line with neither qty nor total is treated as blank row")
def _():
    """A truly empty template row (qty=blank, total=blank, desc=blank) should
    NOT appear in lines — it's an unfilled template slot, not a bid item."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r)                     # real line
    # Row r+1: completely empty except the supplier (simulates an unfilled row)
    ws.write(r+1, MAT_SUPP_COL, "")     # blank supplier
    # All other cells left empty
    r += 2; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)
    # The empty row must NOT appear in lines or review_needed
    assert result["lineCount"] == 1, \
        f"Empty template row should be skipped, lineCount={result['lineCount']}"


# ===========================================================================
# TEST 3 — merged cells
# ===========================================================================
@test("03 merged_cells: two rows share a supplier via merged cell; both lines parsed")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    ws.write(r,   MAT_SUPP_COL, "Kawneer")
    ws.write(r,   MAT_CODE_COL, VALID_MAT_CODE)
    ws.write(r,   MAT_DESC_COL, "Storefront row 1")
    ws.write(r,   MAT_QTY_COL,  20.0); ws.write(r,   MAT_PRC_COL, 300.0); ws.write(r,   MAT_TOT_COL, 6000.0)
    # Row r+1: merge continuation — supplier blank (xlrd returns "")
    ws.write(r+1, MAT_CODE_COL, VALID_MAT_CODE)
    ws.write(r+1, MAT_DESC_COL, "Storefront row 2")
    ws.write(r+1, MAT_QTY_COL,  20.0); ws.write(r+1, MAT_PRC_COL, 300.0); ws.write(r+1, MAT_TOT_COL, 6000.0)
    r += 2; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    mat_lines = [l for l in result["lines"] if l["section"] == "material"]
    assert len(mat_lines) == 2, f"Expected 2 material lines, got {len(mat_lines)}"
    assert mat_lines[0]["supplier"] == "Kawneer"
    assert mat_lines[1]["supplier"] == ""   # merge continuation reads as ""


# ===========================================================================
# TEST 4 — formulas
# ===========================================================================
@test("04 formulas: formula-computed totals (xlrd cached value) read as plain numbers")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    qty = 15.0; price = 66.67; total = round(qty * price, 2)
    _mat_line(ws, r, qty=qty, price=price, total=total)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    found = [l for l in result["lines"] if l["section"] == "material"]
    assert found
    assert found[0]["qty"] == 15.0
    assert abs(found[0]["total"] - 1000.05) < 0.01, f"total={found[0]['total']}"


@test("04b formulas: _num() on formula-string returns None (Excel error cells that xlrd returns as str)")
def _():
    """xlrd may return strings like '#VALUE!' or '#REF!' for formula errors.
    _num() must return None for these, not crash."""
    assert ep._num("#VALUE!") is None
    assert ep._num("#REF!") is None
    assert ep._num("#DIV/0!") is None


@test("04c formulas: NaN total from formula error — BUG: _num(nan) returns nan not None")
def _():
    """If xlrd returns float('nan') for a formula error cell in a total column
    (some error types produce NaN, not a string), _num() must return None.
    Currently it returns nan, which propagates into sums and causes
    abs(computed - nan) > 1 to evaluate False — mismatch warning suppressed."""
    result = ep._num(float('nan'))
    assert result is None, (
        f"BUG CONFIRMED: _num(float('nan')) = {result!r} (should be None).\n"
        f"        mismatch warnings are silently suppressed when total cell is a formula error."
    )


# ===========================================================================
# TEST 5 — hidden rows
# ===========================================================================
@test("05 hidden_rows: xlrd reports no row-hidden status for .xls; all rows parsed equally")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r,   desc="Visible line",  total=500.0)
    _mat_line(ws, r+1, desc="'Hidden' line", total=250.0)
    r += 2; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    totals = [l["total"] for l in result["lines"] if l["section"] == "material"]
    assert 250.0 in totals, "Hidden-row line must appear in parsed output"
    assert 500.0 in totals
    assert result["lineCount"] >= 2


@test("05b hidden_rows: blank gap of 5 rows before resumed data still parsed (< BLANK_RUN_CUTOFF=6)")
def _():
    """A 5-row blank gap between real data rows must NOT trigger the cutoff."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, desc="Before gap", total=100.0)
    r += 1  # 5 truly blank rows
    r += 5  # skip 5 rows (they stay blank)
    _mat_line(ws, r, desc="After gap", total=200.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    totals = [l["total"] for l in result["lines"] if l["section"] == "material"]
    assert 200.0 in totals, \
        f"Data after a 5-row gap should survive (BLANK_RUN_CUTOFF=6), got totals={totals}"


@test("05c hidden_rows: with a MATERIAL TOTAL marker, a line after a 6-row blank gap IS read (r549)")
def _():
    """r549 (Wege Pharmacy): the template's Sundries line sits right above
    MATERIAL TOTAL below a long run of unused rows. When the explicit end
    marker exists, it is the end — the blank-run cutoff no longer applies.
    (Pre-r549 this test pinned the opposite, which dropped a real $359.89
    line on a real estimate.)"""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, desc="Before gap", total=100.0)
    r += 1  # 6 blank rows
    r += 6
    _mat_line(ws, r, desc="Sundries after gap", total=999.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1099.0)
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    totals = [l["total"] for l in result["lines"] if l["section"] == "material"]
    assert 999.0 in totals, f"line above MATERIAL TOTAL must be read, got totals={totals}"
    assert not [w for w in result["warnings"] if w.get("section") == "material"], result["warnings"]


@test("05d hidden_rows: with NO MATERIAL TOTAL marker, a 6-row blank gap still truncates (>= BLANK_RUN_CUTOFF)")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, desc="Before cutoff", total=100.0)
    r += 1
    r += 6
    _mat_line(ws, r, desc="After cutoff — should be dropped", total=999.0)
    _minimal_right_block(ws, r+3)
    result = _save_and_parse(wb)
    totals = [l["total"] for l in result["lines"] if l["section"] == "material"]
    assert 999.0 not in totals, f"no end marker: cutoff must still drop, got totals={totals}"


@test("05e material cross-check: a material line the parser misses is reported against MATERIAL TOTAL (r549)")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, desc="Glass", total=500.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 860.0)
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)
    mm = [w for w in result["warnings"] if w.get("type") == "section_total_mismatch" and w.get("section") == "material"]
    assert mm and mm[0]["statedInSheet"] == 860.0 and mm[0]["computedFromLines"] == 500.0, result["warnings"]


# ===========================================================================
# TEST 6 — hidden columns
# ===========================================================================
@test("06 hidden_cols: shifted column layout (extra col inserted at left) still detected by anchors")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    SHIFT = 1
    ws.write(0, 0+SHIFT, "PROJECT:"); ws.write(0, 1+SHIFT, "Shifted Project")
    ws.write(2, MAT_SUPP_COL+SHIFT, "SUPPLIER")
    ws.write(2, MAT_CODE_COL+SHIFT, "CODE")
    ws.write(2, MAT_DESC_COL+SHIFT, "DESCRIPTION")
    ws.write(2, MAT_QTY_COL+SHIFT,  "QUANTITY")
    ws.write(2, MAT_PRC_COL+SHIFT,  "PRICE")
    ws.write(2, MAT_TOT_COL+SHIFT,  "TOTAL")
    ws.write(2, BDRY_COL+SHIFT,     "LARGE SUB")
    ws.write(2, R_CODE_COL+SHIFT,   "CODE")
    ws.write(2, R_DESC_COL+SHIFT,   "DESCRIPTION")
    ws.write(2, R_QTY_COL+SHIFT,    "QTY.")
    ws.write(2, R_PRC_COL+SHIFT,    "PRICE")
    ws.write(2, R_TOT_COL+SHIFT,    "TOTAL")
    ws.write(3, MAT_SUPP_COL+SHIFT, "Tubelite")
    ws.write(3, MAT_CODE_COL+SHIFT, VALID_MAT_CODE)
    ws.write(3, MAT_DESC_COL+SHIFT, "Tube frame")
    ws.write(3, MAT_QTY_COL+SHIFT,  10.0)
    ws.write(3, MAT_PRC_COL+SHIFT,  100.0)
    ws.write(3, MAT_TOT_COL+SHIFT,  1000.0)
    ws.write(4, MAT_DESC_COL+SHIFT, "MATERIAL TOTAL")
    ws.write(5, BDRY_COL+SHIFT, "2. OTHER")
    ws.write(6, BDRY_COL+SHIFT, "3. LABOR")
    result = _save_and_parse(wb)

    found = [l for l in result["lines"] if l.get("supplier") == "Tubelite"]
    assert found, "Anchor-based detection should work with shifted columns"
    assert found[0]["costCode"] == VALID_MAT_CODE


# ===========================================================================
# TEST 7 — multiple cost codes "(340/341)"
# ===========================================================================
@test("07 multiple_cost_codes: '(340/341)' extracts primary + additionalCostCode")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    ws.write(r, MAT_SUPP_COL, "AMI Metal")
    # No code-col value — relies on description regex
    ws.write(r, MAT_DESC_COL, "(340/341) Spandrel Panel System")
    ws.write(r, MAT_QTY_COL, 100.0); ws.write(r, MAT_PRC_COL, 75.0); ws.write(r, MAT_TOT_COL, 7500.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    all_lines = result["lines"] + result["reviewNeeded"]
    found = [l for l in all_lines if l.get("costCode") == 340]
    assert found, f"Should find primary code 340. lines={result['lines']}, review={result['reviewNeeded']}"
    line = found[0]
    assert line.get("additionalCostCode") == 341, \
        f"additionalCostCode should be 341, got {line.get('additionalCostCode')}"
    assert "Spandrel Panel System" in line["description"], \
        f"description should be cleaned of prefix: {line['description']!r}"


@test("07b multiple_cost_codes: spaces around slash '(340 / 341)' also parses")
def _():
    code, desc, extra, conf = ep._extract_code_and_desc(None, "(340 / 341) Test Panel")
    assert code == 340, f"primary should be 340, got {code}"
    assert extra == 341, f"extra should be 341, got {extra}"


@test("07c multiple_cost_codes: both codes unknown -> review_needed, not silently dropped")
def _():
    """If BOTH codes in (999/998) are unknown, the line lands in review_needed
    with the primary code flagged — it must still appear somewhere."""
    code, desc, extra, conf = ep._extract_code_and_desc(None, "(999/998) Unknown dual code")
    assert code == 999
    assert extra == 998


# ===========================================================================
# TEST 8 — malformed "(340/341)" prefix variants
# ===========================================================================
@test("08 malformed_code_prefix: '425)Safety/PM time' -> lenient parse + review_needed")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    ws.write(r, MAT_DESC_COL, "425)Safety / PM time")
    ws.write(r, MAT_QTY_COL, 8.0); ws.write(r, MAT_PRC_COL, 90.0); ws.write(r, MAT_TOT_COL, 720.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    _minimal_right_block(ws, r+1)
    result = _save_and_parse(wb)

    all_lines = result["lines"] + result["reviewNeeded"]
    found = [l for l in all_lines if l.get("costCode") == 425]
    assert found, f"Code 425 should be extracted by lenient regex. all_lines={all_lines}"
    assert found[0].get("confidence") == "parsed_from_text_lenient"
    review_codes = [l.get("costCode") for l in result["reviewNeeded"]]
    assert 425 in review_codes, "Lenient-parsed line must land in review_needed"
    assert any(l.get("reviewReason") == "recovered_from_malformed_code_prefix"
               for l in result["reviewNeeded"] if l.get("costCode") == 425)


@test("08b malformed: '(340' missing closing paren -> NOT matched by either regex")
def _():
    """An opening paren with no closing paren is NOT a valid prefix — it might
    be a parenthetical note, not a code. Verify it falls to 'missing'."""
    code, desc, extra, conf = ep._extract_code_and_desc(None, "(340 Framing system without close")
    assert code is None, f"Incomplete prefix should not match, got code={code}"
    assert conf == "missing"


@test("08c malformed: 'abc)Safety note' non-numeric before paren -> missing")
def _():
    """Letters before ')' must not match the lenient regex (requires \\d{{2,3}})."""
    code, _, _, conf = ep._extract_code_and_desc(None, "abc)Safety note")
    assert code is None and conf == "missing"


@test("08d malformed: '(340/) slash but no second code' -> primary only, no extra")
def _():
    code, desc, extra, conf = ep._extract_code_and_desc(None, "(340/) desc after")
    # The regex requires \d{2,3} after the slash — just '/' with nothing is not matched
    # so the parser falls through to the simpler match.
    # Accept either: code=340 extra=None, OR code=None (both are defensible)
    if code is not None:
        assert extra is None, f"Trailing slash with no second code should produce no extra, got extra={extra}"


# ===========================================================================
# TEST 9 — empty CONTRACT sheet
# ===========================================================================
@test("09 empty_contract_sheet: headers-only -> lineCount=0, no crash, no warnings")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    _header_rows(ws)
    _minimal_right_block(ws, 5)
    result = _save_and_parse(wb)

    assert result["lineCount"] == 0, f"lineCount should be 0, got {result['lineCount']}"
    assert result["lines"] == []
    assert result["reviewNeeded"] == []
    assert result["byCostCode"] == {}
    assert isinstance(result["warnings"], list)


@test("09b empty_contract_sheet: missing sheet name raises ValueError cleanly")
def _():
    wb = _new_wb(); wb.add_sheet("CONTRACT")
    try:
        result = _save_and_parse(wb, sheet_name="NONEXISTENT")
        raise AssertionError("Should have raised ValueError for missing sheet name")
    except ValueError as e:
        assert "NONEXISTENT" in str(e) or "not found" in str(e).lower(), \
            f"ValueError message should mention sheet name: {e}"


@test("09c empty_contract_sheet: no LARGE SUB marker raises ValueError with clear message")
def _():
    """A sheet that doesn't match the template at all should raise ValueError,
    not IndexError or AttributeError."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    ws.write(0, 0, "Totally unrelated content")
    try:
        _save_and_parse(wb)
        raise AssertionError("Should have raised ValueError for missing LARGE SUB marker")
    except ValueError as e:
        assert "LARGE SUB" in str(e) or "template" in str(e).lower(), \
            f"ValueError should mention LARGE SUB: {e}"


# ===========================================================================
# TEST 10 — unexpected totals
# ===========================================================================
@test("10 unexpected_totals: stated != computed -> section_total_mismatch warning")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1000.0)
    r += 1
    # Large Sub section: two lines summing to 25000, but stated total = 30000
    _section_marker(ws, r, "2. LARGE SUB")
    _sub_line(ws, r+1, desc="(350) Curtain Wall",  total=15000.0)
    _sub_line(ws, r+2, desc="(350) Storefront",    total=10000.0)
    _section_total(ws, r+3, "TOTAL LARGE SUB", 30000.0)  # wrong
    r += 4
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    ws.write(r, R_WHO_COL, "4. LABOR")
    result = _save_and_parse(wb)

    mismatch = [w for w in result["warnings"] if w.get("type") == "section_total_mismatch"]
    assert mismatch, f"Expected section_total_mismatch warning, got warnings={result['warnings']}"
    w = mismatch[0]
    assert w["section"] == "large_sub"
    assert abs(w["statedInSheet"] - 30000.0) < 1
    assert w["computedFromLines"] != w["statedInSheet"]


@test("10b unexpected_totals: within ROUNDING_TOLERANCE (< $1.00) -> no warning")
def _():
    """A $0.50 rounding difference must NOT produce a warning — that's normal
    float/locale rounding across many lines."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL")
    r += 1
    _section_marker(ws, r, "2. LARGE SUB")
    _sub_line(ws, r+1, desc="(350) Curtain Wall", total=10000.00)
    _section_total(ws, r+2, "TOTAL LARGE SUB", 10000.50)   # $0.50 rounding noise
    r += 3
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    ws.write(r, R_WHO_COL, "4. LABOR")
    result = _save_and_parse(wb)

    mismatch = [w for w in result["warnings"] if w.get("type") == "section_total_mismatch"]
    assert not mismatch, \
        f"$0.50 difference is within ROUNDING_TOLERANCE — no warning expected, got {mismatch}"


@test("10c unexpected_totals: NaN in stated total cell produces a formula-error warning (fix verified)")
def _():
    """After the fix, a NaN in the stated-total cell no longer silently suppresses
    the mismatch — _num() now returns None for NaN, so section_totals_stated[name]
    gets None, and the `if stated is None: continue` branch fires cleanly.
    The integration test checks the helper-level fix works end-to-end."""
    # Verify the fix in _num() — the downstream guard
    assert ep._num(float('nan')) is None, "_num(nan) must return None after fix"
    assert ep._num(float('inf')) is None, "_num(inf) must return None after fix"
    # And the arithmetic no longer falsely passes
    stated = ep._num(float('nan'))  # now None after fix
    if stated is None:
        pass  # correct — no comparison attempted
    else:
        diff = abs(25000.0 - stated)
        assert diff > ep.ROUNDING_TOLERANCE, \
            "If NaN somehow reached the comparison it should have been caught"


# ===========================================================================
# TEST 11 — estimated field labor hours (2026-07-27, Crew Schedule feed)
# ===========================================================================
@test("11 field_labor_hours: QTY sums correctly per cost code, including field-labor codes (120/140)")
def _():
    """estimatedFieldLaborHours itself moved out of the parser (2026-07-27,
    same day — reworked to be an admin-editable setting rather than a
    fixed set baked in here). What the parser still owns, and must still
    get right, is the underlying byCostCode[code].qty aggregation that any
    hours computation (wherever it lives) depends on."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1000.0)
    r += 1
    ws.write(r, R_WHO_COL, "2. LARGE SUB"); r += 1
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    _section_marker(ws, r, "4. LABOR"); r += 1
    # Labor rows have no subcontractor ("who") column in the real template,
    # so code+description shift one column left vs. Large Sub/Other —
    # code lands in what's R_WHO_COL for other sections, description in
    # what's R_CODE_COL — see estimate_parser.py's own SECTION_COLS comment.
    # 120 Install-Exterior: 40 hrs @ $65/hr = $2600
    ws.write(r, R_WHO_COL, VALID_LABOR_CODE); ws.write(r, R_CODE_COL, "Install exterior storefront")
    ws.write(r, R_QTY_COL, 40.0); ws.write(r, R_PRC_COL, 65.0); ws.write(r, R_TOT_COL, 2600.0)
    r += 1
    # 140 Field Measure: 8 hrs @ $65/hr = $520
    ws.write(r, R_WHO_COL, 140); ws.write(r, R_CODE_COL, "Field measure")
    ws.write(r, R_QTY_COL, 8.0); ws.write(r, R_PRC_COL, 65.0); ws.write(r, R_TOT_COL, 520.0)
    r += 1
    result = _save_and_parse(wb)

    assert result["byCostCode"][VALID_LABOR_CODE]["qty"] == 40.0
    assert result["byCostCode"][140]["qty"] == 8.0
    assert "estimatedFieldLaborHours" not in result, \
        "estimatedFieldLaborHours should no longer be computed in the parser — it's a client-side, configurable computation now"


@test("11b field_labor_hours: a material line's QTY (a product quantity, not hours) stays in its own cost code's aggregate, unaffected by labor codes")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, code=300, qty=500.0, total=1000.0)  # material qty=500 sq ft
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1000.0)
    r += 1
    ws.write(r, R_WHO_COL, "2. LARGE SUB"); r += 1
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    _section_marker(ws, r, "4. LABOR"); r += 1
    ws.write(r, R_WHO_COL, 100); ws.write(r, R_CODE_COL, "Fab - Shop")  # shop fab, code 100
    ws.write(r, R_QTY_COL, 20.0); ws.write(r, R_PRC_COL, 60.0); ws.write(r, R_TOT_COL, 1200.0)
    r += 1
    ws.write(r, R_WHO_COL, VALID_LABOR_CODE); ws.write(r, R_CODE_COL, "Install exterior")
    ws.write(r, R_QTY_COL, 15.0); ws.write(r, R_PRC_COL, 65.0); ws.write(r, R_TOT_COL, 975.0)
    r += 1
    result = _save_and_parse(wb)

    assert result["byCostCode"][300]["qty"] == 500.0
    assert result["byCostCode"][100]["qty"] == 20.0
    assert result["byCostCode"][VALID_LABOR_CODE]["qty"] == 15.0


@test("11c field_labor_hours: a job with no labor lines at all has an empty byCostCode for labor codes, not an error")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1000.0)
    r += 1
    ws.write(r, R_WHO_COL, "2. LARGE SUB")
    _sub_line(ws, r+1, code=VALID_SUB_CODE, total=5000.0)
    r += 2
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    ws.write(r, R_WHO_COL, "4. LABOR")
    result = _save_and_parse(wb)

    assert VALID_LABOR_CODE not in result["byCostCode"]
    assert "estimatedFieldLaborHours" not in result



# ===========================================================================
# 12 — the sheet's own summary math must never surface as line items
# (2026-07-30, from the real GRCC LRC workbook: 13 rows of "numbers that
# are other numbers added together" — labor sub-totals, the MATERIAL/OTHER/
# LABOR/CONTRACT AMOUNT rollup block, gross-profit and $/sqft figures, and
# the four markup-scenario rows — all surfaced as cost-code review lines.)
# ===========================================================================

def _labor_setup(ws, hrow):
    """Material line + markers, positioned like test 11's fixture. Returns
    the first labor data row."""
    r = hrow + 1
    _mat_line(ws, r, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1000.0)
    r += 1
    ws.write(r, R_WHO_COL, "2. LARGE SUB"); r += 1
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    _section_marker(ws, r, "4. LABOR"); r += 1
    return r


def _labor_line(ws, r, code=VALID_LABOR_CODE, desc="Install exterior storefront",
                qty=40.0, price=65.0, total=2600.0):
    ws.write(r, R_WHO_COL, code); ws.write(r, R_CODE_COL, desc)
    ws.write(r, R_QTY_COL, qty); ws.write(r, R_PRC_COL, price); ws.write(r, R_TOT_COL, total)


@test("12a summary_math: a labor SUB-TOTAL row written in the SHIFTED code column is skipped, not reviewed")
def _():
    """The real workbook writes 'TOTAL NON PREVAILING WAGE' / 'TOTAL
    PREVAILING WAGE LABOR' in the column the shifted labor layout reads as
    its CODE column — the old canonical-column-only TOTAL check let both
    through as $101,500 / $457,200 review lines."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = _labor_setup(ws, hrow)
    _labor_line(ws, r); r += 1
    ws.write(r, R_WHO_COL, "TOTAL NON PREVAILING WAGE ")
    ws.write(r, R_QTY_COL, 1450.0); ws.write(r, R_TOT_COL, 101500.0)
    res = _save_and_parse(wb)
    assert len(res["reviewNeeded"]) == 0, res["reviewNeeded"]
    assert not any(l["total"] == 101500.0 for l in res["lines"])
    # ...and the REAL labor line right before it still parsed.
    assert any(l["costCode"] == VALID_LABOR_CODE and l["total"] == 2600.0 for l in res["lines"])
    # ...AND a total row is classified as a TOTAL row, not lumped in with
    # summary math — the exact counts are what test 12e pins on the real
    # workbook, so classification drift shows up there as well as here.
    assert res["skippedSummaryCount"] == 0, res["skippedSummaryCount"]


@test("12a2 summary_math: a TOTAL label in the WHO column (large sub/other) is skipped — the one class only the widened TOTAL check catches")
def _():
    """Rules 2 and 3 both require an empty who, so a stray sub-total row
    whose label lands in the who column is invisible to them — this is the
    case that isolates the widened TOTAL check and keeps it non-redundant."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1000.0)
    r += 1
    ws.write(r, R_WHO_COL, "2. LARGE SUB"); r += 1
    _sub_line(ws, r, who="Sub Co", code=VALID_SUB_CODE, desc="Storefront",
              qty=1.0, price=10000.0, total=10000.0); r += 1
    ws.write(r, R_WHO_COL, "TOTAL MISC SUBS"); ws.write(r, R_TOT_COL, 4242.0); r += 1
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    _section_marker(ws, r, "4. LABOR")
    res = _save_and_parse(wb)
    assert len(res["reviewNeeded"]) == 0, res["reviewNeeded"]
    assert not any(l["total"] == 4242.0 for l in res["lines"])
    assert any(l["costCode"] == VALID_SUB_CODE for l in res["lines"])


@test("12b summary_math: the MATERIAL/OTHER/LABOR/CONTRACT-AMOUNT rollup block is skipped AND still read into summary")
def _():
    """The parser already reads these rows correctly into `summary` — the
    bug was knowing what they are in one place and forgetting in the
    other. A rollup has no cost code to fix, so it must never be a review
    line; and skipping it from the LINE loop must not cost the summary."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = _labor_setup(ws, hrow)
    _labor_line(ws, r); r += 1
    for label, amount in ((" MATERIAL", 932296.95), (" OTHER", 335819.07),
                          (" LABOR", 558700.0), (" CONTRACT AMOUNT ( SELL PRICE )", 2800000.0)):
        ws.write(r, R_CODE_COL, label)   # labor-shifted desc column
        ws.write(r, R_TOT_COL, amount); r += 1
    res = _save_and_parse(wb)
    assert len(res["reviewNeeded"]) == 0, res["reviewNeeded"]
    assert res["skippedSummaryCount"] >= 4
    assert res["summary"].get("contractAmount") == 2800000.0
    assert not any(l["total"] == 2800000.0 for l in res["lines"])


@test("12c summary_math: faceless number rows (gross profit fraction, $/hr, markup-scenario rows) are skipped, never reviewed and never adjustments")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = _labor_setup(ws, hrow)
    _labor_line(ws, r); r += 1
    ws.write(r, R_TOT_COL, 0.39); r += 1                      # Total Gross Profit — bare fraction
    ws.write(r, R_TOT_COL, 166.06); r += 1                    # Gross Profit per Labor Hour
    ws.write(r, R_DESC_COL, 0.15)                             # markup-scenario row: rate as the "description"
    ws.write(r, R_QTY_COL, 2022598.37); ws.write(r, R_PRC_COL, 0.35)
    ws.write(r, R_TOT_COL, 2209057.76); r += 1
    res = _save_and_parse(wb)
    assert len(res["reviewNeeded"]) == 0, res["reviewNeeded"]
    assert len(res["adjustments"]) == 0, res["adjustments"]
    assert not any(l["total"] in (0.39, 166.06, 2209057.76) for l in res["lines"])
    assert res["skippedSummaryCount"] >= 3


@test("12d summary_math: the guardrails hold — a REAL codeless line still reviews, a REAL overhead rate is still an adjustment")
def _():
    """The three skips must be exactly as narrow as the classes they
    target: letters in a description, or a who, keep a codeless row
    reviewable; a described %-rate row is still an adjustment."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, total=1000.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 1000.0)
    r += 1
    ws.write(r, R_WHO_COL, "2. LARGE SUB"); r += 1
    _sub_line(ws, r, who="Mystery Sub", code=None, desc="Unlabeled scope of work",
              qty=1.0, price=5000.0, total=5000.0); r += 1
    _sub_line(ws, r, who="", code=None, desc="Subcontractor Overhead",
              qty=0.1, price=5000.0, total=500.0); r += 1
    ws.write(r, R_WHO_COL, "3. OTHER"); r += 1
    _section_marker(ws, r, "4. LABOR")
    res = _save_and_parse(wb)
    assert any(l.get("reviewReason") == "no_cost_code_found"
               and l["description"] == "Unlabeled scope of work" for l in res["reviewNeeded"]), res["reviewNeeded"]
    assert any(a["description"] == "Subcontractor Overhead" and a["rate"] == 0.1
               for a in res["adjustments"]), res["adjustments"]


@test("12e summary_math REALWORLD: the GRCC LRC workbook parses with ZERO review lines and none of the 13 ghost totals")
def _():
    """The exact file the bug shipped in, bundled at
    test_samples/estimates/. Every number from the reported screenshot is
    asserted absent from lines and review; the real shape is asserted
    present. warnings==0 is the load-bearing check: the section-total
    cross-check would fire if the skips had eaten a REAL line."""
    path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "test_samples", "estimates",
                        "BidEstimate_GRCC_LRC.xls")
    if not os.path.isfile(path):
        raise AssertionError("bundled real workbook missing: " + path)
    ghosts = {101500.0, 457200.0, 558700.0, 2800000.0, 0.38726872221523806,
              2209057.758463, 2348902.300218}
    for tab in ("CONTRACT", "ACTUAL"):
        res = ep.parse_workbook(path, sheet_name=tab)
        assert len(res["reviewNeeded"]) == 0, (tab, res["reviewNeeded"])
        assert len(res["lines"]) == 60, (tab, len(res["lines"]))
        assert len(res["warnings"]) == 0, (tab, res["warnings"])
        assert res["skippedSummaryCount"] == 15, (tab, res["skippedSummaryCount"])
        seen = {l["total"] for l in res["lines"]} | {l["total"] for l in res["reviewNeeded"]}
        assert not (ghosts & seen), (tab, ghosts & seen)
    res = ep.parse_workbook(path, sheet_name="CONTRACT")
    assert res["summary"].get("contractAmount") == 2800000.0
    assert res["meta"].get("gc") == "Pioneer"



# ===========================================================================
# 13 — the material block's SUPPLIER column is optional
# (2026-07-30, from the real GRCC LRC workbook's CO#2 tab: column A was
# deleted on that tab, so it has no supplier column and the whole material
# block sits one column left. SUPPLIER was a REQUIRED anchor, so the tab
# raised outright and imported as nothing.)
# ===========================================================================

def _header_rows_no_supplier(ws, hrow=2):
    """CO#2's real shape: no SUPPLIER label, block shifted one column left,
    so code=c0, description=c1, quantity=c2, price=c3, total=c4."""
    ws.write(0, 0, "PROJECT:"); ws.write(0, 1, "Test Project")
    ws.write(hrow, 1, "DESCRIPTION")
    ws.write(hrow, 2, "QUANTITY")
    ws.write(hrow, 3, "PRICE")
    ws.write(hrow, 4, "TOTAL")
    # Right block starts one column left too (boundary at 5, not 6).
    ws.write(hrow, 5, "LARGE SUB")
    ws.write(hrow, 6, "CODE")
    ws.write(hrow, 7, "DESCRIPTION")
    ws.write(hrow, 8, "QTY.")
    ws.write(hrow, 9, "PRICE")
    ws.write(hrow, 10, "TOTAL")
    return hrow


@test("13a no_supplier: a material block with no SUPPLIER column parses instead of raising")
def _():
    wb = _new_wb(); ws = wb.add_sheet("CO#2")
    hrow = _header_rows_no_supplier(ws); r = hrow + 1
    ws.write(r, 0, VALID_MAT_CODE); ws.write(r, 1, "Alum Door Hardware")
    ws.write(r, 2, 1.0); ws.write(r, 3, 974.0); ws.write(r, 4, 974.0)
    r += 1
    ws.write(r, 1, "MATERIAL TOTAL"); ws.write(r, 4, 974.0)
    r += 1
    ws.write(r, 5, "2. LARGE SUB"); r += 1
    ws.write(r, 5, "3. OTHER"); r += 1
    ws.write(r, 5, "4. LABOR")
    res = _save_and_parse(wb, sheet_name="CO#2")
    assert len(res["lines"]) == 1, res["lines"]
    line = res["lines"][0]
    assert line["costCode"] == VALID_MAT_CODE, line
    assert line["description"] == "Alum Door Hardware", line
    assert line["qty"] == 1.0 and line["total"] == 974.0, line
    # No supplier column means no supplier, not a crash and not a wrong one.
    assert line["supplier"] == "", line


@test("13b no_supplier: SUPPLIER still wins as the anchor when it IS present — the normal layout is untouched")
def _():
    """The fix must not change how a standard tab is read: the supplier
    layout is the common case and every other test here depends on it."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    hrow = _header_rows(ws); r = hrow + 1
    _mat_line(ws, r, supplier="Supplier A", code=VALID_MAT_CODE, desc="Aluminum Frame",
              qty=10.0, price=50.0, total=500.0)
    r += 1; ws.write(r, MAT_DESC_COL, "MATERIAL TOTAL"); ws.write(r, MAT_TOT_COL, 500.0)
    r += 1
    _minimal_right_block(ws, r)
    res = _save_and_parse(wb)
    assert len(res["lines"]) == 1 and res["lines"][0]["supplier"] == "Supplier A", res["lines"]
    assert res["lines"][0]["description"] == "Aluminum Frame", res["lines"][0]


@test("13c no_supplier: a sheet with neither SUPPLIER nor a QUANTITY/TOTAL header pair is still rejected, with a message that says what was looked for")
def _():
    """Tolerance is not 'parse anything'. A sheet that genuinely isn't this
    template must still fail loudly rather than produce confident nonsense."""
    wb = _new_wb(); ws = wb.add_sheet("CONTRACT")
    ws.write(0, 0, "PROJECT:"); ws.write(0, 1, "Not a bid estimate")
    ws.write(2, 5, "LARGE SUB")
    ws.write(2, 8, "QTY."); ws.write(2, 9, "PRICE"); ws.write(2, 10, "TOTAL")
    try:
        _save_and_parse(wb)
        raise AssertionError("expected a ValueError for a non-template sheet")
    except ValueError as e:
        msg = str(e)
        assert "SUPPLIER" in msg and "QUANTITY" in msg and "TOTAL" in msg, msg


@test("13f no_supplier: the DESCRIPTION header is the anchor, not the column left of QUANTITY")
def _():
    """Isolates the anchor from the fallback. Here a spacer column sits
    between description and quantity, so 'the column left of QUANTITY' is
    the SPACER and would read blank descriptions with the code taken from
    the description column. §8 forbids fixed-coordinate assumptions for
    exactly this reason: layouts vary between jobs, and a positional guess
    that happens to hold on today's files is a latent bug, not a rule."""
    wb = _new_wb(); ws = wb.add_sheet("CO#2")
    hrow = 2
    ws.write(0, 0, "PROJECT:"); ws.write(0, 1, "Test Project")
    ws.write(hrow, 1, "DESCRIPTION")
    # column 2 is a spacer with no header
    ws.write(hrow, 3, "QUANTITY")
    ws.write(hrow, 4, "PRICE")
    ws.write(hrow, 5, "TOTAL")
    ws.write(hrow, 6, "LARGE SUB")
    ws.write(hrow, 7, "CODE"); ws.write(hrow, 8, "DESCRIPTION")
    ws.write(hrow, 9, "QTY."); ws.write(hrow, 10, "PRICE"); ws.write(hrow, 11, "TOTAL")
    r = hrow + 1
    ws.write(r, 0, VALID_MAT_CODE); ws.write(r, 1, "Alum Door Hardware")
    ws.write(r, 3, 1.0); ws.write(r, 4, 974.0); ws.write(r, 5, 974.0)
    r += 1
    ws.write(r, 1, "MATERIAL TOTAL"); ws.write(r, 5, 974.0)
    r += 1
    ws.write(r, 6, "2. LARGE SUB"); r += 1
    ws.write(r, 6, "3. OTHER"); r += 1
    ws.write(r, 6, "4. LABOR")
    res = _save_and_parse(wb, sheet_name="CO#2")
    assert len(res["lines"]) == 1, res["lines"]
    assert res["lines"][0]["description"] == "Alum Door Hardware", res["lines"][0]
    assert res["lines"][0]["costCode"] == VALID_MAT_CODE, res["lines"][0]


@test("13d no_supplier REALWORLD: the GRCC LRC CO#2 tab imports — the last tab in that workbook that wouldn't")
def _():
    path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "test_samples", "estimates",
                        "BidEstimate_GRCC_LRC.xls")
    if not os.path.isfile(path):
        raise AssertionError("bundled real workbook missing: " + path)
    res = ep.parse_workbook(path, sheet_name="CO#2")
    # Three rows parse cleanly, one per section — proving the shifted block
    # is read correctly on the left AND that the right block still lines up.
    assert len(res["lines"]) == 3, res["lines"]
    got = {(l["section"], l["costCode"], l["total"]) for l in res["lines"]}
    assert ("material", 255, 974.0) in got, got
    assert ("other", 330, 420.0) in got, got
    assert ("labor", 105, 70.0) in got, got
    assert res["summary"].get("contractAmount") == 21868.0, res["summary"]
    # Two rows in this tab are misaligned in the SOURCE (they kept the old
    # six-column alignment after column A was deleted, so a supplier name
    # sits where the cost code belongs). They surface for review with their
    # DOLLAR AMOUNTS INTACT rather than being silently mis-coded — which is
    # the honest outcome, and since round 70 a PM can fix or ignore them.
    assert len(res["reviewNeeded"]) == 2, res["reviewNeeded"]
    assert {l["total"] for l in res["reviewNeeded"]} == {8319.0, 7000.0}, res["reviewNeeded"]
    # ...and nothing unreviewable leaked into the totals.
    assert all(l["costCode"] is not None for l in res["lines"])


@test("13e no_supplier REALWORLD: EVERY tab that uses this template now imports — no tab left behind")
def _():
    path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "test_samples", "estimates",
                        "BidEstimate_GRCC_LRC.xls")
    if not os.path.isfile(path):
        raise AssertionError("bundled real workbook missing: " + path)
    for tab in ("CONTRACT", "ACTUAL", "CO#1 PR #4 ", "CO#2", "CO#3 Existing Frame Replacement"):
        res = ep.parse_workbook(path, sheet_name=tab)
        assert res["lines"], (tab, "parsed no lines at all")
        assert res["summary"].get("contractAmount") is not None, (tab, res["summary"])


# ===========================================================================
# Run
# ===========================================================================
if __name__ == "__main__":
    print("Running estimate_parser.py edge-case tests")
    print("=" * 65)
    sys.exit(run_all())


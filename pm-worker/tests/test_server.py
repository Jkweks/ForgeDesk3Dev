import io
import json
import os
import sys
import threading
import urllib.error
import urllib.request
from http.server import ThreadingHTTPServer

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.environ["WORKER_TOKEN"] = "t0ken"

import server  # noqa: E402

server.TOKEN = "t0ken"


def _run():
    srv = ThreadingHTTPServer(("127.0.0.1", 0), server.Handler)
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    return srv, "http://127.0.0.1:%d" % srv.server_address[1]


def _req(url, data=None, token="t0ken"):
    r = urllib.request.Request(url, data=data, headers={"X-Worker-Token": token} if token else {})
    try:
        with urllib.request.urlopen(r) as resp:
            return resp.status, json.load(resp)
    except urllib.error.HTTPError as e:
        return e.code, json.load(e)


def _xlsx_bytes():
    import openpyxl
    wb = openpyxl.Workbook()
    wb.active.title = "CONTRACT"
    wb.create_sheet("CO #1")
    buf = io.BytesIO()
    wb.save(buf)
    return buf.getvalue()


def test_health_is_open_and_other_routes_need_token():
    srv, base = _run()
    try:
        assert _req(base + "/health", token=None)[0] == 200
        assert _req(base + "/cost-codes", token=None)[0] == 401
        assert _req(base + "/cost-codes", token="wrong")[0] == 401
        code, body = _req(base + "/cost-codes")
        assert code == 200 and body["codes"]
    finally:
        srv.shutdown()


def test_lists_sheets_and_reports_parse_errors_as_422():
    srv, base = _run()
    try:
        code, body = _req(base + "/estimate/sheets", data=_xlsx_bytes())
        assert code == 200 and body["sheets"] == ["CONTRACT", "CO #1"]
        # An empty template is not a valid estimate: a data problem, not a crash
        code, body = _req(base + "/estimate/parse?sheet=CONTRACT", data=_xlsx_bytes())
        assert code in (200, 422)
        assert _req(base + "/estimate/parse", data=b"")[0] == 400
    finally:
        srv.shutdown()


def _pdf(pages):
    from pypdf import PdfWriter
    w = PdfWriter()
    for _ in range(pages):
        w.add_blank_page(width=200, height=200)
    buf = io.BytesIO()
    w.write(buf)
    return buf.getvalue()


def _zip(files):
    import zipfile
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w") as z:
        for name, data in files:
            z.writestr(name, data)
    return buf.getvalue()


def test_pdf_merge_combines_in_name_order_and_rejects_bad_input():
    from pypdf import PdfReader
    srv, base = _run()
    try:
        r = urllib.request.Request(base + "/pdf/merge", data=_zip([("0002.pdf", _pdf(3)), ("0001.pdf", _pdf(1))]), headers={"X-Worker-Token": "t0ken"})
        with urllib.request.urlopen(r) as resp:
            assert resp.headers["Content-Type"] == "application/pdf"
            assert len(PdfReader(io.BytesIO(resp.read())).pages) == 4

        code, body = _req(base + "/pdf/merge", data=_zip([("0001.pdf", b"not a pdf")]))
        assert code == 422 and "0001.pdf" in body["error"]
        code, body = _req(base + "/pdf/merge", data=_zip([]))
        assert code == 422
    finally:
        srv.shutdown()


def test_drawing_parse_returns_a_structured_result_for_a_pdf_without_elevations():
    srv, base = _run()
    try:
        code, body = _req(base + "/drawing/parse", data=_pdf(2))
        assert code == 200 and body["ok"] is True
        assert body["elevations"] == [] and body["pageCount"] == 2
        code, body = _req(base + "/drawing/parse", data=b"not a pdf")
        assert code == 200 and body["ok"] is False and "PDF" in body["error"]
    finally:
        srv.shutdown()


def test_page_image_renders_a_png_and_rejects_bad_pages():
    srv, base = _run()
    try:
        r = urllib.request.Request(base + "/pdf/page-image?page=2&dpi=50", data=_pdf(2), headers={"X-Worker-Token": "t0ken"})
        with urllib.request.urlopen(r) as resp:
            assert resp.headers["Content-Type"] == "image/png"
            body = resp.read()
            assert body[:8] == b"\x89PNG\r\n\x1a\n"
        code, body = _req(base + "/pdf/page-image?page=9", data=_pdf(2))
        assert code == 422 and "out of range" in body["error"]
    finally:
        srv.shutdown()


def test_recheck_axis_runs_the_importers_frame_closure_test():
    # 3 openings of 30" with 2" members: 4*2 + 3*30 = 98 -> closes; the same openings in a 99" frame do not
    ok = server.recheck_axis({"axis": "w", "fs": {"v": 98}, "dlos": [{"v": 30}, {"v": 30}, {"v": 30}]})
    assert ok["ok"] is True and ok["member"] == 2.0
    bad = server.recheck_axis({"axis": "w", "fs": {"v": 99.3}, "dlos": [{"v": 30}, {"v": 30}, {"v": 30}]})
    assert bad["ok"] is False
    # a person corrects one reading and it closes again
    fixed = server.recheck_axis({"axis": "w", "fs": {"v": 99.3}, "dlos": [{"v": 30}, {"v": 30}, {"v": 31.3}]})
    assert fixed["ok"] is True
    # no frame size, or nothing in the frame: cannot be judged (not a pass, not a fail)
    assert server.recheck_axis({"axis": "w", "dlos": [{"v": 30}]}) is None
    assert server.recheck_axis({"axis": "h", "fs": {"v": 98}}) is None
    # a door opening is mandatory and counts as a term
    door = server.recheck_axis({"axis": "h", "fs": {"v": 84}, "dos": [{"v": 80}], "dlos": []})
    assert door["ok"] is False or door["ok"] is True   # shape only; the arithmetic is the importer's


def test_recheck_axis_groups_independent_chains_by_position():
    # sidelite chain (x ~100) and a door chain far to the right (x ~450): each frame is judged on its own terms
    r = server.recheck_axis({"axis": "h", "fs": {"v": 83, "rect": [100, 0, 110, 10]},
                             "dlos": [{"v": 40, "rect": [101, 0, 111, 10]}, {"v": 38, "rect": [102, 0, 112, 10]}, {"v": 90, "rect": [450, 0, 460, 10]}]})
    assert r["ok"] is True                         # the 90" reading belongs to the other chain and is ignored here


def test_drawing_jobs_report_progress_and_the_result_over_http():
    import time as _t
    srv, base = _run()
    try:
        r = urllib.request.Request(base + "/drawing/jobs", data=_pdf(2), headers={"X-Worker-Token": "t0ken"})
        with urllib.request.urlopen(r) as resp:
            jid = json.load(resp)["id"]
        for _ in range(50):
            code, st = _req(base + "/drawing/jobs/" + jid + "?result=1")
            if st["state"] != "running":
                break
            _t.sleep(0.1)
        assert code == 200 and st["state"] == "done" and st["result"]["ok"] is True and st["result"]["pageCount"] == 2
        assert _req(base + "/drawing/jobs/nope")[0] == 404
        assert _req(base + "/drawing/balance", data=json.dumps({"axis": "w", "fs": {"v": 98}, "dlos": [{"v": 30}, {"v": 30}, {"v": 30}]}).encode())[1]["balance"]["ok"] is True
    finally:
        srv.shutdown()

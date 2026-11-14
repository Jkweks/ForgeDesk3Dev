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

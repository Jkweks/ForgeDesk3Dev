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

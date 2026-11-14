"""pm-worker HTTP service (standard library only). See README.md."""
import hmac
import io
import json
import os
import tempfile
import threading
import time
import uuid
import zipfile
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

import cost_codes
import drawing_import
import estimate_parser

MAX_BODY = 50 * 1024 * 1024
TOKEN = os.environ.get("WORKER_TOKEN", "")


def _suffix(data: bytes) -> str:
    # .xlsx is a zip; legacy .xls is an OLE2 compound file.
    return ".xlsx" if data[:2] == b"PK" else ".xls"


def merge_pdfs(zip_bytes: bytes) -> bytes:
    """Merge the PDFs inside a zip archive, in file-name order (callers name them 0001.pdf, 0002.pdf ...)."""
    from pypdf import PdfReader, PdfWriter

    writer = PdfWriter()
    with zipfile.ZipFile(io.BytesIO(zip_bytes)) as z:
        names = sorted(n for n in z.namelist() if not n.endswith("/"))
        if not names:
            raise ValueError("The archive contains no files")
        for name in names:
            try:
                reader = PdfReader(io.BytesIO(z.read(name)))
                if reader.is_encrypted:
                    raise ValueError("is password protected")
                for page in reader.pages:
                    writer.add_page(page)
            except Exception as e:
                raise ValueError("%s: %s" % (name, e))
    out = io.BytesIO()
    writer.write(out)
    return out.getvalue()


def page_image(pdf_bytes: bytes, page: int, dpi: int) -> bytes:
    """Render one page (1-based) of a PDF to PNG, for the size-check viewer."""
    import pdfplumber

    with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
        if page < 1 or page > len(pdf.pages):
            raise ValueError("page %d is out of range (the PDF has %d pages)" % (page, len(pdf.pages)))
        im = pdf.pages[page - 1].to_image(resolution=dpi)
        buf = io.BytesIO()
        im.original.save(buf, format="PNG")
        return buf.getvalue()


# ── drawing import jobs: a large set takes minutes, so it runs in a thread and the caller polls ──
_JOBS = {}
_JOBS_LOCK = threading.Lock()
_JOB_TTL = 3600


def start_drawing_job(pdf_bytes: bytes) -> str:
    jid = uuid.uuid4().hex
    job = {"state": "running", "done": 0, "count": 0, "note": "", "busy": 0, "started": time.time(), "result": None, "error": None}
    with _JOBS_LOCK:
        for k in [k for k, v in _JOBS.items() if time.time() - v["started"] > _JOB_TTL]:
            del _JOBS[k]
        _JOBS[jid] = job

    def tick(done, count, note=None, busy=None):
        job.update(done=done, count=count, note=note or job["note"], busy=busy or 0)

    def run():
        try:
            job["result"] = drawing_import.parse_drawing_set(pdf_bytes, progress=tick)
            job["state"] = "done"
        except Exception as e:  # parse_drawing_set never raises by design; this is the last resort
            job["error"] = "%s: %s" % (type(e).__name__, e)
            job["state"] = "failed"

    threading.Thread(target=run, daemon=True).start()
    return jid


def drawing_job(jid: str, include_result: bool):
    job = _JOBS.get(jid)
    if job is None:
        return None
    out = {k: job[k] for k in ("state", "done", "count", "note", "busy", "error")}
    if include_result and job["state"] == "done":
        out["result"] = job["result"]
    return out


def recheck_axis(p: dict):
    """Does the frame close on these CURRENT values? The importer's own test (drawing_import._balance_*), re-run
    after a person corrects a number. `p`: {axis: 'w'|'h', fs: {v, rect?}, dos: [{v, rect?}], dlos: [{v, rect?}],
    printed_members: [[v, ...]]}. Returns the balance dict, or None when it cannot be judged."""
    key = "top" if p.get("axis") == "w" else "x0"
    cross = 1 if p.get("axis") == "w" else 0

    def val(d):
        return None if d is None or d.get("v") is None else float(d["v"])

    def ev(d):
        rect = d.get("rect")
        e = {"value": val(d)}
        if rect and len(rect) == 4:
            e[key] = float(rect[cross])
        return e

    fs = p.get("fs")
    if not fs or val(fs) is None:
        return None
    dos = [ev(d) for d in (p.get("dos") or []) if val(d) is not None]
    dlos = [ev(d) for d in (p.get("dlos") or []) if val(d) is not None]
    fs_ev = ev(fs)
    printed = [[float(x) for x in row] for row in (p.get("printed_members") or []) if isinstance(row, list) and len(row) >= 2]
    pair = p.get("axis") == "w"

    if key in fs_ev:                                   # chain grouping by position, like the importer
        # terms with no footprint are assumed to be in the frame (the JS port did the same)
        loose = [e for e in dos + dlos if key not in e]
        placed = [e for e in dos + dlos if key in e]
        tagged = [dict(fs_ev, _k="fs")] + [dict(e, _k="do") for e in dos if key in e] + [dict(e, _k="dlo") for e in dlos if key in e]
        group = []
        for g in drawing_import._chain_groups(tagged, key):
            if any(e["_k"] == "fs" for e in g):
                group = g
                break
        d_vals = [e["value"] for e in group if e["_k"] == "do"]
        if not pair:
            d_vals = d_vals[:1]
        l_vals = [e["value"] for e in group if e["_k"] == "dlo"] + [e["value"] for e in loose if e in dlos]
    else:
        d_vals = [e["value"] for e in dos][: (None if pair else 1)]
        l_vals = [e["value"] for e in dlos]

    r = drawing_import._balance_axis(fs_ev["value"], d_vals or None, l_vals)
    if r is not None and not r["ok"] and pair:
        for row in printed:
            pr = drawing_import._balance_printed(fs_ev["value"], d_vals, l_vals, row)
            if pr:
                r = pr
                break
    if r is None:
        return None
    return {"ok": r["ok"], "chains": [r], "member": r.get("member"), "used": r.get("used", 0), "dropped": r.get("dropped", 0)}


def parse(data: bytes, sheet: str):
    with tempfile.NamedTemporaryFile(suffix=_suffix(data), delete=True) as f:
        f.write(data)
        f.flush()
        return estimate_parser.parse_workbook(f.name, sheet_name=sheet, cost_code_lookup=cost_codes.lookup())


def sheets(data: bytes):
    with tempfile.NamedTemporaryFile(suffix=_suffix(data), delete=True) as f:
        f.write(data)
        f.flush()
        return estimate_parser.list_sheet_names(f.name)


class Handler(BaseHTTPRequestHandler):
    server_version = "pm-worker"

    def _send(self, code, payload):
        body = json.dumps(payload).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _authed(self):
        sent = self.headers.get("X-Worker-Token", "")
        return bool(TOKEN) and hmac.compare_digest(sent, TOKEN)

    def _send_bytes(self, code, body, content_type):
        self.send_response(code)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/health":
            return self._send(200, {"ok": True, "parserRevision": estimate_parser.PARSER_REVISION})
        if not self._authed():
            return self._send(401, {"error": "unauthorized"})
        if path.startswith("/drawing/jobs/"):
            job = drawing_job(path.rsplit("/", 1)[-1], include_result=urlparse(self.path).query.find("result=1") >= 0)
            return self._send(200 if job else 404, job or {"error": "unknown job"})
        if path == "/cost-codes":
            return self._send(200, {"version": cost_codes.COST_CODES_VERSION, "codes": cost_codes.flat_list()})
        self._send(404, {"error": "not found"})

    def do_POST(self):
        if not self._authed():
            return self._send(401, {"error": "unauthorized"})
        url = urlparse(self.path)
        length = int(self.headers.get("Content-Length") or 0)
        if length <= 0 or length > MAX_BODY:
            return self._send(413 if length > MAX_BODY else 400, {"error": "missing or oversized body"})
        data = self.rfile.read(length)
        try:
            if url.path == "/estimate/sheets":
                return self._send(200, {"sheets": sheets(data)})
            if url.path == "/drawing/balance":
                return self._send(200, {"balance": recheck_axis(json.loads(data.decode("utf-8")))})
            if url.path == "/drawing/jobs":
                return self._send(202, {"id": start_drawing_job(data)})
            if url.path == "/pdf/page-image":
                q = parse_qs(url.query)
                dpi = max(40, min(200, int((q.get("dpi") or ["110"])[0])))
                return self._send_bytes(200, page_image(data, int((q.get("page") or ["1"])[0]), dpi), "image/png")
            if url.path == "/drawing/parse":
                return self._send(200, drawing_import.parse_drawing_set(data))
            if url.path == "/pdf/merge":
                return self._send_bytes(200, merge_pdfs(data), "application/pdf")
            if url.path == "/estimate/parse":
                sheet = (parse_qs(url.query).get("sheet") or ["CONTRACT"])[0]
                return self._send(200, parse(data, sheet))
        except Exception as e:  # parser errors are data problems, report them to the caller
            return self._send(422, {"error": str(e), "type": type(e).__name__})
        self._send(404, {"error": "not found"})

    def log_message(self, fmt, *args):  # quiet; the container log is for real problems
        pass


if __name__ == "__main__":
    if not TOKEN:
        raise SystemExit("WORKER_TOKEN is required")
    ThreadingHTTPServer(("0.0.0.0", int(os.environ.get("PORT", "8780"))), Handler).serve_forever()

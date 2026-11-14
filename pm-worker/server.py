"""pm-worker HTTP service (standard library only). See README.md."""
import hmac
import io
import json
import os
import tempfile
import zipfile
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

import cost_codes
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

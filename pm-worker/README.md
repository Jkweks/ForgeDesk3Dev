# pm-worker

Small internal Python service for Project Management jobs that are impractical to rewrite in
PHP: today the **BidEstimate parser**; later OCR (PO / quote / acknowledgement / receiving) and the
drawing importer (see `docs/plans/pm-suite-integration.md`). Laravel calls it over the Docker
network; it is never exposed publicly.

## Provenance
`estimate_parser.py`, `cost_codes.py` and `drawing_import.py` are vendored from the Vos PM Suite (`jv_vos`, commit 2ff173b,
parser revision 550). The parser is anchor-based and has years of real-file fixes, so it is reused as
is rather than ported. When changing it, keep `PARSER_REVISION` in step: Laravel flags cached
imports parsed with an older revision as stale.

## API (all except /health need header `X-Worker-Token`)
- `GET  /health`
- `GET  /cost-codes` - the master cost code list
- `POST /estimate/sheets` - body = workbook bytes; returns `{sheets: [...]}`
- `POST /pdf/page-image?page=N&dpi=110` - body = PDF; returns that page as a PNG (used by the size-check viewer)
- `POST /drawing/parse` - body = a shop-drawing set PDF (slow: ~1s/page, up to minutes); returns elevations with R.O./F.S./D.L.O. sizes, quantities, system, finish, glass tags
- `POST /pdf/merge` - body = zip of PDFs (merged in file-name order); returns the merged `application/pdf`
- `POST /estimate/parse?sheet=CONTRACT` - body = workbook bytes; returns the parsed estimate
  (lines, byCostCode, reviewNeeded, warnings, summary, parserRevision)

Config: `WORKER_TOKEN` (required), `PORT` (default 8780).

## Tests
```
docker build -t pm-worker-test pm-worker
docker run --rm pm-worker-test python -m pytest -q tests/test_server.py   # HTTP service
docker run --rm pm-worker-test python tests/test_estimate_parser.py        # parser (script-style)
```
The parser script builds .xls fixtures with xlwt: 48 synthetic checks pass. Three `REALWORLD` checks need the
client workbook `test_samples/estimates/BidEstimate_GRCC_LRC.xls`, which is not in git (as in the PM Suite repo),
so they report missing-file failures unless you copy it in.

## Running it
Optional compose profile, so the existing stack is unchanged: set `PM_WORKER_TOKEN` in `.env`, then
`docker compose --profile pm up -d pm-worker`. Laravel reaches it at `http://pm-worker:8780`.

## Parser regression tests (vendored from the PM Suite)
`tests/test_drawing_import*.py` are the PM Suite's own drawing-import tests (script style: exit code 0 = pass).
`test_drawing_import_balance.py` (the frame-closure arithmetic, 14 checks) and `test_drawing_import.py` run on synthetic
data. The others (`curtainwall`, `door_pairs`, `door_terms`, `expansion_mullion`, `system_block`, `title_block`, `typed_markups`, `2up_value_gap`)
need real client drawing sets in `test_samples/drawing_import/` (not in git) and report SKIP without them; copy the sets in to run them.
```
for f in tests/test_drawing_import*.py; do docker run --rm pm-worker-test python $f; done
```

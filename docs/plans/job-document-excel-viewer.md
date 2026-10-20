# Plan: In-page Excel viewer for job documents

## Context
Job documents (SOF, EZ Estimate, PO, Other) are uploaded on the Jobs page and stored on the private `local` disk
(`job_documents/{jobId}/{uuid}.ext`). Today the file name is just a link to `/download`, so users must download
Excel files and open them elsewhere. Goal: click a spreadsheet and view it in a modal, read-only.
Allowed upload types already include `xlsx, xlsm, xls, csv`.

## Recommended approach: server-side parse to JSON, render in a modal
`phpoffice/phpspreadsheet ^5.4` is already installed (used by `EzEstimateController`), so no new dependency, and the
file never leaves the authenticated API (same `jobs.documents.view` gate as download). Client-side SheetJS was
rejected: new vendored lib, needs the raw file fetched to the browser, weaker xlsm/xls fidelity.

### Backend
- `JobDocumentController::preview(int $jobId, int $documentId)` (new, next to `download()`):
  - Same lookup + `Storage::disk('local')->exists` check as `download()`.
  - Reject non-spreadsheet extensions with 415.
  - `IOFactory::createReaderForFile()` with `setReadDataOnly(true)` (skips styles → faster, cheaper), load from `Storage::disk('local')->path()`.
  - Use calculated cached values (`getCalculatedValue` wrapped in try/catch; fall back to raw) so formulas show results, not `=SUM(...)`.
  - Per sheet return `{name, rows: [[...]], merges, truncated}`; cap at e.g. 500 rows × 50 cols per sheet and a sheet-count cap; use `getHighestDataRow/Column` to trim empty space. Skip hidden sheets (flag them).
  - Dates: format via `NumberFormat` only if we load styles; with data-only, render as serials — **decision below**.
  - Catch parse errors → 422 `{error}`; log like `store()`.
- Route in `routes/api.php` after the download route (line ~535), specific-before-parameterized order preserved:
  `GET /business-jobs/{jobId}/documents/{documentId}/preview` → `permission:jobs.documents.view`.
- `format()` adds `previewable` (bool by extension) and `preview_url`.

### Frontend (`resources/views/jobs.blade.php`)
- `renderJobDocuments()` (~line 1480): for `previewable` docs add an eye icon button next to the file name calling `openJobDocPreview(jobId, docId, name)`; the name link stays as download.
- One shared `xl` modal (add near the other modals, ~line 881): sheet tabs (Tabler `nav-tabs`) + scrollable table (`table-sm table-bordered`, sticky header row/col, `max-height`), "Download" button, truncation notice.
- `openJobDocPreview` fetches via existing `jobsAPI()`, builds table with existing `escapeHtml()` (never innerHTML raw cell values). Honor merges via `colspan/rowspan`.
- Reuse `showModal/hideModal` helpers already used on the page.

### Out of scope (could follow)
PDF/image inline preview (trivially an `<iframe>`/`<img>` on the same modal), editing, cell styling/colours, charts.

## Open decisions (defaults chosen)
1. Dates/number formats: default = load with styles (not data-only) only to format dates/numbers correctly; slower on big EZ estimates. Fallback if slow: data-only + format dates by column heuristics.
2. Row/column caps: 500 × 50 per sheet (adjustable).

## Verification
- Feature test `tests/Feature/JobDocumentPreviewTest.php` (run via `docker compose exec app php artisan test` after `config:clear`; never bare against live DB): build a small xlsx with PhpSpreadsheet in `Storage::fake('local')`, assert JSON sheets/rows, 403 without `jobs.documents.view`, 415 for pdf, 404 missing file.
- Manual: upload a real EZ Estimate `.xlsm`, a `.xls`, and a `.csv` on the Jobs page; open preview, switch sheets, check merged cells, truncation notice, dark mode, and that a >25MB-class large workbook doesn't time out.

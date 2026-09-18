# Quality Tracking: PDF Ingestion, Elevation Matching, and Verification

## Context

The shop receives PDF quality-issue inspection reports from an external process. Today there's no way to track these in ForgeDesk — they exist only as loose PDFs, with no link back to the work order elevation they concern, no way to see trends over time, and no review workflow. The goal is to eliminate manual double-entry: uploading a PDF should automatically extract its data and guess the correct elevation, while still letting a manager/admin review, correct, and formally verify each report before it's treated as authoritative. Longer term, the business wants to slice and chart this data by elevation completion date, user, and other dimensions to spot quality trends.

**Confirmed with user:**
- PDFs are text-based/digitally generated (not scanned) — plain text extraction is sufficient, no OCR needed.
- When elevation matching is ambiguous, the system should still assign its best-guess elevation automatically, but flag the record as low-confidence so it surfaces prominently in the review queue (never leave it fully unmatched).

**Still unknown / to confirm once implementation starts:** the exact field layout/labels on the PDF form (job number, elevation tag, inspector, severity, description, etc.) and whether a single PDF can cover multiple elevations. Plan assumes one PDF = one report = one elevation; a real sample PDF should be reviewed at the start of Phase 2 to finalize the label list, and the plan adjusted if that assumption is wrong.

## Domain grounding (existing code to build on)

- **`FdWoElevation`** (`fd_wo_elevations`) is the entity reports attach to: `elevation_tag` (free-text label, e.g. "A1"), `elevation_type_id` → `FdElevationType` (has `matchTerms()` for alias-based fuzzy matching — reuse this), `date_completed` (nullable date — **the field to chart against**), belongs to `FdWorkOrder`.
- **`FdWorkOrder`**: identified as `{job_number}-{release_token}` via `releaseLabel()` (e.g. "12345-R2"); useful for narrowing candidates if a job/WO number appears on the PDF.
- Two separate user tables: `fd_users` (shop floor) vs `users` (office/Sanctum auth). Quality report upload/review is an office-user action → link to `users`, matching `JobDocument`/`FdWoDrawing`.
- **Document pattern to mirror** (`app/Models/JobDocument.php`, `app/Models/FdWoDrawing.php` + their controllers): plain FK to parent, `uuid` filename, columns `original_name/file_path/file_size/file_mime/uploaded_by`, `local` private disk, `index/store/download/destroy` controller shape. No polymorphism used anywhere in this app — follow that convention.
- **Two-phase parse-then-confirm pattern to mirror**: `WorkOrderController::parseExcel()` (`app/Http/Controllers/Api/WorkOrderController.php` ~L451-593) extracts via label-scanning + alias lists + regex fallback, returning a payload for review before commit. This is the model for PDF ingestion — **not** `EzEstimateController`, which commits immediately with no review step.
- **Verification pattern to mirror**: `CycleCountSession` (`assigned_to` vs `reviewed_by` as distinct roles, `status` enum, `complete($reviewerId)` method) and `PurchaseOrder` (`status`/`approved_by`/`approved_at`, guarded `approve()`). Use the same shape: `status`, `verified_by`, `verified_at`, guarded `verify()`/`reject()` methods on the model.
- **Reporting pattern to mirror**: `ReportsController::usageAnalytics()` / `jointsCompletedReport()` — group-by-date/category/user JSON shape, each with CSV (`generateCSV()`) and PDF (dompdf) export siblings.
- **No PDF text-extraction library and no charting library currently exist** in this app — both are new dependencies (`smalot/pdfparser`; `chart.js`).
- **Permissions convention**: one migration per feature using `Permission::firstOrCreate()` + `Role::assignPermission()`, explicit admin grant required even though backend auto-passes admin. Mirror `database/migrations/2026_09_10_000002_add_job_documents_permissions.php`.

## Plan

### Phase 1 — Schema + staging plumbing

**Migrations** (`laravel/database/migrations/`):

1. `create_quality_reports_table.php` — table `quality_reports`:
   - `elevation_id` nullable FK → `fd_wo_elevations`, nullOnDelete
   - `work_order_id` nullable FK → `fd_work_orders`, nullOnDelete (denormalized for filtering pre/without a firm elevation match)
   - `status`: `pending_review` / `verified` / `rejected`
   - `report_date` nullable date, `inspector_name` nullable string, `severity` nullable string, `issue_description` nullable text
   - `raw_extracted_text` nullable longtext (full parse dump, for audit/re-parsing)
   - `extracted_fields` nullable json (as-ingested snapshot, kept separate from the editable columns above)
   - `elevation_tag_guess` nullable string (raw tag text seen on the PDF, retained even after reassignment)
   - `auto_matched` boolean default true; `match_confidence` nullable decimal(5,2); `match_candidates` nullable json (ranked list of `{elevation_id, work_order_id, score, elevation_tag, work_order_label}`)
   - `matched_by_user_id` nullable FK → `users`, nullOnDelete (set on manual override)
   - `verified_by` nullable FK → `users`, nullOnDelete; `verified_at` nullable datetime; `rejected_reason` nullable text
   - `uploaded_by` FK → `users`, nullOnDelete
   - timestamps; indexes on `elevation_id`, `work_order_id`, `status`, `report_date`
2. `create_quality_report_files_table.php` — table `quality_report_files`: `quality_report_id` FK cascade, `original_name`, `file_path`, `file_size`, `file_mime`, `uploaded_by` nullable FK, timestamps. Disk `local`, path `quality_reports/{qualityReportId}/{uuid}.{ext}`.
3. `add_quality_permissions.php` — see Permissions section below.

**Models** (`laravel/app/Models/`):
- `QualityReport.php` — relations to `FdWoElevation`, `FdWorkOrder`, `User` (×3: uploader/matcher/verifier), `hasMany(QualityReportFile::class)`. Methods: `verify(int $userId)`, `reject(int $userId, ?string $reason)` (both guard `status === 'pending_review'`), `reassignElevation(int $elevationId, int $userId)` (sets `elevation_id`, denormalizes `work_order_id`, sets `auto_matched = false`, `matched_by_user_id`).
- `QualityReportFile.php` — plain belongsTo, mirrors `JobDocument`.
- Additive edit: `FdWoElevation::qualityReports(): HasMany`.

**Controller** (`app/Http/Controllers/Api/QualityReportController.php`): `index` (filters: status, elevation_id, work_order_id, uploaded_by, verified_by, date range against `report_date` or elevation `date_completed`), `show`, `store` (upload only in this phase — persists the row + file immediately as `pending_review`, extraction/matching stubbed/deferred to Phase 2), `update` (edit fields / reassign elevation), `destroy` (blocked once `verified`).

**Routes** in `routes/api.php`, permission-gated (see Phase 3/5).

Ship a basic review-queue Blade view (`resources/views/quality.blade.php`) that lists uploaded reports and lets a user manually pick the elevation and edit fields — this alone removes the "loose PDF with no tracking" problem even before auto-matching exists.

### Phase 2 — Extraction + auto-matching

- Add `smalot/pdfparser` via composer.
- **Before writing extraction logic**, get a sample PDF from the user and finalize the label list (currently assumed: job/WO number, elevation tag, report date, inspector, severity, description).
- `app/Services/QualityReportExtractionService.php`: `Pdf::getText()` → label-scan the linear text (find known label strings, capture text until the next label/line break — the text-stream analog of `parseExcel()`'s "value to the right of label" logic) with regex fallback for unmatched fields. Populates `raw_extracted_text`, `extracted_fields`, and the editable columns.
- `app/Services/ElevationMatcherService.php::match(QualityReport $report): array`:
  1. Candidate pool: elevations whose WO status is `active`/`on_hold`, plus `complete` WOs within a recent window (e.g. 30–60 days) of `date_completed`.
  2. Narrow by extracted job number / WO release code if present (exact match, fall back to unfiltered pool on zero hits).
  3. Score by `elevation_tag` match: exact (high), substring/similarity-scaled (medium), plus a smaller boost from `FdElevationType::matchTerms()` matching type names/aliases found in the raw text.
  4. Tiebreak by date proximity between `report_date` and the candidate's `date_completed`/`date_requested`.
  5. Keep top 5 as `match_candidates`; **always** set `elevation_id`/`work_order_id`/`match_confidence` from the top-scored candidate (per user's confirmed preference — never leave unmatched), and let the review UI visually flag anything below a confidence floor (e.g. <20) as low-confidence needing prioritized review.
- Wire both services into `store()`; add `POST /quality-reports/{id}/rematch` to re-run matching after an edit.
- Extend the review UI: show `raw_extracted_text` alongside editable fields, ranked candidate list with one-click reassignment.

### Phase 3 — Verification workflow

- Controller actions `verify()` / `reject(reason)` calling the model methods, each guarded to `pending_review` only (422 otherwise, mirroring `PurchaseOrder::approve()`).
- Routes: `POST /quality-reports/{id}/verify`, `POST /quality-reports/{id}/reject`.
- UI: Verify/Reject buttons in the detail view, gated by `data-permission="quality.verify"`.

### Phase 4 — Reporting/charting

- New `app/Http/Controllers/Api/QualityAnalyticsController.php` (kept separate from the already-large `ReportsController`), following the `usageAnalytics()`/`jointsCompletedReport()` triad:
  - `qualityTrendsReport(Request $request)`: date range params, groups by `elevation.date_completed` (fallback `report_date` when unmatched to a firm elevation), by `elevation.elevationType.name`, by `uploaded_by` and separately `verified_by`, by `severity`; returns `{by_date, by_elevation_type, by_user, by_severity, summary}`.
  - `qualityTrendsReportCsv` / `qualityTrendsReportPdf` siblings (dompdf view `resources/views/pdfs/quality-trends-report.blade.php`).
- Routes: `GET /reports/quality-trends`, `/csv`, `/pdf`, under `permission:quality.view`.
- Add `chart.js` to `package.json` (first chart library in this app). Add chart canvases to the quality Blade view / a quality-analytics section, fed by the JSON endpoint, following the existing vanilla-JS-per-page convention (no framework).

### Permissions (add in Phase 1, enforced incrementally as each phase's routes land)

New migration mirroring `2026_09_10_000002_add_job_documents_permissions.php`:
- `quality.view` — admin, manager, office_staff
- `quality.create` — admin, manager, office_staff
- `quality.edit` — admin, manager
- `quality.verify` — admin, manager
- `quality.delete` — admin only
- Explicitly grant every permission to `admin` (required even though backend auto-passes it — frontend `hasPermission()` checks the literal list).
- Enforce via `permission:{name}` route middleware; gate UI elements with `data-permission="quality.*"` per `docs/ACTION_PERMISSIONS_GUIDE.md`.

## Verification

- `php artisan test` (SQLite in-memory) for new feature tests covering: upload creates a `pending_review` row + file; matcher scoring/candidate ranking against seeded elevations/work orders; guarded `verify()`/`reject()` transitions; permission middleware blocking non-`quality.verify` roles from verify/reject endpoints.
- Manually upload a real sample PDF once available, confirm extracted fields and matched elevation look right, and walk through the review queue UI end-to-end (edit → reassign → verify) in a browser.
- Confirm `qualityTrendsReport` JSON shape against seeded historical data spanning multiple elevation completion dates/users, then spot-check the rendered charts and the CSV/PDF exports.

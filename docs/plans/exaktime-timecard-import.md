# Exaktime Timecard Import → Work Order Labor Actuals

## Context

Shop labor is clocked in Arcoro Exaktime, which knows employee, job, cost code and in/out time — but not ForgeDesk work orders. ForgeDesk already has labor *estimates* per work order (`FdWorkOrder::estimateMinutes()`), but no *actuals*. Goal: import Exaktime timecards and attribute every minute to a specific work order (`FdWorkOrder`, one job → many releases/WOs), including the case where several WOs are open on one job at once.

## Findings that shape the design

- **Exaktime access is unconfirmed.** A search found no public timecard API docs; Arcoro documents a CSV export from the *Time Card Summary* page in ExakTime Connect and ERP connectors (AccountLinx). So: **build around CSV upload first**, keep the ingest layer swappable for an API pull if Arcoro grants access. A real sample export is needed before Phase 1 is finalized (field names, whether job + cost code columns appear, whether a punch has a stable ID).
- Exaktime's grain is **job (+ cost code)**, almost certainly not WO. Job number should match `BusinessJob.job_number`. WO is `{job_number}-{release_token}` (`FdWorkOrder::releaseLabel()`).
- **Two user tables:** shop floor is `fd_users` (has `fd_wo_assignments` pivot: `assignedUsers()` on the WO); office is `users`. Employees map to `fd_users`.
- Existing patterns to mirror: parse-then-confirm import (`WorkOrderController::parseExcel()`), permissions-per-feature migration (`2026_09_10_000002_add_job_documents_permissions.php`), reports registry (`docs/plans/reports-registry.md`), scheduled commands in `routes/console.php`.

## Attribution model (the core decision)

Raw timecards are stored immutably; attribution lives in a separate allocation table so it can be re-run or corrected without touching source data. Resolution order for each entry, first match wins:

1. **Explicit WO reference** — the Exaktime job or cost code carries the release (e.g. `12345-R2`, or cost code `R2`). Allocate 100% to that WO. (Best long-term fix: have the shop clock into release-level codes; the rest of this plan handles the cases where they don't.)
2. **Single open WO** on the job on the entry date → 100%.
3. **User-tied rule** (the "2 users, 1 WO each" case) — a rule `(job, fd_user) → WO`. Fallback hint: if that user is in `fd_wo_assignments` for exactly one open WO on the job, use it.
4. **Percentage rule** (the "split time" case) — job-level weights across its open WOs, e.g. 60/40. Default suggestion: weight by `estimateMinutes()['effective']`, editable. Rounded with largest-remainder so minutes always sum exactly.
5. **Else → `needs_review`** queue. A manager allocates manually (single WO or %), with a "save as rule for this job/user" checkbox.

Rules are **date-effective** (`effective_from`/`effective_to`), since the mix of open WOs changes over a job's life. "Open on the entry date" uses `date_issued`/planned dates/`completed_at`, not just current `status`, so late or re-imported entries land correctly. Manual allocations are flagged and never overwritten by a rule re-run.

## Schema (new migrations)

- `fd_users.exaktime_employee_id` (string, nullable, unique) — employee mapping.
- `time_import_batches`: `source` (csv|api), `original_name`, `file_path`, `uploaded_by` (→ users), `status`, row counts (new/updated/skipped/unmatched), `period_start/end`.
- `time_entries` (raw): `batch_id`, `external_id` (nullable), `dedupe_hash` (unique; employee+date+in+out+cost code when no external ID), `exaktime_employee_id`, `fd_user_id` (nullable), `raw_job`, `cost_code`, `business_job_id` (nullable), `work_date`, `started_at`, `ended_at`, `minutes`, `status` (`allocated|needs_review|unmatched_job|unmatched_employee|ignored`), `raw` (json of the original row).
- `time_entry_allocations`: `time_entry_id`, `work_order_id`, `minutes`, `percent`, `method` (`explicit|single|user_rule|percent_rule|manual`), `rule_id` (nullable), `allocated_by` (nullable → users). Invariant: allocations for an entry sum to the entry's minutes.
- `wo_time_rules`: `business_job_id`, `work_order_id`, `fd_user_id` (nullable → user-tied; null → percent rule), `percent` (nullable), `effective_from`, `effective_to`, `created_by`. Validation: percent rules for a job + date window total 100.

Re-imports upsert by `external_id`/`dedupe_hash`: changed punches are updated; if the entry already has `manual` allocations and its minutes changed, flag it `needs_review` rather than silently rescaling.

## Phases

### Phase 0 — Confirm inputs (blocking)
Get a real Time Card Summary CSV export (a week, including a job worked by 2+ people). Confirm: job identifier format vs `job_number`, cost-code usage, per-punch vs per-day rows, stable punch ID, whether Arcoro will provide API credentials. Adjust the parser/mapping before building.

### Phase 1 — Ingest
- Migrations + models (`TimeImportBatch`, `TimeEntry`, `TimeEntryAllocation`, `WoTimeRule`).
- `App\Services\TimeImport\ExaktimeCsvParser` (label/alias header matching like `parseExcel()`), `TimecardImporter` (parse → stage → commit; idempotent).
- Employee mapping screen: unmatched Exaktime employees → pick `fd_users` row (saves `exaktime_employee_id`).
- Job resolution: raw job → `BusinessJob`; unmatched ones surfaced, not dropped.
- `time:import {file}` artisan command + upload endpoint with a preview step (counts, unmatched employees/jobs) before commit.

### Phase 2 — Allocation engine
- `App\Services\TimeAllocator`: implements the five-step order above; `allocate(TimeEntry)`, `reallocateJob(job, from, to)` (skips manual).
- Largest-remainder rounding; transactional; always leaves allocations summing to entry minutes.
- Run automatically after import and whenever a rule is saved.

### Phase 3 — Rules + review UI
- Blade view `timecards.blade.php` (vanilla JS against the API, per app convention): Import tab, Needs Review queue, Rules tab.
- Per-job rule editor: toggle "by person" vs "by percentage"; shows the open WOs, their estimates, and suggested estimate-weighted %.
- Review queue row actions: assign to one WO / split % / ignore; "save as rule".
- API under `/api/v1/timecards/*` (specific routes before parameterized). Permissions migration: `timecards.view`, `timecards.import`, `timecards.allocate`; enforced server-side.

### Phase 4 — Surfacing actuals
- Work order detail: actual hours vs `estimateMinutes()`, broken down by person and day.
- Report: estimated vs actual by WO / job / employee (register in reports registry; CSV + PDF siblings per existing pattern).
- Optionally, actual-vs-estimate figures feed `minutes_per_joint` tuning later (out of scope here).

### Phase 5 — Automation (if API access exists)
Scheduled `time:pull` (in `routes/console.php`, `withoutOverlapping()`), same importer entry point. Otherwise, a weekly "import due" reminder on the dashboard.

## Testing
Feature tests via `docker compose exec app php artisan test` after `config:clear` (never bare against the live DB). Cases: idempotent re-import; each resolution step in order; 60/40 split with odd minutes sums exactly; user-tied rule with two users/two WOs; date-effective rules across a WO closing; manual allocation survives re-run; unmatched employee/job paths.

## Open questions
1. Can the shop clock into release-level codes (makes step 1 cover most cases)? Strongly preferred if feasible.
2. Is time on a `pending` WO (job steps not done) allowed, or only `active`/`on_hold`?
3. Should non-WO time (cost code like shop cleanup/PTO) be ignored, or tracked as job-level overhead?
4. Is labor *cost* (rates) needed, or hours only? Plan assumes hours only.
5. Does a single timecard ever span a job change mid-shift (needs splitting by punch, not by day)?

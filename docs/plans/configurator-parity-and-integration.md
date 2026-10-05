# Plan: /config parity with fab_utils + cleaner configurator → reservation → cut list flow

Status: DRAFT for review (2026-10-02). Nothing implemented.

## Goal

1. Every fab_utils configurator capability the shop actually relies on exists in `/config`, with
   **identical numeric output** (fab_utils is the reference; its math is considered correct).
2. A configured opening flows to inventory reservation and the cut station through one obvious
   path inside ForgeDesk's existing Job → Work Order → Elevation model, instead of the current
   three-button / multi-entry-point process.

## What was compared

fab_utils: `configurator/workspace.html` (8k lines: Opening → Frame → Door → HWLib tabs, Job/WO
bar, Save-to-Work-Order, record table with bulk edit, hardware sets, combined output),
`export.html` (Work Order Package PDF), `door-labels.html`, `pdf-template-admin.html`, `api.php`.
ForgeDesk: `configurator/frame.blade.php`, `DoorFrameConfigurationController` (~2k lines),
`Services/Configurator/*` (Frame/Door/Hwlib BOM generators, ReservationBridge, CutFlowExportService),
`CutListController` + `fabrication/cut-lists.blade.php`, `jobs.blade.php` config/hw-set sections.

Findings below marked **(verified)** were confirmed in code; **(unverified)** need a check in Phase 0.

## A. Functional parity gaps

| fab_utils capability | /config today | Action |
|---|---|---|
| Frame/door/hwlib calculation | Ported as PHP generators | **Phase 0: prove parity, don't assume** (unverified) |
| Stock-length / stick yield block (`buildStockRows`, `renderStockBlock`) on output | None in cut-sheet PDF (verified by grep) | Add: sticks needed per PN/finish; reuse CutPlanner if it already computes this |
| Work Order Package PDF (`export.html`: hinge prep sections, HW QC checklist, "verify before releasing") | Simpler `configurator-cut-sheet` PDF (frame/door/hardware/hinge prep/notes) | Diff the two; port missing sections (HW QC, verify-before-release) |
| Door labels (`door-labels.html`, Avery 5161 + thermal-safe) | Nothing in /config | Port as a printable view/PDF; likely tie into tiger-bridge Zebra labels later |
| Configurable PDF layout (`pdf_template`, `pdf-blocks.js`) | Fixed Blade PDF | Decide if still wanted (Q2); otherwise drop |
| Combined output across all saved configs in a WO | One config at a time | Work-order-level rollup (see Phase 3) |
| Record table: bulk field edit, "Recalc All Needs-Recalc", bulk add doors/frames | Duplicate modal + link/unlink only | Phase 3 grid |
| HW sets: build set, apply to many openings (pair/single aware) | Sets admin + `applySet` API exist; apply UI lives on `jobs.blade` only, not /config | Surface in the opening list as multi-select → apply set |
| Override Parts | Manual part edit + diff-prompt on regenerate | Likely covered; confirm |
| Legacy `hardware-calculator.html` (pre-hwlib) | n/a | Intentionally out of scope (hwlib is the successor) |
| Released = locked, un-release as explicit escape hatch | `release()` exists; **no un-release endpoint** (verified: only `unreserve` from reserved→draft) | Add explicit un-release with defined side effects (Phase 2) |

## B. Why the process feels clunky (current state, verified)

1. **Three overlapping state buttons.** Reserve / Create Reservation / Release. Meaning depends on
   status (draft → reserved → released), and Create Reservation exists only as a repair step for
   "released before BOM generated".
2. **Reservation per opening.** `ConfigurationReservationBridge` makes one `JobReservation` per
   configuration, so a job with 40 openings gets 40 reservations in the Fulfillment screen.
3. **Reservation sync is async** (`SyncConfigurationReservationJob`), so availability lags edits and
   the UI can't say "reserved: yes/in sync".
4. **No Job/WO context in /config.** Left list only filters by job; fab_utils forces Job → WO first.
   Release hard-fails with "must be tied to a work order", discovered only at the end.
5. **Three entry points create configs** (/config modal, jobs page, elevation attach) with
   different fields; WO linking is an after-the-fact matcher (`ElevationConfigurationMatcher`).
6. **Cut list is a second hop.** Release pushes the *whole WO's* cut list to CutFlow
   (`CutFlowExportService`), but editing happens in a separate page (`/fabrication/cut-lists`),
   and edits to configurator-sourced lines can be overwritten by the next release (the controller
   docstring admits this).
7. **Reservation units vs cut consumption mismatch (unverified).** Reservation commits length
   stock as fractions of a stick (1/10 rounding); CutFlow plans real sticks. Need to confirm what
   happens to the reservation when cuts are logged (does `consumed_qty` move?).

## C. Decisions (2026-10-02)

1. **Stick yield** matters for (a) the quantity reserved and (b) the stock-length report in the PDF. Nothing else.
2. **Package PDF + door labels are the fabricators' source of truth** → exact parity, top priority. The
   **PDF template editor stays**, but only to change that format.
3. **Reserve before release stays.** Workflow: reserve on rough sizes → final measure → update → release.
4. **One reservation per job**, summarising all of that job's configurations in the reserved state.
5. **No un-release once cutting has started.** The generated cut list **must be editable**.
6. **Parity harness first.**

## D. Phases

### Phase 0 - Parity harness (first; gates everything)
- Export real saved configs from fab_utils' `configurator` DB (`saved_configs`, `hardware_configs`) covering single/pair,
  each handing, transom, threshold, each door type, center pivot, stacked rails.
- Run identical inputs through fab_utils JS (`calculate()`, `evaluateFormula`, `buildStockRows`) and the ForgeDesk
  generators; diff per-part PN, qty, length, plus stick counts.
- Also compare rendered output: package PDF sections and label content, field by field.
- Output = the real bug list; keep as a PHPUnit golden-file test (run via `docker compose exec app php artisan test`).

### Phase 1 - Source-of-truth outputs
- **Package PDF**: port missing sections (HW QC, verify-before-release, hinge prep etc.) from `export.html`.
- **Door labels**: port `door-labels.html` (Avery 5161 + thermal-safe variant).
- **PDF template editor**: port `pdf-template-admin` + block model (`pdf-blocks.js`) so the layout is editable in
  ForgeDesk; the package PDF renders from the template.
- **Stock-length report** in the PDF (sticks per PN/finish).

### Phase 2 - Stick-based reservation, one per job
- Replace the per-opening reservation (`ConfigurationReservationBridge`) with a **job-level** reservation.
  Desired qty per product = sticks needed for **all reserved/released configs of the job combined**, using the stick-yield
  logic from Phase 0 (not per-line 1/10 rounding, which over-reserves when summed).
- Sync is recomputed from the whole job on any change; make it synchronous (or visibly "syncing") so availability is trustworthy.
- Migration/backfill: collapse existing per-config reservations into the job reservation; `job_reservation_id` on configs
  points at the shared one.
- Release keeps the lines committed; consumption comes from cut logs (verify how CutFlow cuts relate to `consumed_qty`).

### Phase 3 - Cleaner state model
- Keep Draft -> Reserved -> Released, but one clear primary action per state (Reserve / Update reservation / Release)
  instead of three overlapping buttons; Release shows a preflight modal (validation, WO link, ungenerated sections, shortfalls).
- "Final measure" step: editing a reserved config's dimensions regenerates parts and updates the job reservation.
- Un-release allowed only while no cut is logged on its cut-list lines.

### Phase 4 - Job/WO-centred /config
- Job -> WO selector first; grid of that job's openings (status, finish, series, door/frame/hw generated, reserved, in cut list).
- Bulk actions: apply HW set, change finish, regenerate, reserve/release selected. Job-level BOM/stick rollup (= fab_utils "combined output").
- One shared "new opening" modal for /config, jobs page and elevation attach.

### Phase 5 - Editable cut list that survives re-release
- Tag cut lines `source = configurator | manual` and keep a per-line override flag.
- Re-release merges: refreshes untouched configurator lines, never overwrites edited/manual/already-cut lines, and shows a diff of what changed.
- Embed the cut list on the WO page; keep it editable until a line is cut (existing lock rule).

### Phase 6 - Retire fab_utils configurator
After Phase 0 mismatches are zero and a real job has run through /config: final catalog reseed (see memory
`fab-utils-configurator-reseed`), freeze fab_utils, replace the old beta nav link.

## Resolved
- Released configurations stay in the job reservation and **fulfil from it as sticks are cut** (cut log -> `consumed_qty`).
  Phase 2 must define the cut-log -> reservation-item mapping (verify how CutFlow cuts currently relate to `consumed_qty`).
- Edited cut lists are **flagged** (`cut list diverges from BOM`) on the configuration, so a later re-run
  (e.g. damage at install) warns that the cut list was hand-edited. Phase 5: record divergence on the config, show
  a badge in the grid/detail, and show the diff against the regenerated BOM when re-running.

## E. Risks
- Parity may expose BOM-generator bugs that change already-reserved quantities → re-run sync
  deliberately, not silently.
- Changing reservation granularity touches existing data (`job_reservation_id` on configs) →
  needs a migration/backfill plan.
- Tests: run via `docker compose exec app php artisan test` after `config:clear`; never against the live DB.

# Plan: Integrate Vos PM Suite (jv_vos) into ForgeDesk

Status: **decisions made — ready to start Phase 0** — branch `feature/pm-suite-integration`, 2026-11-13.
Source: `/mnt/homeNAS/Container/jv_vos` (git repo, build 573, GHCR image `ghcr.io/jkweks/jvvos`).

## Decisions (2026-11-13)

- **Target:** full absorption — one software for everything (Option C path to B).
- **Master data:** ForgeDesk is master for jobs, POs and vendors. PM Suite / F: drive data is *pulled into* ForgeDesk; no two-way sync needed.
- **Files:** the F: share stays. ForgeDesk needs a first-class F: share integration (mount, path mapping, indexing) rather than moving files.
- **Cut-over:** PM Suite is not in production use (newly developed), so no downtime/migration window constraints and no live-data preservation beyond test data. Phase 1 co-existence can be skipped or kept minimal.
- **Work Orders:** PM Suite WO is unused — do **not** port it. Keep ForgeDesk `FdWorkOrder`; only revisit the Excel Rev 6 fill if still wanted.
- **Roles:** ForgeDesk roles grow to support PM roles. The existing office-staff role is the closest base; add `pm.*` permissions plus superintendent / company-dashboard capabilities as permissions or flags.

Effect on phases: skip Phase 1 SSO/proxy work (replace with import-only tooling), drop PM-side Work Orders from Phase 4, make "F: share integration" a Phase 0/2 deliverable, and treat PM Suite data as a one-time/repeatable import into ForgeDesk tables.

## Inventory model constraint (2026-11-13)

ForgeDesk inventory is **stock inventory**, kept separate from job-specific material:
- **Stock** (tracked in `inventory_locations`, reservations, cycle counts) is replenished by stock POs. Jobs only touch it through the **small order fulfillment** process (job reservations), which exists to give demand visibility.
- **Job-specific material** (the bulk of PM Suite POs) is ordered separately per job and is **not tracked between receiving and installation**. Receiving a job PO records *that it arrived*, not stock on hand.

Consequences for the integration:
1. PM Suite POs/receiving must **not** write to `InventoryLocation` / `quantity_on_hand` by default. That would pollute stock counts and cycle counts.
2. `PurchaseOrder` needs a **purpose/type**: `stock` (existing behaviour, receives into locations), `internal` (internal demand PO, see 4) vs `job` (receipt is a status/quantity-received record only, linked to the job, no location, no on-hand change). Existing stock PO flows and reports stay unchanged and must filter by type.
3. Job POs feed Material Forecast (estimated / ordered / received per cost code) and the job dashboard, not stock reports or reorder suggestions (`suggested_order_qty`, stockout days).
4. **SOF internal PO is the bridge.** A small-order-fulfillment job is initiated by an internal SOF PO submitted in PM Suite. That PO carries the EZ estimate, and from it ForgeDesk creates the job reservations (stock demand) and door/frame configurations. PM Suite has no SOF concept today; it is new. The SOF PO is issued in the normal PO builder to an internal vendor record (initially **Vos Glass - Fabrication**), and the vendor's **Internal** setting is what makes the PO `type = internal`. It doesn't receive anything; it is the *demand document*. A job can therefore have: the SOF PO (internal, drives reservations/configs), reservation lines against stock, and ordinary job POs (direct-ship). The job view shows all three, labelled.
5. Out of scope unless later requested: tracking job material from receiving through installation (staging locations, issue to job). Leave a hook (receipt records keep job + cost code + received date) so it can be added without rework.



- Python `http.server` backend (`server.py`, 10.7k lines, with a known ~900-line duplicated
  region where only the last `def` wins) + vanilla-JS front end (`vos-pm-suite.html` + ~40 `*.js`).
- Storage: Postgres 16 (`vospm` DB, schemas `pm` / `pm_sandbox`) via `pg_store.py` — a
  **document store**, not relational: `shared_data` (one row per top-level key of the old
  shared-data.json: `prods`, `jobs`, `pos`, `vendors`, `changeHistory`, ...), `documents`
  (path-keyed blobs: estimates cache, users.json, etc.), `meta`.
- Files (job folders, PO PDFs, submittals) live on the F: share mounted at `/mnt/f`.
- Auth: already delegates to ForgeDesk (`VOS_AUTH=forgedesk`; `authsync` mirrors ForgeDesk
  `users` into `auth_users` via a read-only `vos_sync` role). Roles/flags are separate (Manage Users).
- Feature areas: Dashboard, Submittal Tracker/Builder/Closeout, Purchase Orders, Material Forecast,
  Receiving, Vendors/Contacts, Work Orders (fills Excel Rev 6 template, Dropbox photo links),
  Labor Schedule, Product Library, Change History, Procore sync, BidEstimate/EZ-estimate import,
  OCR (PO / quote / acknowledgement / receiving), vendor/hardware/Kawneer/Glazier-Studio importers.
- Heavy system deps: Tesseract, Poppler, LibreOffice (PDF/Excel/OCR).

## 2. Overlap with ForgeDesk (the real integration surface)

| PM Suite | ForgeDesk equivalent | Notes |
|---|---|---|
| `jobs` (+ nested submittals, samples) | `BusinessJob`, `JobReservation`, `JobDocument` | Job number is the natural key; PM Suite jobs carry submittal/closeout state ForgeDesk lacks |
| `pos` (PO builder, receiving, ack discrepancy) | `PurchaseOrder` / `PurchaseOrderItem`, `Supplier` | Biggest overlap. ForgeDesk POs are stock POs; PM Suite POs are job POs. Add a PO type so the two coexist without touching stock (see Inventory model constraint) |
| `vendors`, contacts | `Supplier` | Merge directory; PM Suite has richer contact/doc data |
| `prods` (submittal product library) | `Product`, configurator hardware library | Different purpose (spec/color PDFs vs stock items) — likely keep separate, link by part no. |
| Work Orders (Excel WO form, hardware sets, door schedule) | `FdWorkOrder`, `FdWoElevation`, shipment flow, door/frame configurator | Overlap in intent; PM Suite WO is the *PM-side* form, FD WO is shop-floor |
| Labor Schedule | `FdScheduleEntry`, `FdWoOperatorSchedule` | Possible consolidation later |
| Material Forecast (estimated/ordered/received per cost code) | Job reservations (small orders only) | Complementary: forecast reads job POs; reservations (created from the SOF PO's EZ estimate) cover stock demand. Not merged |
| EZ/BidEstimate parser | `EzEstimateController` | Two parsers of the same workbook — dedupe candidate |
| Users / roles | `User`, `Role`, `Permission` | Already linked one-way; fold into ForgeDesk permissions (`pm.*`) |
| Change History | (none general) | Keep as PM Suite feature or generalise |

## 3. Integration options

**A. Keep separate, tighten links (low effort).** Run PM Suite as a sidecar; SSO via ForgeDesk, link
by job number, embed/iframe or nav link, read-only API for cross-data. Lowest risk; leaves duplicate
POs/vendors/jobs and the Python/vanilla-JS stack.

**B. Absorb like CutFlow (recommended end state).** Port into Laravel under a `PmSuite` namespace,
own Postgres database/connection (`pmsuite`), in-process — the pattern already used for CutFlow
(`App\Models\CutFlow\*`, `database/migrations/cutflow/`, separate connection). Replace document store
with relational tables, rewrite the front end as Blade/Livewire (or keep the vanilla JS pages inside Blade
shells, which matches ForgeDesk's own page pattern). Largest effort.

**C. Hybrid / strangler (recommended path to B).** Start with A, then migrate module by module,
shared entities first, retiring the Python app once each module is ported. Python parts that are
hard to port (OCR, drawing/estimate parsers, LibreOffice WO fill) stay as a small internal
worker service called from Laravel (like `tiger-bridge`), instead of being rewritten.

**Recommendation: C.** Rewriting 40k+ lines of working, field-tested logic in one go is the main risk.

## 4. Proposed phases

**Phase 0 — Discovery (no code changes to prod)**
- Confirm watched F: roots and filename conventions for §4a; prototype the `pm_files` table and ingest endpoint.
- Read-only inventory of `pm.shared_data` keys and record counts from a prod backup; write the
  collection → table mapping.
- Decide source-of-truth per entity (jobs, POs, vendors) — see open questions.
- Pin down the F: share vs ForgeDesk `storage` / `JobDocument` file-location story.

**Phase 1 — Co-existence (Option A, ~days)**
- Add PM Suite to ForgeDesk docker network/compose (or documented side-by-side), reverse-proxy under
  `/pm` with ForgeDesk session SSO (replace `authsync` + `vos_sync` with a signed handoff or shared
  session check; retire the committed `vos_sync` password in `docker/forgedesk-readonly.sql`).
- Nav entry + `pm.*` permissions in ForgeDesk; PM Suite honours them.
- Job number link both ways (ForgeDesk job page → PM Suite job; PM Suite dashboard → ForgeDesk shipments/WO).

**Phase 2 — Shared entities**
- Vendors ↔ `Supplier`: one-time merge + two-way sync, then ForgeDesk as master.
- Jobs ↔ `BusinessJob`: ForgeDesk master for header fields; PM Suite keeps submittal/closeout/sample state keyed by job number.
- Dedupe the EZ-estimate parser (one implementation, one cache).

**Phase 3 — Purchasing**
- Add a `type` (`stock` | `job` | `internal`) to `PurchaseOrder`; backfill existing rows as `stock`. Port the PM Suite PO builder as `job` POs (+ extra columns: job link, cost code, surcharge, issue stage, voided axis, ack discrepancy).
- SOF PO: on submit, store the attached EZ estimate and run the existing estimate → reservations / door configuration path (`EzEstimateController`, `JobReservation`, `DoorFrameConfiguration`), idempotently (re-submitting or revising the PO updates the reservations rather than duplicating them). It is addressed to a vendor marked **Internal**: `Supplier` gets an `is_internal` boolean, editable by users with supplier-edit permission as an "Internal vendor" toggle on the vendor info page (shown as a badge in the vendor list). A PO whose vendor is internal is automatically `type = internal`; users never set the type by hand, and changing a vendor's internal flag is blocked (or warns) once it has POs. Internal vendors are excluded from the vendor-spend/lead-time reports and from reorder suggestions. Seed `Vos Glass - Fabrication` as the first internal vendor. It has no receiving step. Open detail: how revisions/cancellations should adjust or release reservations. Also keep internal-vendor POs out of vendor spend/lead-time reports.
- Job-PO receiving records received qty/date/flags only and **never** touches `InventoryLocation`. Stock-PO receiving is unchanged (canonical `InventoryLocation` path, not `adjustQuantity()`).
- Audit existing PO queries, reports and reorder logic so they filter on `type = stock`.
- OCR import (PO/quote/ack/receiving) becomes a Python worker behind a Laravel queue job.
- Material Forecast reads from the unified tables.

**Phase 4 — Submittals, closeout, WO**
- Port Submittal Tracker/Builder/Closeout, Product Library (separate `pm_products`), Change History (generalise to a ForgeDesk audit log).
- Work Orders: reconcile PM-side WO form with `FdWorkOrder`; keep Excel Rev 6 fill as a worker.
- Labor Schedule → `FdScheduleEntry`.

**Phase 5 — Retire Python app**; keep only the OCR/parsing worker; archive repo.

## 4a. Document ingestion from the F: share

**Mount:** F: is mounted into the ForgeDesk containers as `/mnt/f` over SMB/CIFS (read-only for ingestion; ForgeDesk writes only where a feature requires it). Because it's SMB, inotify inside the container will not see changes made from other machines, so ingestion does not rely on it.

**Trigger (Option 3 — Windows-side watcher):**
- A small service on the Windows file server (`FileSystemWatcher`, or a scheduled task wrapping it) watches the configured roots (e.g. `Engineering\...` job folders, estimates, PO PDFs) for created/changed/renamed files.
- It debounces (wait until the file size is stable for a few seconds, since large PDFs copy slowly), then POSTs `{path, size, mtime, event}` to a ForgeDesk ingest endpoint, e.g. `POST /api/v1/ingest/fdrive`, authenticated with a dedicated service token (not a user session) and an IP allow-list.
- The endpoint does no parsing: it records the file in a `pm_files` table (path, size, mtime, sha256 when available, status) and dispatches a queued import job. It returns immediately.
- Follows the `tiger-bridge` pattern (small standalone Windows service, README, tests); a sibling folder `fdrive-watcher/` in this repo.

**Safety net (reconciliation scan):** the watcher can miss events (service down, server reboot, network blip). A Laravel scheduled command runs nightly, plus on demand from an admin button, and walks only the watched roots over `/mnt/f` comparing path/size/mtime to `pm_files`. Anything new or changed is queued exactly like a watcher event. The watcher also sends a periodic heartbeat so ForgeDesk can show "watcher offline" in the UI.

**Import jobs (queued, idempotent):**
- Key on `(job number parsed from path, sha256)`; re-delivered events, moves and re-scans must not create duplicates.
- Classify by folder/filename rules into a document type, then route:
  - **Estimates / drawings:** parse and apply automatically (deterministic; existing override mechanism handles corrections).
  - **OCR documents (PO, quote, acknowledgement, receiving):** run OCR in the Python worker, store results in a **review inbox**; a person approves before PO/receiving records are created or inventory moves.
  - **Unclassified / unknown job:** park in the inbox with the reason; never guess.
- Failures retry with backoff, then surface in the inbox with the error; nothing is silently dropped.

**Open details:** exact watched roots and filename conventions; whether the watcher runs on the file server itself or on a PC with the mount; service-token rotation; max file size/timeouts for OCR jobs.

## 4b. Feature checklist (mark what to include + priority)

Fill in **Include** (Y/N) and **Priority** (1 = first, 2, 3, Later). "Proposed" is my starting suggestion, not a decision.

| # | PM Suite feature | Proposed | Include (Y/N) | Priority | Notes |
|---|---|---|---|---|---|
| 1 | Jobs / job dashboard | Port (merge into BusinessJob) |  |  |  |
| 2 | Purchase orders (builder, PDF, voided/closed axes) | Port (into PurchaseOrder; types stock / job / internal) |  |  |  |
| 3 | Receiving | Port (job receipts only; no stock impact) |  |  |  |
| 4 | Vendors / Contacts tab | Merge into Supplier |  |  |  |
| 5 | Material Forecast | Port |  |  |  |
| 6 | EZ / BidEstimate import | Merge with EzEstimateController |  |  |  |
| 7 | Submittal Tracker | Port |  |  |  |
| 8 | Submittal Builder (combined PDF) | Port or keep client-side pdf-lib |  |  |  |
| 9 | Closeout checklist + closeout vendors | Port |  |  |  |
| 10 | Product Library (spec / color PDFs) | Port as separate pm_products |  |  |  |
| 11 | Change History / restore points | Generalise to ForgeDesk audit log |  |  |  |
| 12 | Labor Schedule | Merge into FdScheduleEntry |  |  |  |
| 13 | OCR import: PO / quote / acknowledgement / receiving | Python worker + review inbox |  |  |  |
| 14 | Users / roles (admin, superintendent, company dashboard) | Map to ForgeDesk roles/permissions |  |  |  |
| 15 | Work Orders (Excel Rev 6, door schedule, hardware sets) | Dropped (use FdWorkOrder) |  |  |  |
| 16 | Dropbox file requests / opening-photo folders | Dropped with Work Orders (confirm) |  |  |  |
| 17 | Procore submittal sync | Undecided |  |  |  |
| 18 | Email Templates / Outlook compose | Undecided |  |  |  |
| 19 | Glass Visualizer / glass shape designer | Undecided |  |  |  |
| 20 | PDF markup (markup review status) | Undecided |  |  |  |
| 21 | Drawing import (drawing_import.py) | Undecided (Python worker if kept) |  |  |  |
| 22 | Hardware catalog importer | Undecided (overlaps configurator hwlib) |  |  |  |
| 23 | Kawneer importer | Undecided |  |  |  |
| 24 | Glazier Studio importer | Undecided |  |  |  |
| 25 | Vendor catalog importer | Undecided |  |  |  |
| 26 | Universal Search | Undecided (ForgeDesk search?) |  |  |  |
| 27 | Activity Timeline | Undecided |  |  |  |
| 28 | To-do list | Undecided |  |  |  |
| 29 | Production chart | Undecided |  |  |  |
| 30 | QR codes | Undecided |  |  |  |
| 31 | Dashboard widgets | Fold into customizable dashboard |  |  |  |

## 5. Key risks / things to settle first

0. **Stock contamination:** any code path that lets a job PO change on-hand quantities or stock reports is a bug. Needs tests (job-PO receive leaves `quantity_on_hand` and `inventory_locations` unchanged).
1. **Two sources of truth** for POs/jobs/vendors during transition — needs a strict ownership rule per field.
2. **Document-store → relational migration**: nested submittals/samples/revision history, restore points; idempotent importer + dry-run report.
3. **File storage**: F: share paths (`\\...\Submittal - General`) vs ForgeDesk storage; Windows-path assumptions in the PM Suite code.
4. **`server.py` duplicated region** — any port must source from the *last* definition of each name.
5. **Saves/merge semantics**: PM Suite does optimistic merge of shared collections with a rev sequence; relational port must not lose concurrent-edit behaviour.
6. **Secrets**: `docker/forgedesk-readonly.sql` contains a committed password; rotate and don't copy it.
7. **Ops**: Tesseract/Poppler/LibreOffice image weight; keep out of the Laravel `app` image (worker container).
8. **Tests**: PM Suite has ~100 JS/Python tests with `known_failures.txt`; carry them as a regression net for each ported module.

## 6. Open questions for the user

1. Target: full absorption (B/C) or just tight co-existence (A)? full absorption, one software for all
2. Which system is master for POs, vendors and jobs once merged? forge will be master, data will be pulled from fdrive or pm side into forge
3. Is the F: share staying, or should files move into ForgeDesk storage / Dropbox? f share is staying, will need to integrate
4. Who uses PM Suite (PM count) and can it be down during cut-over windows? currently not used in production environment, newly developed
5. Is the existing Work Orders feature in PM Suite in active use vs. ForgeDesk's `FdWorkOrder`? pm suite no
6. Should PM Suite's separate roles (admin/superintendent/company dashboard) map to ForgeDesk roles? forgedesk roles to develop to suppport pm roles (office staff is current forge role with simialr)

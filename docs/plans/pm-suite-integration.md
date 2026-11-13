# Plan: Integrate Vos PM Suite (jv_vos) into ForgeDesk

Status: **decisions made — ready to start Phase 0** — branch `feature/pm-suite-integration`, 2026-11-13.
Source: `/mnt/homeNAS/Container/jv_vos` (git repo, build 573, GHCR image `ghcr.io/jkweks/jvvos`).

## Decisions (2026-11-13)

- **Target:** full absorption — one software for everything (Option C path to B).
- **Master data:** ForgeDesk is master for jobs, POs and vendors. PM Suite / F: drive data is *pulled into* ForgeDesk; no two-way sync needed.
- **Files:** the F: share stays. ForgeDesk needs a first-class F: share integration (mount, path mapping, indexing) rather than moving files.
- **Cut-over:** PM Suite is not in production use (newly developed), so no downtime/migration window constraints and no live-data preservation beyond test data. Phase 1 co-existence can be skipped or kept minimal.
- **Work Orders:** PM Suite's own WO feature is unused and is **not ported**. Instead (updated after checklist review) work orders are **requested from the PM side and validated by the existing ForgeDesk setup** (`FdWorkOrder`, shipment/request flow), i.e. a PM-initiated request that Forge checks and accepts or rejects. The Excel Rev 6 fill is not required.
- **Roles:** ForgeDesk roles grow to support PM roles. The existing office-staff role is the closest base; add `pm.*` permissions plus superintendent / company-dashboard capabilities as permissions or flags.

Effect on phases: skip Phase 1 SSO/proxy work (replace with import-only tooling), replace PM-side Work Orders in Phase 4 with a PM work-order *request* into `FdWorkOrder`, make "F: share integration" a Phase 0/2 deliverable, and treat PM Suite data as a one-time/repeatable import into ForgeDesk tables.

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
| BidEstimate parser (PM Suite) | `EzEstimateController` (EZ Estimate) | **Different sources, not duplicates**: BidEstimate is an in-house format we control and can change; EZ Estimate comes from an external system we don't control. Keep two importers (separate, each with its own cache), sharing downstream code where output shapes match |
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
- Port the BidEstimate importer as its own importer beside `EzEstimateController` (do not merge parsers); share cost-code mapping and downstream estimate → forecast code only.

**Phase 3 — Purchasing**
- Add a `type` (`stock` | `job` | `internal`) to `PurchaseOrder`; backfill existing rows as `stock`. Port the PM Suite PO builder as `job` POs (+ extra columns: job link, cost code, surcharge, issue stage, voided axis, ack discrepancy).
- SOF PO: on submit, store the attached EZ estimate and run the existing estimate → reservations / door configuration path (`EzEstimateController`, `JobReservation`, `DoorFrameConfiguration`), idempotently (re-submitting or revising the PO updates the reservations rather than duplicating them). It is addressed to a vendor marked **Internal**: `Supplier` gets an `is_internal` boolean, editable by users with supplier-edit permission as an "Internal vendor" toggle on the vendor info page (shown as a badge in the vendor list). A PO whose vendor is internal is automatically `type = internal`; users never set the type by hand, and changing a vendor's internal flag is blocked (or warns) once it has POs. Internal vendors are excluded from the vendor-spend/lead-time reports and from reorder suggestions. Seed `Vos Glass - Fabrication` as the first internal vendor. It has no receiving step. Open detail: how revisions/cancellations should adjust or release reservations. Also keep internal-vendor POs out of vendor spend/lead-time reports.
- Job-PO receiving records received qty/date/flags only and **never** touches `InventoryLocation`. Stock-PO receiving is unchanged (canonical `InventoryLocation` path, not `adjustQuantity()`).
- Audit existing PO queries, reports and reorder logic so they filter on `type = stock`.
- OCR import (PO/quote/ack/receiving) becomes a Python worker behind a Laravel queue job.
- Material Forecast reads from the unified tables.

**Phase 4 — Submittals, closeout, WO**
- Port Submittal Tracker/Builder/Closeout, Product Library (separate `pm_products`), Change History (generalise to a ForgeDesk audit log).
- Work Orders: PM creates a **work order request** (job, elevations/openings, drawings from the drawing import) which Forge validates against its existing setup before creating/accepting an `FdWorkOrder`. Follows the existing `ShipmentRequest` request/approve pattern. No Excel Rev 6 fill.
- Labor Schedule → `FdScheduleEntry`.

**Phase 5 — Retire Python app**; keep only the OCR/parsing worker; archive repo.

## 4a. Document ingestion from the F: share

**Mount:** F: is mounted into the ForgeDesk containers as `/mnt/f` over SMB/CIFS **read-only for now** (decision below); ForgeDesk never writes to it in this phase. Because it's SMB, inotify inside the container will not see changes made from other machines, so ingestion does not rely on it.

**Trigger (Option 3 — Windows-side watcher):**
- A small service on the Windows file server (`FileSystemWatcher`, or a scheduled task wrapping it) watches the configured roots (e.g. `Engineering\...` job folders, estimates, PO PDFs) for created/changed/renamed files.
- It debounces (wait until the file size is stable for a few seconds, since large PDFs copy slowly), then POSTs `{path, size, mtime, event}` to a ForgeDesk ingest endpoint, e.g. `POST /api/v1/ingest/fdrive`, authenticated with a dedicated service token (not a user session) and an IP allow-list.
- The endpoint does no parsing: it records the file in a `pm_files` table (path, size, mtime, sha256 when available, status) and dispatches a queued import job. It returns immediately.
- Follows the `tiger-bridge` pattern (small standalone Windows service, README, tests); a sibling folder `fdrive-watcher/` in this repo.

### F: access decision: read-only now, read/write after full adoption

**Decision: mount `/mnt/f` read-only (SMB `ro`, and a read-only share account) for now.** Ingestion, file indexing, PDF viewing and OCR only need to read. Read-only keeps a bug or bad import from damaging the existing folder tree while PM Suite and Forge are still being adopted and the structure is unsettled. Revisit **read/write after full adoption**, when Forge can own and standardize the folder structure (job folders, submittal/PO naming).

To keep that switch cheap, all F: access goes through one `FDriveStorage` service with a `FDRIVE_WRITE_ENABLED` setting (default `false`). While false, Forge-generated files (PO PDFs, submittal packages, closeout docs) are stored in Forge storage and offered as a download; users can save or copy them onto F: themselves. Flipping the setting and remounting `rw` later turns on direct saves to the standardized path without code changes.

### Recognising Forge-generated files (no wasted re-imports)

Whether a file reaches F: through a direct save later or a user dropping a download there today, ingestion must recognise it and tell new versions from unchanged ones, so it doesn't re-OCR or re-import work Forge already has.

- **Identity marker on every generated file.** PDFs get embedded custom metadata (XMP/Info): `forge_doc_id`, `doc_type`, `revision`, `content_sha256`, `generated_at`. Xlsx files get the same in custom document properties. The filename also carries a short suffix as a fallback, e.g. `..._FD-<doc_id>-r<rev>.pdf`, because metadata can be stripped by print-to-PDF or email tools.
- **Registry.** Every generated file is recorded in `pm_files` at creation (doc_id, type, revision, sha256, size, job, path once known).
- **Cheap detection order on each event or scan** (stop at the first conclusive step):
  1. Path + size + mtime unchanged since the last seen row, so skip. No hashing, no read.
  2. New or changed: read the marker (first few KB of the file) or filename suffix.
  3. Marker found and `sha256` matches the registered revision, so it is a Forge file we already hold. Record the path and **skip import**.
  4. Marker found, same `doc_id`, different hash or higher revision, so it was edited or re-saved outside Forge. Record it as a **new version** and route it to the review inbox (never silently overwrite).
  5. No marker: external document. Hash it and treat it as new if the hash is unseen; otherwise it is a duplicate/move, so just update the path.
- **Moves and renames** are detected by hash and don't trigger re-import.
- **Version history:** `pm_files` keeps every seen revision with its hash, so the UI can show "v3 saved from Forge, v4 edited on F:" and a person can choose which is current.
- **Open details:** exact metadata fields; confirm Word/Excel/Outlook "Save as PDF" paths keep the markers; which document types are generated in the first release.

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


Fill in **Include** (Y/N) and **Priority** (1 = first, 2, 3, Later). Priority: High / Medium / Low; **Hold** = not now. "Proposed" is my starting suggestion, not a decision.

**Build order from priorities:** High first (jobs, POs, vendors, BidEstimate import, material forecast, submittal tracker + builder, product library, roles), then Medium (receiving, change history, WO request flow, drawing import, to-do), then Low (dashboard widgets, labor schedule groundwork, OCR groundwork, glass visualizer, universal search). Hold items are not scheduled.

**Rule applied:** blank or undecided rows = Hold; included (Y) with no priority = Low.

| # | PM Suite feature | Proposed | Include (Y/N) | Priority | Notes |
|---|---|---|---|---|---|
| 1 | Jobs / job dashboard | Port (merge into BusinessJob) | Y | High |  |
| 2 | Purchase orders (builder, PDF, voided/closed axes) | Port (into PurchaseOrder; types stock / job / internal) | Y | High |  |
| 3 | Receiving | Port (job receipts only; no stock impact) | Y | Medium |  |
| 4 | Vendors / Contacts tab | Merge into Supplier | Y | High |  |
| 5 | Material Forecast | Port | Y | High | Reads job POs, so job POs need cost code and estimate fields from the start |
| 6 | BidEstimate import | Separate importer beside EzEstimateController | Y | High | BidEstimate importer stays separate (we control that format; EZ Estimate is external) |
| 7 | Submittal Tracker | Port | Y | High | Base for the Submittal Builder (#8) |
| 8 | Submittal Builder (combined PDF) | Port or keep client-side pdf-lib | Y | High |  |
| 9 | Closeout checklist + closeout vendors | Port | Hold |  |  |
| 10 | Product Library (spec / color PDFs) | Port as separate pm_products | Y | High |  |
| 11 | Change History / restore points | Generalise to ForgeDesk audit log | Y | Medium |  |
| 12 | Labor Schedule | Merge into FdScheduleEntry | Y | Low | Not a priority, but any groundwork that will help should be built as needed |
| 13 | OCR import: PO / quote / acknowledgement / receiving | Python worker + review inbox | Y | Low | Lower priority, but groundwork should be there |
| 14 | Users / roles (admin, superintendent, company dashboard) | Map to ForgeDesk roles/permissions | Y | High | Implement as planned |
| 15 | Work Orders (Excel Rev 6, door schedule, hardware sets) | Replace: PM requests, Forge validates (`FdWorkOrder`) | Y (as request flow) | Medium | PM-initiated work order requests validated by the existing Forge setup; not a port of the PM Suite WO feature |
| 16 | Dropbox file requests / opening-photo folders | Dropped with PM Suite WO (confirm) | Hold |  | Revisit once the WO request flow exists |
| 17 | Procore submittal sync | Undecided | Hold |  |  |
| 18 | Email Templates / Outlook compose | Undecided | Hold |  | need to look into methods for this (Outlook/mail integration approach TBD) |
| 19 | Glass Visualizer / glass shape designer | Undecided | Y | Low |  |
| 20 | PDF markup (markup review status) | Undecided | Hold |  |  |
| 21 | Drawing import (drawing_import.py) | Undecided (Python worker if kept) | Y | Medium | will be helpfulf for work orders/ etc |
| 22 | Hardware catalog importer | Undecided (overlaps configurator hwlib) | Hold |  |  |
| 23 | Kawneer importer | Undecided | Hold |  |  |
| 24 | Glazier Studio importer | Undecided | Hold |  |  |
| 25 | Vendor catalog importer | Undecided | Hold |  |  |
| 26 | Universal Search | Undecided (ForgeDesk search?) | Y | Low | a universal search that covers the entirety of forge may not be a bad idea |
| 27 | Activity Timeline | Undecided | Hold |  |  |
| 28 | To-do list | Undecided | Y | Medium | job/pm specific list |
| 29 | Production chart | Undecided | Hold |  |  |
| 30 | QR codes | Undecided | Hold |  |  |
| 31 | Dashboard widgets | Fold into customizable dashboard | Y | Low |  |

## 4c. Phase 0 findings (2026-11-13)

- **No data to migrate.** The live PM Suite database (`jv_vos-db-1`) holds no business data: `pm.shared_data`, `pm.documents` and `pm.meta` are empty and only `pm.auth_users` has rows (24 mirrored ForgeDesk accounts). The `backups/` dumps are ~12 KB, so they are empty too. `/mnt/f` is not mounted on this host. So there is nothing to import yet, and no data-migration or cut-over work is needed now. Importers can be built from the code's data shapes and tested with fixtures.
- **Vendor shape (PM Suite)** is richer than `Supplier`: company name, primary contact, preferred sales rep, additional contacts, multiple labelled phones and emails, website, address, products/services, notes, document attachments, and a **BidEstimate code plus learned aliases** (used to match the estimate's Supplier column to a vendor). `Supplier` has one contact (name/email/phone) and no aliases. **Gap to close:** `supplier_contacts` (many, with role and a preferred-rep flag), `supplier_phones`/`emails` (or a JSON column), `products_services`, `bidestimate_code`, `supplier_aliases`, attachments.
- **PO shape (PM Suite)** is nested: a PO holds `lines[]` that can be groups of sub-lines (flattened on read), each with its own cost code that overrides the PO-level cost code. Statuses are a different lifecycle from Forge's (draft, submittal-request, material-release, subcontract variants; plus separate `closed` and `voided` axes and numbered revisions with drafts). **Gap to close:** `PurchaseOrder` needs a status mapping or a separate "issue stage" column, revisions, and grouped line items.
- **Next build step (High priority, no data dependency): vendor extension** (the gap above) with the Supplier page showing the extra contacts and aliases, then the job-PO columns (job link, issue stage, revisions, grouped lines).

## 4d. Navigation: front of house vs back of house (decided and built 2026-11-13)

PM Suite is the **front-of-house** (project management, office) system; ForgeDesk is **back of house** (inventory, shop, fabrication). Keep that split visible while it is one app, one login and one dataset.

**Today:** navigation is config-driven (`config/navigation.php`, plus the `navigation_classic.php` set), rendered by `partials/nav-menu.blade.php`, and gated by `nav.*` permissions. CutFlow already has its own layout (`components/cutflow/layout`).

**Options**
1. *New sections in the existing nav* (Projects, Purchasing, Submittals gated by `nav.pm`). Cheapest, but the menu is already ~9 sections and the two audiences would still see each other's clutter.
2. *Workspace switcher* in the header: **Front of House** / **Back of House** (working names: "Projects" and "Shop & Inventory"). Each workspace has its own nav config; the switcher is only shown to users who can see both.
3. *Separate shell like CutFlow*: own layout, own `/pm/*` URL prefix and own dashboard, cross-links only. Strongest separation, most chrome work.

**Recommendation: 2 built on 3's URL/layout conventions.**
- New `config/navigation_pm.php` (and the same for the classic set). Existing `navigation.php` stays the back-of-house nav, unchanged.
- All PM pages live under `/pm/*` and use the same layout with the PM nav loaded; a header switcher toggles workspace and remembers the choice per user.
- `nav.pm` permission gates the front of house; the office-staff role gets it by default. Users with only shop roles never see the switcher. Default landing follows role (office staff land in Projects, shop staff in Shop).
- **Shared entities are shown in both, with a different emphasis**, cross-linked on the job number: Jobs (PM: submittals, POs, forecast, to-do; shop: reservations, work orders, shipments), Vendors/Suppliers (PM: contacts and POs; shop: products and low stock) and Purchase Orders (PM: job and internal POs; shop: stock POs). The existing stock/job/internal PO type decides which workspace a PO is primarily shown in.
- Front-of-house page groups (from the feature checklist): Dashboard, Jobs, Submittals (tracker, builder), Purchasing (job POs, receiving, vendors, material forecast), Library (product library), To-do, Admin (users/roles, change history).
- The PM to-do list, universal search and dashboard widgets (all in the checklist) sit in the front-of-house shell and can surface back-of-house data.

**Decisions (2026-11-13):**
- Workspace switcher with the `/pm/*` prefix. Names: **Project Management** and **Fabrication**.
- **Jobs are shared data with a lifecycle.** PMs bid and win jobs in Project Management (`bidding` -> `won` -> `lost`/`active`). Fabrication has no visibility until a job is **activated**; from then on it also sees the job's drawings, POs, work orders and so on. Implemented as a fail-closed global scope on `BusinessJob` (bidding/won/lost are hidden from every existing Fabrication query); only the PM endpoints opt out.
- **The field app gets nothing from Project Management for now.** Any field-app development must hold back PM data and features until this is revisited.

**Built:** `nav.pm`, `pm.jobs.view`, `pm.jobs.manage` permissions (admin, manager, office_staff); `config/navigation_pm.php`; workspace switcher (top navbar and sidebar, shown only to users with both workspaces); `/pm` dashboard, `/pm/jobs` (bids, stage moves, activate), `/pm/vendors`; PM-only users landing on `/` are redirected to `/pm`.

**Two job pages (decided):** Project Management's `/pm/jobs` drives Fabrication's job pages; they stay separate pages. **Until PM is fully adopted, Fabrication keeps creating and editing jobs exactly as it does today**: the goal is to build onto the existing ForgeDesk flow, not interrupt it. Concretely:
- Jobs created in Fabrication are active from the start (`origin = fabrication`, the default for all existing jobs) and appear in the PM job list with no bid history.
- Jobs created as PM bids (`origin = pm`) reserve their job number against Fabrication but are otherwise invisible to it until activated.
- Both workspaces edit the same record. No field ownership rules yet; once PM is the standard entry point, tighten which fields Fabrication can edit using `origin` as the migration marker.

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
3. Is the F: share staying, or should files move into ForgeDesk storage / Dropbox? f share is staying, will need to integrate. **Access: read-only for now** (see 4a); revisit read/write after full adoption to standardize file structures.
4. Who uses PM Suite (PM count) and can it be down during cut-over windows? currently not used in production environment, newly developed
5. Is the existing Work Orders feature in PM Suite in active use vs. ForgeDesk's `FdWorkOrder`? pm suite no; goal is PM-requested work orders validated by Forge
6. Should PM Suite's separate roles (admin/superintendent/company dashboard) map to ForgeDesk roles? forgedesk roles to develop to suppport pm roles (office staff is current forge role with simialr)

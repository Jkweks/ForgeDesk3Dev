# Configurator — Issues / Layout / Features

Living checklist for the door/frame configurator. Converted from `configurator.txt`
and kept up to date as items land. Checked items link back to what was actually
built, not just what was requested — verify against the code if it's been a
while since the note was written.

## Workflow

- [x] **Configurator sub-categories.** Hardware library categories can have an
  optional second level (e.g. Cylinders → Rim/Mortise/Cores/Rings, Panics →
  Rim/CVR/Mortise), managed from the Category modal in Configurator admin →
  Hardware Library. Category pickers (Hardware tab item-add form, admin
  Items filter) flatten a category with subcategories into one
  "Category - Subcategory" option per subcategory, plus a bare "Category"
  entry for any items left without one — a category with no subcategories
  is unaffected. `configurator_hwlib_subcategories` table,
  `ConfiguratorHwlibItem::subcategory_id`, `fbBuildHwCategoryOptions()`.
- [x] **Work order / elevation integration.** Configs can be pulled into a work
  order's Door Schedule (existing drafts listed + attachable), production steps
  gate on release via `StageGateService::blockingConfigurationReasonFor()`, and
  elevations re-sync (non-destructively — orphans are flagged, never deleted) if
  scope changes after the work order exists.
  `ElevationConfigurationMatcher`, `ElevationController::availableConfigurations/attachConfiguration`.
- [x] **Length-driven components.** Products can be flagged `is_length_based`;
  reservation quantity rounds up to the next 1/10 of stock length instead of
  going to the cut list. `Product::is_length_based`, `ConfigurationReservationBridge::quantityContribution()`.
- [x] **Investigate laggyness** loading configs / editing parts within a config —
  root causes found and fixed:
  - Every part edit/generate call on a **reserved** config ran
    `ConfigurationReservationBridge::reserve()` synchronously — reloading the
    whole BOM tree and saving each touched `Product` one at a time (each
    triggering a committed-quantity recalculation). Moved off the request
    path onto the existing Redis queue worker.
    `SyncConfigurationReservationJob`, `DoorFrameConfigurationController::syncReservationIfReserved()`.
  - `Product::quantity_available`/`quantity_available_packs` are `$appends`
    accessors that **always** recomputed from a per-product reservation
    query, silently discarding any value a caller had already computed more
    cheaply (e.g. `DashboardController`'s single aggregate query) — Eloquent
    calls the accessor on every read/serialization regardless of a manually
    set raw attribute. This caused the "quantities not loading in job
    dashboard until headings clicked" symptom (both the initial load and the
    sort-click hit the same slow N+1-laden request) and made
    `/configurator/catalog/tree` expensive (every nested product in the
    frame catalog re-ran the query). Fixed the accessors to respect an
    already-set raw attribute; `ConfiguratorCatalogController::tree()` now
    also hides the appends on its nested products entirely, since that tree
    never displays them. `Product::getQuantityAvailableAttribute()`/`getQuantityAvailablePacksAttribute()`.
  - The Frame Builder re-fetched the entire frame catalog tree and hardware
    catalog on **every** config click instead of caching them for the page
    session (unlike the door catalog, which already cached correctly) —
    added the same cache guard. `fbLoadCatalogTree()`, `fbLoadHwCatalog()`.
  - **Not yet fixed / follow-up:** the frontend still does a full
    `GET .../{id}` reload (`fbLoadDetail()`) after every single part
    edit/save/generate/delete instead of patching local state from the
    mutation's own response — compounds whatever cost remains on each save.
    Deferred as a separate, larger frontend refactor (~10 handlers in
    `frame.blade.php`).
- [ ] **Tie rod calculation** — select tie rod length from the bottom rail's
  *calculated* length (checked against stile width), not a fixed table. E.g. a
  Medium stile door with a 28.8125" bottom rail should pick the tie rod that
  matches 30.813". Port the logic from the blank door calculator's tie-rod page.
- [x] **Hardware pair checkbox** only shows when opening type is Pair
  (`fb-hw-leaf-wrap`, toggled in `fbToggleHand()` / `fbRenderDetail()`).
- [x] **Save-and-continue.** Saving Opening/Frame/Door auto-advances to the next
  relevant tab; Frame/Door saves (and adding a hardware link) auto-generate the
  BOM on first run-through; a later edit previews the regenerated BOM, diffs it
  against what's saved, and only prompts to overwrite if something actually
  changed. Manual "Generate" buttons still force-regenerate with no prompt.
  `fbGenerateWithDiffPrompt()`, `fbAdvanceTab()`, `preview=1` query param on the
  three `generate*Parts` endpoints.
- [x] **Bulk duplication**, exact and with modified values (e.g. flipped hand).
  `POST /door-frame-configurations/{id}/duplicate` clones opening/frame/door/
  hardware data per row and auto-generates BOM; a "Flip Hand" checkbox swaps
  LH/RH across opening + door handing in one click instead of hand-picking
  overrides.
- [x] **Reserved ↔ Draft**, with reservation sync as fields change. The
  Reserve/Back-to-Draft buttons are now one toggle: outlined "Reserve" while
  draft, solid "Reserved" once reserved — clicking it while reserved prompts
  that switching back to draft cancels the reservation and releases committed
  inventory. `fbToggleReserve()`. Reservation stays in sync with part changes
  via `syncReservationIfReserved()` on every part-mutation endpoint.
- [x] **Configuration name removed** — door tag(s) are the identity/name, and
  quantity is always `count(door_tags)`, never entered separately.
- [x] **Linked duplicates.** Configs created together via bulk duplication share
  a `duplicate_group_id`; the detail view shows a banner naming the linked
  siblings with "Unlink This One" / "Unlink All" actions
  (`POST .../unlink` with `scope: single|all`).
- [x] **Export cutlist → CutFlow.** Releasing a configuration pushes the whole
  work order's released lineal cut-list directly to CutFlow over HTTP (no
  manual CSV round-trip) — one CutFlow `CutJob` per work order
  (`forgedesk_job_id = "wo-{id}"`), merged/updated in place on re-release
  without resetting already-recorded cut progress. A work order also has an
  "Upload Cutlist" button for lists not sourced from the configurator (e.g.
  curtainwall), which merge into the same CutFlow job.
  `App\Services\Configurator\CutFlowExportService` (ForgeDesk),
  `ImportController::apiImport()` (CutFlow, separate repo at
  `/mnt/homeNAS/Container/cutflow`) — see the `cutflow-integration` memory
  for the network/identity details.
- [x] **Minimum drop length** field added to product data
  (`products.minimum_drop_length`) — not yet consumed by a cut-flow drop
  printout, since cut flow itself is still back-burnered.

## Layout — Opening

- [x] Scope at top of the tab.
- [x] Divider lines grouping Scope/Type/Finish/Glazing, then Hand/Hinging,
  then Width/Height (`<hr>` between each group in `fb-opening-form`).
- [x] **Glazing** consolidated onto Opening as the single source of truth
  (`door_frame_opening_specs.glazing`, the real 11-value
  `configurator_glass_specs.thickness` domain) — removed from both Frame and
  Door tabs. `DoorBomGenerator` now reads it from `openingSpecs->glazing`
  instead of the door config; the frame tab's old `glazing` column was neverin
  actually read by `FrameBomGenerator` (only `transom_glazing` is), so
  nothing there lost any real behavior.
- [x] Hand: LHRA relabeled to "LHR Active"; selecting it prompts
  ("non-standard configuration, please verify") since RHR Active is the
  standard, no-warning option. `fbCheckNonStandardPairHand()`.
- [x] **Hinge spacing standard.** "Number of Hinges" + "Hinge Spacing
  Standard" fields show on the Opening tab when Hinging = Butt; a computed
  "hinge prep locations from door top" preview renders after saving, and the
  same locations appear on the cut-sheet PDF. Standards (top/bottom distance +
  reference point, e.g. Standard: 2-15/16" top / 3" from door bottom;
  Curries: 7-1/4" top / 12-1/4" from finished floor, both center-of-prep) are
  admin-managed (Configurator admin → Door Catalog → Hinge Spacing
  Standards) rather than hardcoded, so new ones don't need a deploy;
  additional hinges beyond the top/bottom pair space evenly between them.
  Selecting a Butt Hinge hardware item auto-fills (read-only) its quantity
  from the hinge count — enforced server-side too, not just in the UI.
  `ConfiguratorHingeSpacingStandard::locations()`, `DoorFrameOpeningSpec::hingeLocations()`,
  `ConfiguratorDoorCatalogController::storeHingeSpacingStandard()` et al.
- [x] Opening Width/Height: default 36 single / 72 pair, height defaults to 84
  regardless; soft-limit prompts at <30" wide / <70" tall (confirmed
  thresholds); labels read "Door Opening Width" / "Door Opening Height".
- [x] Global Settings gear icon on the Opening tab edits top/bottom/hinge/lock
  gap (`configurator_settings` singleton row, `ConfiguratorSetting::current()`,
  `GET/PUT /configurator/settings`). Only `bottom_gap` actually drives a
  calculation today (`DoorBomGenerator`'s stile length, pulled from here
  instead of a per-door field); top/hinge/lock gap are stored ahead of the
  generators that will eventually consume them — same "wire now, consume
  later" pattern as `minimum_drop_length`.
- [ ] Toast naming the specific incomplete/invalid field on failed validation —
  not built. Failed saves currently show a generic "Validation failed" message;
  the field-level `errors` object Laravel already returns isn't surfaced.

## Layout — Frame

- [x] Transom selection + its fields grouped in their own bordered box between
  divider lines.
- [x] Frame-tab Glazing select removed (see Opening note above).
- [x] **Fixed:** total frame height now accepts non-integer/fractional entry
  (fraction-entry widget wired to `fb-frame-height` via `fdAttach`/`fdRefresh`).

## Layout — Doors

- [x] Hinging, Handing, Glazing, and Bottom Gap all removed from the Door tab
  — driven entirely by Opening data (+ the global bottom gap setting) now.
  `DoorFrameOpeningSpec::deriveDoorHanding()`/`deriveHingeType()` compute the
  door-generator's handing/hinge-type values from opening type + hand +
  hinging (center-pivot hinging takes priority, matching the old "CP
  SINGLE"/"CP PAIR" handing values). The door config row still mirrors these
  derived values on save (so `formatDoorConfig()`/duplication keep reading
  something meaningful) — the Door tab just no longer asks for them directly.
- [ ] Door Series / Stile Width — Door Series display is already name-cased
  ("Standard"/"Thermal"/"Monumental"); Stile Width options are populated from
  the catalog and haven't been specifically audited for casing.
- [x] Top rail uses the recommended value; bottom rail defaults to 10"
  (existing behavior, unchanged this pass).
- [ ] Set default top and bottom rails for doors, all bottom rails default to 10", narrow to 2 1/8 top, medium to 3.5, medium 4" to 4", wide to 5"

## Layout — Hardware

- [x] Standard filter hides categories with no items matching the filter
  (`fbRenderHwCategorySelect()` checks `vos_standard` before listing a
  category).
- [x] Leaf selector only shows for pairs (`fb-hw-leaf-wrap`).
- [ ] Series input still exists as a manual field — not yet auto-derived from
  the door/frame selection to generate the correct backer parts per side/type
  (e.g. thermal door → `PEPTTD1-0R`/`PEPTTD2-0R`, frame → 2× `PEPT-0R`).

### Hardware advanced rules controller — not started

Needs a full rule-set walkthrough with the user before design (flagged
explicitly — the examples below are illustrative, not exhaustive):

- P1420 always requires push + pull hardware selection (cylinders, paddle
  latches, or lever handles for each).
- Single door: the P1420 strike is a **prep** cut into the frame (`P1420-ST`),
  not a physical part. Pair: that prep moves to the inactive leaf, which also
  needs ≥1 flush bolt (2 if a threshold is present).
- P1421 is similar but needs a **physical** strike: `P1171-0R` (single) or
  `P1172-0R` (pair).
- Pull handle centerline is a fixed dimension above P1420/P1421 hardware, and
  equals panic AFF when a panic is used. A custom rim panic ties the pull
  backset to the calculated panic backset; a standard panic uses a fixed
  value instead.
- `P3101-0R` EPT prep location depends on system: frame-side prep with a
  14000 or 4500 system, door-side prep with a 14000 I/O system.
  
## Notes on this file

Originally `configurator.txt` (plain notes). Converted to Markdown so progress
can be tracked with checkboxes. When picking an item back up, re-verify its
"done" status against the code before relying on this file — it reflects state
as of 2026-09-21.

# Configurator parity harness - first run (2026-10-02)

Tool: `php artisan configurator:parity-check [--kind=door|frame] [--id=N] [--verbose-diffs]`
(`app/Console/Commands/ConfiguratorParityCheck.php`). Read-only: builds unsaved models from fab_utils'
`saved_configs.inputs`, runs `DoorBomGenerator`/`FrameBomGenerator`, diffs against `saved_configs.outputs`.
Matching is by part number (finish suffix stripped); weatherstrip compared in feet when fab_utils notes `LF`.
Data file: `storage/app/fab_utils_parity/saved_configs.json` (re-dump with psql, see memory
`fab-utils-configurator-reseed` for the piping method).

First run: door 64 ok / 33 differ / 0 errors; frame 8 ok / 106 differ / 0 errors (211 saved configs).

## Caveat
`saved_configs.outputs` are historical - fab_utils' catalog may have changed since a config was saved, so a diff
is a *lead*, not proof. Phase 0b: also execute fab_utils' live `calculate()` JS against its current catalog
(node) for an apples-to-apples reference.

## Findings
1. **Opening dimensions lose precision (likely real bug).** `door_frame_opening_specs.door_opening_width/height` are
   `decimal(10,2)`; `calculated_length` on frame/door/hardware part tables is `decimal(10,2)` and `total_frame_height`
   is `decimal(10,2)`. Fractional inches (93.625, 88.125, 1/16", 1/32") are rounded to 0.01", e.g. stile
   92.8125 -> 92.81 and a 0.005" shift from rounded H (seen in configs #19, #73). Cut lists/labels get rounded lengths.
   Fix: widen to `decimal(10,4)` (matches `bottom_gap`) + update casts; migration is non-destructive.
2. **Frame weatherstrip (P1098A) is the biggest frame diff (87 configs).** fab_utils treats product
   `default_accessories` / weather items as `per_length` and reports linear feet (ceil(len x qty / 12)); the
   ForgeDesk catalog imported them as `per_opening` qty 1 (3 vs 17 ft on a typical frame). Catalog-import gap:
   `product_accessories.json` semantics (`qty_type` default `per_length`, `is_weather` regex
   `felt|gasket|seal|weather`, `glass_thicknesses` filter) were not carried over. Fix in importer + reseed.
3. **Kit expansion.** fab_utils `expandKits()` splits kit PNs (e.g. P1928A -> P1929A x3 + P5919); confirm ForgeDesk handles
   this (S449, P1928C show up as missing/extra).
4. **Tie rod PN differs on ~20 door configs** (P022O vs P022P/P022V for 36" wide-stile). Lookup logic is identical in
   both codebases, so this is probably catalog drift since save - verify via live JS run (Phase 0b).
5. **Finish handling.** Several diffs are `-C2` expected but ForgeDesk substitutes `-0R`/other (E2550, P797, E4013/E4015
   in BL/DB): the product exists in fab_utils by PN string but isn't stocked in that finish in ForgeDesk. Not a math
   bug - shows as a reservation/resolution warning. Needs a decision per part (create finish variant vs accept fallback).
6. **Door rail/stile extrusion PN differences** (E7418 vs E7415, E6422/E14144/E4531 swaps) - door-type catalog lookups;
   same drift caveat, check with live JS run.

## Next
- Phase 0b: node-run fab_utils live calc for the same inputs -> removes the drift caveat.
- Fix 1 (migration) and 2 (importer) first; they are clearly ForgeDesk-side.
- Add hardware (hwlib) comparison - `hardware_configs` + `hwlib_saved_config_links` - not covered yet.
- Convert to a PHPUnit golden test once the diffs are triaged to zero or an explicit allow-list.

## Update - fixes applied (same day)
- Migration `2026_10_02_000001_widen_configurator_dimension_precision` applied on dev; casts widened to 4 places.
- `ImportFabUtilsFrameCatalog`: (a) accessory `qty_type` default now `per_length` (matches fab_utils JS);
  (b) now imports fab_utils `frame_components` + `frame_fasteners` (previously ignored - S449 screws, S420/S070,
  threshold clips were missing; ForgeDesk had 0 fasteners). Dumps `frame_components.json`/`frame_fasteners.json`
  must exist in `storage/app/fab_utils_import/` (re-dump with the psql pipe from the reseed memory note).
- Frame catalog reseeded on dev (no existing frame configs were affected - there were none).
- Harness after fixes: door 66 ok / 31 diff; frame 87 ok / 27 diff (was 64/33 and 8/106).
- Remaining frame diffs are mostly *stale references*: older saved outputs predate fab_utils' current weatherstrip
  (LF) logic and show P1098A as a cut extrusion, and older stop lengths. Door diffs are tie rod / door-type PN picks
  (P022O vs P022P, E7418 vs E7415) - need the live-JS reference (Phase 0b) to separate drift from real bugs.
- Still open: non-weather per_length accessories (go to extrusion list in fab_utils), accessory glass_thicknesses filter,
  kit expansion, finish-availability policy, hardware (hwlib) comparison.

## Update 2 - live fab_utils reference (Phase 0b done)
`laravel/tests/Parity/fab_utils_live.js` loads fab_utils' real calculator pages in jsdom and replays each saved config
through the page's own `loadConfig()` (no writes to fab_utils), giving current-catalog outputs for 195 of 211 configs
(16 produce nothing in fab_utils itself, e.g. legacy inputs). Against that reference:

| | before | after all fixes |
|---|---|---|
| frame | 8 / 114 | **102 / 103 match** |
| door | 64 / 97 | **81 / 84 match** |

Extra fix this round: frame components now carry fab_utils' accessory `glass_thicknesses` filter (migration
`2026_10_02_000002`, importer, `FrameBomGenerator`) - the 1" storefront gasket was being added to every glazing.
The earlier tie-rod/door-type door diffs were catalog drift in the *saved* outputs, not ForgeDesk bugs.

Remaining (not ForgeDesk bugs unless noted):
- 3 door configs (#244-246): legacy saved `hingeType` is blank; fab_utils falls through to the center-pivot stile PN, harness maps blank -> continuous.
- Quantity-0 configs are skipped (fab_utils returns nothing meaningful).
- **Frame #70 (open lead):** 4500 Series, transom + threshold, glass 1": vertical transom gutter/stop lengths differ
  (24 vs 24.25, 23.969 vs 24.219) and one gasket qty (14 vs 16). Old config without `thresholdHeight`; check how
  fab_utils treats threshold + transom together vs `FrameFormulaEvaluator`.
- Admin UI for frame components doesn't expose `glass_thicknesses` yet (API validation also not updated).
- Still unchecked: non-weather per-length accessories, kit expansion, finish-availability policy, hardware (hwlib).

## Update 3 - frame #70 solved, hardware library compared (Phase 0 complete for BOM math)
Results vs live fab_utils: **frame 103/103, hardware (hwlib) 53/53, door 81/84** (the 3 are legacy configs with a blank hinge type).

Fixes this round:
- **Frame #70**: a series can hold two profiles with the same role label that differ by glazing (e.g. two "Head Transom Gutter":
  1.25" for 1" glass, 1.0" for thin glass). `section:<role>` formula terms now use the profile that applies to the
  configuration's glazing (`FrameBomGenerator`).
- **Hardware backers/fasteners were never generated** for catalog-imported items: item-backer rows have no PN of their own,
  only a link to the backer record; the generator skipped them. Now falls back to `backer->pn/description`.
- **Pair openings**: a hardware link with `leaf = both` is now doubled on a pair (fab_utils `effectiveLinkQty`). 464 of 475
  fab_utils links are `both`. Previously a pair got single-leaf hardware (applies once parts are regenerated).
- **Backer sides**: only the door/frame sides included by the opening's scope are pulled.
- **Finish-less stock** (NULL finish, e.g. ASA strike) was never matched by `FinishFallbackResolver` and silently dropped
  from the BOM; it is now the last fallback.

Reference scripts: `laravel/tests/Parity/fab_utils_live.js` (door/frame) and `fab_utils_hwlib.js` (hardware). Data prep for hardware:
`psql ... "SELECT json_agg(row_to_json(t)) FROM (select l.id link_id, l.saved_config_id, l.item_id, l.quantity, l.leaf, l.series,
i.name item_name, i.manufacturer, i.model_number, i.pn, i.handed, c.name category_name, s.inputs->>'handing' handing
from hwlib_saved_config_links l join hwlib_items i on i.id=l.item_id left join hwlib_categories c on c.id=i.category_id
join saved_configs s on s.id=l.saved_config_id) t" > hwlinks.json`.

### Decisions needed
1. **Kit parts conflict.** fab_utils expands `P1928A -> 3x P1929A + 1x P5919` and `P1928C -> 3x P1929B + 1x P5920`.
   `MaterialCheckController::applyPartRules` has them swapped (`P1928A -> P1929B`, `P1928C -> P1929A`). Which is right?
   Also: should BOM/reservation rows expand kits into pieces (fab_utils does for display; reservation then reserves pieces)?
2. **Handed (L/R suffix) hardware**: implemented in fab_utils but no item is flagged `handed` in either catalog -> skipped for now.
3. **Hardware items without a stock PN** (e.g. Ives 5BB1 hinge) produce no BOM row in ForgeDesk by design; fab_utils lists them
   by model number. Keep (not reservable) or show them on the package PDF as "no stock part"?

## Update 4 - kits, special-order hardware, handed hardware (decisions applied)
- **Kits -> pieces** (`Services/Configurator/KitExpander`, used by Frame/Door/Hwlib generators): `P1928A -> 3 P1929A + 1 P5919`,
  `P1928C -> 3 P1929B + 1 P5920` (fab_utils values; we stock pieces). `MaterialCheckController` mapping corrected to match.
  Parity harness expands kits on the fab_utils reference the same way.
- **Special-order hardware**: hardware items with no stock product now produce a row with `product_id = null` + manufacturer/model
  (migration `2026_10_02_000003`), instead of being dropped. Shown in the config UI as "Special order" and as its own
  "Special-Order Hardware" table on the cut-sheet PDF. Commits nothing to the job reservation. Pair/leaf doubling applies.
- **Handed hardware**: items flagged `handed` get an L/R suffix from the opening's handing before the finish suffix
  (LH/RHR -> L, RH/LHR -> R; pairs and center-pivot none): P1421 + RHR -> P1421L-0R. `FinishFallbackResolver` finds it.
- **Default cover/strike auto-add** (fab_utils behaviour, was missing): adding a hardware item also adds its default
  strike/cover link if not already on the opening (`addHardwareLink`).
- **Catalog data changes (dev DB, not in fab_utils)**: item 22 "Adams Rite - 4510 Deadlatch" set `handed`, new item 71
  "Adams Rite - 4510 Cover (handed)" pn `P1411`, flagged `needs_review` (P1411L description in inventory is "Cover For LH P1421"),
  wired as item 22's `default_cover_item_id`. **A `configurator:import-fab-utils-hwlib` reseed would wipe these** - mirror them in
  fab_utils (or add to the importer) before reseeding.
- Harness after all of this: frame 103/103, hardware 53/53, door 81/84 (blank-hinge legacy configs).
- Verified directly: RHR/C2 -> P1421L-0R + P1411L-C2; LHR/DB -> P1421R-0R + P1411R-DB; RH inswing/BL -> P1421R-0R + P1411R-BL;
  custom item -> special-order row; P1928A x2 -> P1929A-0R x6 + P5919-0R x2.

## Update 5 - Phase 1 started: door labels (leaf checklist only)
Scope confirmed with user: the label that matters is fab_utils' **4" x 2" leaf checklist** (door-labels.html "Leaf Checklist"),
not the 4" x 1" part labels (the part labels are what the TigerStop/CutFlow saw labels cover).
- `Services/Configurator/DoorLabelService` - label **data** (renderer-agnostic): one label per leaf of each physical door tag
  (pair = LH + RH), job / WO#, door tag, hand, INSWING flag, checklist (Midrail if the door has one, Glass Stops, Set Blocks,
  Hinge Screws, Glass Jack, plus Strike / EPT / Overhead Stop when linked), initials line. Sorted job -> WO -> door tag (natural).
- `GET /api/v1/config/labels/sources` (door picker) and `GET /api/v1/config/labels?configuration_ids[]=` (label payload).
- `/config/labels` page: job/WO/door picker, 2 x 5 sheet preview (10 per sheet), "start at label N" to reuse a part-used sheet, Print.
  Linked from Configurator nav and a "Labels" button on a configuration. Hand = solid (LH/LHR) vs outline (RH/RHR) so it works in B&W.
- **Zebra later**: the payload is the interface. A ZPL renderer + tiger-bridge call can consume `labels[]` unchanged; the sheet page
  becomes an alternative output. (Label stock/dpi decisions needed then: 4x2 thermal.)
- Behaviour difference vs fab_utils: fab_utils printed one set per *saved record* ("3820A / 3820B" = one record); ForgeDesk prints
  one per **physical door tag**.
- Tested: payload against built configs (single/pair, midrail, linked hardware). **Not yet viewed in a browser** (no configs in dev DB).

### Package PDF - what fab_utils actually uses (to confirm)
The stored `pdf_templates` use hwlib blocks (`hwlib_hardware`, `hwlib_backers`, `hwlib_variables`, `hwlib_inspection`), so the current
source of truth is the hwlib-era report in workspace.html ("Export PDF - Choose Sections"): Door sheets, Frame sheets, Cut List,
Stock Length Requirements, Bill of Materials, Field Install Pick List - NOT the older export.html "Work Order Package". Layout is a
12-column block grid (key / span / enabled / per-column toggles) edited in pdf-template-admin. Gap list vs ForgeDesk cut sheet:
per-opening sheets with hwlib hardware / backers / resolved prep variables / inspection checks; WO-level cut list, stock lengths,
BOM, field-install pick list; configurable layout; the "verify before release" attestations (dims current, hardware complete).

## Update 6 - Phase 1: fabrication package (hwlib-era report) built
Confirmed with user: the report fabricators use is the **hwlib-era** per-opening report (workspace.html `downloadHwlibPDF`).
- `Services/Configurator/PackageReportService` - port of that report's data. Per opening a DOOR and a FRAME sheet of blocks:
  summary pills, extrusion, component (door) / weatherstrip + hardware (frame), hwlib hardware schedule, backers & fasteners
  (side-filtered, grouped), resolved hardware variables (grouped by item, overrides highlighted, `PANIC_BACKSET` always
  highlighted, `<CODE>_DF` door/frame selector, `<CODE>_DESC` labels, hidden/unconfigured items skipped), inspection sign-off
  (values as tape fractions, initials box). Whole-set pages: cut list, stock lengths (first-fit-decreasing sticks, E6169=120",
  default 252" or product stick length), hardware BOM, field-install pick list. Reads the configuration's **actual parts**
  (so manual edits and what's reserved match the paper), not a recompute.
- `configurator_pdf_templates` + `ConfiguratorPdfTemplate`: block layout per report type (order / 1-12 span / enabled / per-column
  toggles). `configurator:import-fab-utils-pdf-templates` imported fab_utils' saved door + frame layouts. Edited via the
  "Edit layout" dialog on the package page (needs `configurator.catalog.manage`). Only format is stored; data is always live.
- API: `GET /config/package/sources`, `GET /config/package?configuration_ids[]=&sections[...]`, `GET /config/pdf-templates`,
  `PUT /config/pdf-templates/{door|frame}`. Page `/config/package` (job/WO picker, section toggles, browser print = Save PDF,
  same approach as fab_utils). In the Configurator nav.
- Bug found+fixed: `HwlibBomGenerator` ignored the configuration's quantity, so a 2-door configuration reserved hardware for one
  door. Now multiplied by the number of openings (backers/fasteners follow). Regenerate hardware parts on existing configs.
- Tested: built a full configuration in a rolled-back transaction (frame+door parts, 3 hardware links incl. handed lock, a
  special-order hinge, a backer) -> all blocks populate; handles a partly-built real config; endpoints return 200;
  page scripts pass `node --check`. **Not yet viewed in a browser / printed.**
- Known deviations / to decide:
  * handed items show model + suffix like fab_utils ("4510 DeadlatchL"); the stock PN (P1421L) is clearer - consider showing it.
  * hw cut rules / "door stop split" (export.html legacy) intentionally not ported - the hwlib report doesn't use them either.
  * Hardware variable *values* come from `HwlibResolver`, which has not yet been parity-tested against fab_utils' `hwlibResolveVar`.
  * Release "verify" attestations (dimensions current / hardware complete) belong to the Phase 3 release preflight.

## Update 7 - hardware variable resolver verified; handed PN display
- **`HwlibResolver` parity: 53/53 combos, 2,039 variable values (58 codes, 155 overrides) match fab_utils' own `hwlibResolveVar`**
  (value and overridden flag). Harness: `tests/Parity/fab_utils_hwvars.js` extracts fab_utils' resolver verbatim and runs it per saved
  link; `configurator:parity-check --hwvars=...` rebuilds each combo (door + paired frame sharing hardware) as one configuration in a
  transaction that is always rolled back, runs `HwlibResolver`, and diffs. Dumps needed (psql): saved_configs(id,inputs,frame_config_id,
  work_order_id) -> hv_configs.json; hwlib_saved_config_links + item/category names -> hv_links.json; hwlib_variables(id,code,default_value)
  -> hv_vars.json; frame_profiles 'Lock Jamb Stop' with series/system names -> hv_lockstop.json.
- BOM-math parity is now complete across frame, door, hardware lists and hardware variables.
- Handed items now show the stocked PN on the schedule/BOM/field-install ("P1421L · 4510 Deadlatch") instead of "4510 DeadlatchL".

## Update 8 - release flow, job-level reservation, locking, un-release, cut-list divergence
Decisions (2026-10-03): released = locked; cut-list hand edits allowed for rare fixes (flagged); un-release only before cutting starts.
- **One reservation per job** (`job_reservations.source = 'configurator'`, partial unique index per job). `ConfigurationReservationBridge`
  rewritten (same callers): recomputed from ALL of the job's reserved / released / in-progress configurations each time.
  Extrusions = whole sticks via `StickYield` (first-fit-decreasing across the job; same number as the stock-length page), roll stock =
  job inches / roll length to 1/10, everything else summed, special-order hardware commits nothing. Never reduced below consumed.
  Emptied reservation is cancelled and revived when parts return. Each configuration's `job_reservation_id` points at the job reservation.
- **Locking**: released configurations were already uneditable (all edit endpoints gate on `canEdit()`); verified by test.
- **Release**: `GET .../release-preflight` (blockers: invalid / parts not generated / no work order; warnings: shortages vs available,
  special-order items, cut-list hand edits, roll-length gaps) and `POST .../release` now requires `confirm_dimensions` +
  `confirm_hardware` (fab_utils' two attestations) and re-checks blockers server-side. /config shows a release dialog.
- **Un-release**: `POST .../unrelease` -> back to *reserved* (editable, still committed). Refused once any cut is logged on the work
  order; rebuilds the generated cut-list lines (`parts.source = 'configurator'`) from the still-released openings; needs
  `confirm_discard_cut_edits` if the list was hand-edited.
- **Cut list**: `parts.source` ('configurator' | 'csv' | 'manual') and `cut_jobs.bom_diverged_at` (cutflow DB migration). Hand edits via
  /fabrication/cut-lists mark the job diverged; /config shows "Cut list edited by hand" on every configuration of that work order.
- **Stick lengths**: frame importer stores fab_utils' 288" on the products (`configurator_length`); door extrusions default 252", E6169 120"
  (`StickYield`). Applied to dev directly (rerunning the frame importer on dev would detach existing configs' frame series).
- Tested end to end in rolled-back transactions on both databases (default + cutflow): 2 openings share one reservation with packed sticks;
  back-to-draft shrinks/cancels; release refused without confirmations; edit blocked when released; un-release rebuilds cut list; re-release;
  hand edit sets divergence and gates un-release; a logged cut blocks un-release.
- **Not done yet**: consuming the reservation as sticks are cut (cut logs -> `consumed_qty`); a cut-lists page notice for diverged lists
  (data is exposed as `bom_diverged`); browser check of the new release dialog.
- **Data gaps found**: weatherstrip `P1098A` ("Door Stop Pile") and several gaskets (P4631, P1221, P487, 027851, PTB33, P4500, P6587, P2183 ...)
  have roll sizes only in their descriptions - set `is_length_based` + roll length on those products or they reserve "one each per line".

## Update 9 - roll lengths applied; frame gasket substitution
- `docs/plans/roll-lengths-draft.csv` reviewed and applied on dev via `php artisan products:apply-roll-lengths <csv> --apply`
  (marks products length-based + roll length in inches; dry run without --apply). 21 products: P1098A 2000', P912 250', P1477/P1431 500',
  P487/027857/PTB28 500' (confirmed guesses), PTB148/PTB94 400', plus the 500'/250'/400' ones read from descriptions.
  **Re-run this against production after the equivalent catalog is there** - it is data, not schema.
- **Frame gasket** now reserves against `P2728-250-0R` (250' rolls; the 500' is being phased out): `ImportFabUtilsFrameCatalog::STOCK_SUBSTITUTIONS`
  maps fab_utils' generic P2728 to P2728-250 on every import, and the 9 dev frame components were repointed. Parity harness aliases it so
  frame parity stays 103/103.
- Check: job reservation now holds gaskets/weatherstrip as fractions of a roll (e.g. P0017 0.2, P912 0.2, P1098A 0.1) instead of "one each".

## Update 10 - fabrication package is now one sheet per piece (correction)
Earlier build printed one door page + one frame page per *configuration* (all tags, both leaves, all hardware). Correct behaviour,
matching fab_utils (a record per door tag) and the intent: **one door page per leaf of every door tag, one frame page per tag**,
each showing only what belongs to that piece. A pair = 2 door pages + 1 frame page.
- Parts are divided out per tag; for a pair each leaf gets half, except once-per-pair pieces (Active Astragal Stile, Astragal -> active leaf;
  Inactive Meeting Stile -> inactive leaf). Gasket inches divide the same way. Whole-job pages (cut list, stock, BOM, field install) stay totals.
- Leaves: LH + RH; active leaf follows the pair handing (PAIR-RHRA -> RH active, PAIR-LHRA -> LH active). Title/pill show e.g. "LH LEAF (INACTIVE)".
- Hardware per sheet: a link marked active/inactive appears only on that leaf; qty is per leaf on a door, both leaves on the frame. An item
  appears on a sheet only if it has something to do there (a prep value for that side, or a backer on that side); items with nothing
  side-specific go where they mount - Strike / Frame / Threshold categories on the frame, everything else on the door.
  Backers, variables and inspection rows follow the same filter.
- **Handed hardware on pairs is now per leaf** (`HandedHardware`): active-leaf lock = that leaf's hand (RHRA -> P1421R, cover P1411R),
  inactive = the other hand, "both leaves" = one L + one R. Applied to the sheets, the whole-job BOM, field-install list and the
  reservation BOM generator. (fab_utils gave pairs no suffix because its one record covered both leaves.)
- Verified in a rolled-back test: pair "201" -> 3 pages (LH leaf, RH leaf, frame), singles "101A/101B" -> 4 pages; RH active leaf carries lock+cover,
  LH leaf only the hinge, frame the strike + both leaves' hinges/backers; generator produces P1421R-0R + P1411R-C2 for the pair, P1421L-0R x2 for the two singles.
- Parity unchanged: frame 103/103, hwlib 53/53, door 81/84.
- Assumptions to confirm on a real print: astragal piece belongs to the active leaf; RHRA = RH leaf active; category defaults above.

## Update 11 - cutting draws down stock and the job reservation
When a stick is finished (all pieces cut) or stopped after cutting some, `CutConsumptionService::closeSession()` runs (hooked into the cut station's
finalize + stopStick; failures are logged and never interrupt cutting):
1. inventory on hand drops by **one whole stick** (`InventoryDeductor` - same location/transaction ledger as completing a reservation; transaction ref `CUT-<stick>`);
2. each cut list on the stick is credited its **share** (its inches / total inches cut from that stick, so a stick shared by jobs sums to 1; waste is shared in proportion) in
   `cut_consumptions` (cutflow DB; `stick_sessions.consumed_at` makes it once-only);
3. the job's configurator reservation item for that extrusion = sum of its cut lists' shares (to 1/10); reservation goes `active -> in_progress`. Using more than reserved raises committed to match.
- `JobReservationItem::binAwareCommitted()` now counts only the **open** quantity (committed - consumed). Before, partial consumption would have held stock against an already-reduced on-hand.
  Nothing consumed partially before this, so no existing behaviour changes. Completing a reservation later only deducts the delta (no double deduction).
- A stick with nothing cut consumes nothing; re-closing a stick is a no-op; a profile matching no product logs a warning and deducts nothing.
- Only extrusions are consumed at the cut station; hardware/components/gaskets stay committed until the reservation is completed through the normal screen.
- Tested (rolled back, both DBs): 3-piece stick -> on hand -1, consumed 1.0, in_progress; re-close no-op; 1-piece stick -> consumed 2.0 (open 0); extra stick -> committed raised to 3, on hand -1;
  cancelled-empty stick -> nothing; available stays consistent throughout.
- Not done: real tablet run-through (hook is in the Livewire component, tested through the service); a notice on /fabrication/cut-lists for hand-edited lists.

## Update 12 - consumption is per cut, as a portion of a STOCK length (supersedes Update 11's per-stick model)
Decision: remnants are treated as drops, so a cut consumes `cut inches / the product's stock length` (252" door, 288" frame, E6169 120") - never a share of the
stick on the saw. 25" off a 252" stock stick = 25" off a 75" drop.
- `CutConsumptionService::recordCut()` is called from the cut station after every real cut (planned, not a recut marker, not a manual keypad cut). One ledger row per cut
  (`cut_consumptions.cut_log_entry_id` unique, `stock_fraction` to 4 places). Per-stick ledger/`consumed_at` removed (migration `2026_10_03_000002`).
- Job reservation item consumed = ledger total for the job's cut lists, to 1/10; on-hand falls to the product's ledger total to 1/10 less what earlier cuts already deducted
  (InventoryDeductor, ref `CUT-STATION`) - always from the ledger, so rounding never drifts. Reservation -> in_progress; over-use raises committed to match.
- On-hand for extrusions is therefore in fractional stock lengths (drops included implicitly); kerf and unusable offcuts are not counted and surface in cycle counts.
- Re-cut: marker consumes nothing, the re-cut itself is a second cut (material used twice). Manual keypad cuts consume nothing.
- Test (rolled back, both DBs): 28 13/16" off a 252" stick and off a 75" drop -> identical fraction 0.1143 each; shelf 0.1 then 0.2, job consumed 0.1 then 0.2; repeat/manual/recut-marker no-ops;
  12 cuts mixed stock/drop -> ledger 1.3716, shelf 1.4, job consumed 1.4 (committed raised from 1.0 as the test cut more than the list needed).

## Update 13 - hardware catalog reseed fixed (upsert importer)
Problem: `configurator:import-fab-utils-hwlib` deleted and recreated the whole catalog. It failed outright once hardware sets referenced items (FK), and even where it
could run it would have wiped ForgeDesk-only data hanging off items (subcategories, functions) and detached hardware links.
- Now an **upsert by natural key** (variable code / category name / item name / backer+fastener pn): stable IDs, nothing referencing the catalog is disturbed, only fab_utils-supplied
  fields are written (ForgeDesk-only data preserved; `handed` only ever switched on). Modes: sync (default), `--create-only`, `--prune`, `--dry-run`. Per-table created/updated/unchanged/removed report.
- `imported_at` stamped on categories/items/backers/fasteners (migration) so `--prune` only removes rows an earlier import brought in and fab_utils dropped, never one made in ForgeDesk;
  anything still referenced is reported and kept.
- Verified on dev: dry run on the current catalog = everything unchanged (76 variables, 60 items, 119 values, 7 backers...). Rolled-back test with a hardware set, a link, a subcategory, a function,
  ForgeDesk-created items and deliberate drift: sync repaired the drift, IDs/links/sets unchanged, ForgeDesk-only data kept; `--create-only` repaired nothing existing; `--prune` removed only the
  imported-and-dropped unused item, kept the in-use one and the ForgeDesk-created one.
- Frame and door catalog importers are still **full replace** (safe only on an empty database). Runbook: `docs/plans/production-catalog-seeding.md`.

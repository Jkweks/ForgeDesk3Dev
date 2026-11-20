# Generating configurator hardware from the PM app's hardware schedule import

Status: **analysis and proposal, nothing built.** Written 2026-11-15 after reading the ForgeDesk configurator (models, services,
dev data), the PM Suite's `hardware_import.py`, and running a throwaway matcher on real schedule lines.

## 1. The idea in one paragraph

The PM Suite reads a door-hardware **schedule** (SpecWorks / AHC PDFs, text or OCR) into *hardware sets* (set number, per-assembly item
list) and *door assignments* (door mark, location, hand). The configurator's hardware library (hwlib) already has the same two things in
its own shape: per-job **hardware sets** (`configurator_hwlib_sets` + `_set_items`) that are **applied to openings**
(`door_frame_configuration_hwlib_sets`), where each opening is identified by its **door tag**. Today those sets are typed by hand (or came
from fab_utils). They can be generated from the schedule: schedule lines are matched to catalog items, the set and its lines are created
on the job, and door marks are matched to openings so the set can be applied. The hard parts are not the parsing (it exists and is
tested) but (a) matching free-text lines to catalog items, (b) per-assembly vs per-leaf quantities, and (c) the join between a door mark
and a configurator opening.

## 2. What the configurator has (verified in the dev database and code)

| Piece | Shape | Today |
|---|---|---|
| Catalog | `configurator_hwlib_items` (category, name, manufacturer, model_number, pn, `vos_standard`, `field_install`, `handed`, `by_others`, `needs_review`, `imported_at`), `_categories` (22), `_variables` (77 prep variables: AFF heights, backsets, ...), `_item_values` (per-item prep values), backers + fasteners | 85 items (60 imported from fab_utils), 36 with **no** prep values |
| Job sets | `configurator_hwlib_sets` (**per job**: `business_job_id` required, `name`, `is_pair`) with `_set_items` (item, `quantity`, `series` Standard/Thermal/Monumental, `leaf` both/active/inactive, per-item value overrides, functions) | 36 sets, 365 lines, jobs 1260201, 4250403, 4250404 |
| Application | `POST /config/hwlib-sets/{id}/apply` (`HwlibSetSync::materialize`): copies the set's lines onto an opening as `configurator_hwlib_links` with prep-value defaults; `cascade()` re-syncs every opening when the set changes (skips released ones) | works |
| Output | `HwlibResolver` (priority chain for each prep value), `HwlibBomGenerator` (links -> BOM rows: item, backers, fasteners; stock Product by `pn`+hand+finish, else a special-order row, `by_others` rows never reserve stock), prep reports | works |
| Opening identity | `door_frame_configuration_doors.door_tag` (e.g. `1060-1`, `103-LH`, `103-RH`). Openings are created by `ElevationConfigurationMatcher` from **Door / Frame work-order elevations**, grouped by tag with `-LH`/`-RH` stripped. Handing in `door_frame_opening_specs` (`hand_single` lh_inswing/rh_inswing/lhr/rhr, `hand_pair` rhr_active/lhra_active, `opening_type` single/pair) | works |
| Existing import precedent | `configurator:import-fab-utils-hwsets` imports a job's sets with items referenced **by catalog name** and aborts if any is missing | the pattern to improve on |

Key semantic to respect: **a set line's quantity is per leaf when `leaf = both`** (doubled on a pair by the BOM generator). `active` /
`inactive` name one leaf already.

## 3. What the PM app produces

`hardware_import.py` (1,100 lines, in `jv_vos`; **not yet vendored into ForgeDesk's pm-worker**): `parse_hardware_schedule(pdf)` returns

```
{ ok, dialect: specworks | ahc, warnings, ocr: {pages, passes, flagged},
  sets: [ { num: "01.1", name, heading, opening: "3'0\" x 7'2\" x 1-3/4\" x A/G x ALF x NON-RTD",
            qtyBasis: assembly,
            doors: [ { mark: "1103B", desc: "EXTERIOR FROM INTERIOR", hand: "RHR", count, heading } ],
            items: [ { qty: "1" (per assembly), total: "9" (all doors), desc: "<free text>", src: {page, box} } ],
            notes, src } ] }
```

It handles two vendor dialects, outlined-text pages by OCR with a second-pass cross-check (`_ocr_checks`), continued headings, shared sets and
scope notes. Item `desc` is **free text**: `CONT HINGE (AL) CFM( 085" )SLF-HD1-PT 628 PEM` = description, model, finish, manufacturer code.
In the PM app these feed a door schedule (door mark -> elevation -> set) and a stamped per-door hardware block.

Two other things in ForgeDesk's own data already point at the same sets:

- **The shop-drawing import reads the HARDWARE SET block on each elevation sheet** (`_hardware_set_block`): 67/E35 -> doors `1100A` set `1.0`,
  `1100B` set `2.0`. ForgeDesk **throws this away** today (`ImportDrawingSet` does not store `hardware`).
- The PM Suite's `doorSchedule` already joins *door mark <-> elevation label <-> set*.

## 4. The chain, and where each link stands

```
hardware schedule PDF --(hardware_import, OCR)--> sets + items + door marks
   drawing elevation sheet --(HARDWARE SET block)--> elevation -> door marks -> set numbers        [parsed, not stored]
sets + items --(matcher)--> hwlib catalog items (+ needs_review stubs)                              [to build]
door marks --> Door/Frame elevations (tag = mark) --> configurator openings (door_tag)              [matcher exists; elevations must exist]
job hwlib set (name = set number) --> apply to the openings whose door mark is on that set          [apply exists]
--> prep values (resolver) + BOM (generator) + cut sheet                                           [exist]
```

## 5. Evidence: how well do schedule lines match the catalog?

A throwaway matcher (manufacturer code map + category keywords + normalised model contained in the line, same manufacturer) on the
**18 distinct item lines of the Northwest Elementary schedule** (OCR'd outlined pages) against the 85-item catalog:

- **14 of 18 matched cleanly, every one with a matching manufacturer code** (Pemko CFM85SLF-HD1 from `CFM( 085" )SLF-HD1-PT`, Corbin Russwin
  PED5201/PED5257, Norton J7500, Rixson 6-336, Pemko 345CNB, Pemko 1715AK, Schlage 20-079 / 23-030, Securitron CEPT, Rockwood VRT22).
- The 4 misses are the useful part: two **wire harnesses** (electrical, McKinney; not prep items, should be listed, not matched), one
  **model variant** (`VRT22C (PREP FOR KEY CYLINDER)` vs catalog `VRT22`), one **spelling** (`DPS-M-GY` vs catalog `DPS-M-GRY`).
- Lessons: a match must require manufacturer agreement; the description text can mislead a category keyword (the "KEY CYLINDER" note
  pushed VRT22C into Cylinder), so category should come from the **start** of the line and the catalog model should win; leading zeros
  inside a model (`085"`) must be normalised.

- **Alpine Apartments (scanned, OCR'd) is the counter-example: 2 of 23 distinct lines matched, and only 3 of 40 lines even when restricted to
  the five sets the Vos elevations reference.** The catalog is built around Tubelite / Pemko / Von Duprin / Corbin Russwin / Rixson
  entrance hardware; Alpine specifies Yale closers, Von Duprin 9847 cover panics, Rockwood kick plates and wall stops, McKinney `T4A3786`
  hinges, Schlage ND-series locksets. **Match rate depends on the job's hardware supplier, not on the parser.** Section 11 has the detail.

## 6. Proposed design

### 6.1 Parse (pm-worker)
Vendor `hardware_import.py` into `pm-worker` (marked ForgeDesk additions, tests vendored like `drawing_import`), `POST /hardware/parse`
as a queued job with page progress (OCR takes minutes). The worker image needs **tesseract + pytesseract** (it has neither today).

### 6.2 Store the schedule relationally (never `.json` files)
`pm_hardware_schedules` (job, `pm_file`, dialect, status, ocr info, warnings) -> `pm_hardware_sets` (num, heading, opening, notes, qty basis)
-> `pm_hardware_set_items` (qty per assembly, heading total, **raw text**, parsed manufacturer code / finish / category / model,
`hwlib_item_id` nullable, `match_status`, confidence, `page` + `box` so a reviewer sees the line on the schedule like Check Sizes does) and
`pm_hardware_set_doors` (mark, location, hand, count, heading). A re-read keeps what people decided, as drawing sets do.

### 6.3 Match lines to catalog items
Deterministic, explainable, no guessing: manufacturer code (map in a table, editable) -> category from the line's leading words -> model
containment on a normalised model -> **same manufacturer required**. Outcomes: `matched` (auto), `ambiguous` (several), `variant` (model
extends a catalog model: propose it), `unmatched`. Unmatched lines never block: a person picks a catalog item, or accepts **a new catalog
stub** created with `needs_review = true` (the column and `imported_at` already exist for exactly this) and no prep values, so the BOM can
list it as special-order and the configurator shows what still needs prep data. Non-prep items (wire harness, power supply kits,
"by others" scope notes) are classed as **BOM-only** and never enter prep calculation. Matches a person confirms are remembered
(`schedule text -> item`) so the next job's schedule matches faster.

### 6.4 Quantity and leaf
The schedule's qty is **per assembly** (SpecWorks) or per heading (AHC, imported as printed with a review warning). The configurator's is per
leaf for `both`. Rules: single door -> `both`, qty as printed; pair -> symmetric items (hinges, closers, pulls) -> `both` with qty / 2 when
the printed qty is even; items that exist once (lock, flushbolt, astragal, coordinator) -> `active` or `inactive` by item type
(flushbolts and strikes' partner on the inactive leaf); anything not decided by those rules is `review`. Pair vs single comes from the
schedule's `SGL` / `PR` and the hand (`LHR`, `RHRA/LHRI`). Always shown to the person with the arithmetic, never silently applied.

### 6.5 Sets, doors and openings
- A schedule set becomes a job hwlib set named after the set number (`01.1`, matching the drawings and the PM Suite), `is_pair` from its
  doors, lines from the matches. **Idempotent by (job, set name)**, like `import-fab-utils-hwsets`, and `HwlibSetSync::cascade` already
  re-applies a changed set to its openings (skipping released ones).
- **Door marks are the join key.** For each schedule door mark, find the opening whose `door_tag` is the mark (`1100A`, or `-LH` / `-RH`
  forms). If there is none, offer to **seed a Door elevation (tag = mark)** on the work order (the seeding path from work-order
  requests now exists), which makes `ElevationConfigurationMatcher` create the opening; the hand fills `door_frame_opening_specs`.
- **Store the drawing's HARDWARE SET block** (`drawing_elevations` -> door marks + set numbers). It is the independent cross-check
  (does the door the drawing shows have the set the schedule gives?) and tells a work-order request which door marks an elevation
  contains. Disagreement is a flag, never an overwrite.

### 6.6 Review, in the existing style
A **Hardware** tab on the PM job page (master list of sets, detail with the lines and their matches, the schedule page image with each line
boxed, confirm / change / stub, door assignments with their opening status) and a button "Create / update configurator sets". Nothing is
written to the configurator until a person confirms, same posture as drawing import ("suggested, never silently written").

## 7. What it does not do (and why)

- It cannot invent **prep values** (AFF heights, backsets) for an item the catalog has not got: 36 of 85 items have none today. A stub keeps
  the BOM honest; Fabrication fills prep data once per item, then every job benefits. The number of stubs per schedule is the real
  cost of this feature, and the matcher's remembered matches reduce it over time.
- It does not set **finish** (the schedule's finish codes 626/628/630/689/719 are not the configurator's C2/DB/BL finishes for the frame);
  it keeps the printed code on the line for the BOM and leaves the opening's finish alone.
- Pair quantity splitting is rule-based and will sometimes need a person (6.4).

## 8. Build order (each step useful on its own)

1. **Store the drawing's HARDWARE SET block** (tiny; the data is already parsed). Immediately useful to work-order requests.
2. Vendor the importer into pm-worker, add OCR to the image, parse endpoint + job, relational storage, review page (read-only view first).
3. The matcher and a reviewable match table (no configurator writes yet); measure on the four real schedules (Northwest, Alpine, Beacon
   Hill / Eastgate, Kentwood) and tune. **Measured so far: 78 % (Northwest) and 8 % (Alpine).** Expect the first schedule from a new
   supplier to be mostly stubs and judge success by how fast the catalog and the remembered matches grow, not by a fixed rate.
4. "Create / update configurator sets": sets + lines + `needs_review` stubs, idempotent.
5. Door marks -> openings: link existing, seed Door elevations for the rest, apply sets, cross-check with the drawing.
6. Learned matches and a stub-review queue for Fabrication.

## 9. Risks and open questions

- **Which schedules will exist?** Two dialects are supported (SpecWorks, AHC). A new vendor format means a new dialect; the importer's own
  philosophy ("grows one verified format at a time") applies.
- **Door marks vs opening tags.** Vos opening tags in Fabrication (`13B-LH`) may not equal the schedule's marks (`1100A`) on every job.
  Needs a look at a job where both exist (Beacon Hill has a schedule and an accepted work order) before step 5.
- **Revisions.** A schedule re-issued with changes must update sets without clobbering prep overrides people made on set lines
  (`configurator_hwlib_set_item_values`): merge by (set, item), report removed lines, never drop overrides silently.
- **Stock mapping.** Matched items only become stock-reserving BOM rows if the item has a `pn` that resolves to a Product; most
  third-party hardware is special-order, which the BOM already handles.
- **Submittal link (later).** Matched hardware could also link to the PM product library (`pm_products`, which now holds the seeded
  Tubelite / Guardian / Kawneer products) for hardware cut sheets in the submittal package.

## 10. Decisions needed
0. **What does Vos prep, by category?** The prep-relevant / loose / by-others flag per hardware category (section 11, point 2) needs your call
   before the matcher can decide stub vs listed-only: e.g. are closers, kick plates, wall stops, passage sets prepped by Vos?

1. Should a matched-but-unconfirmed line be applied automatically above some confidence, or must every line be confirmed once?
   (Recommendation: confirm once per schedule line, remember it, auto-confirm repeats.)
2. Where do Door elevations for door marks come from when the work order only has storefront elevations: seeded from the schedule /
   drawing block (recommended) or entered by Fabrication?
3. Pair quantity rules: confirm the item types that live on one leaf only.

## 11. Second corpus: Alpine Apartments (scanned, OCR'd by the PM Suite importer)

Run in a throwaway container from the PM Suite's own image: SpecWorks dialect, pages 2-41 read by OCR twice, **7 sets, 65 item lines
(23 distinct), 13 door rows**, 4 lines flagged by the importer for checking. The shop drawings (13 elevations with a HARDWARE SET block)
reference sets **B1, B3, B5, B6, B8**; the schedule also has B1.0 and B1.1, so the drawing's `B1` is ambiguous against the schedule's
`B1`, `B1.0`, `B1.1` (set-number normalisation needs a rule and a flag).

| | Northwest Elementary | Alpine Apartments |
|---|---|---|
| Distinct item lines | 18 | 23 |
| Matched cleanly | 14 (78 %) | 2 (9 %) |
| Matched within the sets the drawings use | 14 (all of it) | 3 of 40 lines |
| What did not match | wire harnesses, 1 variant, 1 spelling | most of it: no Yale / VDC 9847 / kick plate / wall stop / ND-series in the catalog |

What this changes:

1. **Stubs are the normal outcome for a new supplier**, so the stub path (`needs_review`, special-order BOM row, remembered match) is the
   core of the feature, not an edge case. The auto-match is an accelerator.
2. **A schedule lists every door on the project; Vos preps a subset.** The drawing's HARDWARE SET block (and the Door elevations on the
   work order) are the scope filter: only sets used by Vos doors are imported by default, the rest are shown as "not Vos scope". On
   Alpine that is 5 of 7 sets. Within a set, some items are never prepped by Vos at all (kick plates, wall stops, passage sets on
   interior doors): a per-category **prep-relevant / loose / by others** flag decides whether an unmatched line needs a stub or is just
   listed.
3. **Near-model suggestions are worth having.** Edit-distance-1 candidates would have offered `T4A3786` -> catalog `TA3786` (McKinney
   hinge), `VRT22C` -> `VRT22`, `DPS-M-GY` -> `DPS-M-GRY`, and `3452CNB` -> `345CNB` (likely an OCR slip). Offer them for confirmation,
   never apply them.
4. **OCR noise reaches the item text** ("- PLEASE CONFIRM LEVER STYLE", "( zy 6 EA CLASSROOM LOCK", "CVRPANIC", "CLOSER po 5801", "DOME STOP
   442 626 ROC wig tes"). The matcher must tolerate trailing prose and fused words, and the review screen must show the line on the
   schedule page image (the importer keeps `page` + `box` for each line) so a person can read the original.
5. **Quantities are per assembly, so a pair's set must be read with its door row's hand** (`RHRA/LHRI`, `LHR`, `RH`): B1 is a pair
   assembly (two closers' worth of hardware for two leaves), B5 is a single door with four doors sharing the set.

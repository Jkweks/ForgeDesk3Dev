# Configurator Integration Plan
## fab_utils → ForgeDesk

**Created:** 2026-09-01  
**Status:** Awaiting review — do not begin development until authorized  
**Branch target:** `claude/sso-integration-effort-o5tdmq` (or a new branch as directed)

---

## Source Repositories

- **ForgeDesk:** `/home/user/ForgeDesk3Dev` (repo: `jkweks/forgedesk3dev`)
- **fab_utils:** `/home/user/fab_utils` (repo: `jkweks/fab_utils`) — must be re-cloned in a new session via `mcp__Claude_Code_Remote__add_repo` then `git clone --depth 1 https://github.com/Jkweks/fab_utils /home/user/fab_utils`

---

## Background

`fab_utils/configurator` is a mature, standalone PHP/PostgreSQL door+frame configurator with:
- Full extrusion cut-list calculation (client-side JS, complex formula engine)
- Hardware library (hwlib) with flexible per-item variable system
- Status workflow: draft → quote → released
- Export: Extrusion CSV (CNC-ready) and Forge CSV (purchasing)
- Dark-mode only, custom CSS (amber `#f0a500` accent, near-black `#0f1117` bg)
- Fonts: Barlow Condensed (headers), Barlow (body), DM Mono (data)
- No authentication — completely open API
- Docker: web on port 8032, postgres:16-alpine on port 5439, DB name `configurator`

ForgeDesk currently has a **stub configurator** — 7 Eloquent models with placeholder length calculations, no Blade view, and a release endpoint that does nothing downstream. These stubs should be removed entirely and replaced with the real configurator from fab_utils.

---

## Key Files to Know

### fab_utils configurator
| File | Purpose |
|---|---|
| `configurator/door-calculator.html` | Door material configurator (103 KB) — all calculation JS inline |
| `configurator/frame-calculator.html` | Frame material configurator (84 KB) — uses formula evaluator |
| `configurator/hwlib-hardware-calculator.html` | Hardware library configurator v2.0 (148 KB) |
| `configurator/api.php` | Single-file PHP API router (~1400 lines) |
| `configurator/hwlib_api.php` | Hardware library API router |
| `configurator/db/init.sql` | Base schema + seed data |
| `configurator/db/migrate_*.sql` | Incremental migrations (all idempotent) |
| `configurator/js/pdf-report.js` | Shared PDF export logic |
| `configurator/fd3notes.md` | Bug/glitch log (describes ForgeDesk 3 issues, not fab_utils issues) |

### ForgeDesk stub configurator (to be removed)
| File | Action |
|---|---|
| `laravel/app/Models/DoorFrameConfiguration.php` | Delete |
| `laravel/app/Models/DoorFrameConfigurationDoor.php` | Delete |
| `laravel/app/Models/DoorFrameOpeningSpec.php` | Delete |
| `laravel/app/Models/DoorFrameFrameConfig.php` | Delete |
| `laravel/app/Models/DoorFrameFramePart.php` | Delete |
| `laravel/app/Models/DoorFrameDoorConfig.php` | Delete |
| `laravel/app/Models/DoorFrameDoorPart.php` | Delete |
| `laravel/app/Http/Controllers/Api/DoorFrameConfigurationController.php` | Delete |
| `laravel/routes/api.php` lines 433–440 | Remove configurator routes |

Tables to drop (via new migration): `door_frame_configurations`, `door_frame_configuration_doors`, `door_frame_opening_specs`, `door_frame_frame_configs`, `door_frame_frame_parts`, `door_frame_door_configs`, `door_frame_door_parts`

**Keep:** `configurator.*` permissions (repurposed for new system).

---

## fab_utils Data Model (Critical Reference)

### Core table: `saved_configs`
Stores BOTH door and frame records (discriminated by JSONB key presence).
- Door: `inputs->>'hingeType' IS NOT NULL`
- Frame: `inputs->>'sysName' IS NOT NULL`

```
saved_configs
  id              SERIAL PK
  work_order_id   INTEGER FK → work_orders(id)
  label           VARCHAR(200)       -- opening mark e.g. "1100A"
  inputs          JSONB              -- all dimensional/spec inputs
  outputs         JSONB              -- cut list: extrusions[] + components[]
  frame_config_id INTEGER FK → saved_configs(id)  -- door links to its frame
  applied_set_id  INTEGER FK → hwlib_sets(id)
  status          VARCHAR(20)        -- draft | quote | released
  needs_recalc    BOOLEAN
  created_at      TIMESTAMPTZ
```

### Door `inputs` JSONB shape
```json
{
  "qty": 1, "series": "STANDARD", "stile": "NARROW STILE",
  "W": 36.0, "H": 84.0, "handing": "LH",
  "topRailLbl": "3 1/2\"", "botRailLbl": "4\"",
  "hingeType": "BUTT HINGES", "openingAngle": 90,
  "glassThk": "1/4\"", "botGap": 0.6875,
  "finish": "BL", "midRailLbl": null, "midQty": 0
}
```

### Frame `inputs` JSONB shape
```json
{
  "qty": 1, "sysName": "4500 Series", "seriesName": "Standard Single",
  "W": 38.0, "H": 86.0, "TH": null,
  "handing": "LH", "finish": "DB", "glass": 0.25,
  "hasTransom": false, "hasThreshold": false
}
```

### `outputs` JSONB shape (same for door and frame)
```json
{
  "extrusions": [
    { "label": "HINGE STILE", "pn": "E7055-BL", "qty": 1, "len": 83.8125, "stockLen": 252 }
  ],
  "components": [
    { "label": "TIE ROD", "pn": "P022H", "qty": 1, "note": "Rod len: 37 3/8\"" }
  ]
}
```

### Project hierarchy (fab_utils)
```
jobs(id, name, archived)
  └── work_orders(id, job_id, name, archived)
        └── saved_configs(id, work_order_id, label, inputs, outputs, ...)
              └── hwlib_saved_config_links(id, saved_config_id, item_id, qty, ...)
        └── hwlib_sets(id, job_id, name, ...)
```

### Extrusion CSV export format (must remain CNC-compatible)
Columns: `part number, finish, length, quantity, job, work order, entry tag, 0, 0, part description, 0, 0`

---

## ForgeDesk Work Order Flow (Critical Reference)

### Hierarchy
```
BusinessJob (business_jobs)
  └── FdWorkOrder (fd_work_orders) — business_job_id FK
        └── FdWoElevation (fd_wo_elevations) — work_order_id FK
              └── FdWoStage (fd_wo_stages) — elevation_id FK
```

### Key fields on FdWoElevation
- `elevation_tag` — maps to `saved_configs.label` (opening mark)
- `scope` varchar — currently `'assemble'`, can be `'door'` or `'frame'` after integration
- `elevation_type_id` FK → `fd_elevation_types` — "Door" and "Frame" types already seeded
- `configurator_opening_id` — **new FK to add** (nullable → `configurator_openings`)

### Stage templates already seeded
For "Door" and "Frame" elevation types: Programmed (1), CNC (2), Assembled (3)

### Critical gap (confirmed)
There is zero FK relationship between the existing stub configurator and work orders. Both link to `business_jobs` via `business_job_id` but are siblings, not linked. The integration plan creates this bridge.

---

## Hierarchy Mapping (fab_utils → ForgeDesk)

```
fab_utils                         ForgeDesk (after integration)
─────────────────                 ──────────────────────────────
jobs                          →   BusinessJob  (already exists)
  └── work_orders             →   FdWorkOrder  (already exists)
        └── saved_configs     →   ConfiguratorOpening  (new table)
              └── hwlib links →   HwlibSavedConfigLink (new table)
                                        ↓  [on release]
                                  FdWoElevation (new FK: configurator_opening_id)
                                    └── FdWoStage (auto-created via templates)
```

---

## New Schema Design

### `configurator_openings` (replaces `saved_configs`)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `business_job_id` | FK → business_jobs | restrict |
| `work_order_id` | FK → fd_work_orders nullable | restrict; optional until release |
| `label` | varchar(200) nullable | opening mark e.g. "1100A" |
| `scope` | enum | `door_and_frame`, `frame_only`, `door_only` |
| `quantity` | unsignedInteger | default 1 |
| `status` | enum | `draft`, `released` |
| `door_inputs` | jsonb nullable | |
| `door_outputs` | jsonb nullable | extrusions + components |
| `frame_inputs` | jsonb nullable | |
| `frame_outputs` | jsonb nullable | extrusions + components |
| `needs_recalc` | boolean | default false |
| `created_by_id` | FK → users nullable | |
| `deleted_at` | timestamp | SoftDeletes |
| `timestamps` | | |

### Frame catalog tables (new, prefixed `cfg_`)
Migrated from `migrate_frames.sql`: `cfg_frame_systems`, `cfg_frame_series`, `cfg_frame_profiles`, `cfg_frame_components`, `cfg_frame_fasteners`, `cfg_frame_roles`

### Door catalog tables (new, prefixed `cfg_`)
Migrated from `init.sql`: `cfg_door_types`, `cfg_rails`, `cfg_glass_specs`, `cfg_tie_rods`, `cfg_setting_block_kits`

### Hardware library tables (Phase 4, prefixed `hwlib_`)
From `migrate_hwlib*.sql`: `hwlib_variables`, `hwlib_categories`, `hwlib_category_variables`, `hwlib_items`, `hwlib_item_values`, `hwlib_saved_config_links`, `hwlib_link_values`, `hwlib_sets`, `hwlib_set_items`, `hwlib_set_item_values`

---

## New API Endpoints

New controller: `app/Http/Controllers/Api/ConfiguratorController.php`

| Method | Route | Permission |
|---|---|---|
| GET | `/configurator/openings` | `configurator.view` |
| POST | `/configurator/openings` | `configurator.create` |
| GET | `/configurator/openings/{id}` | `configurator.view` |
| PUT | `/configurator/openings/{id}` | `configurator.edit` |
| DELETE | `/configurator/openings/{id}` | `configurator.edit` |
| POST | `/configurator/openings/{id}/release` | `configurator.release` |
| GET | `/configurator/frame-catalog` | `configurator.view` |
| GET | `/configurator/door-catalog` | `configurator.view` |
| GET | `/configurator/hwlib/items` | `configurator.view` |
| POST | `/configurator/openings/{id}/hardware-links` | `configurator.edit` |
| GET | `/configurator/openings/{id}/export/csv` | `configurator.view` |
| GET | `/configurator/openings/{id}/export/pdf` | `configurator.view` |

**Release endpoint behavior:**
1. Validate inputs/outputs populated for scope
2. Set `status = 'released'`
3. Auto-create `FdWoElevation` row(s) on linked `FdWorkOrder` (door scope → door elevation; frame scope → frame elevation)
4. Apply stage templates to each elevation (Programmed, CNC, Assembled)
5. Set `fd_wo_elevations.configurator_opening_id` = this opening's id

---

## UI Porting Strategy

**Two new Blade views** extending `layouts/app.blade.php`:
- `resources/views/configurator/door.blade.php` — ported from `door-calculator.html`
- `resources/views/configurator/frame.blade.php` — ported from `frame-calculator.html`
- `resources/views/configurator/hardware.blade.php` — ported from `hwlib-hardware-calculator.html` (Phase 4)

**Critical:** The calculation JavaScript in the HTML files is correct, complex, and battle-tested. **Keep it as-is.** Only adapt DOM references, form input names, and API call URLs. Do not rewrite the math.

**Styling adaptation:**
| fab_utils token | Tabler equivalent |
|---|---|
| `--bg: #0f1117` | `data-bs-theme="dark"` on body |
| `--accent: #f0a500` (amber) | Tabler `--tblr-primary` override or `warning` |
| `--accent3: #10b981` (green) | `text-success` |
| Custom panels | `.card` + `.card-body` |
| Custom table | `.table` |

---

## Open Questions (Must Resolve Before Starting)

1. **Product catalog alignment** — fab_utils has extrusion part numbers (e.g. `E7055-BL`) in its own `parts`/`catalog_extrusions` tables. ForgeDesk has a separate `products` table (inventory). Are these the same catalog? If ForgeDesk products already include aluminum extrusions, link `cfg_frame_profiles.product_id → products.id`. If not, maintain cfg catalog tables separately and only bridge to `products` for inventory/reservation purposes. **Needs clarification before Phase 1.**

2. **Pair door model** — fab_utils stores a pair as one `saved_config` with `handing: "PAIR LH"`. Recommendation: keep single-row approach. A pair produces two elevations on release (active + inactive). **Confirm.**

3. **Work order requirement at creation** — Allow draft configs under just a `BusinessJob` (no work order yet), link to work order before release? Recommendation: yes, make `work_order_id` optional until release. **Confirm.**

4. **`hardware_frame_preps` tables** — Schema exists in fab_utils, no UI implemented. Recommendation: skip in this integration. **Confirm.**

5. **Older `hardware_configs` flow** — predecessor to hwlib, separate from it. Recommendation: skip, go straight to hwlib. **Confirm.**

6. **Extrusion CSV column order** — confirm with CNC team: `part number, finish, length, quantity, job, work order, entry tag, 0, 0, part description, 0, 0`

---

## Phases & Timeline

### Phase 1 — Foundation (Days 1–3)
- Remove ForgeDesk stub models, controller, routes
- New migration to drop stub tables
- New Laravel migrations: `configurator_openings`, `cfg_frame_*`, `cfg_door_*`
- Seed frame/door catalog from fab_utils SQL files
- Add `configurator_opening_id` FK to `fd_wo_elevations`
- New `ConfiguratorOpening` Eloquent model + relationships
- New `ConfiguratorController` with CRUD + catalog endpoints
- New API routes in `routes/api.php`

### Phase 2 — Door Calculator UI (Days 3–5)
- Port `door-calculator.html` → `resources/views/configurator/door.blade.php`
- Tabler form + card layout
- Calculation JS adapted to ForgeDesk API endpoints
- Job/WO picker using existing `BusinessJob` and `FdWorkOrder` APIs
- Draft save + load end-to-end

### Phase 3 — Frame Calculator UI (Days 5–7)
- Port `frame-calculator.html` → `resources/views/configurator/frame.blade.php`
- Frame catalog endpoint + formula evaluator JS adapted
- Save + load end-to-end

### Phase 4 — Release → Work Order Bridge (Days 7–8)
- Release endpoint: validate → released → auto-create elevations → apply stage templates
- Show configurator link on work order elevation detail view
- Extrusion CSV export endpoint (CNC-compatible format)
- Cut-list PDF using dompdf

### Phase 5 — Hardware Library (Days 8–11)
- Migrate hwlib schema: `hwlib_*` migrations + Eloquent models
- Seed hwlib catalog (Variables.md data, categories, seeded items)
- Port `hwlib-hardware-calculator.html` → `resources/views/configurator/hardware.blade.php`
- Hardware set management
- Admin pages for hwlib catalog

### Phase 6 — BOM → Job Reservation Bridge (Days 11–12)
- On release, auto-create `JobReservation` items from `door_outputs.components` + `frame_outputs.components`
- Map fab_utils part numbers → ForgeDesk `products` (depends on Question 1 resolution)

**Total: ~12 development days (2.5 weeks) for Phases 1–5. Phase 6 adds 1–2 days after catalog question is resolved.**

---

## Verification Checkpoints

| Phase | Check |
|---|---|
| 1 | `composer test` passes; old configurator routes return 404; new catalog endpoints return data |
| 2 | Create/save/load door config; BOM matches fab_utils output for same inputs |
| 3 | Same for frame; formula evaluations match |
| 4 | Release creates elevations on work order; stage templates applied; CSV export matches format |
| 5 | hwlib items attach to openings; variable values save/reload correctly |
| 6 | Released config creates reservation items visible on job |

---

## Notes for Future Session

When resuming this plan in a new session:
1. Re-clone fab_utils: add repo via MCP then `git clone --depth 1 https://github.com/Jkweks/fab_utils /home/user/fab_utils`
2. Reference this file for all context — do not re-explore from scratch
3. Resolve the 6 open questions above before writing any code
4. Start with Phase 1 cleanup (removing stubs) before adding new schema
5. The calculation JS in fab_utils HTML files is the ground truth for math — read it carefully before adapting
6. The `configurator.*` permissions already exist in the DB — no new permission seeds needed

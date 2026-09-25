# Hardware set input: Category / Manufacturer / Part table + quick-add

## Context

Hardware is currently picked in two places in the configurator module:

1. **Hardware Library admin → Sets → Item rows** (`resources/views/configurator/admin.blade.php`, `hwSetItemRowHtml`/`hwSetAddItemRow`) — building a named, job-scoped hardware set (`ConfiguratorHwlibSet` → `ConfiguratorHwlibSetItem`). Each row is currently a single flat "pick an item by name" `<select>` plus qty/series/leaf/notes — no category/manufacturer narrowing, and no function selection.
2. **Configurator → an opening's hardware tab** (`resources/views/configurator/frame.blade.php`, the `fb-hardware-add-form` block, ~line 650-825) — attaching hardware directly to one opening (`ConfiguratorHwlibLink`). This one *already* has the cascade the user wants (Category → Item, where the item carries manufacturer/PN and — once picked — a Function picker appears for whatever functions that item supports, e.g. Von Duprin 98 → NL/QEL/CD). It's just missing an explicit Manufacturer step and any quick-add path.

The user wants both surfaces reshaped into a Function(=Category) → Manufacturer → Part row, and a quick-add path for when the desired manufacturer/part doesn't exist yet in the hardware library — tagging what gets quick-added as **needs review** so it can be tracked and cleaned up later (their examples: backers and shims, which tend to get entered ad hoc).

Confirmed with the user:
- "Function" in column A = the existing **Category** concept (Hinges, Closers, Panic Devices, etc.), not the existing "Function" code entity (EO/NL/DT/QEL/CD). Those codes still apply, but as a second-stage picker once a specific part is chosen — mirroring what `frame.blade.php` already does.
- Both surfaces (admin Set editor, and the configurator's per-opening hardware add) get this treatment.

## Data model changes

**Migration** `add_needs_review_to_configurator_hwlib_items` (new file, follows the style of `2026_09_22_213000_create_configurator_hwlib_functions_tables.php`):
- `configurator_hwlib_items`: add `boolean('needs_review')->default(false)->after('active')`.
- New pivot table `configurator_hwlib_set_item_functions` (`set_item_id` → `configurator_hwlib_set_items` cascade, `function_id` → `configurator_hwlib_functions` restrict, unique pair) — mirrors `configurator_hwlib_link_functions` exactly, since `ConfiguratorHwlibSetItem` needs the same functions-per-row concept `ConfiguratorHwlibLink` already has.

**Models:**
- `app/Models/ConfiguratorHwlibItem.php`: add `needs_review` to `$fillable` and `$casts` (boolean).
- `app/Models/ConfiguratorHwlibSetItem.php`: add a `functions()` `belongsToMany` through `configurator_hwlib_set_item_functions`, copied from `ConfiguratorHwlibLink::functions()` (app/Models/ConfiguratorHwlibLink.php:41-49).

## Backend changes

`app/Http/Controllers/Api/ConfiguratorHwlibAdminController.php`:
- `itemRules()` (line ~298): add `'needs_review' => 'boolean'`.
- `adminIndex()` (line 62-70): eager-load `setItems.functions` too (alongside the existing `setItems.item`, `setItems.values.variable`) so the Set modal can render each row's currently-selected functions.
- `setSetItems()` (line 612-652): accept `items.*.function_ids` (`nullable|array`, each `integer|exists:configurator_hwlib_functions,id`); after creating each `$setItem`, call `$setItem->functions()->sync($row['function_ids'] ?? [])`.
- `materializeSetIntoConfiguration()` (line 774-798): after `updateOrCreate`-ing `$link`, sync `$link->functions()->sync($setItem->functions->pluck('id'))` so functions chosen in the admin Set editor survive into the real `ConfiguratorHwlibLink` when the set is applied to an opening. Today this data would silently disappear.

No new endpoints needed for quick-add — it reuses the existing `POST /config/hwlib-items` (`storeItem`) with `needs_review: true` in the payload.

`app/Http/Controllers/Api/DoorFrameConfigurationController.php` — no changes needed; `addHardwareLink`/`updateHardwareLink` (lines 1660-1802) already accept `function_ids` and sync them onto the link.

## Frontend changes

### Shared cascade logic (new small JS helper, inlined near the top of each blade file that needs it — no shared JS module exists in this codebase, views are self-contained per CLAUDE.md)

Given a `categories` array (each with `.items`, each item having `manufacturer`, `pn`, `name`, `functions`), build:
1. Category `<select>` (existing pattern in both files already).
2. Manufacturer `<select>` — distinct, sorted `manufacturer` values among items in the selected category (fall back to a single "—" option if a category has no manufacturer set on any item).
3. Part `<select>` — items filtered by category + manufacturer, labeled `name — pn`.
4. A "+ Add new part" link below the Part select, revealing 2 inline inputs (Manufacturer prefilled/editable, Name/PN) and a small "Add" button that `POST /config/hwlib-items` with `{ category_id, manufacturer, name, pn, needs_review: true }`, then injects the new item into the in-memory catalog array and selects it in the Part select (no full page reload needed).
5. Once a Part is selected, render that item's function picker exactly like `fbRenderHwFunctionPicker` (frame.blade.php:748-770) — grouped functions as mutually-exclusive radios, ungrouped as checkboxes. This logic is duplicated (not extracted into a shared file) into the admin Set row, scoped to that row's DOM subtree via `closest('.hwlib-set-item-row')` instead of fixed element IDs, since a Set can have multiple rows at once (frame.blade.php's version assumes one add-form, so its IDs stay fixed).

### `resources/views/configurator/admin.blade.php` (Set editor rows)

Rewrite `hwSetItemRowHtml`/`hwSetAddItemRow`/`hwSetCollectItems` (lines 2040-2067):
- Replace the single item `<select>` with the Category → Manufacturer → Part cascade described above, scoped per-row (each `.hwlib-set-item-row` gets its own 3 selects + function-picker container + quick-add affordance).
- Quantity/Series/Leaf/Notes stay as-is.
- `hwSetCollectItems()` also collects `function_ids` per row (checked radios/checkboxes within that row).
- `hwSetOpenModal()` (line 2069-2082): when rendering existing rows for an already-saved set, pre-select category/manufacturer/part from `row.item` and pre-check `row.functions`.

### `resources/views/configurator/frame.blade.php` (per-opening hardware add)

- Insert a Manufacturer `<select id="fb-hw-manufacturer">` between the existing Category select (`fb-hw-category`) and Item select (`fb-hw-item`), around line 705-737 (`fbFilterHwItems`). `fbFilterHwItems` gets an extra filter step for the chosen manufacturer; changing the category select repopulates the manufacturer select first (reset to "All" or first value), which then repopulates the item select — same cascade direction already used for category → item.
- Add the same "+ Add new part" quick-add affordance near `fb-hw-item`, wired the same way as in admin.blade.php (POST to `/config/hwlib-items` with `needs_review: true`, category prefilled from `fb-hw-category`'s resolved `categoryId`, then inject into `fbHwCategories` in place and re-render the manufacturer/item selects with the new item selected).
- No change needed to the function picker (`fbRenderHwFunctionPicker`) — it already works once an item is selected, including a freshly quick-added one (it'll just show no functions yet, which is correct for a brand-new stub).

### Hardware Library admin → Items tab (`resources/views/configurator/partials/hwlib-admin.blade.php` + admin.blade.php's item rendering JS)

- Table header (line 99) gets a "Review" column; render a `<span class="badge bg-yellow-lt">Needs Review</span>` when `item.needs_review` is true.
- Add a "Needs review only" filter checkbox next to the existing category filter (line 91-93), applied in the item-rendering JS the same way the category filter already is.
- Item modal (`hwlib-admin-modals.blade.php`, "Hardware Library: Item Modal", ~line 128-183): add a `needs_review` checkbox alongside the existing Active/VOS Standard/Field Install/Handed checkboxes (~line 178-183), so a reviewer can clear the flag once they've verified/completed the stub (fill in missing fields, correct the name, etc.) and save.

## Verification

1. `docker compose exec app php artisan migrate` — confirm the new column and pivot table apply cleanly against the dev DB (per project convention, `composer test` on host is broken; use `docker compose exec app php artisan test` — see project memory).
2. In the browser: open Hardware Library admin → Sets → edit/create a set. Confirm each item row now shows Category → Manufacturer → Part selects that cascade correctly, that picking a part with existing functions (e.g. a Von Duprin 98 already tagged with NL/QEL/CD in Items) shows the function picker, and that saving + reopening the set preserves the selected part and functions.
3. Use "+ Add new part" on a category/manufacturer combination with no matching part; confirm it creates a new `ConfiguratorHwlibItem` with `needs_review = true`, immediately selectable in the row, and that it now appears in Items tab tagged "Needs Review".
4. Apply the set to a configuration (`applySet`) and confirm the resulting `ConfiguratorHwlibLink` on that opening carries the same functions that were selected on the set item.
5. In the Configurator itself (a door/frame opening's Hardware tab), confirm the new Manufacturer select narrows the Part list correctly, that quick-add works the same way there, and that adding hardware still successfully triggers `hardware-parts/generate` as before.
6. In the Items tab, verify the "Needs Review" badge/filter shows the quick-added stub, and that unchecking "Needs Review" in the Item modal and saving clears the badge.

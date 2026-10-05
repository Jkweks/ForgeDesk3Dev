# Seeding / re-seeding the configurator catalog (dev -> production)

Source of truth until cutover is the fab_utils `configurator` database (container `fab_utils-db-1`, db `configurator`, user `shimshop`,
port 5439). The importers read JSON dumps from `storage/app/fab_utils_import/` inside the app container.

## 1. Dump (read-only on fab_utils)
Pipe each table through the app container (the host cannot write `storage/`):

    for t in frame_systems frame_series frame_profiles frame_components frame_fasteners products \
             door_types rails rail_lugs mid_lugs glass_specs setting_block_kits tie_rods pdf_templates \
             hwlib_variables hwlib_categories hwlib_category_variables hwlib_items hwlib_item_values \
             hwlib_fasteners hwlib_backers hwlib_backer_fasteners hwlib_item_backers; do
      docker exec fab_utils-db-1 psql -U shimshop -p 5439 -d configurator -t -A -q \
        -c "SELECT COALESCE(json_agg(row_to_json(t)), '[]') FROM $t t" \
        | docker exec -i <app container> sh -c "cat > storage/app/fab_utils_import/$t.json"
    done
    # product_accessories.json (per-product default accessories):
    docker exec fab_utils-db-1 psql -U shimshop -p 5439 -d configurator -t -A -q \
      -c "SELECT COALESCE(json_agg(row_to_json(t)), '[]') FROM (SELECT pn, default_accessories FROM products WHERE default_accessories IS NOT NULL AND jsonb_array_length(default_accessories) > 0) t" \
      | docker exec -i <app container> sh -c "cat > storage/app/fab_utils_import/product_accessories.json"

## 2. Import, in this order
1. `php artisan migrate --force`
2. `php artisan configurator:import-fab-utils`            frame catalog   (**full replace**)
3. `php artisan configurator:import-fab-utils-doors`      door catalog    (**full replace**)
4. `php artisan configurator:import-fab-utils-hwlib`      hardware library (**upsert** - safe any time, see below)
5. `php artisan configurator:import-fab-utils-pdf-templates`   sheet layouts
6. `php artisan products:apply-roll-lengths docs/plans/roll-lengths-draft.csv --dry-run`, review, then `--apply`  (gasket / weatherstrip roll lengths)

Steps 2-3 delete and recreate their catalog tables: run them on an empty (pre-go-live) database only. Once configurations exist they
would detach frame series / door types from them. (Converting them to the same upsert as hwlib is a small, known follow-up.)

## 3. Hardware library importer modes (`configurator:import-fab-utils-hwlib`)
Upserts by natural key (variable code, category name, item name, backer / fastener pn); IDs never change, so hardware sets, hardware links on
configurations and link values are never disturbed. Writes only fab_utils-supplied fields - ForgeDesk-only data (item subcategories, item
functions, needs_review, sets, items created in ForgeDesk) is left alone, and `handed` is only ever switched on.

| flag | effect |
|---|---|
| *(none)* | **sync** - update existing rows to the dump; each matched item's values/backers and category's variable list mirror the dump |
| `--create-only` | add what is missing, change nothing that exists (use once ForgeDesk, not fab_utils, is where the catalog is edited) |
| `--prune` | also remove rows an earlier import brought in that the dump no longer has - only if nothing references them; ForgeDesk-created rows never |
| `--dry-run` | print created / updated / unchanged / removed per table; write nothing |

Always `--dry-run` first on production. After every run the importer re-applies the ForgeDesk additions (`HANDED_PNS`, `FORGEDESK_ITEMS`:
the handed 4510 deadlatch + its cover, flagged `needs_review`).

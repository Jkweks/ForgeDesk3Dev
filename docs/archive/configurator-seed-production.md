# Configurator Product-Variant Seed (Production)

Postgres-friendly, hand-runnable equivalent of Laravel migration
`2026_09_22_210829_seed_configurator_product_variants.php`. Use this if you
want to seed production's `products` table directly via `psql` instead of
(or in addition to) running `php artisan migrate` there.

## What it does

- Inserts up to 310 configurator-derived placeholder `Product` rows (extrusions,
  rail lugs, tie rods, setting blocks, etc. — the Tubelite EZ Estimate parts
  that back the frame/door configurator's BOM generator), each with the
  correct finish variant (`0R`/`C2`/`DB`/`BL`) where applicable.
- Resolves `supplier_id` the same way the migration does: prefer supplier
  `id = 1`, else a supplier named `'Tubelite'`, else whatever supplier
  exists first.
- Fixes up two hwlib catalog quirks discovered during the fab_utils reseed
  (see project memory "configurator-finish-backfill-method" /
  "fab-utils-configurator-reseed"):
  - `ASA` (strike) — clears its `pn`, soft-deletes its placeholder Product.
    It ships bundled with parent hardware, like every other Strike item.
  - `P1421` (Adams Rite 4510 Deadlatch) — marks the hwlib item `handed`,
    clears its ambiguous generic `pn`, soft-deletes the generic placeholder
    Product (the real orderable SKUs are `P1421L`/`P1421R`).

## Idempotency

Every insert is gated on `WHERE NOT EXISTS (... part_number, finish ...)`,
matching the migration's own skip-if-exists logic. **Safe to run more than
once** — re-running is a no-op for rows already present. Safe to run
whether or not `configurator:import-fab-utils*` has already populated the
catalog tables on that database.

## Before running

1. **Check the `suppliers` resolution is correct for this environment.**
   `id = 1` is not guaranteed to be Tubelite everywhere — run this first
   and eyeball the result:
   ```sql
   SELECT COALESCE(
       (SELECT id FROM suppliers WHERE id = 1),
       (SELECT id FROM suppliers WHERE name = 'Tubelite'),
       (SELECT id FROM suppliers ORDER BY id LIMIT 1)
   ) AS resolved_supplier_id;
   ```
2. Back up (or snapshot) `products` and `configurator_hwlib_items` first —
   standard practice before any prod data script, even an idempotent one.
3. Run inside a transaction (already wrapped in `BEGIN;`/`COMMIT;` below) so
   you can `ROLLBACK;` instead of `COMMIT;` if a dry run turns up something
   unexpected — e.g. run everything up to (not including) the final
   `COMMIT;` line, inspect `SELECT count(*) FROM products WHERE nonsof AND
   is_special_order;`, then decide whether to commit or roll back.

## How to run

```bash
# extract the SQL block below into a .sql file, then:
psql "$DATABASE_URL" -f configurator_seed_production.sql

# or, against a container:
docker compose exec -T postgres psql -U <user> -d <db> < configurator_seed_production.sql
```

## SQL

```sql
-- Configurator product-variant seed (production)
-- Postgres-friendly equivalent of Laravel migration
-- 2026_09_22_210829_seed_configurator_product_variants.php
--
-- Idempotent: run it as many times as you like. Every row is skipped if a
-- Product with the same (part_number, finish) already exists, matching the
-- migration's own upsert-by-skip logic. Safe to run whether or not
-- configurator:import-fab-utils* has already run on this DB.
--
-- IMPORTANT: verify the `suppliers` resolution picks the right row for THIS
-- database before running (see step 1 below) - id=1 is not guaranteed to be
-- Tubelite on every environment.

BEGIN;

-- 1. Resolve the fallback supplier_id the same way the migration does:
--    prefer id=1, then a supplier literally named 'Tubelite', then just
--    take whatever supplier exists first (never leaves it NULL if any
--    supplier row exists at all).
WITH target_supplier AS (
    SELECT COALESCE(
        (SELECT id FROM suppliers WHERE id = 1),
        (SELECT id FROM suppliers WHERE name = 'Tubelite'),
        (SELECT id FROM suppliers ORDER BY id LIMIT 1)
    ) AS id
),
new_rows (sku, part_number, finish, description, manufacturer, manufacturer_part_number) AS (
    VALUES
    ('163036-0R', '163036', '0R', 'Thermal Subframe', 'Kawneer', NULL),
    ('450502-0R', '450502', '0R', 'Door Head with Transom Dovetails', 'Kawneer', NULL),
    ('450520-0R', '450520', '0R', 'Snap in Door Stop', 'Kawneer', NULL),
    ('A641010-BL', 'A641010', 'BL', 'Door Rail 10" - Thermal', NULL, '*A641010'),
    ('A641010-C2', 'A641010', 'C2', 'Door Rail 10" - Thermal', NULL, '*A641010'),
    ('A641010-DB', 'A641010', 'DB', 'Door Rail 10" - Thermal', NULL, '*A641010'),
    ('A641414-BL', 'A641414', 'BL', 'Door Rail 4" - Thermal', NULL, '*A641414'),
    ('A641414-C2', 'A641414', 'C2', 'Door Rail 4" - Thermal', NULL, '*A641414'),
    ('A641414-DB', 'A641414', 'DB', 'Door Rail 4" - Thermal', NULL, '*A641414'),
    ('A641515-BL', 'A641515', 'BL', 'Door Rail 5" - Thermal', NULL, '*A641515'),
    ('A641515-C2', 'A641515', 'C2', 'Door Rail 5" - Thermal', NULL, '*A641515'),
    ('A641515-DB', 'A641515', 'DB', 'Door Rail 5" - Thermal', NULL, '*A641515'),
    ('A642525-BL', 'A642525', 'BL', 'Door Rail 2 1/2" - Thermal', NULL, '*A642525'),
    ('A642525-C2', 'A642525', 'C2', 'Door Rail 2 1/2" - Thermal', NULL, '*A642525'),
    ('A642525-DB', 'A642525', 'DB', 'Door Rail 2 1/2" - Thermal', NULL, '*A642525'),
    ('A643030-BL', 'A643030', 'BL', 'Door Rail 3" - Thermal', NULL, '*A643030'),
    ('A643030-C2', 'A643030', 'C2', 'Door Rail 3" - Thermal', NULL, '*A643030'),
    ('A643030-DB', 'A643030', 'DB', 'Door Rail 3" - Thermal', NULL, '*A643030'),
    ('A646464-BL', 'A646464', 'BL', 'Door Rail 4" - Thermal', NULL, '*A646464'),
    ('A646464-C2', 'A646464', 'C2', 'Door Rail 4" - Thermal', NULL, '*A646464'),
    ('A646464-DB', 'A646464', 'DB', 'Door Rail 4" - Thermal', NULL, '*A646464'),
    ('A647071-BL', 'A647071', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL MEDIUM STILE', NULL, '*A647071'),
    ('A647071-C2', 'A647071', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL MEDIUM STILE', NULL, '*A647071'),
    ('A647071-DB', 'A647071', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL MEDIUM STILE', NULL, '*A647071'),
    ('A647273-BL', 'A647273', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL WIDE STILE', NULL, '*A647273'),
    ('A647273-C2', 'A647273', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL WIDE STILE', NULL, '*A647273'),
    ('A647273-DB', 'A647273', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL WIDE STILE', NULL, '*A647273'),
    ('A647475-BL', 'A647475', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL NARROW STILE', NULL, '*A647475'),
    ('A647475-C2', 'A647475', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL NARROW STILE', NULL, '*A647475'),
    ('A647475-DB', 'A647475', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - THERMAL NARROW STILE', NULL, '*A647475'),
    ('A647677-BL', 'A647677', 'BL', 'Door Stile (Bevel) - THERMAL WIDE STILE', NULL, '*A647677'),
    ('A647677-C2', 'A647677', 'C2', 'Door Stile (Bevel) - THERMAL WIDE STILE', NULL, '*A647677'),
    ('A647677-DB', 'A647677', 'DB', 'Door Stile (Bevel) - THERMAL WIDE STILE', NULL, '*A647677'),
    ('A647879-BL', 'A647879', 'BL', 'Door Stile (Bevel) - THERMAL MEDIUM STILE', NULL, '*A647879'),
    ('A647879-C2', 'A647879', 'C2', 'Door Stile (Bevel) - THERMAL MEDIUM STILE', NULL, '*A647879'),
    ('A647879-DB', 'A647879', 'DB', 'Door Stile (Bevel) - THERMAL MEDIUM STILE', NULL, '*A647879'),
    ('A648181-BL', 'A648181', 'BL', 'Door Inactive Meeting Stile - THERMAL WIDE STILE', NULL, '*A648181'),
    ('A648181-C2', 'A648181', 'C2', 'Door Inactive Meeting Stile - THERMAL WIDE STILE', NULL, '*A648181'),
    ('A648181-DB', 'A648181', 'DB', 'Door Inactive Meeting Stile - THERMAL WIDE STILE', NULL, '*A648181'),
    ('A648282-BL', 'A648282', 'BL', 'Door Inactive Meeting Stile - THERMAL MEDIUM STILE', NULL, '*A648282'),
    ('A648282-C2', 'A648282', 'C2', 'Door Inactive Meeting Stile - THERMAL MEDIUM STILE', NULL, '*A648282'),
    ('A648282-DB', 'A648282', 'DB', 'Door Inactive Meeting Stile - THERMAL MEDIUM STILE', NULL, '*A648282'),
    ('A648383-BL', 'A648383', 'BL', 'Door Stile (Center Pivot) - THERMAL MEDIUM STILE', NULL, '*A648383'),
    ('A648383-C2', 'A648383', 'C2', 'Door Stile (Center Pivot) - THERMAL MEDIUM STILE', NULL, '*A648383'),
    ('A648383-DB', 'A648383', 'DB', 'Door Stile (Center Pivot) - THERMAL MEDIUM STILE', NULL, '*A648383'),
    ('A648686-BL', 'A648686', 'BL', 'Door Stile (Center Pivot) - THERMAL NARROW STILE', NULL, '*A648686'),
    ('A648686-C2', 'A648686', 'C2', 'Door Stile (Center Pivot) - THERMAL NARROW STILE', NULL, '*A648686'),
    ('A648686-DB', 'A648686', 'DB', 'Door Stile (Center Pivot) - THERMAL NARROW STILE', NULL, '*A648686'),
    ('A648787-BL', 'A648787', 'BL', 'Door Inactive Meeting Stile - THERMAL NARROW STILE', NULL, '*A648787'),
    ('A648787-C2', 'A648787', 'C2', 'Door Inactive Meeting Stile - THERMAL NARROW STILE', NULL, '*A648787'),
    ('A648787-DB', 'A648787', 'DB', 'Door Inactive Meeting Stile - THERMAL NARROW STILE', NULL, '*A648787'),
    ('A648889-BL', 'A648889', 'BL', 'Door Stile (Bevel) - THERMAL NARROW STILE', NULL, '*A648889'),
    ('A648889-C2', 'A648889', 'C2', 'Door Stile (Bevel) - THERMAL NARROW STILE', NULL, '*A648889'),
    ('A648889-DB', 'A648889', 'DB', 'Door Stile (Bevel) - THERMAL NARROW STILE', NULL, '*A648889'),
    ('A649090-BL', 'A649090', 'BL', 'Door Stile (Center Pivot) - THERMAL WIDE STILE', NULL, '*A649090'),
    ('A649090-C2', 'A649090', 'C2', 'Door Stile (Center Pivot) - THERMAL WIDE STILE', NULL, '*A649090'),
    ('A649090-DB', 'A649090', 'DB', 'Door Stile (Center Pivot) - THERMAL WIDE STILE', NULL, '*A649090'),
    ('E1537-BL', 'E1537', 'BL', 'Door Rail 3" - Monumental', NULL, NULL),
    ('E1537-C2', 'E1537', 'C2', 'Door Rail 3" - Monumental', NULL, NULL),
    ('E1537-DB', 'E1537', 'DB', 'Door Rail 3" - Monumental', NULL, NULL),
    ('E1544-BL', 'E1544', 'BL', 'Door Rail 5" - Monumental', NULL, NULL),
    ('E1544-C2', 'E1544', 'C2', 'Door Rail 5" - Monumental', NULL, NULL),
    ('E1544-DB', 'E1544', 'DB', 'Door Rail 5" - Monumental', NULL, NULL),
    ('E1547-BL', 'E1547', 'BL', 'Door Stile (Bevel) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1547-C2', 'E1547', 'C2', 'Door Stile (Bevel) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1547-DB', 'E1547', 'DB', 'Door Stile (Bevel) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1548-BL', 'E1548', 'BL', 'Door Inactive Meeting Stile - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1548-C2', 'E1548', 'C2', 'Door Inactive Meeting Stile - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1548-DB', 'E1548', 'DB', 'Door Inactive Meeting Stile - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1549-BL', 'E1549', 'BL', 'Door Stile (Center Pivot) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1549-C2', 'E1549', 'C2', 'Door Stile (Center Pivot) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1549-DB', 'E1549', 'DB', 'Door Stile (Center Pivot) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1550-BL', 'E1550', 'BL', 'Door Rail 3 1/2" - Monumental', NULL, NULL),
    ('E1550-C2', 'E1550', 'C2', 'Door Rail 3 1/2" - Monumental', NULL, NULL),
    ('E1550-DB', 'E1550', 'DB', 'Door Rail 3 1/2" - Monumental', NULL, NULL),
    ('E1551-BL', 'E1551', 'BL', 'Door Rail 6" - Monumental', NULL, NULL),
    ('E1551-C2', 'E1551', 'C2', 'Door Rail 6" - Monumental', NULL, NULL),
    ('E1551-DB', 'E1551', 'DB', 'Door Rail 6" - Monumental', NULL, NULL),
    ('E1649-BL', 'E1649', 'BL', 'Door Astragal Stile - STANDARD NARROW STILE', NULL, NULL),
    ('E1649-C2', 'E1649', 'C2', 'Door Astragal Stile - STANDARD NARROW STILE', NULL, NULL),
    ('E1649-DB', 'E1649', 'DB', 'Door Astragal Stile - STANDARD NARROW STILE', NULL, NULL),
    ('E1650-BL', 'E1650', 'BL', 'Door Astragal Stile - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E1650-C2', 'E1650', 'C2', 'Door Astragal Stile - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E1650-DB', 'E1650', 'DB', 'Door Astragal Stile - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E1653-BL', 'E1653', 'BL', 'Door Astragal Stile - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1653-C2', 'E1653', 'C2', 'Door Astragal Stile - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1653-DB', 'E1653', 'DB', 'Door Astragal Stile - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E1654-BL', 'E1654', 'BL', 'Door Astragal Stile - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E1654-C2', 'E1654', 'C2', 'Door Astragal Stile - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E1654-DB', 'E1654', 'DB', 'Door Astragal Stile - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E1655-BL', 'E1655', 'BL', 'Door Astragal Stile - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E1655-C2', 'E1655', 'C2', 'Door Astragal Stile - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E1655-DB', 'E1655', 'DB', 'Door Astragal Stile - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E1897-BL', 'E1897', 'BL', 'Door Stile (Bevel) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E1897-C2', 'E1897', 'C2', 'Door Stile (Bevel) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E1897-DB', 'E1897', 'DB', 'Door Stile (Bevel) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E2056-BL', 'E2056', 'BL', 'Door Rail 9" (stacked) - Monumental', NULL, NULL),
    ('E2056-C2', 'E2056', 'C2', 'Door Rail 9" (stacked) - Monumental', NULL, NULL),
    ('E2056-DB', 'E2056', 'DB', 'Door Rail 9" (stacked) - Monumental', NULL, NULL),
    ('E5855-BL', 'E5855', 'BL', 'Door Rail 2 3/8" - Monumental', NULL, NULL),
    ('E5855-C2', 'E5855', 'C2', 'Door Rail 2 3/8" - Monumental', NULL, NULL),
    ('E5855-DB', 'E5855', 'DB', 'Door Rail 2 3/8" - Monumental', NULL, NULL),
    ('E6002-BL', 'E6002', 'BL', 'Door Rail 6 3/8" (mid-panel) - Standard', NULL, NULL),
    ('E6002-C2', 'E6002', 'C2', 'Door Rail 6 3/8" (mid-panel) - Standard', NULL, NULL),
    ('E6002-DB', 'E6002', 'DB', 'Door Rail 6 3/8" (mid-panel) - Standard', NULL, NULL),
    ('E6182-BL', 'E6182', 'BL', 'Door Rail 7 1/2" - Standard', NULL, NULL),
    ('E6182-C2', 'E6182', 'C2', 'Door Rail 7 1/2" - Standard', NULL, NULL),
    ('E6182-DB', 'E6182', 'DB', 'Door Rail 7 1/2" - Standard', NULL, NULL),
    ('E6189-BL', 'E6189', 'BL', 'Door Rail 7 1/2" - Monumental', NULL, NULL),
    ('E6189-C2', 'E6189', 'C2', 'Door Rail 7 1/2" - Monumental', NULL, NULL),
    ('E6189-DB', 'E6189', 'DB', 'Door Rail 7 1/2" - Monumental', NULL, NULL),
    ('E6437-BL', 'E6437', 'BL', 'Door Stile (Bevel) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6437-C2', 'E6437', 'C2', 'Door Stile (Bevel) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6437-DB', 'E6437', 'DB', 'Door Stile (Bevel) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6438-BL', 'E6438', 'BL', 'Door Inactive Meeting Stile - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6438-C2', 'E6438', 'C2', 'Door Inactive Meeting Stile - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6438-DB', 'E6438', 'DB', 'Door Inactive Meeting Stile - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6441-BL', 'E6441', 'BL', 'Door Stile (Center Pivot) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6441-C2', 'E6441', 'C2', 'Door Stile (Center Pivot) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6441-DB', 'E6441', 'DB', 'Door Stile (Center Pivot) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6443-BL', 'E6443', 'BL', 'Door Rail 3 1/2" - Monumental', NULL, NULL),
    ('E6443-C2', 'E6443', 'C2', 'Door Rail 3 1/2" - Monumental', NULL, NULL),
    ('E6443-DB', 'E6443', 'DB', 'Door Rail 3 1/2" - Monumental', NULL, NULL),
    ('E6444-BL', 'E6444', 'BL', 'Door Rail 4 1/2" - Monumental', NULL, NULL),
    ('E6444-C2', 'E6444', 'C2', 'Door Rail 4 1/2" - Monumental', NULL, NULL),
    ('E6444-DB', 'E6444', 'DB', 'Door Rail 4 1/2" - Monumental', NULL, NULL),
    ('E6445-BL', 'E6445', 'BL', 'Door Rail 12" (stacked) - Monumental (Stacked)', NULL, NULL),
    ('E6445-C2', 'E6445', 'C2', 'Door Rail 12" (stacked) - Monumental (Stacked)', NULL, NULL),
    ('E6445-DB', 'E6445', 'DB', 'Door Rail 12" (stacked) - Monumental (Stacked)', NULL, NULL),
    ('E6446-BL', 'E6446', 'BL', 'Door Rail 20" (stacked) - Monumental (Stacked)', NULL, NULL),
    ('E6446-C2', 'E6446', 'C2', 'Door Rail 20" (stacked) - Monumental (Stacked)', NULL, NULL),
    ('E6446-DB', 'E6446', 'DB', 'Door Rail 20" (stacked) - Monumental (Stacked)', NULL, NULL),
    ('E6454-BL', 'E6454', 'BL', 'Door Rail 10" - Monumental', NULL, NULL),
    ('E6454-C2', 'E6454', 'C2', 'Door Rail 10" - Monumental', NULL, NULL),
    ('E6454-DB', 'E6454', 'DB', 'Door Rail 10" - Monumental', NULL, NULL),
    ('E6616-BL', 'E6616', 'BL', 'Door Rail 6" - Standard', NULL, NULL),
    ('E6616-C2', 'E6616', 'C2', 'Door Rail 6" - Standard', NULL, NULL),
    ('E6616-DB', 'E6616', 'DB', 'Door Rail 6" - Standard', NULL, NULL),
    ('E6627-BL', 'E6627', 'BL', 'Door Inactive Meeting Stile - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6627-C2', 'E6627', 'C2', 'Door Inactive Meeting Stile - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6627-DB', 'E6627', 'DB', 'Door Inactive Meeting Stile - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6628-BL', 'E6628', 'BL', 'Door Stile (Center Pivot) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6628-C2', 'E6628', 'C2', 'Door Stile (Center Pivot) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6628-DB', 'E6628', 'DB', 'Door Stile (Center Pivot) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6629-BL', 'E6629', 'BL', 'Door Rail 4 1/2" - Monumental', NULL, NULL),
    ('E6629-C2', 'E6629', 'C2', 'Door Rail 4 1/2" - Monumental', NULL, NULL),
    ('E6629-DB', 'E6629', 'DB', 'Door Rail 4 1/2" - Monumental', NULL, NULL),
    ('E6844-BL', 'E6844', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E6844-C2', 'E6844', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E6844-DB', 'E6844', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL NARROW STILE', NULL, NULL),
    ('E6845-BL', 'E6845', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6845-C2', 'E6845', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6845-DB', 'E6845', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL MEDIUM STILE', NULL, NULL),
    ('E6846-BL', 'E6846', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6846-C2', 'E6846', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6846-DB', 'E6846', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL WIDE STILE', NULL, NULL),
    ('E6855-BL', 'E6855', 'BL', 'Door Rail 2 1/8" - Standard', NULL, NULL),
    ('E6855-C2', 'E6855', 'C2', 'Door Rail 2 1/8" - Standard', NULL, NULL),
    ('E6855-DB', 'E6855', 'DB', 'Door Rail 2 1/8" - Standard', NULL, NULL),
    ('E6856-BL', 'E6856', 'BL', 'Door Rail 4" - Standard', NULL, NULL),
    ('E6856-C2', 'E6856', 'C2', 'Door Rail 4" - Standard', NULL, NULL),
    ('E6856-DB', 'E6856', 'DB', 'Door Rail 4" - Standard', NULL, NULL),
    ('E6858-BL', 'E6858', 'BL', 'Door Rail 5" - Standard', NULL, NULL),
    ('E6858-C2', 'E6858', 'C2', 'Door Rail 5" - Standard', NULL, NULL),
    ('E6858-DB', 'E6858', 'DB', 'Door Rail 5" - Standard', NULL, NULL),
    ('E6859-BL', 'E6859', 'BL', 'Door Rail 3" - Standard', NULL, NULL),
    ('E6859-C2', 'E6859', 'C2', 'Door Rail 3" - Standard', NULL, NULL),
    ('E6859-DB', 'E6859', 'DB', 'Door Rail 3" - Standard', NULL, NULL),
    ('E6861-BL', 'E6861', 'BL', 'Door Rail 4 1/2" - Standard', NULL, NULL),
    ('E6861-C2', 'E6861', 'C2', 'Door Rail 4 1/2" - Standard', NULL, NULL),
    ('E6861-DB', 'E6861', 'DB', 'Door Rail 4 1/2" - Standard', NULL, NULL),
    ('E6862-BL', 'E6862', 'BL', 'Door Rail 12" (stacked) - Standard (Stacked)', NULL, NULL),
    ('E6862-C2', 'E6862', 'C2', 'Door Rail 12" (stacked) - Standard (Stacked)', NULL, NULL),
    ('E6862-DB', 'E6862', 'DB', 'Door Rail 12" (stacked) - Standard (Stacked)', NULL, NULL),
    ('E6863-BL', 'E6863', 'BL', 'Door Rail 7 1/2" - Standard', NULL, NULL),
    ('E6863-C2', 'E6863', 'C2', 'Door Rail 7 1/2" - Standard', NULL, NULL),
    ('E6863-DB', 'E6863', 'DB', 'Door Rail 7 1/2" - Standard', NULL, NULL),
    ('E6865-BL', 'E6865', 'BL', 'Door Rail 20" (stacked) - Standard (Stacked)', NULL, NULL),
    ('E6865-C2', 'E6865', 'C2', 'Door Rail 20" (stacked) - Standard (Stacked)', NULL, NULL),
    ('E6865-DB', 'E6865', 'DB', 'Door Rail 20" (stacked) - Standard (Stacked)', NULL, NULL),
    ('E6866-BL', 'E6866', 'BL', 'Door Rail 6 1/2" - Standard', NULL, NULL),
    ('E6866-C2', 'E6866', 'C2', 'Door Rail 6 1/2" - Standard', NULL, NULL),
    ('E6866-DB', 'E6866', 'DB', 'Door Rail 6 1/2" - Standard', NULL, NULL),
    ('E7054-BL', 'E7054', 'BL', 'Door Rail 4" - Standard', NULL, NULL),
    ('E7054-C2', 'E7054', 'C2', 'Door Rail 4" - Standard', NULL, NULL),
    ('E7054-DB', 'E7054', 'DB', 'Door Rail 4" - Standard', NULL, NULL),
    ('E7055-BL', 'E7055', 'BL', 'Door Stile (Bevel) - STANDARD NARROW STILE', NULL, NULL),
    ('E7055-C2', 'E7055', 'C2', 'Door Stile (Bevel) - STANDARD NARROW STILE', NULL, NULL),
    ('E7055-DB', 'E7055', 'DB', 'Door Stile (Bevel) - STANDARD NARROW STILE', NULL, NULL),
    ('E7056-BL', 'E7056', 'BL', 'Door Inactive Meeting Stile - STANDARD NARROW STILE', NULL, NULL),
    ('E7056-C2', 'E7056', 'C2', 'Door Inactive Meeting Stile - STANDARD NARROW STILE', NULL, NULL),
    ('E7056-DB', 'E7056', 'DB', 'Door Inactive Meeting Stile - STANDARD NARROW STILE', NULL, NULL),
    ('E7057-BL', 'E7057', 'BL', 'Door Stile (Center Pivot) - STANDARD NARROW STILE', NULL, NULL),
    ('E7057-C2', 'E7057', 'C2', 'Door Stile (Center Pivot) - STANDARD NARROW STILE', NULL, NULL),
    ('E7057-DB', 'E7057', 'DB', 'Door Stile (Center Pivot) - STANDARD NARROW STILE', NULL, NULL),
    ('E7059-BL', 'E7059', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - STANDARD NARROW STILE', NULL, NULL),
    ('E7059-C2', 'E7059', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - STANDARD NARROW STILE', NULL, NULL),
    ('E7059-DB', 'E7059', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - STANDARD NARROW STILE', NULL, NULL),
    ('E7068-BL', 'E7068', 'BL', 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE', NULL, NULL),
    ('E7068-C2', 'E7068', 'C2', 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE', NULL, NULL),
    ('E7068-DB', 'E7068', 'DB', 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE', NULL, NULL),
    ('E7085-BL', 'E7085', 'BL', 'Door Stile (Rabbet/Continuous Hinge) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7085-C2', 'E7085', 'C2', 'Door Stile (Rabbet/Continuous Hinge) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7085-DB', 'E7085', 'DB', 'Door Stile (Rabbet/Continuous Hinge) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7086-BL', 'E7086', 'BL', 'Door Stile (Bevel) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7086-C2', 'E7086', 'C2', 'Door Stile (Bevel) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7086-DB', 'E7086', 'DB', 'Door Stile (Bevel) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7087-BL', 'E7087', 'BL', 'Door Inactive Meeting Stile - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7087-C2', 'E7087', 'C2', 'Door Inactive Meeting Stile - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7087-DB', 'E7087', 'DB', 'Door Inactive Meeting Stile - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7088-BL', 'E7088', 'BL', 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7088-C2', 'E7088', 'C2', 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7088-DB', 'E7088', 'DB', 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE 4in.', NULL, NULL),
    ('E7140-BL', 'E7140', 'BL', 'Door Rail 4 1/2" - Standard', NULL, NULL),
    ('E7140-C2', 'E7140', 'C2', 'Door Rail 4 1/2" - Standard', NULL, NULL),
    ('E7140-DB', 'E7140', 'DB', 'Door Rail 4 1/2" - Standard', NULL, NULL),
    ('E7148-BL', 'E7148', 'BL', 'Door Rail 3" - Standard', NULL, NULL),
    ('E7148-C2', 'E7148', 'C2', 'Door Rail 3" - Standard', NULL, NULL),
    ('E7148-DB', 'E7148', 'DB', 'Door Rail 3" - Standard', NULL, NULL),
    ('E7248-BL', 'E7248', 'BL', 'Door Rail 1 9/32" - Standard', NULL, NULL),
    ('E7248-C2', 'E7248', 'C2', 'Door Rail 1 9/32" - Standard', NULL, NULL),
    ('E7248-DB', 'E7248', 'DB', 'Door Rail 1 9/32" - Standard', NULL, NULL),
    ('E7255-BL', 'E7255', 'BL', 'Door Rail 2 1/8" - Standard', NULL, NULL),
    ('E7255-C2', 'E7255', 'C2', 'Door Rail 2 1/8" - Standard', NULL, NULL),
    ('E7255-DB', 'E7255', 'DB', 'Door Rail 2 1/8" - Standard', NULL, NULL),
    ('E7418-BL', 'E7418', 'BL', 'Door Stile (Center Pivot) - STANDARD WIDE STILE', NULL, NULL),
    ('E7418-C2', 'E7418', 'C2', 'Door Stile (Center Pivot) - STANDARD WIDE STILE', NULL, NULL),
    ('E7418-DB', 'E7418', 'DB', 'Door Stile (Center Pivot) - STANDARD WIDE STILE', NULL, NULL),
    ('E7419-BL', 'E7419', 'BL', 'Door Rail 6 1/2" - Standard', NULL, NULL),
    ('E7419-C2', 'E7419', 'C2', 'Door Rail 6 1/2" - Standard', NULL, NULL),
    ('E7419-DB', 'E7419', 'DB', 'Door Rail 6 1/2" - Standard', NULL, NULL),
    ('E7459-BL', 'E7459', 'BL', 'Door Rail 3/4" - Standard', NULL, NULL),
    ('E7459-C2', 'E7459', 'C2', 'Door Rail 3/4" - Standard', NULL, NULL),
    ('E7459-DB', 'E7459', 'DB', 'Door Rail 3/4" - Standard', NULL, NULL),
    ('E7734-BL', 'E7734', 'BL', 'Door Rail 1 3/4" - Standard', NULL, NULL),
    ('E7734-C2', 'E7734', 'C2', 'Door Rail 1 3/4" - Standard', NULL, NULL),
    ('E7734-DB', 'E7734', 'DB', 'Door Rail 1 3/4" - Standard', NULL, NULL),
    ('P0142D10-0R', 'P0142D10', '0R', 'Mid Rail Lug for E6865', NULL, NULL),
    ('P0142D175-0R', 'P0142D175', '0R', 'Mid Rail Lug for E7734', NULL, NULL),
    ('P0142D2125-0R', 'P0142D2125', '0R', 'Mid Rail Lug for E6855', NULL, NULL),
    ('P0142D3-0R', 'P0142D3', '0R', 'Mid Rail Lug for E6859', NULL, NULL),
    ('P0142D4-0R', 'P0142D4', '0R', 'Mid Rail Lug for E6856', NULL, NULL),
    ('P0142D45-0R', 'P0142D45', '0R', 'Mid Rail Lug for E6861', NULL, NULL),
    ('P0142D5-0R', 'P0142D5', '0R', 'Mid Rail Lug for E6858', NULL, NULL),
    ('P0142D6-0R', 'P0142D6', '0R', 'Mid Rail Lug for E6862', NULL, NULL),
    ('P0142D65-0R', 'P0142D65', '0R', 'Mid Rail Lug for E6866', NULL, NULL),
    ('P0142D75-0R', 'P0142D75', '0R', 'Mid Rail Lug for E6863', NULL, NULL),
    ('P0142M10-0R', 'P0142M10', '0R', 'Mid Rail Lug for E6446', NULL, NULL),
    ('P0142M2375-0R', 'P0142M2375', '0R', 'Mid Rail Lug for E5855', NULL, NULL),
    ('P0142M35-0R', 'P0142M35', '0R', 'Mid Rail Lug for E6443', NULL, NULL),
    ('P0142M45-0R', 'P0142M45', '0R', 'Mid Rail Lug for E6444', NULL, NULL),
    ('P0142M6-0R', 'P0142M6', '0R', 'Mid Rail Lug for E6445', NULL, NULL),
    ('P0142T3-0R', 'P0142T3', '0R', 'Mid Rail Lug for A643030', NULL, NULL),
    ('P0142T4-0R', 'P0142T4', '0R', 'Mid Rail Lug for A646464', NULL, NULL),
    ('P022A-0R', 'P022A', '0R', 'Tie Rod - NARROW STILE (24"-30")', NULL, NULL),
    ('P022B-0R', 'P022B', '0R', 'Tie Rod - NARROW STILE (30"-36")', NULL, NULL),
    ('P022C-0R', 'P022C', '0R', 'Tie Rod - NARROW STILE (36"-42")', NULL, NULL),
    ('P022D-0R', 'P022D', '0R', 'Tie Rod - NARROW STILE (38"-44")', NULL, NULL),
    ('P022E-0R', 'P022E', '0R', 'Tie Rod - NARROW STILE (42"-48")', NULL, NULL),
    ('P022F-0R', 'P022F', '0R', 'Tie Rod - MEDIUM STILE (24"-30")', NULL, NULL),
    ('P022I-0R', 'P022I', '0R', 'Tie Rod - MEDIUM STILE (42"-48")', NULL, NULL),
    ('P022J-0R', 'P022J', '0R', 'Tie Rod - MEDIUM STILE 4in. (24"-30")', NULL, NULL),
    ('P022K-0R', 'P022K', '0R', 'Tie Rod - MEDIUM STILE 4in. (30"-36")', NULL, NULL),
    ('P022L-0R', 'P022L', '0R', 'Tie Rod - MEDIUM STILE 4in. (36"-42")', NULL, NULL),
    ('P022M-0R', 'P022M', '0R', 'Tie Rod - MEDIUM STILE 4in. (38"-44")', NULL, NULL),
    ('P022N-0R', 'P022N', '0R', 'Tie Rod - MEDIUM STILE 4in. (42"-48")', NULL, NULL),
    ('P022O-0R', 'P022O', '0R', 'Tie Rod - WIDE STILE (24"-30")', NULL, NULL),
    ('P022Q-0R', 'P022Q', '0R', 'Tie Rod - WIDE STILE (36"-42")', NULL, NULL),
    ('P022R-0R', 'P022R', '0R', 'Tie Rod - WIDE STILE (42"-48")', NULL, NULL),
    ('P022S-0R', 'P022S', '0R', 'Tie Rod - MONUMENTAL NARROW STILE (24"-30")', NULL, NULL),
    ('P022T-0R', 'P022T', '0R', 'Tie Rod - MONUMENTAL NARROW STILE (30"-36")', NULL, NULL),
    ('P022U-0R', 'P022U', '0R', 'Tie Rod - MONUMENTAL NARROW STILE (42"-48")', NULL, NULL),
    ('P022V-0R', 'P022V', '0R', 'Tie Rod -  (0"-9999")', NULL, NULL),
    ('P046T10-0R', 'P046T10', '0R', 'Rail Lug for A641010', NULL, NULL),
    ('P046T25-0R', 'P046T25', '0R', 'Rail Lug for A642525', NULL, NULL),
    ('P046T4-0R', 'P046T4', '0R', 'Rail Lug for A641414', NULL, NULL),
    ('P046T5-0R', 'P046T5', '0R', 'Rail Lug for A641515', NULL, NULL),
    ('P052D2125-0R', 'P052D2125', '0R', 'Rail Lug for E7255', NULL, NULL),
    ('P052D3-0R', 'P052D3', '0R', 'Rail Lug for E7148', NULL, NULL),
    ('P052D4-0R', 'P052D4', '0R', 'Rail Lug for E7054', NULL, NULL),
    ('P052D45-0R', 'P052D45', '0R', 'Rail Lug for E7140', NULL, NULL),
    ('P052D6-0R', 'P052D6', '0R', 'Rail Lug for E6616', NULL, NULL),
    ('P052D65-0R', 'P052D65', '0R', 'Rail Lug for E7419', NULL, NULL),
    ('P052D75-0R', 'P052D75', '0R', 'Rail Lug for E6182', NULL, NULL),
    ('P052M10-0R', 'P052M10', '0R', 'Rail Lug for E6454', NULL, NULL),
    ('P052M3-0R', 'P052M3', '0R', 'Rail Lug for E1537', NULL, NULL),
    ('P052M35-0R', 'P052M35', '0R', 'Rail Lug for E1550', NULL, NULL),
    ('P052M45-0R', 'P052M45', '0R', 'Rail Lug for E6629', NULL, NULL),
    ('P052M5-0R', 'P052M5', '0R', 'Rail Lug for E1544', NULL, NULL),
    ('P052M6-0R', 'P052M6', '0R', 'Rail Lug for E1551', NULL, NULL),
    ('P052M75-0R', 'P052M75', '0R', 'Rail Lug for E6189', NULL, NULL),
    ('P1401-0R', 'P1401', '0R', 'Mid Rail Lug for E7459', NULL, NULL),
    ('P1431-0R', 'P1431', '0R', 'Glass Gasket - 5/16"', NULL, NULL),
    ('P1928A-0R', 'P1928A', '0R', 'Setting Block Kit - STANDARD 3/16"', NULL, NULL),
    ('P1928B-0R', 'P1928B', '0R', 'Setting Block Kit - STANDARD 3/8"', NULL, NULL),
    ('P1928C-0R', 'P1928C', '0R', 'Setting Block Kit - STANDARD 1/2"', NULL, NULL),
    ('P1928D-0R', 'P1928D', '0R', 'Setting Block Kit #2 - STANDARD 3/16"', NULL, NULL),
    ('P1928E-0R', 'P1928E', '0R', 'Setting Block Kit #2 - STANDARD 3/8"', NULL, NULL),
    ('P1928F-0R', 'P1928F', '0R', 'Setting Block Kit - THERMAL 1/2"', NULL, NULL),
    ('P1928G-0R', 'P1928G', '0R', 'Setting Block Kit #2 - THERMAL 1/2"', NULL, NULL),
    ('P2728-0R', 'P2728', '0R', 'Standard Storefront Gasket', NULL, NULL),
    ('P597-0R', 'P597', '0R', 'Mid Rail Lug for E2056', NULL, NULL),
    ('P795DT-C2', 'P795DT', 'C2', 'Top Offset Pivot - Door Portion', NULL, NULL),
    ('P795DT-DB', 'P795DT', 'DB', 'Top Offset Pivot - Door Portion', NULL, NULL),
    ('P795H-C2', 'P795H', 'C2', 'Top Offset Pivot - Frame Portion', NULL, NULL),
    ('P795H-DB', 'P795H', 'DB', 'Top Offset Pivot - Frame Portion', NULL, NULL),
    ('S166-0R', 'S166', '0R', '1/4-20 x 1" FHMS', NULL, NULL),
    ('S197-0R', 'S197', '0R', 'Mid Rail Lug Fastener #1 for E7459', NULL, NULL),
    ('S206-0R', 'S206', '0R', 'Mid Rail Lug Fastener #2 for E5855', NULL, NULL),
    ('S270-0R', 'S270', '0R', 'Mid Rail Lug Fastener #2 for A643030', NULL, NULL)
)
INSERT INTO products (
    sku, part_number, finish, description, manufacturer, manufacturer_part_number,
    unit_cost, quantity_on_hand, quantity_committed, supplier_id,
    is_special_order, nonsof, created_at, updated_at
)
SELECT
    nr.sku, nr.part_number, nr.finish, nr.description, nr.manufacturer, nr.manufacturer_part_number,
    0, 0, 0, ts.id,
    true, true, now(), now()
FROM new_rows nr
CROSS JOIN target_supplier ts
WHERE NOT EXISTS (
    SELECT 1 FROM products p
    WHERE p.part_number = nr.part_number
    AND p.finish IS NOT DISTINCT FROM nr.finish
);

-- 2. ASA strike: not a stocked/sold part - bundled with its parent
--    lockset/panic hardware. Every other item in the hwlib "Strike"
--    category has pn = NULL for the same reason.
UPDATE configurator_hwlib_items
SET pn = NULL, updated_at = now()
WHERE pn = 'ASA';

UPDATE products
SET deleted_at = now(), updated_at = now()
WHERE part_number = 'ASA'
    AND finish IS NULL
    AND deleted_at IS NULL;

-- 3. P1421 (Adams Rite 4510 Deadlatch): a handed item. P1421L/P1421R
--    already exist as the real orderable SKUs, so mark the hwlib catalog
--    item handed and clear its ambiguous generic pn/placeholder Product.
UPDATE configurator_hwlib_items
SET handed = true, pn = NULL, updated_at = now()
WHERE pn = 'P1421';

UPDATE products
SET deleted_at = now(), updated_at = now()
WHERE part_number = 'P1421'
    AND finish IS NULL
    AND deleted_at IS NULL;

COMMIT;
```

## Source

Generated from the `$rows` array in
`laravel/database/migrations/2026_09_22_210829_seed_configurator_product_variants.php`
(2026-09-22) — see that file's own doc comment and
`docs/configurator.md` / project memory "configurator-finish-backfill-method"
for how the finish data was derived (Tubelite EZ Estimate `P Formulas` /
`SL Formulas` sheets, not guessed).

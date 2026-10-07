-- ============================================================================
-- Move all stock in "Fab Aisle" (and every location beneath it) to
-- "Standard Holding Area".                                   pgAdmin Query Tool
--
-- HOW TO USE
--   STEP 1  Run the SELECT. Confirm the source and target names match what you
--           expect (edit the two names at the top of STEP 2 if they differ).
--   STEP 2  Run the DO block as-is. dry_run is true, so it only REPORTS what it
--           would do (see the "Messages" tab) and changes nothing.
--           Then set dry_run := false and run it again to make the move.
--   STEP 3  Run the verification SELECTs.
--
-- WHAT IT DOES
--   * Finds the source location and all of its descendants (any depth).
--   * For each product, adds that product's quantity from those locations
--     into ONE row at the holding area (merging with a row that is already
--     there), carrying over committed quantity and the "primary" flag.
--   * Old rows are zeroed and soft-deleted (same as the app does), so nothing
--     is lost and history stays intact.
--   * Product totals do not change (stock only moves between locations).
--   * Optionally writes a "transfer" line in Transaction History for each
--     moved row, like the app's own transfer does.
--   Everything runs as a single transaction: if anything fails, nothing moves.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- STEP 1: look at the candidates
-- ----------------------------------------------------------------------------
SELECT id, name, code, parent_id, path, depth, is_active
FROM storage_locations
WHERE deleted_at IS NULL
  AND (name ILIKE '%fab%aisle%' OR name ILIKE '%holding%')
ORDER BY name;


-- ----------------------------------------------------------------------------
-- STEP 2: preview, then move
-- ----------------------------------------------------------------------------
DO $$
DECLARE
    source_name      text    := 'Fab Aisle';               -- exact name (case-insensitive)
    target_name      text    := 'Standard Holding Area';   -- exact name (case-insensitive)
    dry_run          boolean := true;                      -- true = report only; false = do the move
    log_transactions boolean := true;                      -- write "transfer" lines to Transaction History

    source_id        bigint;
    target_id        bigint;
    target_label     text;
    n                int;
    v_rows           int;
    v_products       int;
    v_units          numeric;
    v_merged         int;
    v_repointed      int;
BEGIN
    -- Resolve the two locations; refuse to guess if a name is missing or ambiguous.
    SELECT count(*), min(id) INTO n, source_id
    FROM storage_locations WHERE deleted_at IS NULL AND lower(name) = lower(source_name);
    IF n <> 1 THEN
        RAISE EXCEPTION 'Expected exactly one location named "%" but found %. Run STEP 1 and fix source_name.', source_name, n;
    END IF;

    SELECT count(*), min(id), min(name) INTO n, target_id, target_label
    FROM storage_locations WHERE deleted_at IS NULL AND lower(name) = lower(target_name);
    IF n <> 1 THEN
        RAISE EXCEPTION 'Expected exactly one location named "%" but found %. Run STEP 1 and fix target_name.', target_name, n;
    END IF;

    -- The source and everything beneath it. (Soft-deleted children are included
    -- on purpose, so no stock is left behind in a hidden sub-location.)
    CREATE TEMP TABLE _src_locs ON COMMIT DROP AS
    WITH RECURSIVE tree AS (
        SELECT id FROM storage_locations WHERE id = source_id
        UNION ALL
        SELECT c.id FROM storage_locations c JOIN tree t ON c.parent_id = t.id
    )
    SELECT id FROM tree;

    IF EXISTS (SELECT 1 FROM _src_locs WHERE id = target_id) THEN
        RAISE EXCEPTION '"%" is inside "%" (or is the same location); nothing to do.', target_label, source_name;
    END IF;

    -- Active stock rows in that tree.
    CREATE TEMP TABLE _moving ON COMMIT DROP AS
    SELECT il.id, il.product_id, il.quantity, il.quantity_committed, il.is_primary,
           il.storage_location_id AS from_location_id
    FROM inventory_locations il
    WHERE il.deleted_at IS NULL
      AND il.storage_location_id IN (SELECT id FROM _src_locs);

    -- One summary row per product; keeper_id is the row that gets re-pointed
    -- when the product has nothing at the holding area yet.
    CREATE TEMP TABLE _per_product ON COMMIT DROP AS
    SELECT product_id,
           SUM(quantity)           AS q,
           SUM(quantity_committed) AS c,
           bool_or(is_primary)     AS prim,
           min(id)                 AS keeper_id
    FROM _moving
    GROUP BY product_id;

    -- Products that already have a row at the holding area (use its lowest id).
    CREATE TEMP TABLE _target_rows ON COMMIT DROP AS
    SELECT product_id, min(id) AS id
    FROM inventory_locations
    WHERE deleted_at IS NULL AND storage_location_id = target_id
      AND product_id IN (SELECT product_id FROM _per_product)
    GROUP BY product_id;

    SELECT count(*) INTO v_rows FROM _moving;
    SELECT count(*), COALESCE(SUM(q), 0) INTO v_products, v_units FROM _per_product;
    SELECT count(*) INTO v_merged FROM _target_rows;
    v_repointed := v_products - v_merged;

    RAISE NOTICE 'Source "%" (id %) + % descendant location(s)  ->  target "%" (id %)',
        source_name, source_id, (SELECT count(*) - 1 FROM _src_locs), target_label, target_id;
    RAISE NOTICE '% stock row(s), % product(s), % total units to move', v_rows, v_products, v_units;
    RAISE NOTICE '  % product(s) already have a row at the holding area (quantities will be added to it)', v_merged;
    RAISE NOTICE '  % product(s) get a new row there', v_repointed;

    IF dry_run THEN
        RAISE NOTICE 'DRY RUN: nothing was changed. Set dry_run := false and run again to move the stock.';
        RETURN;
    END IF;

    -- Audit trail first (product totals are unchanged, so before/after are the same).
    IF log_transactions THEN
        INSERT INTO inventory_transactions
            (product_id, type, quantity, quantity_before, quantity_after,
             reference_number, reference_type, reference_id, notes,
             user_id, transaction_date, created_at, updated_at)
        SELECT m.product_id, 'transfer', m.quantity, p.quantity_on_hand, p.quantity_on_hand,
               'Transfer: ' || fl.name || ' -> ' || target_label, 'location_transfer', target_id,
               'Bulk move from ' || fl.name || ' to ' || target_label || ' (SQL script)',
               NULL, now(), now(), now()
        FROM _moving m
        JOIN products p ON p.id = m.product_id
        JOIN storage_locations fl ON fl.id = m.from_location_id
        WHERE m.quantity > 0;
    END IF;

    -- (a) Product already has a row at the holding area: add into it.
    UPDATE inventory_locations t
    SET quantity           = t.quantity + p.q,
        quantity_committed = t.quantity_committed + p.c,
        is_primary         = t.is_primary OR p.prim,
        updated_at         = now()
    FROM _per_product p
    JOIN _target_rows tr ON tr.product_id = p.product_id
    WHERE t.id = tr.id;

    -- (b) Product has none there: re-point its lowest-id row, carrying the totals.
    UPDATE inventory_locations k
    SET storage_location_id = target_id,
        quantity            = p.q,
        quantity_committed  = p.c,
        is_primary          = p.prim,
        updated_at          = now()
    FROM _per_product p
    WHERE k.id = p.keeper_id
      AND NOT EXISTS (SELECT 1 FROM _target_rows tr WHERE tr.product_id = p.product_id);

    -- (c) Every other source row is now empty: zero it and soft-delete it.
    UPDATE inventory_locations il
    SET quantity = 0, quantity_committed = 0, is_primary = false,
        deleted_at = now(), updated_at = now()
    FROM _moving m
    JOIN _per_product p ON p.product_id = m.product_id
    WHERE il.id = m.id
      AND NOT (il.id = p.keeper_id
               AND NOT EXISTS (SELECT 1 FROM _target_rows tr WHERE tr.product_id = p.product_id));

    RAISE NOTICE 'DONE: moved % stock row(s) / % product(s) / % units to "%".', v_rows, v_products, v_units, target_label;
END $$;


-- ----------------------------------------------------------------------------
-- STEP 3: verify (run after the real move)
-- ----------------------------------------------------------------------------
-- 3a. Nothing active should remain at the source or below it  (expect 0 rows):
WITH RECURSIVE tree AS (
    SELECT id FROM storage_locations WHERE lower(name) = lower('Fab Aisle') AND deleted_at IS NULL
    UNION ALL
    SELECT c.id FROM storage_locations c JOIN tree t ON c.parent_id = t.id
)
SELECT sl.name, count(*) AS stock_rows, SUM(il.quantity) AS units
FROM inventory_locations il
JOIN storage_locations sl ON sl.id = il.storage_location_id
WHERE il.deleted_at IS NULL AND il.quantity <> 0 AND il.storage_location_id IN (SELECT id FROM tree)
GROUP BY sl.name;

-- 3b. What is at the holding area now:
SELECT sl.name, count(*) AS stock_rows, SUM(il.quantity) AS units
FROM inventory_locations il
JOIN storage_locations sl ON sl.id = il.storage_location_id
WHERE il.deleted_at IS NULL AND lower(sl.name) = lower('Standard Holding Area')
GROUP BY sl.name;

-- 3c. Product totals must still equal the sum of their locations (expect 0 rows):
SELECT p.id, p.sku, p.quantity_on_hand, SUM(il.quantity) AS in_locations
FROM products p
JOIN inventory_locations il ON il.product_id = p.id AND il.deleted_at IS NULL
GROUP BY p.id, p.sku, p.quantity_on_hand
HAVING p.quantity_on_hand <> SUM(il.quantity)
LIMIT 50;

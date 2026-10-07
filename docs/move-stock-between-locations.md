# Move stock between storage locations (SQL, pgAdmin)

For bulk moves that would be tedious through the app, such as emptying a whole aisle into a holding
area. Script: [`docs/sql/move-stock-between-locations.sql`](sql/move-stock-between-locations.sql). It is
written for pgAdmin's Query Tool (open the file, or paste it) and ships set up for
**"Fab Aisle" (and everything beneath it) -> "Standard Holding Area"**. To move something else, change
`source_name` and `target_name` at the top of STEP 2.

## Using it

1. **STEP 1** (a plain `SELECT`): lists locations whose names look like the source or target. Confirm the
   exact names; the script matches names exactly (case-insensitive) and refuses to guess.
2. **STEP 2** (the `DO` block): leave `dry_run := true` and run it. Open the **Messages** tab: it reports
   how many stock rows, products and units would move, and how many products already have a row at the
   target. Nothing is changed.
3. Set `dry_run := false` and run STEP 2 again to make the move.
4. **STEP 3**: run the three verification queries (3a and 3c should return no rows).

Take a database backup before the real run, as with any bulk change.

## What it does

- Moves stock from the source location **and every location beneath it, at any depth**, including
  sub-locations that were deleted in the app (so no stock is stranded in a hidden bin).
- Per product, adds the quantity into **one row at the target**, merging with a row that is already there.
  Committed quantity and the "primary" flag carry over. A product that is also stocked somewhere else
  has only its source-tree stock moved.
- The emptied source rows are zeroed and soft-deleted, the same way the app treats removed rows.
- Product totals (`quantity_on_hand`) do not change, since stock only moves between locations.
- Unless `log_transactions := false`, writes a **transfer** line per moved row to Transaction History
  (`Transfer: <from> -> <to>`, "(SQL script)" in the notes), like the app's own transfer.
- One transaction: any error leaves everything as it was. It stops with a clear message if a name matches
  no location or several, or if the target is inside the source tree. Running it twice moves nothing the
  second time.

## Notes

- There is no one-click undo. The transfer lines in Transaction History record where each row came from;
  a backup restore is the clean way back.
- Moves that need to keep a product's stock **split** between several target locations are not handled by
  this script; use the app's transfer for those.
- Verified before it was added here, against a throwaway Postgres 16 with representative data (three rows
  merging into one, a product already present at the target, a product split across the source and an
  unrelated location, a soft-deleted child location, an untouched bystander product): dry run changes
  nothing, the real run produced the expected rows/totals/audit lines, the verification queries came back
  clean, a second run was a no-op, and both guards (unknown name, target inside source) fired. It has not
  been run against the production database; do the dry run there first.
- Relies on the current schema: `inventory_locations.storage_location_id`, `storage_locations.parent_id`,
  soft deletes (`deleted_at`), and the `inventory_transactions` columns. If those change, re-test the script.

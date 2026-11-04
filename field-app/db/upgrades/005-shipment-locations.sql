-- Upgrade an EXISTING field database to contract v5 (pickup / drop-off locations on shipments, for driver navigation).
-- Fresh installs get this from db/init and don't need it. Run once as the superuser, BEFORE ForgeDesk publishes
-- (its fleet:sync-out fails loudly until these columns exist):
--   docker compose exec -T db psql -U fieldadmin -d fieldapp -v ON_ERROR_STOP=1 < db/upgrades/005-shipment-locations.sql
BEGIN;

ALTER TABLE fa_shipments
  ADD COLUMN IF NOT EXISTS pickup_name     text,
  ADD COLUMN IF NOT EXISTS pickup_address  text,
  ADD COLUMN IF NOT EXISTS pickup_lat      double precision,
  ADD COLUMN IF NOT EXISTS pickup_lng      double precision,
  ADD COLUMN IF NOT EXISTS dropoff_name    text,
  ADD COLUMN IF NOT EXISTS dropoff_address text,
  ADD COLUMN IF NOT EXISTS dropoff_lat     double precision,
  ADD COLUMN IF NOT EXISTS dropoff_lng     double precision;

COMMIT;

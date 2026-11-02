-- Upgrade an EXISTING field database to contract v3 (shipment pre/post-delivery inspections).
-- Fresh installs get this from db/init and don't need it. Run once as the superuser, BEFORE
-- ForgeDesk publishes (its fleet:sync-out fails loudly until fa_shipments exists):
--   docker compose exec -T db psql -U fieldadmin -d fieldapp -v ON_ERROR_STOP=1 < db/upgrades/003-shipments.sql
BEGIN;

CREATE TABLE IF NOT EXISTS fa_shipments (
  shipment_number text PRIMARY KEY,
  job_label       text,
  destination     text,
  ship_date       date,
  checklist       text NOT NULL,
  is_active       boolean NOT NULL DEFAULT true,
  synced_at       timestamp
);

REVOKE ALL ON fa_shipments FROM PUBLIC;
GRANT SELECT                 ON fa_shipments TO field_app;
GRANT SELECT, INSERT, UPDATE ON fa_shipments TO forgedesk_sync;

COMMIT;

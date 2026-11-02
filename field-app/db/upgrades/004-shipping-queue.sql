-- Upgrade an EXISTING field database to contract v4 (shipping queue, pre-checks and new shipments from the phone).
-- Fresh installs get this from db/init and don't need it. Run once as the superuser, BEFORE ForgeDesk publishes
-- (its fleet:sync-out fails loudly until these columns/tables exist):
--   docker compose exec -T db psql -U fieldadmin -d fieldapp -v ON_ERROR_STOP=1 < db/upgrades/004-shipping-queue.sql
BEGIN;

ALTER TABLE fa_shipments
  ADD COLUMN IF NOT EXISTS carrier       text,
  ADD COLUMN IF NOT EXISTS status        text NOT NULL DEFAULT 'shipped',
  ADD COLUMN IF NOT EXISTS loads         text,
  ADD COLUMN IF NOT EXISTS pre_checklist text NOT NULL DEFAULT '[]';

ALTER TABLE fa_users       ADD COLUMN IF NOT EXISTS permissions text NOT NULL DEFAULT '[]';
ALTER TABLE fa_submissions ADD COLUMN IF NOT EXISTS submitted_by_user_id bigint;

CREATE TABLE IF NOT EXISTS fa_jobs (
  job_number text PRIMARY KEY,
  job_name   text NOT NULL DEFAULT '',
  is_active  boolean NOT NULL DEFAULT true,
  synced_at  timestamp
);
CREATE TABLE IF NOT EXISTS fa_shipment_types (
  type_id    bigint PRIMARY KEY,
  name       text NOT NULL,
  sort_order integer NOT NULL DEFAULT 0,
  is_active  boolean NOT NULL DEFAULT true,
  synced_at  timestamp
);

REVOKE ALL ON fa_jobs, fa_shipment_types FROM PUBLIC;
GRANT SELECT                 ON fa_jobs, fa_shipment_types TO field_app;
GRANT SELECT, INSERT, UPDATE ON fa_jobs, fa_shipment_types TO forgedesk_sync;

COMMIT;

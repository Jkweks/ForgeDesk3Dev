-- Field app database. Tables fa_* are the ForgeDesk sync contract (v1):
--   docs/fleet-field-sync-contract.md in the ForgeDesk repo. Change them only together.
-- fa_users is published by ForgeDesk too (contract v2); fa_sessions belongs to this app alone.

-- Published by ForgeDesk (read-only for the field app). Upserted by asset_number.
CREATE TABLE fa_assets (
  asset_number      text PRIMARY KEY,
  name              text NOT NULL,
  type_name         text NOT NULL,
  meter_type        text,
  status            text NOT NULL,
  current_meter     numeric(12,1),
  current_job_label text,
  is_active         boolean NOT NULL DEFAULT true,
  synced_at         timestamp
);

-- Inspection checklists per asset type. Published by ForgeDesk.
CREATE TABLE fa_checklists (
  type_name     text PRIMARY KEY,
  template_name text NOT NULL,
  items         text NOT NULL,
  updated_at    timestamp
);

-- Open shipments (contract v4: drafts, ready and shipped). Published by ForgeDesk.
-- checklist is JSON: [{"type_id":1,"type_name":"Glass","items":[{"id":7,"label":"...","input_type":"pass_fail|number|text|choice",
--                      "unit":null,"options":null,"required":true,"photo_required":false}]}]
CREATE TABLE fa_shipments (
  shipment_number text PRIMARY KEY,
  job_label       text,
  destination     text,
  ship_date       date,
  checklist       text NOT NULL,                       -- post-delivery items (see above)
  carrier         text,
  status          text NOT NULL DEFAULT 'shipped',     -- draft | pre_check_done | shipped
  loads           text,                                -- "Glass x4, Metal"
  pre_checklist   text NOT NULL DEFAULT '[]',          -- same JSON shape, pre-shipment items
  pickup_name     text,                                -- contract v5: where the truck loads / unloads, for navigation
  pickup_address  text,
  pickup_lat      double precision,
  pickup_lng      double precision,
  dropoff_name    text,
  dropoff_address text,
  dropoff_lat     double precision,
  dropoff_lng     double precision,
  is_active       boolean NOT NULL DEFAULT true,
  synced_at       timestamp
);

-- Jobs a new shipment can be tagged with (contract v4). Number + name only. Published by ForgeDesk.
CREATE TABLE fa_jobs (
  job_number text PRIMARY KEY,
  job_name   text NOT NULL DEFAULT '',
  is_active  boolean NOT NULL DEFAULT true,
  synced_at  timestamp
);

-- Load types for a new shipment (contract v4). Published by ForgeDesk.
CREATE TABLE fa_shipment_types (
  type_id    bigint PRIMARY KEY,
  name       text NOT NULL,
  sort_order integer NOT NULL DEFAULT 0,
  is_active  boolean NOT NULL DEFAULT true,
  synced_at  timestamp
);

-- Append-only inbox written by the field app, drained by ForgeDesk.
CREATE TABLE fa_submissions (
  id              bigserial PRIMARY KEY,
  submission_uuid text NOT NULL UNIQUE,
  kind            text NOT NULL,
  asset_number    text NOT NULL,
  submitted_by    text,
  submitted_by_user_id bigint,        -- fa_users.forgedesk_user_id, set by the server from the session (contract v4)
  submitted_at    timestamp,
  payload         text NOT NULL,
  ingested_at     timestamp
);
CREATE INDEX fa_submissions_pending_idx ON fa_submissions (id) WHERE ingested_at IS NULL;
CREATE INDEX fa_submissions_by_idx ON fa_submissions (submitted_by, id DESC);

-- Photos for a submission, base64 text.
CREATE TABLE fa_photos (
  id              bigserial PRIMARY KEY,
  submission_uuid text NOT NULL,
  filename        text NOT NULL,
  mime            text,
  data_b64        text NOT NULL
);
CREATE INDEX fa_photos_submission_idx ON fa_photos (submission_uuid);

-- Crew logins. Published by ForgeDesk (contract v2) for users an admin ticked "Allow mobile app"
-- for; the field app only reads them. password_hash is ForgeDesk's own bcrypt hash and is EMPTY
-- unless the account can sign in right now, so it can never be used to log in while blank.
CREATE TABLE fa_users (
  forgedesk_user_id bigint PRIMARY KEY,
  login             text NOT NULL,              -- ForgeDesk email, lowercased
  display_name      text NOT NULL,              -- shown in ForgeDesk as submitted_by
  password_hash     text NOT NULL DEFAULT '',
  permissions       text NOT NULL DEFAULT '[]', -- JSON list of the shipments.* permissions the user holds (contract v4); UI hint only
  is_active         boolean NOT NULL DEFAULT true,
  synced_at         timestamp
);
CREATE UNIQUE INDEX fa_users_login_uq ON fa_users (lower(login));

-- ---- Field app only -------------------------------------------------------------------

CREATE TABLE fa_sessions (
  token_hash text PRIMARY KEY,       -- sha256 of the cookie value; the raw token is never stored
  user_id    bigint NOT NULL REFERENCES fa_users(forgedesk_user_id) ON DELETE CASCADE,
  expires_at timestamp NOT NULL,
  created_at timestamp NOT NULL DEFAULT (now() at time zone 'utc')
);
CREATE INDEX fa_sessions_expiry_idx ON fa_sessions (expires_at);

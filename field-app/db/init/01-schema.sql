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

-- Append-only inbox written by the field app, drained by ForgeDesk.
CREATE TABLE fa_submissions (
  id              bigserial PRIMARY KEY,
  submission_uuid text NOT NULL UNIQUE,
  kind            text NOT NULL,
  asset_number    text NOT NULL,
  submitted_by    text,
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

-- Upgrade an EXISTING field database from contract v1 to v2 (logins published by ForgeDesk).
-- Fresh installs get this from db/init and don't need it. Run once as the superuser:
--   docker compose exec -T db psql -U fieldadmin -d fieldapp -v ON_ERROR_STOP=1 < db/upgrades/002-forgedesk-users.sql
-- Local crew logins from v1 are dropped: those people must be given "Allow mobile app" in ForgeDesk.
BEGIN;

DROP TABLE IF EXISTS fa_sessions;
DROP TABLE IF EXISTS fa_users;

CREATE TABLE fa_users (
  forgedesk_user_id bigint PRIMARY KEY,
  login             text NOT NULL,
  display_name      text NOT NULL,
  password_hash     text NOT NULL DEFAULT '',
  is_active         boolean NOT NULL DEFAULT true,
  synced_at         timestamp
);
CREATE UNIQUE INDEX fa_users_login_uq ON fa_users (lower(login));

CREATE TABLE fa_sessions (
  token_hash text PRIMARY KEY,
  user_id    bigint NOT NULL REFERENCES fa_users(forgedesk_user_id) ON DELETE CASCADE,
  expires_at timestamp NOT NULL,
  created_at timestamp NOT NULL DEFAULT (now() at time zone 'utc')
);
CREATE INDEX fa_sessions_expiry_idx ON fa_sessions (expires_at);

REVOKE ALL ON fa_users, fa_sessions FROM PUBLIC;
GRANT SELECT                         ON fa_users    TO field_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON fa_sessions TO field_app;
GRANT SELECT, INSERT, UPDATE         ON fa_users    TO forgedesk_sync;

COMMIT;

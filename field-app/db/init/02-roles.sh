#!/bin/sh
# Runs once, on first start of an empty data volume. Passwords come from the container env.
set -e

: "${FIELD_APP_DB_PASSWORD:?FIELD_APP_DB_PASSWORD is required}"
: "${FORGEDESK_SYNC_DB_PASSWORD:?FORGEDESK_SYNC_DB_PASSWORD is required}"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
     -v app_pw="$FIELD_APP_DB_PASSWORD" -v sync_pw="$FORGEDESK_SYNC_DB_PASSWORD" <<'SQL'
CREATE ROLE field_app      LOGIN PASSWORD :'app_pw'  CONNECTION LIMIT 30;
CREATE ROLE forgedesk_sync LOGIN PASSWORD :'sync_pw' CONNECTION LIMIT 5;

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO field_app, forgedesk_sync;

-- Public-facing app: read the published data, append submissions. No UPDATE/DELETE on the
-- inbox, so a compromised app cannot rewrite or erase what crews already sent.
GRANT SELECT         ON fa_assets, fa_checklists          TO field_app;
GRANT SELECT, INSERT ON fa_submissions                    TO field_app;
GRANT INSERT         ON fa_photos                         TO field_app;
GRANT SELECT                 ON fa_users                  TO field_app;   -- logins come from ForgeDesk
GRANT SELECT, INSERT, UPDATE, DELETE ON fa_sessions       TO field_app;
GRANT USAGE ON SEQUENCE fa_submissions_id_seq, fa_photos_id_seq TO field_app;

-- ForgeDesk: publish assets, checklists and crew logins; drain the inbox. Never sees sessions.
GRANT SELECT, INSERT, UPDATE ON fa_assets, fa_checklists, fa_users TO forgedesk_sync;
GRANT SELECT                 ON fa_submissions            TO forgedesk_sync;
GRANT UPDATE (ingested_at)   ON fa_submissions            TO forgedesk_sync;
GRANT SELECT, DELETE         ON fa_photos                 TO forgedesk_sync;
SQL

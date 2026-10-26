# Field app ⇄ ForgeDesk sync contract (v2)

`schema_version: 2`. Two codebases depend on this; change it deliberately.

**v2 (additive):** ForgeDesk now also publishes crew logins into `fa_users` (see below). Payloads and the
other tables are unchanged. Existing field databases are upgraded with `field-app/db/upgrades/002-forgedesk-users.sql`
(it drops the v1 local user tables). ForgeDesk's `fleet:sync-out` fails loudly until the field DB has `fa_users`.

## Principles

- **ForgeDesk is local-network only and always initiates.** It connects *out* to the field app's database (Laravel connection `fieldsync`, env `FIELDSYNC_*`, config `config/fleet.php`). Nothing public ever connects to the ForgeDesk database.
- The field app owns a **separate database** (separate host / DMZ). Do not share the ForgeDesk DB with it, even through a restricted role.
- The field app is **untrusted input**. ForgeDesk validates every submission; anything invalid is rejected or held for review in ForgeDesk (`field_sync_log`, Fleet → Field Inbox), never applied blindly.
- Sync is enabled with `FIELDSYNC_ENABLED=true`. Scheduler runs `fleet:sync-out` and `fleet:sync-in` every 2 minutes; both are no-ops when disabled and fail soft (logged, retried) if the field DB is unreachable.

## Field-side tables (PostgreSQL)

```sql
-- Published by ForgeDesk (read-only for the field app). Upserted by asset_number.
CREATE TABLE fa_assets (
  asset_number      text PRIMARY KEY,         -- also the QR payload on asset labels
  name              text NOT NULL,
  type_name         text NOT NULL,            -- joins to fa_checklists.type_name
  meter_type        text,                     -- odometer_miles | engine_hours | none
  status            text NOT NULL,            -- active | in_service_job | out_of_service | in_repair
  current_meter     numeric(12,1),
  current_job_label text,                     -- job name/number or free-text location
  is_active         boolean NOT NULL DEFAULT true,  -- false = hide (retired/sold); rows are never deleted
  synced_at         timestamp
);

-- Inspection checklists per asset type. Published by ForgeDesk. NOTE: no "critical" flag is
-- published; ForgeDesk decides criticality itself when ingesting.
CREATE TABLE fa_checklists (
  type_name     text PRIMARY KEY,
  template_name text NOT NULL,
  items         text NOT NULL,                -- JSON: [{"label": "Brakes"}, ...]
  updated_at    timestamp
);

-- Append-only inbox written by the field app, drained by ForgeDesk.
CREATE TABLE fa_submissions (
  id              bigserial PRIMARY KEY,
  submission_uuid text NOT NULL UNIQUE,       -- client-generated; idempotency key (UUID v4)
  kind            text NOT NULL,              -- inspection | meter | defect
  asset_number    text NOT NULL,
  submitted_by    text,                       -- field user's display name/id (free text; shown in ForgeDesk)
  submitted_at    timestamp,                  -- device time (UTC). Clamped to "now" if >1 day ahead or older than 14 days
  payload         text NOT NULL,              -- JSON, see below
  ingested_at     timestamp                   -- set by ForgeDesk once handled (ingested, rejected or held)
);

-- Crew logins, published by ForgeDesk (read-only for the field app). One row per ForgeDesk user who an
-- administrator ticked "Allow mobile app" for; rows are never deleted. Users sign in with their ForgeDesk
-- email and password.
CREATE TABLE fa_users (
  forgedesk_user_id bigint PRIMARY KEY,
  login             text NOT NULL,            -- ForgeDesk email, lowercased (unique, case-insensitive)
  display_name      text NOT NULL,            -- becomes fa_submissions.submitted_by
  password_hash     text NOT NULL DEFAULT '', -- ForgeDesk's bcrypt hash; '' = cannot sign in right now
  is_active         boolean NOT NULL DEFAULT true,
  synced_at         timestamp
);

-- Photos for a submission, as base64 text (ForgeDesk verifies real image type, re-encodes, stores privately).
CREATE TABLE fa_photos (
  id              bigserial PRIMARY KEY,
  submission_uuid text NOT NULL,
  filename        text NOT NULL,
  mime            text,                       -- informational only; ForgeDesk sniffs the bytes
  data_b64        text NOT NULL               -- JPEG/PNG/WebP, max 10 MB decoded, max 10 per submission
);
```

### Suggested database roles

| Role | Grants |
|---|---|
| `field_app` (public-facing app) | `SELECT` on `fa_assets`, `fa_checklists`, `fa_users`; `SELECT`, `INSERT` on `fa_submissions`; `INSERT` on `fa_photos`. **No UPDATE/DELETE** on the inbox or `fa_users`. |
| `forgedesk_sync` (ForgeDesk) | `SELECT/INSERT/UPDATE` on `fa_assets`, `fa_checklists`, `fa_users`; `SELECT/UPDATE(ingested_at)` on `fa_submissions`; `SELECT/DELETE` on `fa_photos`. |

### Logins (v2)

Accounts are managed in ForgeDesk (Users → *Allow mobile app*, administrators only). The field app keeps no
user management of its own, only sessions (`fa_sessions`, field-app private; ForgeDesk never sees them).

**Security trade-off, accepted deliberately:** signing in with the *same* password means ForgeDesk's password
hash for these users sits on the internet-facing server. Anyone who breaches the field database could try to
crack those hashes offline, and a cracked password may be reused elsewhere. Mitigations built in:

- Only users explicitly opted in are ever published, and only admins can opt a user in.
- A hash is present **only while the account can sign in**: inactive, disabled, deleted, access removed, or still
  on a temporary password (`must_change_password`) all publish `password_hash = ''` (and `is_active = false`).
  Removing access therefore removes the credential from the field side on the next sync (≤ 2 min), and the
  field app rejects existing sessions at once.
- Hashes are bcrypt (cost 12). The field app can never write `fa_users`.
- Treat opted-in accounts as exposed: give them least-privilege ForgeDesk roles, and prefer people who don't
  hold admin roles. An admin who opts in puts an admin hash on the public server.

## Payloads (`fa_submissions.payload`, version 1)

All strings are length-capped by ForgeDesk; unknown keys are ignored.

**`inspection`**
```json
{
  "template_name": "Truck inspection",
  "meter": 48210.0,
  "notes": "optional, ≤5000 chars",
  "items": [
    {"label": "Brakes", "result": "pass|fail|na", "comment": "optional, ≤1000 chars"}
  ]
}
```
`items[].label` should match a published checklist label. Criticality is looked up by label in ForgeDesk (client-sent `critical` is ignored). Any failure opens an `inspection_defect` work order and raises an in-app alert; a failed *critical* item makes the result `fail`, and if the asset type has "auto out-of-service" enabled the asset is set `out_of_service`.

**`meter`**
```json
{ "reading": 48250.0 }
```
A reading below the asset's current meter is *held for review* (Field Inbox), not applied.

**`defect`**
```json
{ "title": "Hydraulic leak", "description": "optional", "severity": "low|medium|high|critical" }
```
Opens a `repair` work order flagged as coming from the field app.

## ForgeDesk behaviour

1. **Out:** upserts `fa_assets` (in-service assets only; others flagged `is_active=false`), `fa_checklists`, and `fa_users` (opted-in users; everyone else previously published is blanked, never deleted). Nothing else leaves ForgeDesk: no costs, purchase info, VIN/plate, notes, or documents.
2. **In:** reads up to 100 rows with `ingested_at IS NULL`, ordered by `id`. For each: skip if `submission_uuid` already in `field_sync_log`; otherwise validate + apply inside a transaction; write a `field_sync_log` row (`ingested` | `rejected` | `needs_review`, with reason and original payload); set `ingested_at` on the remote row. Photos are pulled from `fa_photos`, verified by content sniffing (JPEG/PNG/WebP only), JPEG/PNG re-encoded to strip EXIF/GPS, stored on ForgeDesk's private disk, and removed from the field DB.
3. Replaying a submission (same `submission_uuid`) is always safe.
4. Dashboard shows a warning if the last successful pull/publish is older than 15 minutes.

## Field app responsibilities

- Queue submissions locally while offline and replay them with the **same `submission_uuid`**.
- Resolve a scanned QR code (`asset_number`) against `fa_assets`; show only `is_active` assets.
- Build the inspection form from `fa_checklists.items`.
- Authenticate against `fa_users` only (`is_active AND password_hash <> ''`), keep your own sessions, and take `submitted_by` from the signed-in user's `display_name`, never from the request.
- Re-check `fa_users` on every request so a revoked account stops working as soon as the sync lands.

# Field app ⇄ ForgeDesk sync contract (v3)

`schema_version: 3`. Two codebases depend on this; change it deliberately.

**v3 (additive):** shipment post-delivery inspections. ForgeDesk publishes shipments awaiting a delivery check into
`fa_shipments`; the field app submits kind `shipment_inspection` (shipment number in `fa_submissions.asset_number`).
Existing field databases are upgraded with `field-app/db/upgrades/003-shipments.sql` (run it **before** ForgeDesk's
next `fleet:sync-out`, which fails loudly until `fa_shipments` exists). See "Shipments (v3)" below.

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


## Shipments (v3)

```sql
-- Published by ForgeDesk (read-only for the field app). Only shipments with status `shipped` are active;
-- once a delivery is inspected (or cancelled) the row is flagged is_active=false, never deleted.
CREATE TABLE fa_shipments (
  shipment_number text PRIMARY KEY,   -- SHP-00001
  job_label       text,
  destination     text,
  ship_date       date,
  checklist       text NOT NULL,      -- JSON, post-delivery items for the shipment's load types (below)
  is_active       boolean NOT NULL DEFAULT true,
  synced_at       timestamp
);
```
`checklist`: `[{"type_id":1,"type_name":"Glass","items":[{"id":7,"label":"...","input_type":"pass_fail|number|text|choice","unit":null,"options":null,"required":true,"photo_required":false}]}]`.
As with fleet checklists, no `critical` flag is published; ForgeDesk decides criticality from its own definitions.

**`shipment_inspection`** (`fa_submissions.asset_number` = shipment number)
```json
{ "notes": "optional, ≤5000", "items": [ {"item_id": 7, "value": "pass|fail|na | number | text | choice", "comment": "≤1000", "photos": [0, 2]} ] }
```
`photos` are zero-based indexes into this submission's `fa_photos` rows in insertion (`id`) order; max 30 photos.
ForgeDesk validates every answer against the item definition by id, ignores anything unknown, and **does not reject for a
missing required photo** (the phone enforces it; a lost photo must not cost us a damage report). Outcomes:
unknown shipment => `rejected`; shipment not `shipped` (e.g. already inspected) => `needs_review`; invalid answers => `rejected`;
otherwise the shipment becomes `delivered` (all pass) or `delivered_issues` (any failure) and an alert is raised on failures.
`fa_shipments` grants: `field_app` SELECT; `forgedesk_sync` SELECT/INSERT/UPDATE.


## Pickup / drop-off locations (v5)

Upgrade an existing field DB with `field-app/db/upgrades/005-shipment-locations.sql` **before** ForgeDesk publishes (sync-out fails loudly until the columns exist).

`fa_shipments` gains, all nullable and published by ForgeDesk (read-only for the field app): `pickup_name`, `pickup_address`, `pickup_lat`, `pickup_lng`, `dropoff_name`, `dropoff_address`, `dropoff_lat`, `dropoff_lng`. The phone shows each stop with a **Navigate** link that opens the maps app (coordinates when present, else the address). Shipments created from the phone carry no locations.

## Shipping queue, pre-checks and new shipments (v4)

Upgrade an existing field DB with `field-app/db/upgrades/004-shipping-queue.sql` **before** ForgeDesk publishes (sync-out fails loudly until it exists).

**Published by ForgeDesk (read-only for the field app)**
- `fa_shipments` now holds every *open* shipment (`status` = `draft` | `pre_check_done` | `shipped`), not only shipped ones; delivered and cancelled are flagged `is_active=false`, never deleted. New columns: `status`, `carrier`, `loads` (text such as "Glass x4, Metal"), `pre_checklist` (same JSON shape as `checklist`, pre-shipment items). `checklist` stays the post-delivery list. No `critical` flag in either.
- `fa_jobs(job_number PK, job_name, is_active, synced_at)`: jobs not completed/cancelled. Number and name only; nothing else about a job leaves ForgeDesk.
- `fa_shipment_types(type_id PK, name, sort_order, is_active, synced_at)`.
- `fa_users.permissions`: JSON list limited to `shipments.view|manage|inspect`. A UI hint only; ForgeDesk re-checks on ingest.
- `fa_submissions.submitted_by_user_id` is set by the field server from the session (never from the request body).

**New submission kinds** (`fa_submissions.kind`, all ≤ 20 chars)
| kind | `asset_number` | payload | needs (checked by ForgeDesk against `submitted_by_user_id`) |
|---|---|---|---|
| `shipment_precheck` | shipment number | same as `shipment_inspection`, answers keyed by `pre_checklist` item ids | `shipments.inspect` |
| `shipment_create` | `NEW-xxxxxx` (client reference) | `{"job_numbers":["A-1"],"ship_date":"YYYY-MM-DD","carrier":"","destination":"","notes":"","loads":[{"type_id":1,"piece_count":4}]}` | `shipments.manage` |
| `shipment_reschedule` | shipment number | `{"ship_date":"YYYY-MM-DD"|null}` | `shipments.manage` |
| `shipment_ship` | shipment number | `{}` | `shipments.manage` |

Outcomes: a submitter without the permission (or with no `submitted_by_user_id`, or no longer allowed in) => `rejected`. `shipment_precheck` follows the web rules (records a `pre` inspection; passing moves `draft` to `pre_check_done`; a critical failure alerts). `shipment_create` makes a `draft` shipment numbered by ForgeDesk; job numbers that no longer match are noted on the shipment, not guessed; an unknown/inactive load type => `rejected`. `shipment_precheck` / `shipment_reschedule` on a shipment that is no longer `draft`/`pre_check_done` => `needs_review`. The field server also refuses these kinds for shipments not currently published in the right status.

`shipment_ship` = the web "Mark shipped": only from `pre_check_done` (otherwise `needs_review`); sets `shipped`, stamps `shipped_at`, and defaults `ship_date` to today. The field server accepts it only for shipments currently published as `pre_check_done`, and accepts a `shipment_inspection` for a `pre_check_done` shipment while a `shipment_ship` for it is still in the inbox.
